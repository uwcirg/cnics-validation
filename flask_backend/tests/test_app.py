from flask_backend.app import app
from unittest.mock import patch

@patch('flask_backend.table_service.get_table_data')
def test_get_table_route(mock_service):
    mock_service.return_value = [{'id': 1}]
    client = app.test_client()
    res = client.get('/api/tables/events?limit=5&offset=10')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'id': 1}]}
    mock_service.assert_called_with('events', 5, 10)


@patch('flask_backend.table_service.get_events_with_patient_site')
def test_get_events_route(mock_service):
    mock_service.return_value = [{'id': 1}]
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = None
    client = app_mod.app.test_client()
    res = client.get('/api/events?limit=2&offset=0')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'id': 1}]}
    mock_service.assert_called_with(2, 0)


@patch('flask_backend.table_service.get_events_with_patient_site')
def test_auth_required_events(mock_service):
    mock_service.return_value = []
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.get('/api/events')
    assert res.status_code == 401
    app_mod.keycloak_openid = None


@patch('flask_backend.table_service.get_events_need_packets')
def test_get_need_packets_route(mock_service):
    mock_service.return_value = [{'ID': 1}]
    client = app.test_client()
    res = client.get('/api/events/need_packets?limit=2&offset=5')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'ID': 1}]}
    mock_service.assert_called_with(2, 5)


@patch("flask_backend.table_service.get_table_data")
def test_auth_required(mock_service):
    mock_service.return_value = []
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.get('/api/tables/events')
    assert res.status_code == 401


@patch('flask_backend.table_service.get_events_need_packets')
def test_auth_required_need_packets(mock_service):
    mock_service.return_value = []
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.get('/api/events/need_packets')
    assert res.status_code == 401


@patch('flask_backend.table_service.get_events_for_review')
def test_get_for_review_route(mock_service):
    mock_service.return_value = [{'ID': 2}]
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = None
    client = app_mod.app.test_client()
    res = client.get('/api/events/for_review?limit=3&offset=0')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'ID': 2}]}
    mock_service.assert_called_with(3, 0)


@patch('flask_backend.table_service.get_events_for_review')
def test_auth_required_for_review(mock_service):
    mock_service.return_value = []
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.get('/api/events/for_review')
    assert res.status_code == 401


@patch('flask_backend.table_service.get_events_for_reupload')
def test_get_for_reupload_route(mock_service):
    mock_service.return_value = [{'ID': 3}]
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = None
    client = app_mod.app.test_client()
    res = client.get('/api/events/need_reupload?limit=4&offset=0')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'ID': 3}]}
    mock_service.assert_called_with(4, 0)


@patch('flask_backend.table_service.get_events_for_reupload')
def test_auth_required_for_reupload(mock_service):
    mock_service.return_value = []
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.get('/api/events/need_reupload')
    assert res.status_code == 401


@patch('flask_backend.table_service.get_event_status_summary')
def test_status_summary_route(mock_service):
    mock_service.return_value = {'uploaded': 5}
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = None
    client = app_mod.app.test_client()
    res = client.get('/api/events/status_summary')
    assert res.status_code == 200
    assert res.get_json() == {'data': {'uploaded': 5}}


@patch('flask_backend.table_service.get_event_status_summary')
def test_auth_required_status_summary(mock_service):
    mock_service.return_value = {}
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.get('/api/events/status_summary')
    assert res.status_code == 401


def test_root_route():
    client = app.test_client()
    res = client.get('/')
    assert res.status_code == 200
    assert res.get_json() == {'status': 'ok'}


@patch('flask_backend.table_service.create_user')
def test_add_user_route(mock_service):
    mock_service.return_value = {'id': 1}
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = None
    client = app_mod.app.test_client()
    res = client.post('/api/users', json={'username': 'alice'})
    assert res.status_code == 201
    assert res.get_json() == {'data': {'id': 1}}
    mock_service.assert_called_with({'username': 'alice'})


@patch('flask_backend.table_service.create_user')
def test_auth_required_add_user(mock_service):
    mock_service.return_value = {}
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.post('/api/users', json={})
    assert res.status_code == 401
