from flask import Flask, jsonify, request, abort, send_from_directory, g
from flask_cors import CORS
import os
from typing import Optional
import datetime
from docx import Document
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import letter
from dotenv import load_dotenv
from . import table_service
from . import models
try:
    from flask_authorize import Authorize
except Exception:
    Authorize = None

app = Flask(__name__)

# Enable CORS only for requests coming from the frontend
# Support both the standard and auth vhosts by default, and merge any env-provided origins
# Default allowed origins; include unified domain
default_origins = [
    "https://cnics-validation.pm.ssingh20.dev.cirg.uw.edu",
    "https://frontend.cnics-validation.pm.ssingh20.dev.cirg.uw.edu",
    "https://frontend.auth.cnics-validation.pm.ssingh20.dev.cirg.uw.edu",
]
origins_env = os.getenv("FRONTEND_ORIGIN")
allowed_origins = list(default_origins)
if origins_env:
    parsed = [o.strip() for o in origins_env.split(",") if o.strip()]
    allowed_origins.extend(parsed)
    # de-duplicate while preserving order
    seen = set()
    allowed_origins = [o for o in allowed_origins if not (o in seen or seen.add(o))]

# Apply CORS globally so headers are set on all endpoints consistently
CORS(app, origins=allowed_origins, supports_credentials=True)

# Initialize Flask-Authorize if available
authorize = None
if Authorize is not None:
    authorize = Authorize(app)


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

# Optional directory to serve downloadable event artifacts (e.g., chart zips)
DOWNLOADS_DIR = os.getenv("DOWNLOADS_DIR")
if not DOWNLOADS_DIR:
    DOWNLOADS_DIR = os.path.join(FILES_DIR, "downloads")
try:
    os.makedirs(DOWNLOADS_DIR, exist_ok=True)
except OSError:
    # Directory may be on a read-only volume (e.g., /files mounted ro). We'll
    # still attempt to serve from existing paths without creating directories.
    pass


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
    try:
        from keycloak import KeycloakOpenID  # defer import
        keycloak_openid = KeycloakOpenID(
            server_url=os.getenv("KEYCLOAK_URL", "http://localhost:8080/"),
            realm_name=os.getenv("KEYCLOAK_REALM"),
            client_id=os.getenv("KEYCLOAK_CLIENT_ID"),
            client_secret_key=os.getenv("KEYCLOAK_CLIENT_SECRET"),
        )
    except Exception:
        keycloak_openid = None


def _load_user_from_remote_header() -> Optional[dict]:
    """Return user dict from X-Remote-User header if present, else None.

    When present, look up the user by `users.login`. If not found, abort 403.
    Stores a lightweight user/roles dict on `flask.g.auth_user` for downstream use.
    """
    remote_user = request.headers.get("X-Remote-User")
    if not remote_user:
        return None
    session = models.get_session()
    try:
        # Use the login column per application-level authorization rules
        user = session.query(models.Users).filter_by(login=remote_user).first()
        if user is None:
            abort(403)
        auth_user = {
            "id": user.id,
            "username": user.username,
            "admin": bool(user.admin_flag),
            "uploader": bool(user.uploader_flag),
            "reviewer": bool(user.reviewer_flag),
            "third_reviewer": bool(user.third_reviewer_flag),
            "site": user.site,
        }
        g.auth_user = auth_user
        return auth_user
    finally:
        session.close()

    


