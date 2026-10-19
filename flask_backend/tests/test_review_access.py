"""Access and scoping tests for spec 012 rev. 4 (FR-025, FR-027 – FR-029,
FR-033; research D14 – D16, D19, D20).

Covers:
- `/api/events/for_review` scoped to the caller's unreviewed slots for
  non-admins (D14);
- `GET /api/events/<id>/review`: answers for admins only, plus `slot` and
  `mine` (D15, D16.1);
- `GET /api/events/<id>` limited to assigned reviewers for reviewer-only
  callers (D16.4);
- `/api/tables/<name>` name validation and review tables (D16.5);
- explicit role decorators (analyze C1);
- POST review still refuses slot 3 (analyze I1);
- screen and scrubbed upload are admin-only (FR-033).

Database access is replaced by the fake session from test_scans_review.py.
"""

import importlib
from datetime import date
from unittest.mock import MagicMock, patch

import pytest
from flask import g

import flask_backend.table_service as ts
from flask_backend.models.studies import scans


class FakeEvent:
    def __init__(self, reviewer1_id=None, reviewer2_id=None, reviewer3_id=None,
                 review1_date=None, review2_date=None, review3_date=None,
                 status="assigned"):
        self.id = 5
        self.reviewer1_id = reviewer1_id
        self.reviewer2_id = reviewer2_id
        self.reviewer3_id = reviewer3_id
        self.review1_date = review1_date
        self.review2_date = review2_date
        self.review3_date = review3_date
        self.status = status


class _Result:
    def __init__(self, values):
        self._values = values

    def scalars(self):
        return self

    def all(self):
        return list(self._values)


class FakeSession:
    """`query(...).get(id)` yields the configured event; `add()` is recorded;
    `execute()` answers the scans schema check with a migrated table."""

    def __init__(self, event):
        self._event = event
        self.added = []

    def query(self, *args, **kwargs):
        return self

    def get(self, _event_id):
        return self._event

    def add(self, obj):
        self.added.append(obj)

    def execute(self, *args, **kwargs):
        return _Result(("id", "event_id", "reviewer_id", "created"))

    def commit(self):
        pass

    def rollback(self):
        pass

    def close(self):
        pass


@pytest.fixture(autouse=True)
def _scans(monkeypatch):
    for var in ("REVIEWER_COUNT", "ENABLE_SCRUBBING", "ENABLE_SCREENING", "ENABLE_SENDING"):
        monkeypatch.delenv(var, raising=False)
    monkeypatch.setenv("STUDY_TYPE", "scans")
    import flask_backend.app as app_mod
    monkeypatch.setattr(app_mod, "_schema_errors", {}, raising=False)


def _client_as(user):
    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = None

    def _load():
        g.auth_user = dict(user)
        return g.auth_user

    with patch.object(app_mod, "_load_user_from_remote_header", _load):
        client = app_mod.app.test_client()
        client.environ_base["HTTP_X_REMOTE_USER"] = user["username"]
        yield client


def _user(uid, username, **roles):
    base = {"admin": False, "uploader": False, "reviewer": False, "third_reviewer": False}
    base.update(roles)
    return {"id": uid, "username": username, "site": "TEST", **base}


@pytest.fixture
def reviewer_client():
    yield from _client_as(_user(7, "rev", reviewer=True))


@pytest.fixture
def uploader_client():
    yield from _client_as(_user(9, "up", uploader=True))


@pytest.fixture
def norole_client():
    yield from _client_as(_user(11, "nobody"))


# --- for_review scoping (D14) ---------------------------------------------------


@patch("flask_backend.table_service.get_events_for_review", return_value=[])
def test_for_review_admin_unscoped(mock_svc, admin_client):
    assert admin_client.get("/api/events/for_review?limit=3&offset=0").status_code == 200
    mock_svc.assert_called_with(3, 0)


