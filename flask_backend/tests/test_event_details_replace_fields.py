"""New fields on GET /api/events/<id> (spec 014 research D10, D12, D14).

- `packet_replaceable`: whether this caller may replace the packet now.
- `file_number`: the packet version the scrub page sends back.
- `packet_replaced_at`: the latest replacement made while a reviewer was
  assigned, when sending is off.
"""

import datetime
import importlib
from unittest.mock import MagicMock, patch

import pytest

import flask_backend.table_service as ts

D = datetime.date(2026, 11, 1)


class FakeEvent:
    def __init__(self, status, **fields):
        self.id = 5
        self.patient_id = 7
        self.status = status
        self.send_date = None
        self.review1_date = None
        self.review2_date = None
        self.reviewer1_id = None
        self.reviewer2_id = None
        self.reviewer3_id = None
        for key, value in fields.items():
            setattr(self, key, value)


class FakePatient:
    site = "TEST"


class FakeSession:
    def __init__(self, event):
        self._event = event
        self._target = None

    def query(self, model, *args, **kwargs):
        self._target = model
        return self

    def get(self, _id):
        models = importlib.import_module("flask_backend.models")
        return FakePatient() if self._target is models.PatientsView else self._event

    def close(self):
        pass


def _details(client, event):
    with patch("flask_backend.models.get_session", return_value=FakeSession(event)), \
            patch("flask_backend.table_service.get_event_details",
                  return_value={"id": 5, "file_number": 123, "packet_replaced_at": None}):
        return client.get("/api/events/5")


@pytest.mark.parametrize("status,dates,site,expected", [
    ("scrubbed", {}, "TEST", True),
    ("sent", {"send_date": D}, "TEST", False),
    ("scrubbed", {}, "OTHER", False),
])
def test_packet_replaceable_for_an_uploader(client_as, status, dates, site, expected):
    client = client_as(id=42, uploader=True, site=site)
    res = _details(client, FakeEvent(status, **dates))
    assert res.status_code == 200
    data = res.get_json()["data"]
    assert data["packet_replaceable"] is expected
    assert data["file_number"] == 123


def test_packet_replaceable_is_false_for_a_reviewer(client_as):
    client = client_as(id=9, reviewer=True)
    res = _details(client, FakeEvent("assigned", reviewer1_id=9))
    assert res.status_code == 200
    assert res.get_json()["data"]["packet_replaceable"] is False


# --- table_service.get_event_details ---------------------------------------------


def _profile(monkeypatch, study=None):
    for var in ("STUDY_TYPE", "REVIEWER_COUNT", "ENABLE_SCRUBBING",
                "ENABLE_SCREENING", "ENABLE_SENDING"):
        monkeypatch.delenv(var, raising=False)
    if study:
        monkeypatch.setenv("STUDY_TYPE", study)


def _session(latest):
    session = MagicMock()
    session.execute.return_value.mappings.return_value.first.return_value = {"id": 5, "status": "assigned"}
    session.execute.return_value.mappings.return_value.all.return_value = []
    session.execute.return_value.scalar.return_value = latest
    return session


def test_details_query_selects_file_number(monkeypatch):
    _profile(monkeypatch)
    session = _session(None)
    with patch("flask_backend.table_service.get_session", return_value=session):
        ts.get_event_details(5)
    assert any("e.file_number AS file_number" in str(c.args[0]) for c in session.execute.call_args_list)


def test_packet_replaced_at_when_sending_is_off(monkeypatch):
    _profile(monkeypatch, "scans")
    session = _session(datetime.datetime(2026, 11, 24, 10, 15, 2))
    with patch("flask_backend.table_service.get_session", return_value=session):
        details = ts.get_event_details(5)
    assert details["packet_replaced_at"] == "2026-11-24T10:15:02"
    log_calls = [c for c in session.execute.call_args_list if "FROM logs" in str(c.args[0])]
    assert len(log_calls) == 1
    sql = str(log_calls[0].args[0])
    assert "action = 'replace_packet'" in sql and "controller = 'events'" in sql
    # Only replacements made while a reviewer was assigned count.
    assert log_calls[0].args[1] == {"p": "event_id=5;assigned=1"}


def test_packet_replaced_at_is_null_without_an_assigned_replacement(monkeypatch):
    """Only `assigned=0` rows exist, so the `assigned=1` lookup finds none."""
    _profile(monkeypatch, "dischargesummaries")
    session = _session(None)
    with patch("flask_backend.table_service.get_session", return_value=session):
        details = ts.get_event_details(5)
    assert details["packet_replaced_at"] is None


def test_packet_replaced_at_is_null_when_sending_is_on(monkeypatch):
    _profile(monkeypatch)  # mci: sending on
    session = _session(datetime.datetime(2026, 11, 24, 10, 15, 2))
    with patch("flask_backend.table_service.get_session", return_value=session):
        details = ts.get_event_details(5)
    assert details["packet_replaced_at"] is None
    assert not any("FROM logs" in str(c.args[0]) for c in session.execute.call_args_list)
