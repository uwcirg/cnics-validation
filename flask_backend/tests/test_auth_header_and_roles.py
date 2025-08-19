from unittest.mock import patch


class FakeUser:
    def __init__(self, *, id=1, username="alice", login="alice", admin=False, uploader=False, reviewer=False, third_reviewer=False, site="UW"):
        self.id = id
        self.username = username
        self.login = login
        self.admin_flag = 1 if admin else 0
        self.uploader_flag = 1 if uploader else 0
        self.reviewer_flag = 1 if reviewer else 0
        self.third_reviewer_flag = 1 if third_reviewer else 0
        self.site = site


class _FakeQuery:
    def __init__(self, user):
        self._user = user

    def filter_by(self, **kwargs):
        # We ignore filters in the fake and always return the configured user
        return self

    def first(self):
        return self._user


class _FakeSession:
    def __init__(self, user):
        self._user = user

    def query(self, *args, **kwargs):
        return _FakeQuery(self._user)

    def close(self):
        pass


def _session_for(user_or_none):
    return _FakeSession(user_or_none)


@patch("flask_backend.table_service.get_event_status_summary")
@patch("flask_backend.models.get_session")
def test_header_auth_admin_can_view_status_summary(mock_get_session, mock_summary):
    # Admin user present in DB via header lookup
    mock_get_session.return_value = _session_for(FakeUser(admin=True))
    mock_summary.return_value = {"uploaded": 1}

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events/status_summary", headers={"X-Remote-User": "alice"})
    assert res.status_code == 200
    assert res.get_json() == {"data": {"uploaded": 1}}


@patch("flask_backend.table_service.create_event")
@patch("flask_backend.models.get_session")
def test_header_auth_non_admin_blocked_from_admin_endpoint(mock_get_session, mock_create):
    # Reviewer but not admin
    mock_get_session.return_value = _session_for(FakeUser(reviewer=True))
    mock_create.return_value = {"id": 99}

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.post("/api/events", json={"site": "UW"}, headers={"X-Remote-User": "alice"})
    assert res.status_code == 403


@patch("flask_backend.table_service.get_events_with_patient_site")
@patch("flask_backend.models.get_session")
def test_header_auth_unknown_user_forbidden(mock_get_session, mock_events):
    # No matching user in DB
    mock_get_session.return_value = _session_for(None)
    mock_events.return_value = []

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events", headers={"X-Remote-User": "ghost"})
    assert res.status_code == 403


@patch("flask_backend.models.get_session")
def test_auth_me_returns_auth_user_when_header_present(mock_get_session):
    mock_get_session.return_value = _session_for(FakeUser(admin=True, uploader=True, reviewer=True))

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/auth/me", headers={"X-Remote-User": "alice"})
    assert res.status_code == 200
    data = res.get_json()["data"]
    assert data["username"] == "alice"
    assert data["admin"] is True
    assert data["uploader"] is True
    assert data["reviewer"] is True


def test_auth_me_401_without_header():
    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = None
    client = app_mod.app.test_client()

    res = client.get("/api/auth/me")
    assert res.status_code == 401


@patch("flask_backend.table_service.get_events_need_packets")
@patch("flask_backend.models.get_session")
def test_uploader_can_access_need_packets(mock_get_session, mock_svc):
    mock_get_session.return_value = _session_for(FakeUser(uploader=True))
    mock_svc.return_value = [{"ID": 1}]

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events/need_packets", headers={"X-Remote-User": "alice"})
    assert res.status_code == 200
    assert res.get_json() == {"data": [{"ID": 1}]}


@patch("flask_backend.table_service.get_events_for_reupload")
@patch("flask_backend.models.get_session")
def test_uploader_can_access_need_reupload(mock_get_session, mock_svc):
    mock_get_session.return_value = _session_for(FakeUser(uploader=True))
    mock_svc.return_value = [{"ID": 2}]

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events/need_reupload", headers={"X-Remote-User": "alice"})
    assert res.status_code == 200
    assert res.get_json() == {"data": [{"ID": 2}]}


@patch("flask_backend.table_service.get_events_for_review")
@patch("flask_backend.models.get_session")
def test_uploader_blocked_from_for_review(mock_get_session, mock_svc):
    mock_get_session.return_value = _session_for(FakeUser(uploader=True))
    mock_svc.return_value = []

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events/for_review", headers={"X-Remote-User": "alice"})
    assert res.status_code == 403


@patch("flask_backend.table_service.get_events_for_review")
@patch("flask_backend.models.get_session")
def test_reviewer_can_access_for_review(mock_get_session, mock_svc):
    mock_get_session.return_value = _session_for(FakeUser(reviewer=True))
    mock_svc.return_value = [{"ID": 3}]

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events/for_review", headers={"X-Remote-User": "alice"})
    assert res.status_code == 200
    assert res.get_json() == {"data": [{"ID": 3}]}


@patch("flask_backend.table_service.get_events_need_packets")
@patch("flask_backend.models.get_session")
def test_reviewer_can_access_need_packets(mock_get_session, mock_svc):
    mock_get_session.return_value = _session_for(FakeUser(reviewer=True))
    mock_svc.return_value = [{"ID": 4}]

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events/need_packets", headers={"X-Remote-User": "alice"})
    assert res.status_code == 200
    assert res.get_json() == {"data": [{"ID": 4}]}


@patch("flask_backend.table_service.get_events_for_reupload")
@patch("flask_backend.models.get_session")
def test_reviewer_can_access_need_reupload(mock_get_session, mock_svc):
    mock_get_session.return_value = _session_for(FakeUser(reviewer=True))
    mock_svc.return_value = [{"ID": 5}]

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events/need_reupload", headers={"X-Remote-User": "alice"})
    assert res.status_code == 200
    assert res.get_json() == {"data": [{"ID": 5}]}


@patch("flask_backend.table_service.get_events_need_packets")
@patch("flask_backend.models.get_session")
def test_no_roles_user_blocked_from_any_role_endpoint(mock_get_session, mock_svc):
    mock_get_session.return_value = _session_for(FakeUser())
    mock_svc.return_value = []

    import importlib
    app_mod = importlib.import_module("flask_backend.app")
    client = app_mod.app.test_client()

    res = client.get("/api/events/need_packets", headers={"X-Remote-User": "alice"})
    assert res.status_code == 403