def requires_auth(func):
    """Decorator enforcing authentication.

    Priority:
    1) If `X-Remote-User` header is present, require user to exist in DB
       and attach role flags to request context.
    2) Else if Keycloak is configured, require a valid Bearer token.
    3) Else allow (legacy/dev environments without external auth configured).
    """

    def wrapper(*args, **kwargs):
        if request.method == "OPTIONS":
            return "", 204

        # Header-based auth from fronting web server (apache vhost with LDAP)
        if request.headers.get("X-Remote-User"):
            _load_user_from_remote_header()
            return func(*args, **kwargs)

        # Dev-only override: allow a header to set login directly when enabled
        # Set ALLOW_DEV_HEADER=1 to enable; use header X-Dev-User: <login>
        if os.getenv("ALLOW_DEV_HEADER") == "1":
            dev_login = request.headers.get("X-Dev-User")
            if dev_login:
                # Reuse the same logic as header-based auth but with explicit login
                session = models.get_session()
                try:
                    user = session.query(models.Users).filter_by(login=dev_login).first()
                    if user is None:
                        abort(403)
                    g.auth_user = {
                        "id": user.id,
                        "username": user.username,
                        "admin": bool(user.admin_flag),
                        "uploader": bool(user.uploader_flag),
                        "reviewer": bool(user.reviewer_flag),
                        "third_reviewer": bool(user.third_reviewer_flag),
                        "site": user.site,
                    }
                finally:
                    session.close()
                return func(*args, **kwargs)

        # Fallback to Keycloak if configured: require a valid Bearer token
        if 'keycloak_openid' in globals() and keycloak_openid:
            auth = request.headers.get("Authorization", "")
            if not auth.startswith("Bearer "):
                abort(401)
            token = auth.split(" ", 1)[1]
            try:
                keycloak_openid.userinfo(token)
            except Exception:
                abort(401)
            return func(*args, **kwargs)

        # No external auth configured - allow for local/dev
        return func(*args, **kwargs)

    wrapper.__name__ = func.__name__
    wrapper.__doc__ = func.__doc__
    return wrapper


def requires_roles(*required_roles: str):
    """Decorator enforcing that the authenticated user has ALL required roles.

    Roles correspond to boolean flags placed on g.auth_user: 'admin', 'uploader',
    'reviewer', 'third_reviewer'. This decorator should be stacked under
    @requires_auth so that g.auth_user is populated for header-based auth.
    """

    def decorator(func):
        def wrapper(*args, **kwargs):
            # Allow preflight without role checks
            if request.method == "OPTIONS":
                return "", 204
            auth_user = getattr(g, "auth_user", None)
            # If no header-based auth user is present (e.g., dev or keycloak),
            # do not enforce role checks here.
            if not auth_user:
                return func(*args, **kwargs)
            missing = [r for r in required_roles if not bool(auth_user.get(r))]
            if missing:
                abort(403)
            return func(*args, **kwargs)

        wrapper.__name__ = func.__name__
        wrapper.__doc__ = func.__doc__
        return wrapper

    return decorator


def requires_any_role(*allowed_roles: str):
    """Decorator enforcing the user has ANY of the allowed roles."""

    def decorator(func):
        def wrapper(*args, **kwargs):
            if request.method == "OPTIONS":
                return "", 204
            auth_user = getattr(g, "auth_user", None)
            if not auth_user:
                return func(*args, **kwargs)
            if not any(bool(auth_user.get(r)) for r in allowed_roles):
                abort(403)
            return func(*args, **kwargs)

        wrapper.__name__ = func.__name__
        wrapper.__doc__ = func.__doc__
        return wrapper

    return decorator

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
    q = request.args.get('q') or None
    site = request.args.get('site') or None
    try:
        rows, total = table_service.get_events_with_patient_site_with_total(limit, offset, q, site)
        return jsonify({'data': rows, 'total': total})
    except Exception:
        app.logger.exception("Failed to fetch event data")
        return jsonify({'error': 'Failed to fetch event data'}), 500


@app.route('/api/events', methods=['POST'])
@requires_auth
@requires_roles('admin')
def add_event():
    """Create a new event."""
    data = request.get_json() or {}
    try:
        event = table_service.create_event(data)
        return jsonify({'data': event}), 201
    except table_service.ValidationError as ve:
        app.logger.warning("Validation error when creating event: %s", ve)
        return jsonify({'error': str(ve)}), 400
    except Exception:
        app.logger.exception("Failed to create event")
        return jsonify({'error': 'Failed to create event'}), 500


@app.route('/api/events/need_packets')
@requires_auth
@requires_any_role('reviewer', 'uploader', 'admin')
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
        # Optional: add total if we later add filtering here as well
        return jsonify({'data': rows})
    except Exception:
        app.logger.exception("Failed to fetch table data")
        return jsonify({'error': 'Failed to fetch table data'}), 500


