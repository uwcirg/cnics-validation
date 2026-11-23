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


@pytest.fixture
def client_as():
    """Factory for a test client authenticated as a chosen identity.

    ``client_as(id=42, uploader=True, site="TEST")`` returns a client whose
    requests resolve to that user. Unnamed role flags default to False, so a
    test states exactly the roles it relies on. Calling it again switches
    the identity; the patch is removed when the test ends.
    """
    app_mod = importlib.import_module("flask_backend.app")
    app_mod.keycloak_openid = None
    active = []

    def _make(**user):
        identity = {
            "id": 1,
            "username": "test-user",
            "admin": False,
            "uploader": False,
            "reviewer": False,
            "third_reviewer": False,
            "site": "TEST",
            **user,
        }

        def _load():
            g.auth_user = dict(identity)
            return g.auth_user

        while active:
            active.pop().stop()
        ctx = patch.object(app_mod, "_load_user_from_remote_header", _load)
        ctx.start()
        active.append(ctx)
        client = app_mod.app.test_client()
        client.environ_base["HTTP_X_REMOTE_USER"] = identity["username"]
        return client

    yield _make
    while active:
        active.pop().stop()
