https://cnics.cirg.washington.edu/mci

SRC:

        git clone git@gitlab.cirg.washington.edu:cnics/mci.git

DOCKER DATABASE:

        cp .env.example .env
        docker-compose up -d mariadb

## Container Setup

This repository includes a lightweight Docker configuration based on the setup used in the `asbi-screening-app` project. The compose file builds the React frontend and Flask backend for local development.

### Build and Run

1. Copy `.env.example` to `.env` and edit if necessary. `VITE_API_URL` should
   point at the backend API (defaults to `http://localhost:3001`).
2. Build the Docker images:

   ```bash
   docker-compose build
   ```

3. Start the stack:

   ```bash
   docker-compose up
   ```

   The frontend will be served on <http://localhost:3000/> and the backend API
   on <http://localhost:3001/>.

### Environment Variables

Runtime configuration is provided via a `.env` file that you create by
copying `.env.example`. Docker Compose automatically loads this file when the
services are built or started. The template defines the following variables:

- `DB_ROOT_PASSWORD` – password for the MariaDB root user.
- `DB_NAME` – name of the application's database.
- `DB_USER` – database user for the application.
- `DB_PASSWORD` – password for `DB_USER`.
- `VITE_API_URL` – base URL of the backend API consumed by the React frontend.
- `FHIR_SERVER` – URL of the FHIR server used by the application.

Override these values in your copied `.env` file as needed.

## Local Development

See [docs/development.md](docs/development.md) for instructions on running the application with Docker.

## Generating PDF copies of instruction files

Some pages link to `.pdf` versions of the documents under `app/webroot/files/`.
If a requested PDF does not exist the Flask backend will generate it on demand
using `python-docx` and `reportlab`. You can also pre-create the PDFs locally by
running `python generate_pdfs.py` from the project root.



## Backend API

The Flask backend under `flask_backend/` exposes REST endpoints that the React frontend fetches. Docker Compose runs a `backend` service alongside the `web` frontend service. The frontend reads the API base URL from the `VITE_API_URL` environment variable.

See [docs/separation_of_duties.md](docs/separation_of_duties.md) for details on the responsibilities of each component.
