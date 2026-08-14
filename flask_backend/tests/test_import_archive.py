"""Tests for the bulk-import CSV archive (feature 009-save-import-csv).

Two layers are covered here:

* the pure primitives in `flask_backend.import_archive` — id generation, id
  validation, and the path resolution that keeps a client-supplied id inside
  the archive directory;
* the endpoint behavior in `flask_backend.app` — that every submission is
  archived before it is parsed, that the outcome is recorded beside it, and
  that the three admin read endpoints expose both without ever reading
  outside the archive.

None of this needs a database: the archive write happens before
`models.get_session()`, and the fixtures below fail row validation before any
query, which is how the pre-existing bulk-upload test already works.
"""

import io
import json
import os

import pytest

from flask_backend import import_archive


# --- Primitives -------------------------------------------------------------


def test_new_import_id_matches_the_grammar():
    import_id = import_archive.new_import_id()
    assert import_archive.IMPORT_ID_RE.match(import_id)
    assert import_archive.is_valid_import_id(import_id)


def test_new_import_id_is_unique_within_a_second():
    # Two submissions in the same second must not collide (FR-002); the
    # timestamp alone is only second-precision, so the entropy suffix is what
    # carries this.
    assert import_archive.new_import_id() != import_archive.new_import_id()


@pytest.mark.parametrize(
    'bad_id',
    [
        '../../etc/passwd',
        '/etc/passwd',
        '20260813T144512Z-ZZZZZZZZ',  # suffix is not hex
        '20260813T144512Z-7f3a1c2',   # suffix too short
        '20260813T144512Z-7F3A1C2B',  # uppercase hex is not the grammar
        '20260813144512Z-7f3a1c2b',   # missing the T separator
        '20260813T144512Z-7f3a1c2b/../evil',
        '',
    ],
)
def test_is_valid_import_id_rejects_malformed_ids(bad_id):
    assert not import_archive.is_valid_import_id(bad_id)


def test_is_valid_import_id_rejects_non_strings():
    assert not import_archive.is_valid_import_id(None)
    assert not import_archive.is_valid_import_id(12345)


@pytest.mark.parametrize(
    'bad_id',
    ['../../etc/passwd', '/etc/passwd', '20260813T144512Z-ZZZZZZZZ', ''],
)
def test_submission_path_refuses_to_escape_the_archive(tmp_path, bad_id):
    with pytest.raises(import_archive.InvalidImportId):
        import_archive.submission_path(str(tmp_path), bad_id)
    with pytest.raises(import_archive.InvalidImportId):
        import_archive.record_path(str(tmp_path), bad_id)


def test_paths_for_a_valid_id_sit_inside_the_archive(tmp_path):
    import_id = import_archive.new_import_id()
    csv_path = import_archive.submission_path(str(tmp_path), import_id)
    json_path = import_archive.record_path(str(tmp_path), import_id)
    assert os.path.dirname(csv_path) == os.path.realpath(str(tmp_path))
    assert os.path.basename(csv_path) == f'{import_id}.csv'
    assert os.path.basename(json_path) == f'{import_id}.json'


def test_ensure_archive_dir_creates_it_and_is_idempotent(tmp_path):
    target = tmp_path / 'imports'
    import_archive.ensure_archive_dir(str(target))
    assert target.is_dir()
    # A second call on an existing directory must not raise.
    import_archive.ensure_archive_dir(str(target))
    assert target.is_dir()


def test_write_submission_refuses_to_overwrite(tmp_path):
    import_id = import_archive.new_import_id()
    import_archive.write_submission(str(tmp_path), import_id, b'first')
    with pytest.raises(FileExistsError):
        import_archive.write_submission(str(tmp_path), import_id, b'second')
    assert (tmp_path / f'{import_id}.csv').read_bytes() == b'first'


# --- Archiving at POST /api/events/bulk -------------------------------------


@pytest.fixture
def archive_dir(tmp_path, monkeypatch):
    """Point the app's archive at a temporary directory and yield it."""
    import importlib

    app_mod = importlib.import_module('flask_backend.app')
    target = tmp_path / 'imports'
    monkeypatch.setattr(app_mod, 'IMPORT_ARCHIVE_DIR', str(target))
    return target


def _post_csv(client, body: bytes, filename: str = 'events.csv'):
    return client.post(
        '/api/events/bulk',
        data={'events_csv': (io.BytesIO(body), filename)},
        content_type='multipart/form-data',
    )


def _archived_csvs(archive_dir):
    return sorted(archive_dir.glob('*.csv')) if archive_dir.exists() else []


def _archived_records(archive_dir):
    return sorted(archive_dir.glob('*.json')) if archive_dir.exists() else []


