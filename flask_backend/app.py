from flask import Flask, jsonify, request, abort
import os
from dotenv import load_dotenv
from keycloak import KeycloakOpenID
from . import table_service

load_dotenv()

app = Flask(__name__)

# Optional Keycloak configuration mirroring the Express backend
keycloak_openid = None
if os.getenv("KEYCLOAK_REALM"):
    keycloak_openid = KeycloakOpenID(
        server_url=os.getenv("KEYCLOAK_URL", "http://localhost:8080/"),
        realm_name=os.getenv("KEYCLOAK_REALM"),
        client_id=os.getenv("KEYCLOAK_CLIENT_ID"),
        client_secret_key=os.getenv("KEYCLOAK_CLIENT_SECRET"),
    )


def requires_auth(func):
    """Decorator that enforces Keycloak authentication if configured."""

    def wrapper(*args, **kwargs):
        if keycloak_openid:
            auth = request.headers.get("Authorization", "")
            if not auth.startswith("Bearer "):
                abort(401)
            token = auth.split(" ", 1)[1]
            try:
                keycloak_openid.userinfo(token)
            except Exception:
                abort(401)
        return func(*args, **kwargs)

    wrapper.__name__ = func.__name__
    return wrapper

@app.route('/api/tables/<name>')
@requires_auth
def get_table(name):
    try:
        rows = table_service.get_table_data(name)
        return jsonify({'data': rows})
    except Exception as exc:
        print(exc)
        return jsonify({'error': 'Failed to fetch table data'}), 500

if __name__ == '__main__':
    port = int(os.getenv('PORT', '3000'))
    app.run(host='0.0.0.0', port=port)
