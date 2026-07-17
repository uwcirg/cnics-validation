"""Integration tests for POST /api/events/<id>/review (FR-010, FR-012,
FR-014; contracts "POST /api/events/{event_id}/review").

The endpoint is exercised through the auto-authenticated `admin_client`
fixture (acting user id = 1). The events/reviews tables are mocked so the
test exercises the endpoint's slot resolution and lifecycle advancement
without a database.
"""

import importlib
from unittest.mock import patch


class FakeEvent:
    """Minimal stand-in for an `events` row with assignable lifecycle fields."""

    def __init__(self, reviewer1_id=None, reviewer2_id=None, status="assigned"):
        self.reviewer1_id = reviewer1_id
        self.reviewer2_id = reviewer2_id
        self.review1_date = None
        self.review2_date = None
        self.status = status


class FakeSession:
    """Session whose `query(...).get(id)` yields the configured event."""

    def __init__(self, event):
        self._event = event

    def query(self, *args, **kwargs):
        return self

    def get(self, _event_id):
        return self._event

    def add(self, _obj):
        pass

    def commit(self):
        pass

    def rollback(self):
        pass

    def close(self):
        pass


# --- Role / auth helpers (header-auth path) ---------------------------------


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


# --- Lifecycle advancement --------------------------------------------------


@patch("flask_backend.models.get_session")
def test_reviewer1_submission_sets_reviewer1_done(mock_get_session, admin_client, monkeypatch):
    """Reviewer 1 submitting under a two-reviewer config → `reviewer1_done`."""
    for var in ("STUDY_TYPE", "REVIEWER_COUNT"):
        monkeypatch.delenv(var, raising=False)
    event = FakeEvent(reviewer1_id=1)
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        "/api/events/5/review", json={"mci": "Definite", "cardiac_cath": "0"}
    )

    assert res.status_code == 200
    assert res.get_json()["data"] == {"event_id": 5, "status": "reviewer1_done"}
    assert event.status == "reviewer1_done"
    assert event.review1_date is not None


@patch("flask_backend.models.get_session")
def test_single_reviewer_submission_advances_to_done(mock_get_session, admin_client, monkeypatch):
    """With reviewer_count == 1 the same call advances straight to `done`."""
    monkeypatch.setenv("REVIEWER_COUNT", "1")
    event = FakeEvent(reviewer1_id=1)
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post(
        "/api/events/5/review", json={"mci": "No", "cardiac_cath": "0"}
    )

    assert res.status_code == 200
    assert res.get_json()["data"]["status"] == "done"
    assert event.status == "done"
    # No second-reviewer state is entered.
    assert event.review2_date is None


@patch("flask_backend.models.get_session")
def test_non_assigned_submitter_forbidden(mock_get_session, admin_client):
    """A caller who is not an assigned reviewer for the event gets 403."""
    event = FakeEvent(reviewer1_id=99, reviewer2_id=98)
    mock_get_session.return_value = FakeSession(event)

    res = admin_client.post("/api/events/5/review", json={"mci": "Definite"})

    assert res.status_code == 403


@patch("flask_backend.models.get_session")
def test_missing_event_returns_404(mock_get_session, admin_client):
    """A review for a non-existent event returns 404."""
    mock_get_session.return_value = FakeSession(None)

    res = admin_client.post("/api/events/123/review", json={"mci": "Definite"})

    assert res.status_code == 404


def test_empty_body_returns_400(admin_client):
    """An empty review body is rejected with 400."""
    res = admin_client.post("/api/events/5/review", json={})
    assert res.status_code == 400


def test_body_without_mci_returns_400(admin_client):
    """A review body missing the required `mci` field is rejected with 400."""
    res = admin_client.post("/api/events/5/review", json={"cardiac_cath": "0"})
    assert res.status_code == 400


# --- Auth / role enforcement ------------------------------------------------


def test_review_requires_authentication():
    """With external auth configured, an unauthenticated request gets 401."""
    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = object()
    try:
        client = app_mod.app.test_client()
        res = client.post("/api/events/5/review", json={"mci": "Definite"})
        assert res.status_code == 401
    finally:
        app_mod.keycloak_openid = None


@patch("flask_backend.models.get_session")
def test_review_requires_reviewer_or_admin_role(mock_get_session):
    """A user with neither the reviewer nor admin role is rejected with 403."""
    mock_get_session.return_value = _UserSession(FakeUser(uploader=True))
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.post(
        "/api/events/5/review",
        json={"mci": "Definite"},
        headers={"X-Remote-User": "alice"},
    )

    assert res.status_code == 403