@app.route('/api/events/for_review')
@requires_auth
@requires_any_role('reviewer', 'admin')
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
@requires_any_role('reviewer', 'uploader', 'admin')
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
@requires_roles('admin')
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


# Generic endpoint to fetch events by status with pagination
_ALLOWED_EVENT_STATUSES = {
    'created',
    'uploaded',
    'scrubbed',
    'screened',
    'assigned',
    'sent',
    'reviewer1_done',
    'reviewer2_done',
    'third_review_needed',
    'third_review_assigned',
    'done',
    'rejected',
    'no_packet_available',
}


@app.route('/api/events/by_status/<status>')
@requires_auth
@requires_any_role('reviewer', 'uploader', 'admin')
def events_by_status(status: str):
    status = (status or '').strip()
    limit = get_limit()
    offset = get_offset()
    q = request.args.get('q') or None
    site = request.args.get('site') or None
    try:
        if status == 'uploaded':
            rows, total = table_service.get_to_be_scrubbed_with_total(limit, offset, q, site)
        elif status == 'scrubbed':
            rows, total = table_service.get_to_be_screened_with_total(limit, offset, q, site)
        elif status == 'screened':
            rows, total = table_service.get_to_be_assigned_with_total(limit, offset, q, site)
        elif status == 'assigned':
            rows, total = table_service.get_to_be_sent_with_total(limit, offset, q, site)
        elif status == 'sent':
            rows, total = table_service.get_to_be_reviewed_with_total(limit, offset, q, site)
        else:
            if status not in _ALLOWED_EVENT_STATUSES:
                abort(400)
            rows, total = table_service.get_events_by_status_with_total(status, limit, offset, q, site)
        return jsonify({'data': rows, 'total': total})
    except Exception:
        app.logger.exception("Failed to fetch events by status %s", status)
        return jsonify({'error': 'Failed to fetch table data'}), 500


@app.route('/api/events/<int:event_id>')
@requires_auth
def get_event_details(event_id: int):
    try:
        details = table_service.get_event_details(event_id)
        if not details:
            abort(404)
        return jsonify({'data': details})
    except Exception:
        app.logger.exception("Failed to fetch event details %d", event_id)
        return jsonify({'error': 'Failed to fetch event details'}), 500


@app.route('/api/users', methods=['POST'])
@requires_auth
@requires_roles('admin')
def add_user():
    """Create a new user."""
    data = request.get_json() or {}
    try:
        user = table_service.create_user(data)
        return jsonify({'data': user}), 201
    except Exception:
        app.logger.exception("Failed to create user")
        return jsonify({'error': 'Failed to create user'}), 500


@app.route('/api/auth/me')
@requires_auth
def auth_me():
    """Return the authenticated user's identity and role flags.

    Only header-based auth populates this endpoint. If no header auth is used
    (e.g., Keycloak or dev without auth), this returns 401 because role mapping
    requires a cnics user.
    """
    auth_user = getattr(g, 'auth_user', None)
    if not auth_user:
        abort(401)
    return jsonify({"data": auth_user})


@app.route('/api/reviewer/awaiting')
@requires_auth
@requires_any_role('reviewer', 'third_reviewer', 'admin')
def reviewer_awaiting():
    """Return events awaiting the logged-in reviewer's action (minimal fields)."""
    try:
        q = request.args.get('q') or None
        auth_user = getattr(g, 'auth_user', None) or {}
        uid = auth_user.get('id')
        if not uid:
            abort(401)
        rows = table_service.get_events_awaiting_review(int(uid), q)
        return jsonify({'data': rows})
    except Exception:
        app.logger.exception("Failed to fetch reviewer awaiting list")
        return jsonify({'error': 'Failed to fetch reviewer awaiting list'}), 500


