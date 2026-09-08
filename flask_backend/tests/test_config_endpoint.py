"""Integration tests for GET /api/config (FR-021; contracts
"NEW GET /api/config").

Verifies the endpoint serves the resolved workflow configuration, enforces
its auth/role decorators, and exposes no secrets.
"""

import importlib
from unittest.mock import patch

_CONTROL_VARS = (
    "STUDY_TYPE",
    "STUDY_TITLE",
    "ENABLE_SCRUBBING",
    "ENABLE_SCREENING",
    "ENABLE_SENDING",
    "REVIEWER_COUNT",
)


def _clear_controls(monkeypatch):
    for var in _CONTROL_VARS:
        monkeypatch.delenv(var, raising=False)


class FakeUser:
    def __init__(self, *, admin=False, uploader=False, reviewer=False):
        self.id = 1
        self.username = "alice"
        self.login = "alice"
        self.admin_flag = 1 if admin else 0
        self.uploader_flag = 1 if uploader else 0
        self.reviewer_flag = 1 if reviewer else 0
        self.third_reviewer_flag = 0
        self.site = "UW"


class _UserSession:
    def __init__(self, user):
        self._user = user

    def query(self, *args, **kwargs):
        return self

    def filter_by(self, **kwargs):
        return self

    def first(self):
        return self._user

    def close(self):
        pass


def test_config_endpoint_returns_scans_config(admin_client, monkeypatch):
    """A `scans` deployment's resolved config is served in the contract shape."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "scans")

    res = admin_client.get("/api/config")

    assert res.status_code == 200
    data = res.get_json()["data"]
    assert data["study_type"] == "scans"
    assert data["study_label"] == "SCANS"
    assert data["workflow"] == {
        "scrubbing": False,
        "screening": False,
        "sending": False,
        "reviewer_count": 1,
    }


def test_config_endpoint_returns_full_workflow_config(admin_client, monkeypatch):
    """An unconfigured deployment reports the full-workflow profile."""
    _clear_controls(monkeypatch)

    res = admin_client.get("/api/config")

    assert res.status_code == 200
    data = res.get_json()["data"]
    assert data["workflow"] == {
        "scrubbing": True,
        "screening": True,
        "sending": True,
        "reviewer_count": 2,
    }


def test_config_endpoint_exposes_only_workflow_keys(admin_client, monkeypatch):
    """The payload exposes only the study type/title and the four controls — no secrets."""
    _clear_controls(monkeypatch)

    res = admin_client.get("/api/config")

    data = res.get_json()["data"]
    assert set(data.keys()) == {
        "study_type",
        "study_label",
        "study_title",
        "workflow",
    }
    assert set(data["workflow"].keys()) == {
        "scrubbing",
        "screening",
        "sending",
        "reviewer_count",
    }


def test_config_endpoint_omitted_study_title_is_blank(admin_client, monkeypatch):
    """With no STUDY_TITLE configured, the override is served as an empty string."""
    _clear_controls(monkeypatch)

    res = admin_client.get("/api/config")

    assert res.get_json()["data"]["study_title"] == ""


def test_config_endpoint_serves_study_title_override(admin_client, monkeypatch):
    """A configured STUDY_TITLE is served verbatim (trimmed)."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TITLE", "  DEXA Scans Validation  ")

    res = admin_client.get("/api/config")

    assert res.get_json()["data"]["study_title"] == "DEXA Scans Validation"


def test_config_endpoint_requires_authentication():
    """With external auth configured, an unauthenticated request gets 401."""
    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = object()
    try:
        client = app_mod.app.test_client()
        res = client.get("/api/config")
        assert res.status_code == 401
    finally:
        app_mod.keycloak_openid = None


@patch("flask_backend.models.get_session")
def test_config_endpoint_requires_a_known_role(mock_get_session):
    """A user with none of admin/uploader/reviewer/third_reviewer gets 403."""
    mock_get_session.return_value = _UserSession(FakeUser())
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/config", headers={"X-Remote-User": "alice"})

    assert res.status_code == 403


@patch("flask_backend.models.get_session")
def test_config_endpoint_allows_a_reviewer(mock_get_session):
    """A reviewer (one of the allowed roles) may read the config."""
    mock_get_session.return_value = _UserSession(FakeUser(reviewer=True))
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/config", headers={"X-Remote-User": "alice"})

    assert res.status_code == 200


# --- study_label (spec 010, contracts/config-api.md) ------------------------


def test_config_endpoint_serves_the_study_label(admin_client, monkeypatch):
    """A configured study is published upper-cased as `study_label`."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "mci")

    data = admin_client.get("/api/config").get_json()["data"]

    assert data["study_label"] == "MCI"
    assert data["study_type"] == "mci"


def test_config_endpoint_trims_and_upper_cases_the_study_label(admin_client, monkeypatch):
    """Whitespace and case in STUDY_TYPE do not reach the label (FR-008)."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "  scans  ")

    data = admin_client.get("/api/config").get_json()["data"]

    assert data["study_label"] == "SCANS"
    # The raw type is served exactly as configured; only the label is normalized.
    assert data["study_type"] == "  scans  "


def test_config_endpoint_blank_label_when_study_type_unset(admin_client, monkeypatch):
    """Unset STUDY_TYPE: `study_label` is "" while `study_type` keeps `mci`.

    Asserted together because that is the whole point of the added field —
    the frontend must be able to tell "unconfigured" from "configured as MCI"
    so a heading can omit the study word (FR-005).
    """
    _clear_controls(monkeypatch)

    data = admin_client.get("/api/config").get_json()["data"]

    assert data["study_label"] == ""
    assert data["study_type"] == "mci"


def test_config_endpoint_label_does_not_disturb_title_or_workflow(admin_client, monkeypatch):
    """Adding the label leaves `study_title` and `workflow` untouched."""
    _clear_controls(monkeypatch)
    monkeypatch.setenv("STUDY_TYPE", "mci")
    monkeypatch.setenv("STUDY_TITLE", "  Cardiology  ")

    data = admin_client.get("/api/config").get_json()["data"]

    assert data["study_label"] == "MCI"
    assert data["study_title"] == "Cardiology"
    assert data["workflow"] == {
        "scrubbing": True,
        "screening": True,
        "sending": True,
        "reviewer_count": 2,
    }
