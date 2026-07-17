"""Shared pytest fixtures for flask_backend tests.

The production auth path is Apache basic+ldap → `X-Remote-User` → Flask
`@requires_auth` → DB lookup in `users.login`. Tests that want to
exercise role-protected endpoints without standing up a real DB use the
``admin_client`` fixture below: it patches
``flask_backend.app._load_user_from_remote_header`` so any request
carrying ``X-Remote-User`` gets a full-admin ``g.auth_user`` injected,
satisfying ``@requires_auth``, ``@requires_roles``, and
``@requires_any_role`` without touching the database.
"""

import importlib
from unittest.mock import patch

import pytest
from flask import g


_FAKE_ADMIN = {
    "id": 1,
    "username": "test-admin",
    "admin": True,
    "uploader": True,
    "reviewer": True,
    "third_reviewer": True,
    "site": "TEST",
}


def _fake_load_user_from_remote_header():
    g.auth_user = dict(_FAKE_ADMIN)
    return g.auth_user


@pytest.fixture
def admin_client():
    """Flask test client auto-authenticated as a full-admin user.

    All requests made through this client carry ``X-Remote-User: test``
    and resolve to an admin identity without hitting the database.
    """
    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = None
    with patch.object(
        app_mod,
        "_load_user_from_remote_header",
        _fake_load_user_from_remote_header,
    ):
        client = app_mod.app.test_client()
        client.environ_base["HTTP_X_REMOTE_USER"] = "test"
        yield client