@app.route('/api/events/export')
@requires_auth
@requires_roles('admin')
def events_export():
    """Export events as CSV with detailed fields.

    Query param format=csv defaults to CSV. Response is text/csv.
    """
    try:
        rows = table_service.get_events_export_rows()
        # Build CSV
        import csv
        import io

        output = io.StringIO()
        writer = csv.writer(output)
        # Headings (reduced example; expand as needed)
        headings = [
            'MI', 'Patient ID', 'Patient Site', 'Site Patient ID', 'Event Date', 'Status', 'Creator',
            'Criteria: MI Dx', 'Criteria: CKMB_Q', 'Criteria: CKMB_M', 'Criteria: CKMB', 'Criteria: Troponin', 'Criteria: Other',
            'Add Date', 'Uploader', 'Upload Date', 'Marker (no packet)', 'No Packet Reason', 'Two Attempts?',
            'Prior Event Date', 'Prior Event Onsite?', 'Other Cause', 'Mark No Packet Date', 'Scrubber', 'Scrub Date',
            'Screener', 'Screen Date', 'Rescrub Message', 'Reject Message', 'Assigner', 'Assign Date', 'Sender', 'Send Date',
            'Reviewer 1', 'Review 1 MI', 'Review 1 Abnormal CE Values?', 'Review 1 CE Criteria', 'Review 1 Chest Pain?',
            'Review 1 ECG Changes?', 'Review 1 LVM by Imaging?', 'Review 1 Clinical Intervention?', 'Review 1 Type',
            'Review 1 Secondary Cause', 'Review 1 Other Cause', 'Review 1 False Positive?', 'Review 1 False Positive Reason',
            'Review 1 False Positive Other Cause', 'Review 1 Current Tobacco Use?', 'Review 1 Past Tobacco Use?',
            'Review 1 Cocaine Use?', 'Review 1 Family History?', 'Review 1 Date',
            'Reviewer 2', 'Review 2 MI', 'Review 2 Abnormal CE Values?', 'Review 2 CE Criteria', 'Review 2 Chest Pain?',
            'Review 2 ECG Changes?', 'Review 2 LVM by Imaging?', 'Review 2 Clinical Intervention?', 'Review 2 Type',
            'Review 2 Secondary Cause', 'Review 2 Other Cause', 'Review 2 False Positive?', 'Review 2 False Positive Reason',
            'Review 2 False Positive Other Cause', 'Review 2 Current Tobacco Use?', 'Review 2 Past Tobacco Use?',
            'Review 2 Cocaine Use?', 'Review 2 Family History?', 'Review 2 Date',
            '3rd Review Assigner', '3rd Review Assign Date', 'Reviewer 3', 'Review 3 MI', 'Review 3 Abnormal CE Values?',
            'Review 3 CE Criteria', 'Review 3 Chest Pain?', 'Review 3 ECG Changes?', 'Review 3 LVM by Imaging?',
            'Review 3 Clinical Intervention?', 'Review 3 Type', 'Review 3 Secondary Cause', 'Review 3 Other Cause',
            'Review 3 False Positive?', 'Review 3 False Positive Reason', 'Review 3 False Positive Other Cause',
            'Review 3 Current Tobacco Use?', 'Review 3 Past Tobacco Use?', 'Review 3 Cocaine Use?', 'Review 3 Family History?', 'Review 3 Date',
            'Overall Outcome', 'Overall Primary vs. Secondary', 'Overall False Positive Event?', 'Overall Secondary Cause',
            'Overall Secondary Cause Other', 'Overall False Positive Cause', 'Overall Cardiac Intervention?'
        ]
        writer.writerow(headings)
        for r in rows:
            writer.writerow([
                (r['id'] + 1000) if r.get('id') is not None else '',
                r.get('patient_id',''),
                r.get('site',''),
                r.get('site_patient_id',''),
                r.get('event_date',''),
                r.get('status',''),
                r.get('creator',''),
                r.get('mi_dx',''), r.get('ckmb_q',''), r.get('ckmb_m',''), r.get('ckmb',''), r.get('troponin',''), r.get('other',''),
                r.get('add_date',''), r.get('uploader',''), r.get('upload_date',''), r.get('marker',''), r.get('no_packet_reason',''),
                'Yes' if r.get('two_attempts_flag') else 'No' if r.get('two_attempts_flag') is not None else '',
                r.get('prior_event_date',''), 'Yes' if r.get('prior_event_onsite_flag') else 'No' if r.get('prior_event_onsite_flag') is not None else '',
                r.get('other_cause',''), r.get('markNoPacket_date',''), r.get('scrubber',''), r.get('scrub_date',''), r.get('screener',''), r.get('screen_date',''),
                r.get('rescrub_message',''), r.get('reject_message',''), r.get('assigner',''), r.get('assign_date',''), r.get('sender',''), r.get('send_date',''),
                r.get('reviewer1',''), r.get('review1_mci',''), r.get('review1_abnormal_ce',''), r.get('review1_ce_criteria',''), r.get('review1_chest_pain',''),
                r.get('review1_ecg_changes',''), r.get('review1_lvm',''), r.get('review1_ci',''), r.get('review1_type',''),
                r.get('review1_secondary_cause',''), r.get('review1_other_cause',''), r.get('review1_false_positive',''), r.get('review1_false_positive_reason',''),
                r.get('review1_false_positive_other_cause',''), r.get('review1_current_tobacco',''), r.get('review1_past_tobacco',''), r.get('review1_cocaine',''), r.get('review1_family_history',''), r.get('review1_date',''),
                r.get('reviewer2',''), r.get('review2_mci',''), r.get('review2_abnormal_ce',''), r.get('review2_ce_criteria',''), r.get('review2_chest_pain',''),
                r.get('review2_ecg_changes',''), r.get('review2_lvm',''), r.get('review2_ci',''), r.get('review2_type',''),
                r.get('review2_secondary_cause',''), r.get('review2_other_cause',''), r.get('review2_false_positive',''), r.get('review2_false_positive_reason',''),
                r.get('review2_false_positive_other_cause',''), r.get('review2_current_tobacco',''), r.get('review2_past_tobacco',''), r.get('review2_cocaine',''), r.get('review2_family_history',''), r.get('review2_date',''),
                r.get('assigner3rd',''), r.get('assign3rd_date',''), r.get('reviewer3',''), r.get('review3_mci',''), r.get('review3_abnormal_ce',''),
                r.get('review3_ce_criteria',''), r.get('review3_chest_pain',''), r.get('review3_ecg_changes',''), r.get('review3_lvm',''), r.get('review3_ci',''), r.get('review3_type',''), r.get('review3_secondary_cause',''), r.get('review3_other_cause',''),
                r.get('review3_false_positive',''), r.get('review3_false_positive_reason',''), r.get('review3_false_positive_other_cause',''), r.get('review3_current_tobacco',''), r.get('review3_past_tobacco',''), r.get('review3_cocaine',''), r.get('review3_family_history',''), r.get('review3_date',''),
                r.get('overall_outcome',''), r.get('overall_primary_secondary',''), 'Yes' if r.get('overall_false_positive_event') else 'No' if r.get('overall_false_positive_event') is not None else '', r.get('overall_secondary_cause',''), r.get('overall_secondary_cause_other',''), r.get('overall_false_positive_reason',''), 'Yes' if r.get('overall_ci') else 'No' if r.get('overall_ci') is not None else ''
            ])
        output.seek(0)
        from flask import Response
        return Response(output.getvalue(), mimetype='text/csv', headers={'Content-Disposition': 'attachment; filename=event.csv'})
    except Exception:
        app.logger.exception("Failed to export events")
        return jsonify({'error': 'Failed to export events'}), 500


