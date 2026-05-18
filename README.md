# https://cnics.cirg.washington.edu/mci

SRC:

        git clone git@gitlab.cirg.washington.edu:cnics/mci.git

DOCKER DATABASE:

        cp default.env .env
        docker compose up -d mariadb
        # Initialization scripts under init/ create the schema and define
        # the `patients_view` view, which UNIONs the locally-owned
        # `uw_patients2` table with a FEDERATED proxy of the upstream
        # `cnics_data.Patient` table. See init/06-create-patients-view.sh
        # and default.env's CNICS_DATA_DB_* block for configuration.

## Container Setup

This repository includes a lightweight Docker configuration based on the setup used in the `asbi-screening-app` project. The compose file builds the React frontend and Flask backend for local development.

### Build and Run

1. Copy `default.env` to `.env` and edit if necessary. Note that `VITE_API_URL` (API: Application Programming Interface) is the frontend's API base URL and is loaded from `frontend/default.env`, not from the root `.env`. For unified-domain deployments, leave `VITE_API_URL` empty (same-origin) so the frontend calls `/api/...` on the same host.
2. Build the Docker images:

   ```bash
   docker compose build
   ```

3. Start the stack:

   ```bash
    docker compose up
    ```

    The application will be served on `https://cnics-validation.pm.ssingh20.dev.cirg.uw.edu/` and the API (Application Programming Interface) under the same origin at `/api/...`.
    The compose file mounts `app/webroot/files` into the backend container so
    instruction documents are available at `/files/<name>`.

### Environment Variables

Project-level runtime configuration is provided via a `.env` file at the
repository root that you create by copying `default.env`. Docker Compose
automatically loads this file for variable interpolation when the services
are built or started. The template defines the following variables:

- `DB_ROOT_PASSWORD` – password for the MariaDB root user.
- `DB_NAME` – name of the application's database.
- `DB_USER` – database user for the application.
- `DB_PASSWORD` – password for `DB_USER`.
- `FRONTEND_ORIGIN` – allowed origin for CORS (Cross‑Origin Resource Sharing) requests to the backend.
- `FHIR_SERVER` – **not currently used.** Retained for backward compatibility with deployments that still set it; no runtime component reads this value. Safe to omit.
- `FILES_DIR` – directory containing instruction files served by the backend.
- `DOWNLOADS_DIR` – writable directory where the backend saves generated/downloadable artifacts
  (e.g., uploaded scrubbed ZIPs). Defaults to a subdirectory under `FILES_DIR` if not set.
- `CNICS_DATA_DB_HOST` / `_PORT` / `_NAME` / `_TABLE` / `_USER` / `_PASSWORD` –
  connection parameters for the upstream `cnics_data.Patient` table. The
  mariadb container's init step builds a FEDERATED proxy table from these
  values, and the `patients_view` view UNIONs that proxy with the
  locally-owned `uw_patients2` table. Leaving `CNICS_DATA_DB_HOST` empty
  disables the bridge; `patients_view` is then defined over
  `uw_patients2` only (single-instance / dev fallback). When `cnics_data`
  runs on the host VM rather than in a sibling container, set
  `CNICS_DATA_DB_HOST=host.docker.internal` — Docker Compose maps that
  name to the host gateway. The upstream bridge user only needs `SELECT`
  on `cnics_data.Patient`, and on MySQL 8.0+ should be created with
  `mysql_native_password` to keep FederatedX's handshake happy.

Frontend client variables (those with the `VITE_` prefix, including
`VITE_API_URL`) are loaded from `frontend/default.env`, not the root
`.env`. Edit `frontend/default.env` to override them.

Override these values in your copied `.env` file as needed.

## Local Development

See [docs/development.md](docs/development.md) for instructions on running the application with Docker. For a full end‑to‑end overview of setup, auth, data, and file flows, read [docs/WORKFLOW.md](docs/WORKFLOW.md).


## Backend API


The Flask backend under `flask_backend/` exposes REST endpoints that the React frontend fetches. Docker Compose runs a `backend` service alongside the `web` frontend service. The frontend reads the API base URL from the `VITE_API_URL` environment variable; if unset it uses same-origin.

See [docs/separation_of_duties.md](docs/separation_of_duties.md) for details on the responsibilities of each component.

-### Available Endpoints

- `/api/tables/<name>` – return rows from a database table.
- `/api/events` – events with patient site information.
- `/api/events/need_packets` – events awaiting packet uploads.
- `/api/events/for_review` – events with packets ready for review.
- `/api/events/need_reupload` – events requiring packet re-upload.
- `/api/events/status_summary` – counts of events grouped by status.

### Authentication and Authorization

For the first release, authentication is a two-layer contract and both
halves are load-bearing:

1. **Apache edge (HTTP Basic Auth + LDAP)**: the repository's
   [`.htaccess`](./.htaccess) configures `AuthType basic` with
   `AuthBasicProvider ldap`, an `AuthLDAPURL` pointing at the CNICS
   LDAP servers, and a `require ldap-group` rule that restricts
   access to members of the appropriate LDAP group. A request that
   fails the basic-auth prompt or is not in the group never reaches
   the backend.
