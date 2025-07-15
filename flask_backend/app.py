from flask import Flask, jsonify, request, abort, send_from_directory
import os
from docx import Document
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter
from dotenv import load_dotenv
from keycloak import KeycloakOpenID
from . import table_service


load_dotenv()

FILES_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'app', 'webroot', 'files'))
os.makedirs(FILES_DIR, exist_ok=True)


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


@app.route('/api/events/need_packets')
@requires_auth
def events_need_packets():
    try:
        rows = table_service.get_events_need_packets()
        return jsonify({'data': rows})
    except Exception as exc:
        print(exc)
        return jsonify({'error': 'Failed to fetch table data'}), 500


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

if __name__ == '__main__':
    port = int(os.getenv('PORT', '3000'))
    app.run(host='0.0.0.0', port=port)
