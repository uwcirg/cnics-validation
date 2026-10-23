"""Lifecycle tests for the `dischargesummaries` profile (spec 013, research D1, D2).

Scrub on, screen off, send off, one reviewer: no existing test covered
scrubbing on with screening off. Mocked sessions, as in
`test_scans_workflow.py`; no database is required.
"""

import datetime
from types import SimpleNamespace
from unittest.mock import MagicMock, patch

import pytest

import flask_backend.table_service as ts

_CONTROL_VARS = (
    "ENABLE_SCRUBBING",
    "ENABLE_SCREENING",
    "ENABLE_SENDING",
    "REVIEWER_COUNT",
)

TODAY = datetime.date(2026, 10, 23)


@pytest.fixture(autouse=True)
def dischargesummaries(monkeypatch):
    for var in _CONTROL_VARS:
        monkeypatch.delenv(var, raising=False)
    monkeypatch.setenv("STUDY_TYPE", "dischargesummaries")


def _mock_rows_session():
    session = MagicMock()
    session.execute.return_value.mappings.return_value.all.return_value = []
    session.execute.return_value.scalar.return_value = 0
    return session


def _event(event_id=1, *, upload_date=TODAY, scrub_date=TODAY, status="scrubbed"):
    return SimpleNamespace(
        id=event_id, reviewer1_id=None, reviewer2_id=None, assigner_id=None,
        assign_date=None, upload_date=upload_date, scrub_date=scrub_date,
        screen_date=None, status=status,
    )


def _session_with(events):
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = events
    return session


# --- Queues ----------------------------------------------------------------


def test_ready_to_assign_requires_scrub_but_not_screen():
    predicate = ts._ready_to_assign_predicate()

    assert "e.upload_date IS NOT NULL" in predicate
    assert "e.scrub_date IS NOT NULL" in predicate
    assert "screen_date" not in predicate
    assert "e.assign_date IS NULL" in predicate


@patch("flask_backend.table_service.get_session")
def test_screen_queue_is_empty_and_scrub_queue_is_not(mock_get_session):
    mock_get_session.return_value = _mock_rows_session()

    assert ts.get_to_be_screened_with_total(10, 0, None, None) == ([], 0)
    mock_get_session.assert_not_called()

    ts.get_to_be_scrubbed_with_total(10, 0, None, None)
    sql = str(mock_get_session.return_value.execute.call_args_list[0].args[0])
    assert "e.scrub_date IS NULL" in sql


@patch("flask_backend.table_service.get_session")
def test_review_queue_gates_on_assigned(mock_get_session):
    """With no send step, an `assigned` event is ready for review."""
    mock_get_session.return_value = _mock_rows_session()

    ts.get_events_for_review(10, 0)

    params = [
        c.args[1]
        for c in mock_get_session.return_value.execute.call_args_list
        if len(c.args) > 1
    ]
    assert any(p.get("status") == "assigned" for p in params)


# --- Assignment --------------------------------------------------------------


@patch("flask_backend.table_service.get_session")
def test_first_assignment_of_a_scrubbed_event_sets_assigned(mock_get_session):
    event = _event()
    mock_get_session.return_value = _session_with([event])

    ts.assign_events([1], reviewer_id=7, slot="first", assigner_id=3)

    assert event.status == "assigned"
    assert event.reviewer1_id == 7


@patch("flask_backend.table_service.get_session")
def test_assigning_an_unscrubbed_event_is_refused(mock_get_session):
    """D2: the scrub stage is on, so an uploaded-only event is not ready."""
    event = _event(scrub_date=None, status="uploaded")
    session = _session_with([event])
    mock_get_session.return_value = session

    with pytest.raises(ts.ValidationError, match="not ready for assignment: 1"):
        ts.assign_events([1], reviewer_id=7, slot="first", assigner_id=3)

    assert event.reviewer1_id is None
    assert event.status == "uploaded"
    session.commit.assert_not_called()


def test_second_slot_is_unavailable(admin_client):
    """One reviewer: the second slot cannot be assigned."""
    res = admin_client.post("/api/events/assign_many", json={
        "event_ids": [1], "reviewer_id": 7, "slot": "second",
    })

    assert res.status_code == 400