2. **Flask backend (`X-Remote-User` + role decorators)**: after a
   successful bind, Apache forwards the authenticated identity to
   the Flask backend as the `X-Remote-User` request header. The
   backend looks the user up in the `users` table by the `login`
   field, attaches a compact identity to the Flask request context,
   and enforces role flags (`admin`, `uploader`, `reviewer`,
   `third_reviewer`) via decorators:

- `@requires_auth` – required for all API endpoints; if `X-Remote-User` is present, the user must exist in the database or a 403 is returned.
- `@requires_roles("role1", ...)` – require all named roles (enforced only when header auth is in use).
- `@requires_any_role("role1", ...)` – require at least one of the named roles (enforced only when header auth is in use).

The authoritative decision record for this contract is
[`.specify/memory/constitution.md`](./.specify/memory/constitution.md)
under **Security & Data Governance → Authentication (first release)**.

**Keycloak is deferred to a later release** and is **not supported in
first-release deployments**. The `flask_backend/` tree still contains
Keycloak integration code gated on `KEYCLOAK_REALM` being set; those
paths default off and are explicitly marked as deferred — see
[`flask_backend/README.md`](./flask_backend/README.md) for the current
status of the `KEYCLOAK_*` environment variables. Enabling Keycloak
in any study deployment requires a prior amendment to the constitution.

Current role protections applied:

- Admin only: `POST /api/events`, `GET /api/events/status_summary`, `POST /api/users`
- Reviewer/uploader/admin: `GET /api/events/need_packets`, `GET /api/events/need_reupload`
- Reviewer/admin: `GET /api/events/for_review`

Frontend loads the current user via `GET /api/auth/me` and renders UI based on the returned flags.

Outstanding next steps:

- Confirm Apache is consistently sending `X-Remote-User` and decide on normalization (email vs. netid); ensure `users.login` values match.
- Add/seed required users (e.g., Satinder) with `login` filled and appropriate role flags.
- Review and refine per-endpoint role requirements; extend decorators where needed.

### OpenAPI Documentation

Run `python -m flask_backend.generate_openapi` from the repository root
to generate `openapi.json` describing the backend API. A GitHub action
updates this file on each push.

## Alternative SQLAlchemy models

`flask_backend/models2.py` is a copy of `models.py` with all `back_populates`
arguments removed from the `relationship()` definitions. The current
application still imports `models.py` and does not use `models2.py`.

Removing `back_populates` turns these relationships into one‑way links. SQLA
chemy will no longer keep both sides of a relationship in sync automatically.
For example, appending a `Criterias` object to `Events.criterias` will not set
the corresponding `Criterias.event` attribute unless done manually. The new
file is included for future experimentation and has no effect on the running
code.
---

### Try it out: demo scripts

Two small scripts illustrate the difference:

- `scripts/demo_back_populates.py` – uses `flask_backend.models` (with `back_populates`).
- `scripts/demo_no_back_populates.py` – uses `flask_backend.models2` (without `back_populates`).

Run them after your database is up (e.g., with Docker Compose). If running directly, ensure the project root is on `PYTHONPATH` or run via `python -m` from the repo root:

```bash
docker compose up -d mariadb
export DB_USER=root
export DB_PASSWORD=${DB_ROOT_PASSWORD}
export DB_HOST=127.0.0.1
export DB_NAME=cnics

# Option 1: run scripts directly (they add the repo root to sys.path)
python3 scripts/demo_back_populates.py
python3 scripts/demo_no_back_populates.py

# Option 2: run as modules
python3 -m scripts.demo_back_populates
python3 -m scripts.demo_no_back_populates

To switch which models the backend uses globally, set `SQLA_MODELS`:

```bash
# Use standard models with back_populates (default)
export SQLA_MODELS=models

# Or use the alternate one-way models
export SQLA_MODELS=models2
```
```

The first script will show that the child object's `.event` is synchronized in-memory upon append, while the second script will not.

## File handling and storage

The backend uses two configurable directories for file operations:

- `FILES_DIR`: read-only documents served to the frontend (e.g., instructions under `/files/<name>`).
- `DOWNLOADS_DIR`: writable area for generated or uploaded artifacts (e.g., scrubbed ZIP files) served by `/api/events/download/<id>`.

You can configure these via environment variables. If `DOWNLOADS_DIR` is not set, it falls back to `FILES_DIR/downloads`.

For containerized deployments, choose one of the following:

1) Bind-mount (convenient for development, visible on the host):

```yaml
services:
  backend:
    environment:
      FILES_DIR: /files
      DOWNLOADS_DIR: /downloads
    volumes:
      - ./app/webroot/files:/files:ro   # read-only docs
      - ./downloads:/downloads          # writable artifacts
```

2) Named Docker volumes (isolated, easier lifecycle via `docker volume ls`):

```yaml
services:
  backend:
    environment:
      FILES_DIR: /files
      DOWNLOADS_DIR: /downloads
    volumes:
      - cnics-files:/files:ro
      - cnics-downloads:/downloads

volumes:
  cnics-files:
  cnics-downloads:
```

Notes:
- If files do not need to be accessed by external host processes (confirmed), named volumes are a good default for production.
- Ensure sufficient disk space on the VM hosting Docker; containers do not need their own disk allocation.
- Keep the upload/download locations configurable via env so staging/prod can use different mounts.