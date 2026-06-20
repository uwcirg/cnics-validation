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
- `STUDY_TYPE` – selects the clinical validation study this deployment serves
  (`mci`, `vte`, `cva`, `hf`, `afib`, `scans`); defaults to `mci`. The chosen
  study supplies the default profile for the four workflow-stage controls below.
- `STUDY_TITLE` – optional free-form display string shown verbatim in the
  banner and used for the browser tab title (`CNICS ` + this value, e.g.
  `CNICS DEXA Scans Validation`). When unset, the banner/tab title is derived
  from `STUDY_TYPE` (e.g. `Scans Project`, `MCI Project`). Purely cosmetic.
- `ENABLE_SCRUBBING` – whether uploaded packets pass through the `scrubbed`
  stage (`true`/`false`/`1`/`0`/`yes`/`no`). Defaults to `true`.
- `ENABLE_SCREENING` – whether events pass through the `screened` stage
  (`true`/`false`/`1`/`0`/`yes`/`no`). Defaults to `true`.
- `ENABLE_SENDING` – whether assigned events pass through a separate `sent`
  dispatch stage (`true`/`false`/`1`/`0`/`yes`/`no`). Defaults to `true`.
- `REVIEWER_COUNT` – how many reviewers adjudicate each event; must be `1` or
  `2` (any other value aborts startup). Defaults to `2`. The four controls are
  resolved through the shared configuration layer; leaving them unset runs the
  full validation pipeline, while `STUDY_TYPE=scans` defaults them to the
  selective-bypass profile.
- `FRONTEND_ORIGIN` – allowed origin for CORS (Cross‑Origin Resource Sharing) requests to the backend.
- `FHIR_SERVER` – **not currently used.** Retained for backward compatibility with deployments that still set it; no runtime component reads this value. Safe to omit.
- `FILES_DIR` – directory containing instruction files served by the backend.
- `DOWNLOADS_DIR` – writable directory where the backend saves generated/downloadable artifacts
  (e.g., uploaded scrubbed ZIPs). Defaults to a subdirectory under `FILES_DIR` if not set.
- `CNICS_DATA_BRIDGE_MODE` / `CNICS_DATA_SOCKET_DIR` / `CNICS_DATA_DB_HOST` /
  `_DB_PORT` / `CNICS_DATA_DB_NAME` / `_TABLE` / `_USER` / `_PASSWORD` –
  connection parameters for the upstream `cnics_data.Patient` table. The
  mariadb container's init step builds a FEDERATED proxy of it, and
  `patients_view` UNIONs that proxy with the locally-owned `uw_patients2`
  table. `CNICS_DATA_BRIDGE_MODE` selects `socket` (cnics_data on the same VM,
  reached over a bind-mounted Unix socket — dev) or `tcp` (cnics_data on a
  separate VM, reached over an SSH tunnel — prod). The bridge user only needs
  `SELECT` on `cnics_data.Patient` — keep it read-only; leaving
  `CNICS_DATA_DB_USER` empty disables the bridge entirely. See
  [docs/cnics_data-bridge.md](docs/cnics_data-bridge.md) for the full setup,
  prerequisites, and troubleshooting for both modes.

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

- `FILES_DIR` → mounted read-only at `/files` in the backend; serves instruction/reference documents to the frontend under `/files/<name>`.
- `UPLOAD_DIR` → mounted read-write at `/opt/backend/uploads` in the backend; holds uploaded event packets and generated artifacts (e.g., scrubbed ZIPs) served by `/api/events/download/<id>`. `DOWNLOADS_DIR` is accepted as a back-compat alias; if neither is set the backend falls back to `FILES_DIR/downloads`.

Both directories are bind-mounted from host paths so they survive container rebuilds and are visible to host-level backup tooling. The host paths are controlled by `FILES_DIR` and `UPLOAD_HOST_DIR` in `.env` (see `default.env` for documented defaults); the in-container paths are fixed by `docker-compose.yaml` and should not need to change.

For production deployments, point `UPLOAD_HOST_DIR` at an absolute path outside the repo (e.g. `/srv/cnics/uploads`) so uploaded packets are not entangled with deploy/clone operations and so backup tooling can target a stable location. Ensure the directory is writable by the UID the backend container runs as, and that the host VM has sufficient disk for the expected packet volume.