def _read_only_record(archive_dir):
    paths = _archived_records(archive_dir)
    assert len(paths) == 1
    return json.loads(paths[0].read_text())


class _FakePatient:
    id = 42


class _FakeQuery:
    def __init__(self, patient):
        self._patient = patient

    def filter_by(self, **kwargs):
        return self

    def first(self):
        return self._patient


class _FakeSession:
    """Enough of a SQLAlchemy session for the bulk-import path.

    `flush()` stands in for the id assignment the real session does, so
    criteria rows get an event id to hang off.
    """

    def __init__(self, patient):
        self._patient = patient
        self.added = []

    def query(self, *args, **kwargs):
        return _FakeQuery(self._patient)

    def add(self, obj):
        self.added.append(obj)
        return obj

    def flush(self):
        for obj in self.added:
            if getattr(obj, 'id', None) is None:
                obj.id = 1

    def commit(self):
        pass

    def rollback(self):
        pass

    def close(self):
        pass


@pytest.fixture
def importable_patient(monkeypatch):
    """Make every row's patient lookup succeed, so valid rows import."""
    from flask_backend import models

    monkeypatch.setattr(models, 'get_session', lambda: _FakeSession(_FakePatient()))


VALID_ROW = b'A1,UW,2026-01-15\n'
INVALID_ROW = b'oops\n'


def test_successful_import_archives_the_submission(archive_dir, importable_patient, admin_client):
    body = VALID_ROW + b'A2,UW,2026-02-20\n'
    res = _post_csv(admin_client, body)
    assert res.status_code == 201
    assert res.get_json()['data']['imported'] == 2
    archived = _archived_csvs(archive_dir)
    assert len(archived) == 1
    assert archived[0].read_bytes() == body


def test_partial_import_archives_the_whole_submission(archive_dir, importable_patient, admin_client):
    body = VALID_ROW + INVALID_ROW
    res = _post_csv(admin_client, body)
    assert res.status_code == 201
    payload = res.get_json()['data']
    assert payload['imported'] == 1
    assert len(payload['errors']) == 1
    # The bad row is part of the submission and must be archived with it.
    assert _archived_csvs(archive_dir)[0].read_bytes() == body


def test_fully_rejected_import_is_still_archived(archive_dir, importable_patient, admin_client):
    body = INVALID_ROW + INVALID_ROW
    res = _post_csv(admin_client, body)
    assert res.status_code == 400
    assert _archived_csvs(archive_dir)[0].read_bytes() == body


def test_non_utf8_submission_is_archived_before_it_is_decoded(archive_dir, admin_client):
    # Latin-1 bytes that are not valid UTF-8. The endpoint cannot read this
    # file at all, which is precisely why the write has to come first.
    body = b'A1,UW,2026-01-15,site,caf\xe9\n'
    res = _post_csv(admin_client, body)
    assert res.status_code == 400
    assert 'UTF-8' in res.get_json()['error']
    assert _archived_csvs(archive_dir)[0].read_bytes() == body


def test_same_filename_twice_produces_two_distinct_archives(archive_dir, importable_patient, admin_client):
    first = _post_csv(admin_client, VALID_ROW, 'events.csv')
    second = _post_csv(admin_client, b'B9,UW,2026-03-01\n', 'events.csv')
    assert first.status_code == 201
    assert second.status_code == 201
    archived = _archived_csvs(archive_dir)
    assert len(archived) == 2
    assert {p.read_bytes() for p in archived} == {VALID_ROW, b'B9,UW,2026-03-01\n'}


def test_oversize_submission_is_refused_and_not_stored(archive_dir, monkeypatch, admin_client):
    import importlib

    app_mod = importlib.import_module('flask_backend.app')
    monkeypatch.setattr(app_mod, 'MAX_IMPORT_CSV_BYTES', 16)

    res = _post_csv(admin_client, b'A1,UW,2026-01-15\nA2,UW,2026-01-16\n')
    assert res.status_code == 413
    error = res.get_json()['error']
    assert '16' in error  # names the limit so the administrator knows what to do
    assert _archived_csvs(archive_dir) == []


def test_unwritable_archive_fails_the_import_closed(archive_dir, monkeypatch, admin_client):
    from flask_backend import import_archive as archive_mod

    def _explode(_dir):
        raise OSError('read-only file system')

    monkeypatch.setattr(archive_mod, 'ensure_archive_dir', _explode)

    def _no_session():
        raise AssertionError('no events may be created when archiving fails')

    from flask_backend import models

    monkeypatch.setattr(models, 'get_session', _no_session)

    res = _post_csv(admin_client, VALID_ROW)
    assert res.status_code == 500
    assert 'archive' in res.get_json()['error'].lower()
    assert _archived_csvs(archive_dir) == []


