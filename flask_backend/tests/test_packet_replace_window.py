"""Packet replacement window on POST /api/events/<id>/upload_raw (spec 014).

Research D1: an admin or a same-site uploader may replace a packet until it
is released to reviewers, meaning sent, or (with sending off) reviewed.
Research D2, D4, D14: a replacement undoes the scrub and screen, renames the
old scrubbed copy aside, keeps the reviewer assignment, and writes a `logs`
row.

Fakes follow the `FakeEvent` / `_install` / `_upload` pattern of
test_upload_guard.py.
"""

import datetime
import importlib
import io
import re
from unittest.mock import patch

import pytest

EVENT_ID = 4821
URL = f"/api/events/{EVENT_ID}/upload_raw"
D = datetime.date(2026, 11, 1)
RELEASED = (
    "This packet has already been released to reviewers and can no longer be "
    "replaced here. Contact the systems operator."
)


class FakeEvent:
    def __init__(self, status, **fields):
        self.id = EVENT_ID
        self.patient_id = 7
        self.status = status
        self.uploader_id = None
        self.file_number = None
        self.original_name = None
        self.upload_date = None
        self.scrub_date = None
        self.scrubber_id = None
        self.screen_date = None
        self.screener_id = None
        self.assign_date = None
        self.assigner_id = None
        self.send_date = None
        self.review1_date = None
        self.review2_date = None
        self.reviewer1_id = None
        self.reviewer2_id = None
        self.rescrub_message = None
        self.reject_message = None
        for key, value in fields.items():
            setattr(self, key, value)


class FakePatient:
    def __init__(self, site):
        self.id = 7
        self.site = site


class FakeSession:
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


@pytest.fixture(autouse=True)
def _default_profile(monkeypatch):
    """Full workflow (mci defaults) unless a test picks another study."""
    for var in ("STUDY_TYPE", "REVIEWER_COUNT", "ENABLE_SCRUBBING",
                "ENABLE_SCREENING", "ENABLE_SENDING"):
        monkeypatch.delenv(var, raising=False)


def _install(event, patient_site="TEST"):
    session = FakeSession(event, patient_site)
    return session, patch("flask_backend.models.get_session", return_value=session)


def _upload(client, filename="summary.pdf"):
    return client.post(
        URL,
        data={"chart_file": (io.BytesIO(b"%PDF-1.4"), filename)},
        content_type="multipart/form-data",
    )


def _uploader(client_as, site="TEST"):
    return client_as(id=42, uploader=True, site=site)


def _admin(client_as):
    return client_as(id=1, admin=True, site="CC")


def _logs(session):
    models = importlib.import_module("flask_backend.models")
    return [o for o in session.added if isinstance(o, models.Logs)]


# --- T010: the window --------------------------------------------------------


@pytest.mark.parametrize("status", ["uploaded", "scrubbed", "screened", "assigned", "rejected"])
def test_same_site_uploader_may_replace_inside_the_window(client_as, downloads, status):
    event = FakeEvent(status, upload_date=D)
    session, ctx = _install(event)
    with ctx:
        res = _upload(_uploader(client_as))

    assert res.status_code == 200
    assert res.get_json() == {"data": {"saved": True}}
    assert session.committed


@pytest.mark.parametrize("who", ["uploader", "admin"])
@pytest.mark.parametrize("status,dates", [
    ("sent", {"send_date": D}),
    ("reviewer1_done", {"send_date": D, "review1_date": D}),
    ("done", {"send_date": D, "review1_date": D, "review2_date": D}),
    ("assigned", {"review1_date": D}),     # sending off: reviewed, not sent
])
def test_released_packet_is_refused_to_everyone(client_as, downloads, who, status, dates):
    event = FakeEvent(status, upload_date=D, **dates)
    session, ctx = _install(event)
    client = _uploader(client_as) if who == "uploader" else _admin(client_as)
    with ctx:
        res = _upload(client)

    assert res.status_code == 409
    assert res.get_json() == {"error": RELEASED}
    assert event.status == status
    assert not session.committed
    assert session.added == []
    assert list(downloads.iterdir()) == []


def test_no_packet_available_admin_may_upload(client_as, downloads):
    event = FakeEvent("no_packet_available")
    _session, ctx = _install(event)
    with ctx:
        res = _upload(_admin(client_as))
    assert res.status_code == 200
    assert event.status == "uploaded"


def test_no_packet_available_uploader_is_refused(client_as, downloads):
    event = FakeEvent("no_packet_available")
    _session, ctx = _install(event)
    with ctx:
        res = _upload(_uploader(client_as))
    assert res.status_code == 409
    assert res.get_json() == {"error": RELEASED}


