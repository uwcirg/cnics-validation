from flask import Flask, jsonify, request, abort, send_from_directory
from flask_cors import CORS
import os
from typing import Optional
from docx import Document
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter
from dotenv import load_dotenv
from keycloak import KeycloakOpenID
from . import table_service

app = Flask(__name__)

# Enable CORS only for requests coming from the frontend
allowed_origin = os.getenv(
    "FRONTEND_ORIGIN",
    "https://frontend.cnics-validation.pm.ssingh20.dev.cirg.uw.edu",
)
CORS(
    app,
    resources={r"/api/*": {"origins": allowed_origin}},
    supports_credentials=True,
)


load_dotenv()

# Directory containing static instruction files. When running in Docker the
# path can be overridden with the ``FILES_DIR`` environment variable so the
# backend can access files from outside its build context.
FILES_DIR = os.getenv("FILES_DIR")
if not FILES_DIR:
    FILES_DIR = os.path.abspath(
        os.path.join(os.path.dirname(__file__), "..", "app", "webroot", "files")
    )
os.makedirs(FILES_DIR, exist_ok=True)


def get_limit(default: Optional[int] = None) -> Optional[int]:
    """Return the integer limit requested by the client."""
    value = request.args.get("limit", default)
    try:
        return int(value) if value is not None else None
    except (TypeError, ValueError):
        return default


def get_offset(default: int = 0) -> int:
    """Return the integer offset requested by the client."""
    try:
        return int(request.args.get("offset", default))
    except (TypeError, ValueError):
        return default


def ensure_pdf(doc_path: str, pdf_path: str) -> None:
    """Create a PDF from a doc/docx file if the PDF does not exist."""
    if os.path.exists(pdf_path):
        return
    try:
        doc = Document(doc_path)
        text = "\n".join(p.text for p in doc.paragraphs)
        if not text.strip():
            raise ValueError("empty")
    except Exception:
        text = (
            f"PDF version of {os.path.basename(doc_path)} is not available. "
            f"Please open the .doc file instead."
        )
    c = canvas.Canvas(pdf_path, pagesize=letter)
    width, height = letter
    y = height - 40
    for line in text.split("\n"):
        c.drawString(40, y, line)
        y -= 15
        if y < 40:
            c.showPage()
            y = height - 40
    c.save()

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
        if request.method == "OPTIONS":
            return "", 204
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
    wrapper.__doc__ = func.__doc__
    return wrapper

@app.route('/api/tables/<name>')
@requires_auth
def get_table(name):
    """Return rows from a database table.
    ---
    parameters:
      - name: name
        in: path
        type: string
        required: true
        description: Name of the table
    responses:
      200:
        description: Table rows
        schema:
          type: object
          properties:
            data:
              type: array
              items:
                type: object
    """
    limit = get_limit()
    offset = get_offset()
    try:
        rows = table_service.get_table_data(name, limit, offset)
        return jsonify({'data': rows})
    except Exception:
        app.logger.exception("Failed to fetch table data")
        return jsonify({'error': 'Failed to fetch table data'}), 500


@app.route('/api/events')
@requires_auth
def get_events():
    """Events with associated patient site information.
    ---
    responses:
      200:
        description: Event rows
        schema:
          type: object
          properties:
            data:
              type: array
              items:
                type: object
    """
    limit = get_limit()
    offset = get_offset()
    try:
        rows = table_service.get_events_with_patient_site(limit, offset)
        return jsonify({'data': rows})
    except Exception:
        app.logger.exception("Failed to fetch event data")
        return jsonify({'error': 'Failed to fetch event data'}), 500


@app.route('/api/events', methods=['POST'])
@requires_auth
def add_event():
    """Create a new event."""
    data = request.get_json() or {}
    try:
        event = table_service.create_event(data)
        return jsonify({'data': event}), 201
    except Exception:
        app.logger.exception("Failed to create event")
        return jsonify({'error': 'Failed to create event'}), 500


@app.route('/api/events/need_packets')
@requires_auth
def events_need_packets():
    """Events that require packet uploads.
    ---
    responses:
      200:
        description: Event rows
        schema:
          type: object
          properties:
            data:
              type: array
              items:
                type: object
    """
    limit = get_limit()
    offset = get_offset()
    try:
        rows = table_service.get_events_need_packets(limit, offset)
        return jsonify({'data': rows})
    except Exception:
        app.logger.exception("Failed to fetch table data")
        return jsonify({'error': 'Failed to fetch table data'}), 500


@app.route('/api/events/for_review')
@requires_auth
def events_for_review():
    """Events with packets awaiting review.
    ---
    responses:
      200:
        description: Event rows
        schema:
          type: object
          properties:
            data:
              type: array
              items:
                type: object
    """
    limit = get_limit()
    offset = get_offset()
    try:
        rows = table_service.get_events_for_review(limit, offset)
        return jsonify({'data': rows})
    except Exception:
        app.logger.exception("Failed to fetch table data")
        return jsonify({'error': 'Failed to fetch table data'}), 500


@app.route('/api/events/need_reupload')
@requires_auth
def events_need_reupload():
    limit = get_limit()
    offset = get_offset()
    try:
        rows = table_service.get_events_for_reupload(limit, offset)
        return jsonify({'data': rows})
    except Exception:
        app.logger.exception("Failed to fetch table data")
        return jsonify({'error': 'Failed to fetch table data'}), 500


@app.route('/api/events/status_summary')
@requires_auth
def events_status_summary():
    """Summary counts of events grouped by status.
    ---
    responses:
      200:
        description: Event summary
        schema:
          type: object
          properties:
            data:
              type: object
              additionalProperties:
                type: integer
    """
    try:
        summary = table_service.get_event_status_summary()
        return jsonify({'data': summary})
    except Exception:
        app.logger.exception("Failed to fetch table data")
        return jsonify({'error': 'Failed to fetch table data'}), 500


@app.route('/api/users', methods=['POST'])
@requires_auth
def add_user():
    """Create a new user."""
    data = request.get_json() or {}
    try:
        user = table_service.create_user(data)
        return jsonify({'data': user}), 201
    except Exception:
        app.logger.exception("Failed to create user")
        return jsonify({'error': 'Failed to create user'}), 500


@app.route('/files/<path:filename>')
def get_file(filename: str):
    file_path = os.path.join(FILES_DIR, filename)
    if filename.lower().endswith('.pdf') and not os.path.exists(file_path):
        base = os.path.splitext(filename)[0]
        for ext in ('.docx', '.doc'):
            doc_p = os.path.join(FILES_DIR, base + ext)
            if os.path.exists(doc_p):
                ensure_pdf(doc_p, file_path)
                break
    if not os.path.exists(file_path):
        abort(404)
    return send_from_directory(FILES_DIR, filename)

# Placeholder for OpenAPI generation scripts
swagger = None

# Optional root route for basic health check
@app.route('/')
def index():
    """Simple health check route."""
    return jsonify({'status': 'ok'})

if __name__ == '__main__':
    port = int(os.getenv('PORT', '3000'))
    app.run(host='0.0.0.0', port=port)