# --- Import records (manifests) ---------------------------------------------


def test_partial_import_records_its_skipped_rows(archive_dir, importable_patient, admin_client):
    res = _post_csv(admin_client, VALID_ROW + INVALID_ROW)
    record = _read_only_record(archive_dir)
    assert record['outcome'] == 'partial'
    assert record['imported_count'] == 1
    assert record['skipped_count'] == 1
    # The persisted reasons must be exactly what the administrator saw.
    assert record['errors'] == res.get_json()['data']['errors']


def test_rejected_import_records_a_zero_count(archive_dir, importable_patient, admin_client):
    _post_csv(admin_client, INVALID_ROW + INVALID_ROW)
    record = _read_only_record(archive_dir)
    assert record['outcome'] == 'rejected'
    assert record['imported_count'] == 0
    assert record['skipped_count'] == 2
    assert record['file_available'] is True


def test_clean_import_records_no_errors(archive_dir, importable_patient, admin_client):
    _post_csv(admin_client, VALID_ROW)
    record = _read_only_record(archive_dir)
    assert record['outcome'] == 'imported'
    assert record['imported_count'] == 1
    assert record['errors'] == []


def test_record_carries_the_submitter_and_original_name(archive_dir, importable_patient, admin_client):
    _post_csv(admin_client, VALID_ROW, 'UW-batch-March.csv')
    record = _read_only_record(archive_dir)
    assert record['record_version'] == 1
    assert record['submitted_by_id'] == 1
    assert record['submitted_by'] == 'test-admin'
    assert record['original_name'] == 'UW-batch-March.csv'
    assert record['size_bytes'] == len(VALID_ROW)
    assert record['submitted_at'].endswith('Z')
    assert record['import_id'] == _archived_records(archive_dir)[0].stem


def test_refused_import_is_recorded_without_its_contents(archive_dir, monkeypatch, admin_client):
    import importlib

    app_mod = importlib.import_module('flask_backend.app')
    monkeypatch.setattr(app_mod, 'MAX_IMPORT_CSV_BYTES', 16)

    body = b'A1,UW,2026-01-15\nA2,UW,2026-01-16\n'
    res = _post_csv(admin_client, body, 'too-big.csv')
    assert res.status_code == 413

    record = _read_only_record(archive_dir)
    assert record['outcome'] == 'refused'
    assert record['file_available'] is False
    assert record['original_name'] == 'too-big.csv'
    # The size that got it refused, not the size stored (which is nothing).
    assert record['size_bytes'] >= len(body)
    assert record['imported_count'] == 0
    assert len(record['errors']) == 1
    # An oversize attempt must be visible, but its contents are not kept.
    assert _archived_csvs(archive_dir) == []


def test_unwritable_record_does_not_fail_the_import(archive_dir, importable_patient, monkeypatch, admin_client):
    from flask_backend import import_archive as archive_mod

    def _explode(*args, **kwargs):
        raise OSError('no space left on device')

    monkeypatch.setattr(archive_mod, 'write_record', _explode)

    res = _post_csv(admin_client, VALID_ROW)
    # The bytes are already safe and the events already exist; a missing
    # manifest is not worth reporting a failure the caller cannot act on.
    assert res.status_code == 201
    assert _archived_csvs(archive_dir)[0].read_bytes() == VALID_ROW
    assert _archived_records(archive_dir) == []


# --- Reading the archive ----------------------------------------------------


def _seed(archive_dir, import_id, *, body=b'A1,UW,2026-01-15\n', record=True, **overrides):
    """Place one archived submission (and usually its manifest) on disk."""
    if body is not None:
        import_archive.write_submission(str(archive_dir), import_id, body)
    if record:
        fields = dict(
            submitted_by_id=1,
            submitted_by='test-admin',
            original_name='events.csv',
            size_bytes=len(body or b''),
            imported_count=1,
        )
        fields.update(overrides)
        import_archive.write_record(
            str(archive_dir), import_id, import_archive.build_record(import_id, **fields)
        )
    return import_id


OLDEST = '20260101T090000Z-aaaaaaaa'
MIDDLE = '20260601T120000Z-bbbbbbbb'
NEWEST = '20260813T144512Z-cccccccc'


def test_imports_list_is_newest_first(archive_dir, admin_client):
    for import_id in (MIDDLE, NEWEST, OLDEST):
        _seed(archive_dir, import_id)

    res = admin_client.get('/api/events/imports')
    assert res.status_code == 200
    payload = res.get_json()
    assert payload['total'] == 3
    assert [r['import_id'] for r in payload['data']] == [NEWEST, MIDDLE, OLDEST]


