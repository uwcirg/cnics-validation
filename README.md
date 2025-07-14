https://cnics.cirg.washington.edu/mci

SRC:

        git clone git@gitlab.cirg.washington.edu:cnics/mci.git

Target:

        /srv/www/$FQDN/htdocs/mci
        (legacy)

    For container-based deployments see `docs/docker-compose.md` which describes
    how to run the application with Docker Compose and mount persistent volumes.

Deploy Notes:

        chown -R cnics:www-data ~/mci
        chmod 2770 ~/mci/app/chartUploads
        chmod 2775 ~/mci/app/tmp/[cache|logs|sessions|test]

Puppet Hooks:

        app/config/database.php

TODO:

        Verify that .htaccess has no explicitly allowed users ( what is the default ? )


DOCKER DATABASE:

        cp .env.example .env
        docker-compose up -d mariadb

DATABASE INITIALIZATION:

        # Core schema
        mysql -u$DB_USER -p$DB_PASSWORD $DB_NAME < app/config/sql/sessions.sql
        # Additional schemas are under app/config/sql/
        # Test dataset
        mysql $DB_NAME < app/tests/cnics-mci_test.test_event_derived_datas.schema.sql
## Container Setup

This repository includes a lightweight Docker configuration based on the setup used in the `asbi-screening-app` project. A basic PHP/Apache image is provided along with a docker-compose file for local development.

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
containers are started. The template defines the following variables:

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
These PDFs are not stored in the repository. Run `python generate_pdfs.py` from
the project root to create them locally. The script converts modern `.docx`
files to PDF and generates placeholder PDFs for older `.doc` files.
The script requires the `python-docx` and `reportlab` packages which can be
installed with `pip install python-docx reportlab`.



## Backend API

The Flask backend under `flask_backend/` exposes REST endpoints that the React frontend fetches. Docker Compose runs a `backend` service alongside the `web` frontend service. The frontend reads the API base URL from the `VITE_API_URL` environment variable.

See [docs/separation_of_duties.md](docs/separation_of_duties.md) for details on the responsibilities of each component.