@app.route('/api/events/download/<int:event_id>')
@requires_auth
def events_download(event_id: int):
    """Stream a downloadable artifact for an event (e.g., charts zip).

    Looks for a file under DOWNLOADS_DIR named "<event_id>.zip" or
    "event_<event_id>.zip" and streams it as an attachment.
    """
    # Try a couple of conventional filenames in both DOWNLOADS_DIR and FILES_DIR
    candidates = [f"{event_id}.zip", f"event_{event_id}.zip"]
    for base_dir in (DOWNLOADS_DIR, FILES_DIR):
        for name in candidates:
            path = os.path.join(base_dir, name)
            if os.path.exists(path):
                from flask import send_file
                return send_file(path, as_attachment=True, download_name=name)
    abort(404)


# --- Write endpoints needed by the frontend ---------------------------------

@app.route('/api/criteria', methods=['POST'])
@requires_auth
@requires_any_role('reviewer', 'uploader', 'admin')
def criteria_create():
    data = request.get_json() or {}
    event_id = data.get('event_id')
    name = (data.get('name') or '').strip()
    value = (data.get('value') or '').strip()
    if not event_id or not name:
        return jsonify({'error': 'event_id and name are required'}), 400
    session = models.get_session()
    try:
        crit = models.Criterias(event_id=int(event_id), name=name, value=value)
        session.add(crit)
        session.commit()
        return jsonify({'data': {'id': crit.id}}), 201
    except Exception:
        session.rollback()
        app.logger.exception('Failed to create criteria')
        return jsonify({'error': 'Failed to create criteria'}), 500
    finally:
        session.close()


