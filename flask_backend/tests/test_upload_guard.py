"""Upload status guard on POST /api/events/<id>/upload_raw (specs 013 and 014).

Observed before spec 013 (research D3): the endpoint had no status check. A
same-site uploader could upload at any status, and the handler always reset
the status to `uploaded`, silently undoing a scrub, assignment or completed
review. Spec 013 limited non-admins to `created`/`uploaded` and left admins
unrestricted.

Spec 014 (research D1) replaces that with one replacement window for
admins and same-site uploaders alike: a packet may be replaced until it is
released to reviewers (sent, or reviewed). The window's own tests are in
test_packet_replace_window.py.
"""

import importlib
import io
from unittest.mock import patch

import pytest

URL = "/api/events/4821/upload_raw"
REFUSAL = (
    "This packet has already been released to reviewers and can no longer be "
    "replaced here. Contact the systems operator."
)


class FakeEvent:
    def __init__(self, status, send_date=None, review1_date=None):
        self.id = 4821
        self.patient_id = 7
        self.status = status
        self.uploader_id = None
        self.file_number = None
        self.original_name = None
        self.upload_date = None
        self.scrub_date = None
        self.screen_date = None
        self.assign_date = None
        self.send_date = send_date
        self.review1_date = review1_date
        self.review2_date = None
        self.reviewer1_id = None


class FakePatient:
    def __init__(self, site):
        self.id = 7
        self.site = site


class FakeSession:
    """Answers `query(Events).get` with the event and `query(PatientsView).get` with the patient."""

    def __init__(self, event, patient_site="TEST"):
        self._event = event
        self._patient = FakePatient(patient_site)
        self._target = None
        self.committed = False
        self.added = []

    def query(self, model, *args, **kwargs):
        self._target = model
        return self

    def get(self, _row_id):
        models = importlib.import_module("flask_backend.models")
        return self._patient if self._target is models.PatientsView else self._event

    def add(self, obj):
        self.added.append(obj)

    def commit(self):
        self.committed = True

    def close(self):
        pass


@pytest.fixture
def downloads(tmp_path):
    app_mod = importlib.import_module("flask_backend.app")
    d = tmp_path / "uploads"
    d.mkdir()
    with patch.object(app_mod, "DOWNLOADS_DIR", str(d)):
        yield d


def _install(event, patient_site="TEST"):
    session = FakeSession(event, patient_site)
    return session, patch("flask_backend.models.get_session", return_value=session)


def _upload(client, filename="summary.pdf"):
    return client.post(
        URL,
        data={"chart_file": (io.BytesIO(b"%PDF-1.4"), filename)},
        content_type="multipart/form-data",
    )


def _uploader(client_as):
    return client_as(id=42, uploader=True, site="TEST")


@pytest.mark.parametrize("study", ["mci", "scans", "dischargesummaries"])
@pytest.mark.parametrize("status", ["created", "uploaded"])
def test_same_site_uploader_may_upload_before_scrub(client_as, downloads, monkeypatch, study, status):
    """Every study keeps the replace-while-uploaded path (spec 013 US4)."""
    monkeypatch.setenv("STUDY_TYPE", study)
    event = FakeEvent(status)
    session, ctx = _install(event)
    with ctx:
        res = _upload(_uploader(client_as))

    assert res.status_code == 200
    assert event.status == "uploaded"
    assert event.uploader_id == 42
    assert session.committed
    assert len(list(downloads.iterdir())) == 1


@pytest.mark.parametrize("study", ["mci", "scans", "dischargesummaries"])
@pytest.mark.parametrize("status", ["sent", "reviewer1_done", "done"])
def test_same_site_uploader_is_refused_after_release(client_as, downloads, monkeypatch, study, status):
    """Past the window (spec 014 D1): sent or reviewed."""
    monkeypatch.setenv("STUDY_TYPE", study)
    event = FakeEvent(status, send_date="2026-11-01", review1_date="2026-11-02")
    session, ctx = _install(event)
    with ctx:
        res = _upload(_uploader(client_as))

    assert res.status_code == 409
    assert res.get_json() == {"error": REFUSAL}
    assert event.status == status
    assert event.uploader_id is None
    assert event.file_number is None
    assert event.upload_date is None
    assert not session.committed
    assert list(downloads.iterdir()) == []


def test_admin_may_upload_after_scrub_but_not_after_send(admin_client, downloads):
    """spec 014 narrows the admin bypass: the window applies to admins too."""
    event = FakeEvent("scrubbed")
    _session, ctx = _install(event)
    with ctx:
        res = _upload(admin_client)

    assert res.status_code == 200
    assert event.status == "uploaded"

    sent = FakeEvent("sent", send_date="2026-11-01")
    session, ctx = _install(sent)
    with ctx:
        res = _upload(admin_client)

    assert res.status_code == 409
    assert res.get_json() == {"error": REFUSAL}
    assert sent.status == "sent"
    assert not session.committed


def test_wrong_site_is_still_403_not_409(client_as, downloads):
    event = FakeEvent("scrubbed")
    _session, ctx = _install(event, patient_site="OTHER")
    with ctx:
        res = _upload(_uploader(client_as))

    assert res.status_code == 403


def test_bad_extension_is_still_400_not_409(client_as, downloads):
    event = FakeEvent("scrubbed")
    _session, ctx = _install(event)
    with ctx:
        res = _upload(_uploader(client_as), filename="summary.exe")

    assert res.status_code == 400
