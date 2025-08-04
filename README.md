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
- `/api/events/need_packets` – events awaiting packet uploads.
- `/api/events/for_review` – events with packets ready for review.
- `/api/events/need_reupload` – events requiring packet re-upload.
- `/api/events/status_summary` – counts of events grouped by status.

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
