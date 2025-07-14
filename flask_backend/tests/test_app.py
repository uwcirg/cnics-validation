from flask_backend.app import app
from unittest.mock import patch

@patch('flask_backend.table_service.get_table_data')
def test_get_table_route(mock_service):
    mock_service.return_value = [{'id': 1}]
    client = app.test_client()
    res = client.get('/api/tables/events')
    assert res.status_code == 200
    assert res.get_json() == {'data': [{'id': 1}]}


@patch("flask_backend.table_service.get_table_data")
def test_auth_required(mock_service):
    mock_service.return_value = []
    import importlib
    app_mod = importlib.import_module('flask_backend.app')
    app_mod.keycloak_openid = object()
    client = app_mod.app.test_client()
    res = client.get('/api/tables/events')
    assert res.status_code == 401