@app.route('/api/solicitations', methods=['POST'])
@requires_auth
@requires_any_role('reviewer', 'uploader', 'admin')
def solicitation_create():
    data = request.get_json() or {}
    event_id = data.get('event_id')
    date = data.get('date')
    contact = (data.get('contact') or '').strip()
    if not event_id or not date or not contact:
        return jsonify({'error': 'event_id, date, and contact are required'}), 400
    try:
        y, m, d = [int(x) for x in str(date).split('-')]
        date_obj = datetime.date(y, m, d)
    except Exception:
        return jsonify({'error': 'date must be YYYY-MM-DD'}), 400
    session = models.get_session()
    try:
        sol = models.Solicitations(event_id=int(event_id), date=date_obj, contact=contact)
        session.add(sol)
        session.commit()
        return jsonify({'data': {'id': sol.id}}), 201
    except Exception:
        session.rollback()
        app.logger.exception('Failed to create solicitation')
        return jsonify({'error': 'Failed to create solicitation'}), 500
    finally:
        session.close()


@app.route('/api/events/<int:event_id>', methods=['PUT'])
@requires_auth
@requires_roles('admin')
def events_update(event_id: int):
    data = request.get_json() or {}
    site_patient_id = data.get('site_patient_id')
    site = data.get('site')
    event_date = data.get('event_date')
    # Update fields on events and/or patients
    session = models.get_session()
    try:
        e = session.query(models.Events).get(event_id)
        if e is None:
            return jsonify({'error': 'Event not found'}), 404
        if event_date:
            try:
                y, m, d = [int(x) for x in str(event_date).split('-')]
                e.event_date = datetime.date(y, m, d)
            except Exception:
                return jsonify({'error': 'event_date must be YYYY-MM-DD'}), 400
        # Update patient fields
        if site_patient_id or site:
            p = session.query(models.Patients).get(e.patient_id)
            if p is not None:
                if site_patient_id is not None:
                    p.site_patient_id = site_patient_id
                if site is not None:
                    p.site = site
        session.commit()
        return jsonify({'data': {'updated': True}})
    except Exception:
        session.rollback()
        app.logger.exception('Failed to update event %d', event_id)
        return jsonify({'error': 'Failed to update event'}), 500
    finally:
        session.close()


@app.route('/api/events/<int:event_id>/screen', methods=['POST'])
@requires_auth
@requires_any_role('reviewer', 'admin')
def events_screen(event_id: int):
    data = request.get_json() or {}
    decision = (data.get('decision') or '').strip()
    message = (data.get('message') or '').strip()
    if decision not in {'accept', 'rescrub', 'reject'}:
        return jsonify({'error': 'decision must be one of: accept, rescrub, reject'}), 400
    auth_user = getattr(g, 'auth_user', None) or {}
    session = models.get_session()
    try:
        e = session.query(models.Events).get(event_id)
        if e is None:
            return jsonify({'error': 'Event not found'}), 404
        today = datetime.date.today()
        if decision == 'accept':
            e.screener_id = int(auth_user.get('id') or 0)
            e.screen_date = today
            e.status = 'screened'
        elif decision == 'rescrub':
            e.rescrub_message = message
            e.status = 'uploaded'
            e.screen_date = None
        else:  # reject
            e.screener_id = int(auth_user.get('id') or 0)
            e.screen_date = today
            e.reject_message = message
            e.status = 'rejected'
        session.commit()
        return jsonify({'data': {'updated': True}})
    except Exception:
        session.rollback()
        app.logger.exception('Failed to screen event %d', event_id)
        return jsonify({'error': 'Failed to update screening decision'}), 500
    finally:
        session.close()


