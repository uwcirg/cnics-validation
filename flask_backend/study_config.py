"""Shared workflow configuration layer.

This module is the single place that resolves a deployment's study type
and the four workflow-stage controls into one validated, immutable
configuration object. Every part of the application that needs to know
which lifecycle stages are active reads them from here (Constitution
Principle IV — "Configuration Over Code Forks") rather than scattering
`os.getenv` calls or branching on the study name.

The four controls (Constitution v1.4.0, Principle V — "selective bypass"):

- ``ENABLE_SCRUBBING``  — whether the `scrubbed` stage is entered.
- ``ENABLE_SCREENING``  — whether the `screened` stage is entered.
- ``ENABLE_SENDING``    — whether the `sent` stage is entered.
- ``REVIEWER_COUNT``    — how many reviewers adjudicate an event (1 or 2).

Resolution: the selected ``STUDY_TYPE`` supplies a default *profile*; an
explicit environment variable for any individual control overrides that
profile. ``scans`` bypasses every optional stage; every other (or unset)
study runs the conservative full-workflow profile.

Validation is fail-fast: `get_workflow_config()` raises `WorkflowConfigError`
on any malformed value, and `app.py` calls it at import time so an invalid
configuration aborts startup before the WSGI app can serve a request
(FR-005, SC-004).
"""

import os
from dataclasses import dataclass

# Recognized boolean tokens (case-insensitive). Anything else is a
# configuration error — values are never silently coerced (FR-005).
_TRUE_TOKENS = {"true", "1", "yes"}
_FALSE_TOKENS = {"false", "0", "no"}

# Per-study default control profiles. The study type selects a profile;
# individual `.env` controls still override it (FR-006).
_SCANS_PROFILE = {
    "scrubbing": False,
    "screening": False,
    "sending": False,
    "reviewer_count": 1,
}
# Conservative full-workflow default for every other / unset study (FR-004).
_FULL_WORKFLOW_PROFILE = {
    "scrubbing": True,
    "screening": True,
    "sending": True,
    "reviewer_count": 2,
}
_PROFILES = {"scans": _SCANS_PROFILE}


class WorkflowConfigError(RuntimeError):
    """Raised when the workflow configuration cannot be resolved or validated.

    Raised at application startup (see `app.py`) so an invalid configuration
    aborts construction of the WSGI app and serves no request (FR-005).
    """


@dataclass(frozen=True)
class WorkflowConfig:
    """The resolved, immutable workflow configuration for this deployment."""

    study_type: str
    study_title: str
    scrubbing: bool
    screening: bool
    sending: bool
    reviewer_count: int


def get_study_type() -> str:
    """Return the current study type from the environment (default ``mci``)."""
    return os.getenv("STUDY_TYPE", "mci")


def get_study_title() -> str:
    """Return the deployment's banner study-title override (default empty).

    ``STUDY_TITLE`` is a free-form display string (e.g. "DEXA Scans
    Validation") shown verbatim in the banner and used to build the browser
    tab title ("CNICS " + this value). When unset/blank the frontend falls
    back to a title derived from ``STUDY_TYPE``. It is purely cosmetic, so —
    unlike the workflow controls — any value is accepted and never validated.
    """
    return os.getenv("STUDY_TITLE", "").strip()


# Canonical CNICS clinical sites. The schema has no site table — `site` is a
# free-text column on patient records — so the pick-list is configuration,
# not data: a deployment sets CLINICAL_SITES to the exact site codes its
# patient roster uses. The default is the set of site codes seen across CNICS
# deployments; override per deployment as needed.
_DEFAULT_CLINICAL_SITES = ("CWRU", "Fenway", "JH", "UAB", "UCSD", "UCSF", "UNC", "UW", "Vanderbilt")


def get_clinical_sites() -> list:
    """Return the configured clinical site codes for populating site pickers.

    Read from ``CLINICAL_SITES`` as a comma-separated list (e.g.
    ``"UAB,UCSD,UW"``); order is preserved and blank entries are dropped.
    Falls back to a built-in default when unset or empty. Purely a display
    pick-list, so — like the cosmetic title — any value is accepted.
    """
    raw = os.getenv("CLINICAL_SITES", "")
    sites = [s.strip() for s in raw.split(",") if s.strip()]
    return sites if sites else list(_DEFAULT_CLINICAL_SITES)


def _profile_for(study_type: str) -> dict:
    """Return the default control profile for ``study_type``."""
    return _PROFILES.get((study_type or "").strip().lower(), _FULL_WORKFLOW_PROFILE)


def _resolve_bool(var_name: str, profile_default: bool) -> bool:
    """Resolve one boolean control: explicit `.env` value overrides the profile.

    An unset or empty variable falls back to ``profile_default``. Any value
    outside the recognized token set raises `WorkflowConfigError` naming the
    offending variable and value — never silently coerced (FR-005).
    """
    raw = os.getenv(var_name)
    if raw is None or raw.strip() == "":
        return profile_default
    token = raw.strip().lower()
    if token in _TRUE_TOKENS:
        return True
    if token in _FALSE_TOKENS:
        return False
    raise WorkflowConfigError(
        f"Invalid value for {var_name}: {raw!r}. "
        f"Expected one of: true, false, 1, 0, yes, no (case-insensitive)."
    )


def _resolve_reviewer_count(profile_default: int) -> int:
    """Resolve ``REVIEWER_COUNT``: explicit `.env` value overrides the profile.

    Must parse to an integer in ``{1, 2}``; anything else (including a
    non-integer token, or a value such as 3) raises `WorkflowConfigError`
    naming the variable and value (FR-005, "Unsupported reviewer count").
    """
    raw = os.getenv("REVIEWER_COUNT")
    if raw is None or raw.strip() == "":
        return profile_default
    token = raw.strip()
    try:
        value = int(token)
    except ValueError:
        raise WorkflowConfigError(
            f"Invalid value for REVIEWER_COUNT: {raw!r}. "
            f"Expected an integer, 1 or 2."
        )
    if value not in (1, 2):
        raise WorkflowConfigError(
            f"Invalid value for REVIEWER_COUNT: {value}. Expected 1 or 2."
        )
    return value


def get_workflow_config() -> WorkflowConfig:
    """Resolve and validate the workflow configuration for this deployment.

    Resolution order: the ``STUDY_TYPE`` profile supplies defaults; an
    explicit environment variable overrides any individual control (FR-006).
    Raises `WorkflowConfigError` on any invalid value so startup fails fast
    (FR-005, SC-004).
    """
    study_type = get_study_type()
    profile = _profile_for(study_type)
    return WorkflowConfig(
        study_type=study_type,
        study_title=get_study_title(),
        scrubbing=_resolve_bool("ENABLE_SCRUBBING", profile["scrubbing"]),
        screening=_resolve_bool("ENABLE_SCREENING", profile["screening"]),
        sending=_resolve_bool("ENABLE_SENDING", profile["sending"]),
        reviewer_count=_resolve_reviewer_count(profile["reviewer_count"]),
    )
