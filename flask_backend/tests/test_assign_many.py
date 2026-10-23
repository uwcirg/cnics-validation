"""Atomic two-reviewer assignment (004-reviewer-assignment, research D4).

`POST /api/events/assign_many` gains an optional `reviewer2_id` field so a
two-reviewer deployment assigns both reviewers in one transaction — the
queue predicate is `assign_date IS NULL`, so a per-slot call would drop the
event from the queue before the second reviewer could be assigned.

These tests use mocked sessions and the auto-admin `admin_client` fixture
(see conftest.py); no database is required.
"""

import datetime
from types import SimpleNamespace
from unittest.mock import MagicMock, patch

import pytest

import flask_backend.table_service as ts

TODAY = datetime.date(2026, 10, 23)


def _ready_event(event_id, **dates):
    """An event that has passed every pre-assignment stage, unless `dates`
    blanks one (e.g. ``scrub_date=None``)."""
    fields = dict(upload_date=TODAY, scrub_date=TODAY, screen_date=TODAY)
    fields.update(dates)
    return SimpleNamespace(id=event_id, reviewer1_id=None, reviewer2_id=None,
                           assigner_id=None, assign_date=None, status="screened",
                           **fields)


# --- Service layer: both reviewers set atomically (T009) --------------------


@patch("flask_backend.table_service.get_session")
def test_assign_events_two_reviewer_atomic(mock_get_session):
    """slot='first' with reviewer2_id sets both reviewers, audit, and status
    on every event within a single commit."""
    events = [_ready_event(1), _ready_event(2)]
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = events
    mock_get_session.return_value = session

    result = ts.assign_events([1, 2], reviewer_id=7, slot="first",
                              assigner_id=3, reviewer2_id=9)

    assert result == {"updated": 2}
    for e in events:
        assert e.reviewer1_id == 7
        assert e.reviewer2_id == 9
        assert e.assigner_id == 3
        assert e.assign_date is not None
        assert e.status == "assigned"
    # Atomic: one commit covers every event — all get both reviewers or none do.
    session.commit.assert_called_once()


# --- Endpoint validation: reviewer2_id is rejected when invalid (T010) ------


def test_assign_many_rejects_reviewer2_when_count_one(admin_client, monkeypatch):
    """A second reviewer cannot be assigned in a single-reviewer deployment."""
    monkeypatch.setenv("REVIEWER_COUNT", "1")
    res = admin_client.post("/api/events/assign_many", json={
        "event_ids": [1], "reviewer_id": 7, "slot": "first", "reviewer2_id": 9,
    })
    assert res.status_code == 400
    assert res.get_json().get("error")


def test_assign_many_rejects_reviewer2_with_non_first_slot(admin_client, monkeypatch):
    """reviewer2_id is only valid alongside the first slot."""
    monkeypatch.setenv("REVIEWER_COUNT", "2")
    res = admin_client.post("/api/events/assign_many", json={
        "event_ids": [1], "reviewer_id": 7, "slot": "second", "reviewer2_id": 9,
    })
    assert res.status_code == 400
    assert res.get_json().get("error")


def test_assign_many_rejects_reviewer2_equals_reviewer(admin_client, monkeypatch):
    """The same person must not fill both reviewer slots (FR-011)."""
    monkeypatch.setenv("REVIEWER_COUNT", "2")
    res = admin_client.post("/api/events/assign_many", json={
        "event_ids": [1], "reviewer_id": 7, "slot": "first", "reviewer2_id": 7,
    })
    assert res.status_code == 400
    assert res.get_json().get("error")


# --- Endpoint happy path: reviewer2_id is forwarded to the service (T010) ---


