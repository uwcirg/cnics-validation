"""Flag-aware lifecycle tests (FR-007, FR-008, FR-009, FR-011).

Covers the shared assign/send status transitions and the flag-aware
eligibility/queue predicates in `table_service`, exercised with mocked
sessions so no database is required.
"""

from types import SimpleNamespace
from unittest.mock import MagicMock, patch

import flask_backend.table_service as ts

_CONTROL_VARS = (
    "STUDY_TYPE",
    "ENABLE_SCRUBBING",
    "ENABLE_SCREENING",
    "ENABLE_SENDING",
    "REVIEWER_COUNT",
)


def _clear_controls(monkeypatch):
    for var in _CONTROL_VARS:
        monkeypatch.delenv(var, raising=False)


def _mock_rows_session():
    """A MagicMock session whose execute(...) yields empty result sets."""
    session = MagicMock()
    session.execute.return_value.mappings.return_value.all.return_value = []
    session.execute.return_value.scalar.return_value = 0
    return session


# --- Assignment advances the lifecycle (FR-007/FR-008 entry point) ----------


@patch("flask_backend.table_service.get_session")
def test_first_assignment_sets_status_assigned(mock_get_session):
    """First-reviewer assignment moves the event into the `assigned` state."""
    event = SimpleNamespace(
        id=1, reviewer1_id=None, assigner_id=None, assign_date=None, status="uploaded"
    )
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [event]
    mock_get_session.return_value = session

    ts.assign_events([1], reviewer_id=7, slot="first", assigner_id=3)

    assert event.status == "assigned"
    assert event.reviewer1_id == 7


# --- Send advances the lifecycle only when sending is enabled (FR-009) ------


@patch("flask_backend.emailer.send_assignment_emails_for_event_ids")
@patch("flask_backend.table_service.get_session")
def test_send_sets_status_sent_when_sending_enabled(mock_get_session, mock_email, monkeypatch):
    """With sending enabled, `send_events` advances status to `sent`."""
    _clear_controls(monkeypatch)
    mock_email.return_value = {"attempted": 0, "sent": 0, "skipped": 0, "errors": []}
    event = SimpleNamespace(id=1, sender_id=None, send_date=None, status="assigned")
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [event]
    mock_get_session.return_value = session

    ts.send_events([1], sender_id=2)

    assert event.status == "sent"


@patch("flask_backend.emailer.send_assignment_emails_for_event_ids")
@patch("flask_backend.table_service.get_session")
def test_send_leaves_status_when_sending_disabled(mock_get_session, mock_email, monkeypatch):
    """With sending disabled, the send step does not advance `status`."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("ENABLE_SENDING", "false")
    mock_email.return_value = {"attempted": 0, "sent": 0, "skipped": 0, "errors": []}
    event = SimpleNamespace(id=1, sender_id=None, send_date=None, status="assigned")
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [event]
    mock_get_session.return_value = session

    ts.send_events([1], sender_id=2)

    assert event.status == "assigned"


# --- Reviewer queue eligibility is flag-aware (FR-009, FR-011) --------------


@patch("flask_backend.table_service.get_session")
def test_for_review_uses_sent_status_when_sending_enabled(mock_get_session, monkeypatch):
    """With sending enabled, the review queue keys on `status = 'sent'`."""
    _clear_controls(monkeypatch)
    mock_get_session.return_value = _mock_rows_session()

    ts.get_events_for_review(10, 0)

    params = [
        c.args[1]
        for c in mock_get_session.return_value.execute.call_args_list
        if len(c.args) > 1
    ]
    assert any(p.get("status") == "sent" for p in params)


@patch("flask_backend.table_service.get_session")
def test_for_review_uses_assigned_status_when_sending_disabled(mock_get_session, monkeypatch):
    """With sending disabled, an `assigned` event is review-queue eligible."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("ENABLE_SENDING", "false")
    mock_get_session.return_value = _mock_rows_session()

    ts.get_events_for_review(10, 0)

    params = [
        c.args[1]
        for c in mock_get_session.return_value.execute.call_args_list
        if len(c.args) > 1
    ]
    assert any(p.get("status") == "assigned" for p in params)


# --- Assignment eligibility skips bypassed pre-stages (FR-007, FR-008) ------


def test_ready_to_assign_predicate_skips_bypassed_stages(monkeypatch):
    """With scrubbing+screening off, readiness reduces to upload-only."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("ENABLE_SCRUBBING", "false")
    monkeypatch.setenv("ENABLE_SCREENING", "false")

    predicate = ts._ready_to_assign_predicate()

    assert predicate == "e.upload_date IS NOT NULL AND e.assign_date IS NULL"


def test_ready_to_assign_predicate_requires_all_enabled_stages(monkeypatch):
    """With the full workflow, readiness still requires scrub + screen."""
    _clear_controls(monkeypatch)

    predicate = ts._ready_to_assign_predicate()

    assert "e.upload_date IS NOT NULL" in predicate
    assert "e.scrub_date IS NOT NULL" in predicate
    assert "e.screen_date IS NOT NULL" in predicate
    assert "e.assign_date IS NULL" in predicate
