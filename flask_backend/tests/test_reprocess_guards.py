"""Guards against acting on a superseded packet (spec 014 research D3, D12, D13).

After a replacement the event keeps its reviewer assignment while the new
packet goes back through the enabled pre-assignment stages. Until then:

- a scrubbed copy must name the packet version it was made from (D12);
- screening needs a scrubbed current packet (D13);
- the event cannot be sent (D3);
- the event cannot be reviewed (D3).
"""

import datetime
import importlib
import io
from types import SimpleNamespace
from unittest.mock import MagicMock, patch

import pytest

EVENT_ID = 31
D = datetime.date(2026, 11, 2)


class FakeEvent:
    def __init__(self, **fields):
        self.id = EVENT_ID
        self.patient_id = 7
        self.status = "uploaded"
        self.file_number = 555
        self.upload_date = D
        self.scrub_date = None
        self.scrubber_id = None
        self.screen_date = None
        self.screener_id = None
        self.assign_date = None
        self.send_date = None
        self.reviewer1_id = None
        self.reviewer2_id = None
        self.reviewer3_id = None
        self.review1_date = None
        self.review2_date = None
        self.review3_date = None
        self.rescrub_message = None
        self.reject_message = None
        for key, value in fields.items():
            setattr(self, key, value)

    def snapshot(self):
        return dict(vars(self))


class FakeSession:
    def __init__(self, event):
        self._event = event
        self.committed = False
        self.added = []

    def query(self, *args, **kwargs):
        return self

    def get(self, _id):
        return self._event

    def add(self, obj):
        self.added.append(obj)

    def commit(self):
        self.committed = True

    def rollback(self):
        pass

    def close(self):
        pass


def _profile(monkeypatch, study=None):
    for var in ("STUDY_TYPE", "REVIEWER_COUNT", "ENABLE_SCRUBBING",
                "ENABLE_SCREENING", "ENABLE_SENDING"):
        monkeypatch.delenv(var, raising=False)
    if study:
        monkeypatch.setenv("STUDY_TYPE", study)


@pytest.fixture
def downloads(tmp_path):
    app_mod = importlib.import_module("flask_backend.app")
    d = tmp_path / "uploads"
    d.mkdir()
    with patch.object(app_mod, "DOWNLOADS_DIR", str(d)):
        yield d


# --- (a) upload_scrubbed: packet version (D12) --------------------------------


def _scrub(client, event, packet_version=None):
    data = {"scrubbed_file": (io.BytesIO(b"%PDF-1.4"), "scrubbed.pdf")}
    if packet_version is not None:
        data["packet_version"] = str(packet_version)
    with patch("flask_backend.models.get_session", return_value=FakeSession(event)):
        return client.post(
            f"/api/events/{EVENT_ID}/upload_scrubbed",
            data=data, content_type="multipart/form-data",
        )


@pytest.mark.parametrize("version", [None, "", "abc"])
def test_scrub_without_a_packet_version_is_400(admin_client, downloads, monkeypatch, version):
    _profile(monkeypatch)
    event = FakeEvent()
    res = _scrub(admin_client, event, version)
    assert res.status_code == 400
    assert res.get_json() == {"error": "packet_version is required"}
    assert list(downloads.iterdir()) == []


def test_scrub_of_a_superseded_packet_is_409_and_writes_nothing(admin_client, downloads, monkeypatch):
    _profile(monkeypatch)
    event = FakeEvent(file_number=556)
    before = event.snapshot()
    res = _scrub(admin_client, event, 555)
    assert res.status_code == 409
    assert res.get_json() == {"error": (
        "This event's packet was replaced on 2026-11-02. Download the new packet and scrub it."
    )}
    assert "was replaced on" in res.get_json()["error"]
    assert list(downloads.iterdir()) == []
    assert event.snapshot() == before


def test_scrub_of_the_current_packet_returns_an_assigned_event_to_assigned(admin_client, downloads, monkeypatch):
    """dischargesummaries: scrub is the only stage, so the kept assignment
    makes the event reviewable again."""
    _profile(monkeypatch, "dischargesummaries")
    event = FakeEvent(reviewer1_id=9, assign_date=D)
    res = _scrub(admin_client, event, 555)
    assert res.status_code == 200
    assert event.scrub_date is not None
    assert event.status == "assigned"
    assert (downloads / f"{EVENT_ID}.pdf").exists()