def test_other_site_uploader_gets_403_inside_the_window(client_as, downloads):
    event = FakeEvent("scrubbed", upload_date=D, scrub_date=D)
    _session, ctx = _install(event, patient_site="OTHER")
    with ctx:
        res = _upload(_uploader(client_as))
    assert res.status_code == 403


# --- T011: what a replacement does ------------------------------------------


def test_replacement_undoes_scrub_and_screen_but_keeps_the_assignment(client_as, downloads):
    event = FakeEvent(
        "assigned", upload_date=D, scrub_date=D, scrubber_id=3, screen_date=D,
        screener_id=4, reviewer1_id=9, reviewer2_id=10, assign_date=D, assigner_id=5,
        rescrub_message="redo p.3", file_number=111,
    )
    (downloads / f"{EVENT_ID}.pdf").write_bytes(b"old scrubbed")
    (downloads / f"event_{EVENT_ID}.zip").write_bytes(b"legacy scrubbed")
    session, ctx = _install(event)
    with ctx:
        res = _upload(_uploader(client_as))

    assert res.status_code == 200
    # Undone (D2).
    assert event.scrub_date is None and event.scrubber_id is None
    assert event.screen_date is None and event.screener_id is None
    # Kept (FR-004).
    assert (event.reviewer1_id, event.reviewer2_id) == (9, 10)
    assert event.assign_date == D and event.assigner_id == 5
    assert event.rescrub_message == "redo p.3"
    # New packet version; status derived (full profile: back to scrubbing).
    assert event.file_number != 111
    assert event.status == "uploaded"
    # Old scrubbed copies moved aside, never deleted (D4).
    names = sorted(p.name for p in downloads.iterdir())
    assert f"{EVENT_ID}.pdf" not in names
    assert f"event_{EVENT_ID}.zip" not in names
    assert any(re.fullmatch(rf"superseded_{EVENT_ID}_\d{{14}}\.pdf", n) for n in names)
    assert any(re.fullmatch(rf"superseded_{EVENT_ID}_\d{{14}}\.zip", n) for n in names)
    assert any(n.startswith(f"orig_{EVENT_ID}_") for n in names)
    # Audit row (D14).
    logs = _logs(session)
    assert len(logs) == 1
    log = logs[0]
    assert (log.controller, log.action) == ("events", "replace_packet")
    assert log.params == f"event_id={EVENT_ID};assigned=1"
    assert log.user_id == 42
    assert isinstance(log.time, datetime.datetime)


def test_two_zip_copies_are_both_kept(client_as, downloads):
    """`<id>.zip` and legacy `event_<id>.zip` map to the same superseded
    name; neither may overwrite the other."""
    event = FakeEvent("scrubbed", upload_date=D, scrub_date=D)
    (downloads / f"{EVENT_ID}.zip").write_bytes(b"current")
    (downloads / f"event_{EVENT_ID}.zip").write_bytes(b"legacy")
    _session, ctx = _install(event)
    with ctx:
        assert _upload(_uploader(client_as)).status_code == 200

    kept = sorted(p.read_bytes() for p in downloads.iterdir() if p.name.startswith("superseded_"))
    assert kept == [b"current", b"legacy"]


def test_unassigned_replacement_logs_assigned_0(client_as, downloads):
    event = FakeEvent("scrubbed", upload_date=D, scrub_date=D)
    session, ctx = _install(event)
    with ctx:
        _upload(_uploader(client_as))
    assert [l.params for l in _logs(session)] == [f"event_id={EVENT_ID};assigned=0"]


def test_first_upload_is_not_a_replacement(client_as, downloads):
    event = FakeEvent("created")
    (downloads / f"{EVENT_ID}.pdf").write_bytes(b"stray")
    session, ctx = _install(event)
    with ctx:
        res = _upload(_uploader(client_as))

    assert res.status_code == 200
    assert event.status == "uploaded"
    assert _logs(session) == []
    assert (downloads / f"{EVENT_ID}.pdf").exists()
    assert not any(p.name.startswith("superseded_") for p in downloads.iterdir())


@pytest.mark.parametrize("study,expected", [
    ("scans", "assigned"),               # no scrub/screen: file swapped only
    ("dischargesummaries", "uploaded"),  # must be scrubbed again
])
def test_assigned_replacement_status_by_profile(client_as, downloads, monkeypatch, study, expected):
    monkeypatch.setenv("STUDY_TYPE", study)
    event = FakeEvent("assigned", upload_date=D, scrub_date=D, reviewer1_id=9, assign_date=D)
    _session, ctx = _install(event)
    with ctx:
        res = _upload(_uploader(client_as))
    assert res.status_code == 200
    assert event.status == expected
    assert event.reviewer1_id == 9