@patch("flask_backend.app.table_service.assign_events")
def test_assign_many_forwards_reviewer2_id(mock_assign, admin_client, monkeypatch):
    """A valid Form B body reaches assign_events with reviewer2_id."""
    monkeypatch.setenv("REVIEWER_COUNT", "2")
    mock_assign.return_value = {"updated": 1}
    res = admin_client.post("/api/events/assign_many", json={
        "event_ids": [1], "reviewer_id": 7, "slot": "first", "reviewer2_id": 9,
    })
    assert res.status_code == 200
    assert res.get_json() == {"data": {"updated": 1}}
    assert mock_assign.call_args.kwargs.get("reviewer2_id") == 9


# --- Assignment readiness guard (spec 013 D2) -------------------------------
#
# Assignment used to accept any event id. Now a first-slot assignment refuses
# the whole batch unless every event has passed every enabled pre-assignment
# stage. These tests run through the endpoint with a mocked session.


def _set_profile(monkeypatch, study):
    for var in ("ENABLE_SCRUBBING", "ENABLE_SCREENING", "ENABLE_SENDING", "REVIEWER_COUNT"):
        monkeypatch.delenv(var, raising=False)
    monkeypatch.setenv("STUDY_TYPE", study)


def _assign_first(client, ids):
    return client.post("/api/events/assign_many", json={
        "event_ids": ids, "reviewer_id": 7, "slot": "first",
    })


@pytest.mark.parametrize("blank", ["upload_date", "scrub_date"])
@patch("flask_backend.table_service.get_session")
def test_dischargesummaries_refuses_batch_with_an_unready_event(
    mock_get_session, admin_client, monkeypatch, blank
):
    _set_profile(monkeypatch, "dischargesummaries")
    events = [_ready_event(12), _ready_event(15, **{blank: None}), _ready_event(18, **{blank: None})]
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = events
    mock_get_session.return_value = session

    res = _assign_first(admin_client, [12, 15, 18])

    assert res.status_code == 400
    assert res.get_json()["error"] == "These events are not ready for assignment: 15, 18"
    session.commit.assert_not_called()
    for e in events:
        assert e.reviewer1_id is None
        assert e.status == "screened"


@patch("flask_backend.table_service.get_session")
def test_dischargesummaries_assigns_a_scrubbed_batch(mock_get_session, admin_client, monkeypatch):
    _set_profile(monkeypatch, "dischargesummaries")
    events = [_ready_event(1, screen_date=None), _ready_event(2, screen_date=None)]
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = events
    mock_get_session.return_value = session

    res = _assign_first(admin_client, [1, 2])

    assert res.status_code == 200
    assert res.get_json() == {"data": {"updated": 2}}
    assert all(e.status == "assigned" for e in events)


@patch("flask_backend.table_service.get_session")
def test_scans_assigns_an_uploaded_unscrubbed_event(mock_get_session, admin_client, monkeypatch):
    """Scrubbing is off for scans, so an uploaded event is ready."""
    _set_profile(monkeypatch, "scans")
    event = _ready_event(1, scrub_date=None, screen_date=None)
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [event]
    mock_get_session.return_value = session

    res = _assign_first(admin_client, [1])

    assert res.status_code == 200
    assert event.status == "assigned"


@patch("flask_backend.table_service.get_session")
def test_mci_refuses_an_unscreened_event(mock_get_session, admin_client, monkeypatch):
    _set_profile(monkeypatch, "mci")
    event = _ready_event(4, screen_date=None)
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [event]
    mock_get_session.return_value = session

    res = _assign_first(admin_client, [4])

    assert res.status_code == 400
    assert "4" in res.get_json()["error"]
    session.commit.assert_not_called()


@patch("flask_backend.table_service.get_session")
def test_scans_refuses_an_event_with_no_upload(mock_get_session, admin_client, monkeypatch):
    """Upload is never optional, even when every other stage is off."""
    _set_profile(monkeypatch, "scans")
    event = _ready_event(3, upload_date=None, scrub_date=None, screen_date=None)
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [event]
    mock_get_session.return_value = session

    res = _assign_first(admin_client, [3])

    assert res.status_code == 400
