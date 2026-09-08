"""Tests for the shared workflow configuration layer (FR-002, FR-004,
FR-005, FR-006).

`get_workflow_config()` reads the environment on every call, so each test
sets the environment with `monkeypatch` (auto-reverted afterwards) and
asserts on the freshly resolved configuration.
"""

import pytest

from flask_backend.study_config import (
    get_study_label,
    get_study_type,
    get_workflow_config,
    WorkflowConfigError,
)

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


# --- Study label (spec 010) ------------------------------------------------
#
# `get_study_label()` and `get_study_type()` differ ONLY in how they treat an
# unset STUDY_TYPE, and that difference is the entire point of the accessor.
# The unset case below is load-bearing: if the label ever starts defaulting to
# "MCI", every other assertion in this file still passes while an
# unconfigured deployment silently names a study it was never set up for.


def test_study_label_upper_cases_the_configured_study(monkeypatch):
    """A configured study yields its upper-cased value (FR-003)."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "mci")

    assert get_study_label() == "MCI"


def test_study_label_trims_and_upper_cases(monkeypatch):
    """Surrounding whitespace and mixed case are tolerated (FR-008)."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "  Scans  ")

    assert get_study_label() == "SCANS"


def test_study_label_is_empty_when_unset_but_study_type_still_defaults(monkeypatch):
    """Unset STUDY_TYPE: the label reports "" while the type keeps its `mci` default.

    Both halves are asserted together, in one test, so the distinction between
    the two accessors cannot silently regress — collapsing them would make the
    headings and the assignment email name MCI on a deployment that was never
    configured (FR-005).
    """
    _clear_controls(monkeypatch)

    assert get_study_label() == ""
    assert get_study_type() == "mci"


def test_study_label_is_empty_when_blank(monkeypatch):
    """A whitespace-only STUDY_TYPE is treated as unset (FR-005)."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "   ")

    assert get_study_label() == ""


def test_workflow_config_carries_the_study_label(monkeypatch):
    """The resolved config exposes the label alongside the raw study type."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "scans")

    cfg = get_workflow_config()

    assert cfg.study_type == "scans"
    assert cfg.study_label == "SCANS"


def test_workflow_config_label_is_empty_when_study_type_unset(monkeypatch):
    """An unconfigured deployment resolves a blank label but the `mci` type."""
    _clear_controls(monkeypatch)

    cfg = get_workflow_config()

    assert cfg.study_label == ""
    assert cfg.study_type == "mci"