def test_scrub_of_an_unassigned_event_is_scrubbed_as_before(admin_client, downloads, monkeypatch):
    _profile(monkeypatch)
    event = FakeEvent()
    assert _scrub(admin_client, event, 555).status_code == 200
    assert event.status == "scrubbed"


# --- (b) screen: scrub first (D13) ---------------------------------------------


def _screen(client, event, decision):
    with patch("flask_backend.models.get_session", return_value=FakeSession(event)):
        return client.post(f"/api/events/{EVENT_ID}/screen",
                           json={"decision": decision, "message": "m"})


@pytest.mark.parametrize("decision", ["accept", "rescrub", "reject"])
def test_screening_an_unscrubbed_packet_is_409(admin_client, monkeypatch, decision):
    _profile(monkeypatch)
    event = FakeEvent()
    before = event.snapshot()
    res = _screen(admin_client, event, decision)
    assert res.status_code == 409
    assert res.get_json() == {"error": "This event's packet has not been scrubbed yet."}
    assert event.snapshot() == before


def test_accepting_a_rescrubbed_assigned_event_returns_it_to_assigned(admin_client, monkeypatch):
    _profile(monkeypatch)
    event = FakeEvent(status="scrubbed", scrub_date=D, reviewer1_id=9, assign_date=D)
    res = _screen(admin_client, event, "accept")
    assert res.status_code == 200
    assert event.status == "assigned"


def test_accepting_an_unassigned_event_is_screened_as_before(admin_client, monkeypatch):
    _profile(monkeypatch)
    event = FakeEvent(status="scrubbed", scrub_date=D)
    assert _screen(admin_client, event, "accept").status_code == 200
    assert event.status == "screened"


# --- (c) send_many (D3) ----------------------------------------------------------


@patch("flask_backend.emailer.send_assignment_emails_for_event_ids")
def test_send_refuses_a_batch_with_an_event_being_reprocessed(mock_email, admin_client, monkeypatch):
    _profile(monkeypatch)
    ready = SimpleNamespace(id=1, upload_date=D, scrub_date=D, screen_date=D,
                            send_date=None, sender_id=None, status="assigned")
    reprocessing = SimpleNamespace(id=2, upload_date=D, scrub_date=None, screen_date=None,
                                   send_date=None, sender_id=None, status="uploaded")
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [ready, reprocessing]
    with patch("flask_backend.table_service.get_session", return_value=session):
        res = admin_client.post("/api/events/send_many", json={"event_ids": [1, 2]})

    assert res.status_code == 400
    assert res.get_json() == {"error": "These events are not ready to send: 2"}
    assert ready.send_date is None and ready.status == "assigned"
    session.commit.assert_not_called()
    mock_email.assert_not_called()


def test_to_be_sent_queue_requires_every_enabled_stage(monkeypatch):
    _profile(monkeypatch)
    ts = importlib.import_module("flask_backend.table_service")
    session = MagicMock()
    session.execute.return_value.mappings.return_value.all.return_value = []
    session.execute.return_value.scalar.return_value = 0
    with patch("flask_backend.table_service.get_session", return_value=session):
        ts.get_to_be_sent_with_total(10, 0, None, None)
    queries = [str(c.args[0]) for c in session.execute.call_args_list]
    assert any(
        "e.assign_date IS NOT NULL AND e.send_date IS NULL" in q
        and "e.upload_date IS NOT NULL" in q
        and "e.scrub_date IS NOT NULL" in q
        and "e.screen_date IS NOT NULL" in q
        for q in queries
    )


# --- (d) POST /review (D3) -------------------------------------------------------


def test_review_of_an_event_being_reprocessed_is_409(admin_client, monkeypatch):
    """dischargesummaries Phase A refuses every body at parse time, so the
    parser is stubbed to reach the guard."""
    _profile(monkeypatch, "dischargesummaries")
    ds = importlib.import_module("flask_backend.models.studies.dischargesummaries")
    app_mod = importlib.import_module("flask_backend.app")
    event = FakeEvent(status="uploaded", reviewer1_id=1, assign_date=D)
    session = FakeSession(event)
    with patch.object(ds, "parse_review", return_value={}), \
            patch.object(app_mod, "_study_schema_error", return_value=None), \
            patch("flask_backend.models.get_session", return_value=session):
        res = admin_client.post(f"/api/events/{EVENT_ID}/review", json={"x": 1})

    assert res.status_code == 409
    assert res.get_json() == {"error": "This event's packet is being re-processed."}
    assert event.review1_date is None
    assert not session.committed
