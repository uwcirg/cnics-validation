from flask_backend.app import app
from unittest.mock import patch

@patch('flask_backend.table_service.get_table_data')
def test_get_table_route(mock_service):
    mock_service.return_value = [{'id': 1}]
    client = app.test_client()
    res = client.get('/api/tables/events')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'id': 1}]}


@patch('flask_backend.table_service.get_events_need_packets')
def test_get_need_packets_route(mock_service):
    mock_service.return_value = [{'ID': 1}]
    client = app.test_client()
    res = client.get('/api/events/need_packets')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'ID': 1}]}


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
    res = client.get('/api/events/for_review')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'ID': 2}]}


@patch('flask_backend.table_service.get_events_for_review')
def test_auth_required_for_review(mock_service):
    mock_service.return_value = []
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.get('/api/events/for_review')
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
