"""Regression tests: with no workflow-stage controls set, the resolved
configuration is the full-workflow profile and the shared assign/send
transitions run the complete pipeline (FR-004, FR-017).

This guards the "bypass ≠ change" guarantee — adding `scans` must not alter
the behavior of a study left at its defaults.
"""

from types import SimpleNamespace
from unittest.mock import MagicMock, patch

import flask_backend.table_service as ts
from flask_backend.study_config import get_workflow_config

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


def test_no_controls_resolves_to_full_workflow(monkeypatch):
    """With nothing set, the deployment runs the complete pipeline (FR-004)."""
    _clear_controls(monkeypatch)

    cfg = get_workflow_config()

    assert cfg.scrubbing is True
    assert cfg.screening is True
    assert cfg.sending is True
    assert cfg.reviewer_count == 2


@patch("flask_backend.table_service.get_session")
def test_full_workflow_assignment_advances_status(mock_get_session, monkeypatch):
    """A full-workflow first assignment still advances status to `assigned`."""
    _clear_controls(monkeypatch)
    event = SimpleNamespace(
        id=1, reviewer1_id=None, assigner_id=None, assign_date=None, status="screened"
    )
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [event]
    mock_get_session.return_value = session

    ts.assign_events([1], reviewer_id=4, slot="first", assigner_id=2)

    assert event.status == "assigned"


@patch("flask_backend.emailer.send_assignment_emails_for_event_ids")
@patch("flask_backend.table_service.get_session")
def test_full_workflow_send_advances_status(mock_get_session, mock_email, monkeypatch):
    """A full-workflow send still advances status to `sent`."""
    _clear_controls(monkeypatch)
    mock_email.return_value = {"attempted": 0, "sent": 0, "skipped": 0, "errors": []}
    event = SimpleNamespace(id=1, sender_id=None, send_date=None, status="assigned")
    session = MagicMock()
    session.query.return_value.filter.return_value.all.return_value = [event]
    mock_get_session.return_value = session

    ts.send_events([1], sender_id=2)

    assert event.status == "sent"


def test_full_workflow_review_queue_keys_on_sent(monkeypatch):
    """With the full workflow, the review queue still keys on `status='sent'`."""
    _clear_controls(monkeypatch)

    session = MagicMock()
    session.execute.return_value.mappings.return_value.all.return_value = []
    session.execute.return_value.scalar.return_value = 0
    with patch("flask_backend.table_service.get_session", return_value=session):
        ts.get_events_for_review(5, 0)

    params = [
        c.args[1] for c in session.execute.call_args_list if len(c.args) > 1
    ]
    assert any(p.get("status") == "sent" for p in params)