@app.route('/api/events/bulk', methods=['POST'])
@requires_auth
@requires_roles('admin')
def events_bulk():
    # Minimal placeholder: accept a CSV upload and return 201. Implement parsing later.
    if 'events_csv' not in request.files:
        return jsonify({'error': 'events_csv file is required'}), 400
    # In a future improvement, parse CSV and create events via table_service.create_event
    return jsonify({'data': {'imported': 0}}), 201

@app.route('/api/events/<int:event_id>/upload_scrubbed', methods=['POST'])
@requires_auth
@requires_any_role('reviewer', 'uploader', 'admin')
def events_upload_scrubbed(event_id: int):
    """Accept a scrubbed charts upload and mark the event as scrubbed.

    Expects multipart/form-data with field name 'scrubbed_file'. Saves the
    uploaded file to DOWNLOADS_DIR using a conventional filename and updates
    the event's scrubber, scrub_date, and status.
    """
    try:
        file = request.files.get('scrubbed_file')
        if not file or not file.filename:
            return jsonify({'error': 'No file provided'}), 400

        # Ensure downloads directory exists (may be a separate RW mount)
        try:
            os.makedirs(DOWNLOADS_DIR, exist_ok=True)
        except OSError:
            # If the directory is read-only, abort with a clear message
            return jsonify({'error': 'Downloads directory is not writable'}), 500

        # Save using standard name so the download endpoint can find it
        out_path = os.path.join(DOWNLOADS_DIR, f"{event_id}.zip")
        file.save(out_path)

        # Update event metadata
        session = models.get_session()
        try:
            e = session.query(models.Events).get(event_id)
            if e is None:
                return jsonify({'error': 'Event not found'}), 404
            auth_user = getattr(g, 'auth_user', None) or {}
            if auth_user.get('id'):
                e.scrubber_id = int(auth_user['id'])
            e.scrub_date = datetime.date.today()
            e.status = 'scrubbed'
            session.commit()
        finally:
            session.close()

        return jsonify({'data': {'saved': True}})
    except Exception:
        app.logger.exception('Failed to upload scrubbed file for event %d', event_id)
        return jsonify({'error': 'Failed to upload scrubbed file'}), 500

@app.route('/api/events/assign_many', methods=['POST'])
@requires_auth
@requires_roles('admin')
def events_assign_many():
    data = request.get_json() or {}
    event_ids = data.get('event_ids') or []
    reviewer_id = data.get('reviewer_id')
    slot = (data.get('slot') or '').strip()
    if not isinstance(event_ids, list) or not reviewer_id or not slot:
        return jsonify({'error': 'event_ids, reviewer_id, and slot are required'}), 400
    auth_user = getattr(g, 'auth_user', None) or {}
    assigner_id = auth_user.get('id') or 0
    try:
        result = table_service.assign_events(event_ids, int(reviewer_id), slot, int(assigner_id))
        return jsonify({'data': result})
    except table_service.ValidationError as ve:
        return jsonify({'error': str(ve)}), 400
    except Exception:
        app.logger.exception('Failed to assign events')
        return jsonify({'error': 'Failed to assign events'}), 500


@app.route('/api/events/send_many', methods=['POST'])
@requires_auth
@requires_roles('admin')
def events_send_many():
    data = request.get_json() or {}
    event_ids = data.get('event_ids') or []
    if not isinstance(event_ids, list) or not event_ids:
        return jsonify({'error': 'event_ids required'}), 400
    auth_user = getattr(g, 'auth_user', None) or {}
    sender_id = auth_user.get('id') or 0
    try:
        result = table_service.send_events(event_ids, int(sender_id))
        return jsonify({'data': result})
    except Exception:
        app.logger.exception('Failed to send events')
        return jsonify({'error': 'Failed to send events'}), 500


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