def test_imports_list_pages_with_limit_and_offset(archive_dir, admin_client):
    for import_id in (OLDEST, MIDDLE, NEWEST):
        _seed(archive_dir, import_id)

    res = admin_client.get('/api/events/imports?limit=1&offset=1')
    payload = res.get_json()
    assert [r['import_id'] for r in payload['data']] == [MIDDLE]
    # `total` counts the whole archive, not the page.
    assert payload['total'] == 3


def test_imports_list_is_empty_before_any_import(archive_dir, admin_client):
    res = admin_client.get('/api/events/imports')
    assert res.status_code == 200
    assert res.get_json() == {'data': [], 'total': 0}


def test_submission_without_a_manifest_lists_as_incomplete(archive_dir, admin_client):
    _seed(archive_dir, NEWEST, record=False)

    res = admin_client.get('/api/events/imports')
    record = res.get_json()['data'][0]
    assert record['outcome'] == 'unknown'
    assert record['incomplete'] is True
    assert record['imported_count'] is None
    # The timestamp is still recoverable from the id.
    assert record['submitted_at'] == '2026-08-13T14:45:12Z'
    # ...and the irreplaceable part is still downloadable.
    assert admin_client.get(f'/api/events/imports/{NEWEST}/file').status_code == 200


def test_refused_record_is_listed_but_has_nothing_to_download(archive_dir, admin_client):
    _seed(archive_dir, NEWEST, body=None, refused=True, size_bytes=99, errors=['too large'])

    res = admin_client.get('/api/events/imports')
    payload = res.get_json()
    # A refused submission has a manifest and no file. Listing only `.csv`
    # stems would write this record and then never show it.
    assert payload['total'] == 1
    assert payload['data'][0]['outcome'] == 'refused'
    assert payload['data'][0]['file_available'] is False
    assert admin_client.get(f'/api/events/imports/{NEWEST}/file').status_code == 404


def test_import_detail_returns_the_record(archive_dir, admin_client):
    _seed(archive_dir, NEWEST, errors=['Line 2: bad'], imported_count=3)

    res = admin_client.get(f'/api/events/imports/{NEWEST}')
    assert res.status_code == 200
    record = res.get_json()['data']
    assert record['import_id'] == NEWEST
    assert record['errors'] == ['Line 2: bad']
    assert record['outcome'] == 'partial'


@pytest.mark.parametrize(
    'bad_id',
    [
        '..%2F..%2Fetc%2Fpasswd',
        '20260813T144512Z-ZZZZZZZZ',
        '20260813T144512Z-dddddddd',  # well-formed, but no such import
        'events',
    ],
)
def test_malformed_or_missing_ids_are_not_found(archive_dir, admin_client, bad_id):
    _seed(archive_dir, NEWEST)
    assert admin_client.get(f'/api/events/imports/{bad_id}').status_code == 404
    assert admin_client.get(f'/api/events/imports/{bad_id}/file').status_code == 404


def test_traversal_cannot_read_outside_the_archive(archive_dir, tmp_path, admin_client):
    # A file that exists, one level above the archive, under a name a naive
    # join would reach.
    (tmp_path / 'secret.csv').write_bytes(b'not yours\n')
    res = admin_client.get('/api/events/imports/..%2Fsecret/file')
    assert res.status_code == 404


def test_download_returns_the_submitted_bytes(archive_dir, importable_patient, admin_client):
    body = b'A1,UW,2026-01-15,troponins,2\n'
    posted = _post_csv(admin_client, body, 'march.csv')
    import_id = posted.get_json()['data']['import_id']

    res = admin_client.get(f'/api/events/imports/{import_id}/file')
    assert res.status_code == 200
    assert res.data == body
    # The stored id is the download name, never the name as submitted.
    assert f'{import_id}.csv' in res.headers['Content-Disposition']
    assert 'march.csv' not in res.headers['Content-Disposition']


@pytest.mark.parametrize(
    'path',
    [
        '/api/events/imports',
        f'/api/events/imports/{NEWEST}',
        f'/api/events/imports/{NEWEST}/file',
    ],
)
def test_non_admin_is_refused(archive_dir, monkeypatch, path):
    """The route guard in the SPA is convenience; this decorator is the control."""
    import importlib

    from flask_backend import models
    from flask_backend.tests.test_auth_header_and_roles import FakeUser, _session_for

    _seed(archive_dir, NEWEST)
    monkeypatch.setattr(models, 'get_session', lambda: _session_for(FakeUser(reviewer=True)))

    app_mod = importlib.import_module('flask_backend.app')
    client = app_mod.app.test_client()
    res = client.get(path, headers={'X-Remote-User': 'alice'})
    assert res.status_code == 403
