"""Tests for the shared workflow configuration layer (FR-002, FR-004,
FR-005, FR-006).

`get_workflow_config()` reads the environment on every call, so each test
sets the environment with `monkeypatch` (auto-reverted afterwards) and
asserts on the freshly resolved configuration.
"""

import pytest

from flask_backend.study_config import get_workflow_config, WorkflowConfigError

_CONTROL_VARS = (
    "STUDY_TYPE",
    "STUDY_TITLE",
    "ENABLE_SCRUBBING",
    "ENABLE_SCREENING",
    "ENABLE_SENDING",
    "REVIEWER_COUNT",
)


def _clear_controls(monkeypatch):
    """Remove every workflow-control variable so resolution starts clean."""
    for var in _CONTROL_VARS:
        monkeypatch.delenv(var, raising=False)


def test_study_title_defaults_to_empty(monkeypatch):
    """With STUDY_TITLE unset, the resolved title override is an empty string."""
    _clear_controls(monkeypatch)

    assert get_workflow_config().study_title == ""


def test_study_title_override_is_trimmed(monkeypatch):
    """A configured STUDY_TITLE is resolved verbatim, with surrounding whitespace trimmed."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TITLE", "  DEXA Scans Validation  ")

    assert get_workflow_config().study_title == "DEXA Scans Validation"


def test_scans_profile_resolves_to_full_bypass(monkeypatch):
    """`scans` resolves to scrubbing/screening/sending off, one reviewer."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "scans")

    cfg = get_workflow_config()

    assert cfg.study_type == "scans"
    assert (cfg.scrubbing, cfg.screening, cfg.sending, cfg.reviewer_count) == (
        False,
        False,
        False,
        1,
    )


def test_unset_study_resolves_to_full_workflow(monkeypatch):
    """An unset study runs the conservative full-workflow profile (FR-004)."""
    _clear_controls(monkeypatch)

    cfg = get_workflow_config()

    assert (cfg.scrubbing, cfg.screening, cfg.sending, cfg.reviewer_count) == (
        True,
        True,
        True,
        2,
    )


def test_other_study_resolves_to_full_workflow(monkeypatch):
    """A non-`scans` study (e.g. mci) runs the full-workflow profile."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "mci")

    cfg = get_workflow_config()

    assert (cfg.scrubbing, cfg.screening, cfg.sending, cfg.reviewer_count) == (
        True,
        True,
        True,
        2,
    )


def test_explicit_control_overrides_study_profile(monkeypatch):
    """An explicit `.env` control overrides the study-type default (FR-006)."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "scans")
    # Re-enable screening and a second reviewer on an otherwise-`scans` study.
    monkeypatch.setenv("ENABLE_SCREENING", "true")
    monkeypatch.setenv("REVIEWER_COUNT", "2")

    cfg = get_workflow_config()

    assert cfg.screening is True
    assert cfg.reviewer_count == 2
    # Controls not explicitly set still follow the `scans` profile.
    assert cfg.scrubbing is False
    assert cfg.sending is False


def test_reviewer_count_three_raises_naming_the_variable(monkeypatch):
    """REVIEWER_COUNT=3 is a startup error naming the variable (FR-005)."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("REVIEWER_COUNT", "3")

    with pytest.raises(WorkflowConfigError) as exc:
        get_workflow_config()

    message = str(exc.value)
    assert "REVIEWER_COUNT" in message
    assert "3" in message


def test_malformed_boolean_token_raises(monkeypatch):
    """A non-boolean control token is a startup error, never coerced (FR-005)."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("ENABLE_SCRUBBING", "maybe")

    with pytest.raises(WorkflowConfigError) as exc:
        get_workflow_config()

    assert "ENABLE_SCRUBBING" in str(exc.value)
