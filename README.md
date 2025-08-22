https://cnics.cirg.washington.edu/mci

SRC:

        git clone git@gitlab.cirg.washington.edu:cnics/mci.git

DOCKER DATABASE:

        cp .env.example .env
        docker-compose up -d mariadb
        # Initialization scripts load `init/04-create-patients.sql` which
        # populates the `patients` table from `uw_patients2` if it does not
        # already exist

## Container Setup

This repository includes a lightweight Docker configuration based on the setup used in the `asbi-screening-app` project. The compose file builds the React frontend and Flask backend for local development.

### Build and Run

1. Copy `.env.example` to `.env` and edit if necessary. `VITE_API_URL` should
   point at the backend API (defaults to `https://backend.cnics-validation.pm.ssingh20.dev.cirg.uw.edu`).
2. Build the Docker images:

   ```bash
   docker-compose build
   ```

3. Start the stack:

   ```bash
    docker-compose up
    ```

    The frontend will be served on <https://frontend.cnics-validation.pm.ssingh20.dev.cirg.uw.edu/> and the backend API
    on <https://backend.cnics-validation.pm.ssingh20.dev.cirg.uw.edu/>.
    The compose file mounts `app/webroot/files` into the backend container so
    instruction documents are available at `/files/<name>`.

### Environment Variables

Runtime configuration is provided via a `.env` file that you create by
copying `.env.example`. Docker Compose automatically loads this file when the
services are built or started. The template defines the following variables:

- `DB_ROOT_PASSWORD` – password for the MariaDB root user.
- `DB_NAME` – name of the application's database.
- `DB_USER` – database user for the application.
- `DB_PASSWORD` – password for `DB_USER`.
- `VITE_API_URL` – base URL of the backend API consumed by the React frontend.
- `FRONTEND_ORIGIN` – allowed origin for CORS requests to the backend.
- `FHIR_SERVER` – URL of the FHIR server used by the application.
- `FILES_DIR` – directory containing instruction files served by the backend.
- `EXTERNAL_DB_URL` – optional SQLAlchemy URL for a secondary database.

Override these values in your copied `.env` file as needed.

## Local Development

See [docs/development.md](docs/development.md) for instructions on running the application with Docker.


## Backend API


The Flask backend under `flask_backend/` exposes REST endpoints that the React frontend fetches. Docker Compose runs a `backend` service alongside the `web` frontend service. The frontend reads the API base URL from the `VITE_API_URL` environment variable.

See [docs/separation_of_duties.md](docs/separation_of_duties.md) for details on the responsibilities of each component.

-### Available Endpoints

- `/api/tables/<name>` – return rows from a database table.
- `/api/events` – events with patient site information.
- `/api/events/need_packets` – events awaiting packet uploads.
- `/api/events/for_review` – events with packets ready for review.
- `/api/events/need_reupload` – events requiring packet re-upload.
- `/api/events/status_summary` – counts of events grouped by status.

### Authentication and Authorization

The backend supports header-based authentication via an Apache/Ldap front-end that injects an `X-Remote-User` header. When this header is present, the app looks up the authenticated user in the `users` table by the `login` field and attaches a compact identity to the Flask request context. Role flags (`admin`, `uploader`, `reviewer`, `third_reviewer`) are enforced via decorators:

- `@requires_auth` – required for all API endpoints; if `X-Remote-User` is present, the user must exist in the database or a 403 is returned.
- `@requires_roles("role1", ...)` – require all named roles (enforced only when header auth is in use).
- `@requires_any_role("role1", ...)` – require at least one of the named roles (enforced only when header auth is in use).

Current role protections applied:

- Admin only: `POST /api/events`, `GET /api/events/status_summary`, `POST /api/users`
- Reviewer/uploader/admin: `GET /api/events/need_packets`, `GET /api/events/need_reupload`
- Reviewer/admin: `GET /api/events/for_review`

Frontend loads the current user via `GET /api/auth/me` and renders UI based on the returned flags.

Outstanding next steps:

- Confirm Apache is consistently sending `X-Remote-User` and decide on normalization (email vs. netid); ensure `users.login` values match.
- Add/seed required users (e.g., Satinder) with `login` filled and appropriate role flags.
- Review and refine per-endpoint role requirements; extend decorators where needed.
- Decide whether to require header auth in all environments or keep the permissive dev/Keycloak fallback.

### OpenAPI Documentation

Run `python scripts/generate_openapi.py` to generate `openapi.json` describing
the backend API. A GitHub action updates this file on each push.

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
docker-compose up -d mariadb
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