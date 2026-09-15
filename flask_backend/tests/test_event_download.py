"""Integration tests for GET /api/events/download/<event_id>.

The endpoint used to look only for the scrubbed packet (`<event_id><ext>`)
and the legacy `event_<id>.zip`, so every packet written by
`/api/events/<id>/upload_raw` — which names files
`orig_<event_id>_<file_number><ext>` — was unreachable and the button
404'd. On a deployment with scrubbing switched off (the `scans` profile)
no scrubbed file is ever produced, so that was every event.

`DOWNLOADS_DIR` and `FILES_DIR` are module-level constants resolved at
import time, so they are patched per test onto a tmp_path; the event row
supplying `file_number` is mocked with the `FakeSession` pattern used by
the other endpoint tests.
"""

import importlib
from unittest.mock import patch

import pytest
from flask import g


DOWNLOAD_URL = "/api/events/download/{event_id}"

EVENT_ID = 4821
FILE_NUMBER = 837261234


class FakeEvent:
    def __init__(self, file_number=FILE_NUMBER):
        self.id = EVENT_ID
        self.file_number = file_number


class FakeSession:
    """Session that answers `query(...).get(id)` with one event row."""

    def __init__(self, event):
        self._event = event

    def query(self, _model, *args, **kwargs):
        return self

    def get(self, _row_id):
        return self._event

    def close(self):
        pass


@pytest.fixture
def app_mod():
    return importlib.import_module("flask_backend.app")


@pytest.fixture
def downloads(app_mod, tmp_path):
    """Point both packet directories at an empty tmp dir for one test."""
    d = tmp_path / "uploads"
    d.mkdir()
    with patch.object(app_mod, "DOWNLOADS_DIR", str(d)), patch.object(
        app_mod, "FILES_DIR", str(d)
    ):
        yield d


@pytest.fixture
def event_row(app_mod):
    """Install a mock `events` row, returning a setter for its file_number."""
    event = FakeEvent()

    def _session():
        return FakeSession(event)

    models = importlib.import_module("flask_backend.models")
    with patch.object(models, "get_session", _session):
        yield event


def _full_workflow(app_mod):
    """Patch the workflow config to the scrubbing-enabled profile."""
    cfg = app_mod.get_workflow_config()
    return patch.object(
        app_mod,
        "get_workflow_config",
        lambda: type(cfg)(
            study_type="mci",
            study_label="MCI",
            study_title="",
            scrubbing=True,
            screening=True,
            sending=True,
            reviewer_count=2,
        ),
    )


def _scans_workflow(app_mod):
    """Patch the workflow config to the scrubbing-disabled profile."""
    cfg = app_mod.get_workflow_config()
    return patch.object(
        app_mod,
        "get_workflow_config",
        lambda: type(cfg)(
            study_type="scans",
            study_label="SCANS",
            study_title="",
            scrubbing=False,
            screening=False,
            sending=False,
            reviewer_count=1,
        ),
    )


def _reviewer_client(app_mod):
    """Client authenticated as a plain reviewer — no uploader, no admin."""
    app_mod.keycloak_openid = None

    def _load():
        g.auth_user = {
            "id": 9,
            "username": "test-reviewer",
            "admin": False,
            "uploader": False,
            "reviewer": True,
            "third_reviewer": False,
            "site": "TEST",
        }
        return g.auth_user

    ctx = patch.object(app_mod, "_load_user_from_remote_header", _load)
    ctx.start()
    client = app_mod.app.test_client()
    client.environ_base["HTTP_X_REMOTE_USER"] = "test-reviewer"
    return ctx, client


def test_nothing_on_disk_is_still_a_404(admin_client, downloads, event_row):
    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))
    assert res.status_code == 404


def test_scrubbed_packet_is_served(admin_client, downloads, event_row):
    (downloads / f"{EVENT_ID}.pdf").write_bytes(b"scrubbed")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"scrubbed"
    assert res.headers["Content-Type"].startswith("application/pdf")
    assert f'filename="{EVENT_ID}.pdf"' in res.headers["Content-Disposition"]


def test_legacy_name_is_still_served(admin_client, downloads, event_row):
    (downloads / f"event_{EVENT_ID}.zip").write_bytes(b"legacy")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"legacy"


def test_raw_packet_is_found_by_file_number(admin_client, downloads, event_row):
    """The regression: a raw upload was previously unreachable."""
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"raw"
    assert res.headers["Content-Type"].startswith("application/zip")


def test_raw_packet_is_found_without_a_file_number(admin_client, downloads, event_row):
    """Legacy rows carry no `file_number`; the glob has to find the file."""
    event_row.file_number = None
    (downloads / f"orig_{EVENT_ID}_1.pdf").write_bytes(b"raw-legacy")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"raw-legacy"


def test_another_events_raw_packet_is_not_served(admin_client, downloads, event_row):
    """`orig_4821_*` must not match event 482's packets, or vice versa."""
    event_row.file_number = None
    (downloads / f"orig_{EVENT_ID}9_5.zip").write_bytes(b"different event")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 404


def test_scrubbed_packet_wins_over_the_raw_one(admin_client, downloads, event_row):
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")
    (downloads / f"{EVENT_ID}.zip").write_bytes(b"scrubbed")

    res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"scrubbed"


def test_reviewer_is_not_handed_the_unscrubbed_packet(app_mod, downloads, event_row):
    """With scrubbing in the workflow, the raw packet still carries PHI."""
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")
    ctx, client = _reviewer_client(app_mod)
    try:
        with _full_workflow(app_mod):
            res = client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))
    finally:
        ctx.stop()

    assert res.status_code == 404


def test_reviewer_gets_the_raw_packet_when_scrubbing_is_off(app_mod, downloads, event_row):
    """No scrub stage means the packet as submitted is the only packet."""
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")
    ctx, client = _reviewer_client(app_mod)
    try:
        with _scans_workflow(app_mod):
            res = client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))
    finally:
        ctx.stop()

    assert res.status_code == 200
    assert res.data == b"raw"


def test_reviewer_still_gets_the_scrubbed_packet(app_mod, downloads, event_row):
    (downloads / f"{EVENT_ID}.zip").write_bytes(b"scrubbed")
    ctx, client = _reviewer_client(app_mod)
    try:
        with _full_workflow(app_mod):
            res = client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))
    finally:
        ctx.stop()

    assert res.status_code == 200
    assert res.data == b"scrubbed"


def test_a_database_fault_does_not_hide_a_present_file(app_mod, admin_client, downloads):
    """A failed `file_number` lookup must fall through to the glob, not 404."""
    (downloads / f"orig_{EVENT_ID}_{FILE_NUMBER}.zip").write_bytes(b"raw")
    models = importlib.import_module("flask_backend.models")

    def _boom():
        raise RuntimeError("no database")

    with patch.object(models, "get_session", _boom):
        res = admin_client.get(DOWNLOAD_URL.format(event_id=EVENT_ID))

    assert res.status_code == 200
    assert res.data == b"raw"
