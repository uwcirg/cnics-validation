"""Atomic two-reviewer assignment (004-reviewer-assignment, research D4).

`POST /api/events/assign_many` gains an optional `reviewer2_id` field so a
two-reviewer deployment assigns both reviewers in one transaction — the
queue predicate is `assign_date IS NULL`, so a per-slot call would drop the
event from the queue before the second reviewer could be assigned.

These tests use mocked sessions and the auto-admin `admin_client` fixture
(see conftest.py); no database is required.
"""

from types import SimpleNamespace
from unittest.mock import MagicMock, patch

import flask_backend.table_service as ts


# --- Service layer: both reviewers set atomically (T009) --------------------


@patch("flask_backend.table_service.get_session")
def test_assign_events_two_reviewer_atomic(mock_get_session):
    """slot='first' with reviewer2_id sets both reviewers, audit, and status
    on every event within a single commit."""
    events = [
        SimpleNamespace(id=1, reviewer1_id=None, reviewer2_id=None,
                        assigner_id=None, assign_date=None, status="uploaded"),
        SimpleNamespace(id=2, reviewer1_id=None, reviewer2_id=None,
                        assigner_id=None, assign_date=None, status="uploaded"),
    ]
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