@patch("flask_backend.table_service.get_events_for_review", return_value=[])
def test_for_review_reviewer_scoped(mock_svc, reviewer_client):
    assert reviewer_client.get("/api/events/for_review?limit=3&offset=0").status_code == 200
    mock_svc.assert_called_with(3, 0, reviewer_id=7)


def _status_queries(reviewer_id):
    session = MagicMock()
    session.execute.return_value.mappings.return_value.all.return_value = []
    session.execute.return_value.scalar.return_value = 0
    with patch("flask_backend.table_service.models.get_session", return_value=session):
        ts.get_events_for_review(5, 0, reviewer_id=reviewer_id)
    queries = [str(c.args[0]) for c in session.execute.call_args_list]
    params = [c.args[1] for c in session.execute.call_args_list if len(c.args) > 1]
    return queries, params


def test_table_service_slot_filter():
    queries, params = _status_queries(7)
    for q in queries:
        assert "(e.reviewer1_id = :reviewer_id AND e.review1_date IS NULL)" in q
        assert "(e.reviewer2_id = :reviewer_id AND e.review2_date IS NULL)" in q
    assert all(p.get("reviewer_id") == 7 for p in params)


def test_table_service_no_slot_filter_without_reviewer():
    queries, params = _status_queries(None)
    assert not any(":reviewer_id" in q for q in queries)
    assert not any("reviewer_id" in p for p in params)


# --- GET …/review: content admin-only, slot, mine (D15, D16.1, I2) -------------

STORED = [
    {"reviewer_id": 7, "review_date": "2026-10-16", "regions": []},
    {"reviewer_id": 8, "review_date": None, "regions": []},
    {"reviewer_id": 99, "review_date": None, "regions": []},
]


def _get_review(client, event):
    stored = [dict(r) for r in STORED]
    with patch("flask_backend.models.get_session", return_value=FakeSession(event)), \
         patch.object(scans, "get_reviews", return_value=stored) as mock_get:
        return client.get("/api/events/5/review"), mock_get


def test_review_get_admin_gets_content_with_slots(admin_client):
    res, _ = _get_review(admin_client, FakeEvent(reviewer1_id=7, reviewer2_id=8))
    assert res.status_code == 200
    data = res.get_json()["data"]
    assert [r["slot"] for r in data["reviews"]] == [1, 2, None]
    assert data["mine"] is None


def test_review_get_reviewer_not_yet_reviewed(reviewer_client):
    res, mock_get = _get_review(reviewer_client, FakeEvent(reviewer1_id=7))
    assert res.status_code == 200
    assert res.get_json()["data"] == {"event_id": 5, "reviews": [], "mine": None}
    mock_get.assert_not_called()


def test_review_get_reviewer_already_reviewed(reviewer_client):
    res, _ = _get_review(reviewer_client, FakeEvent(reviewer1_id=7, review1_date=date(2026, 10, 16)))
    data = res.get_json()["data"]
    assert data["reviews"] == []
    assert data["mine"] == {"slot": 1, "review_date": "2026-10-16"}


def test_review_get_reviewer_slot2_reviewed(reviewer_client):
    res, _ = _get_review(reviewer_client, FakeEvent(reviewer1_id=8, reviewer2_id=7,
                                                    review2_date=date(2026, 10, 15)))
    assert res.get_json()["data"]["mine"] == {"slot": 2, "review_date": "2026-10-15"}


def test_review_get_uploader_forbidden(uploader_client):
    res, _ = _get_review(uploader_client, FakeEvent(reviewer1_id=7))
    assert res.status_code == 403


def test_review_get_mine_ignores_slot3(admin_client):
    # admin_client is user id 1.
    res, _ = _get_review(admin_client, FakeEvent(reviewer1_id=7, reviewer3_id=1,
                                                 review3_date=date(2026, 10, 16)))
    assert res.get_json()["data"]["mine"] is None


# --- GET /api/events/<id> (D16.4, C1) -----------------------------------------


def _get_details(client, event):
    with patch("flask_backend.models.get_session", return_value=FakeSession(event)), \
         patch("flask_backend.table_service.get_event_details", return_value={"id": 5}):
        return client.get("/api/events/5")


def test_details_admin(admin_client):
    assert _get_details(admin_client, FakeEvent()).status_code == 200


def test_details_uploader(uploader_client):
    assert _get_details(uploader_client, FakeEvent()).status_code == 200


@pytest.mark.parametrize("slot", ["reviewer1_id", "reviewer2_id", "reviewer3_id"])
def test_details_assigned_reviewer(reviewer_client, slot):
    assert _get_details(reviewer_client, FakeEvent(**{slot: 7})).status_code == 200


def test_details_unassigned_reviewer_forbidden(reviewer_client):
    assert _get_details(reviewer_client, FakeEvent(reviewer1_id=8)).status_code == 403


def test_details_unknown_event_for_reviewer(reviewer_client):
    assert _get_details(reviewer_client, None).status_code == 404


def test_details_requires_a_role(norole_client):
    assert _get_details(norole_client, FakeEvent()).status_code == 403


# --- /api/tables/<name> (D16.5, C1) -------------------------------------------


@pytest.mark.parametrize("name", ["reviews", "review_regions", "Review_Regions"])
def test_tables_review_tables_forbidden_to_reviewer(reviewer_client, name):
    with patch("flask_backend.table_service.get_table_data", return_value=[]) as mock_data:
        assert reviewer_client.get(f"/api/tables/{name}").status_code == 403
    mock_data.assert_not_called()


def test_tables_review_tables_allowed_to_admin(admin_client):
    with patch("flask_backend.table_service.get_table_data", return_value=[]):
        assert admin_client.get("/api/tables/review_regions").status_code == 200


@pytest.mark.parametrize("name", ["events;drop", "1abc", "events%20x"])
def test_tables_invalid_name_not_found(admin_client, name):
    with patch("flask_backend.table_service.get_table_data", return_value=[]) as mock_data:
        assert admin_client.get(f"/api/tables/{name}").status_code == 404
    mock_data.assert_not_called()


def test_tables_other_table_unchanged_for_reviewer(reviewer_client):
    with patch("flask_backend.table_service.get_table_data", return_value=[]):
        assert reviewer_client.get("/api/tables/criterias").status_code == 200


def test_tables_requires_a_role(norole_client):
    with patch("flask_backend.table_service.get_table_data", return_value=[]):
        assert norole_client.get("/api/tables/criterias").status_code == 403


# --- POST review refuses slot 3 (I1) -----------------------------------------


def test_post_review_slot3_refused(reviewer_client):
    session = FakeSession(FakeEvent(reviewer1_id=8, reviewer2_id=9, reviewer3_id=7))
    body = {"regions": [{"region": "Hip", "region_other": "", "laterality": "",
                         "t_score": "", "z_score": ""}]}
    with patch("flask_backend.models.get_session", return_value=session):
        res = reviewer_client.post("/api/events/5/review", json=body)
    assert res.status_code == 403
    assert session.added == []


# --- Admin-only stages (FR-033) -----------------------------------------------


def test_screen_reviewer_forbidden(reviewer_client):
    assert reviewer_client.post("/api/events/5/screen", json={"decision": "accept"}).status_code == 403


def test_upload_scrubbed_reviewer_forbidden(reviewer_client):
    assert reviewer_client.post("/api/events/5/upload_scrubbed").status_code == 403


def test_upload_scrubbed_uploader_forbidden(uploader_client):
    assert uploader_client.post("/api/events/5/upload_scrubbed").status_code == 403


def test_screen_admin_passes_decorator(admin_client):
    # An empty body fails the handler's own validation (400), after the
    # role check has let the request through.
    assert admin_client.post("/api/events/5/screen", json={}).status_code != 403


def test_upload_scrubbed_admin_passes_decorator(admin_client):
    # No file: the handler answers 400, after the role check.
    assert admin_client.post("/api/events/5/upload_scrubbed").status_code != 403
