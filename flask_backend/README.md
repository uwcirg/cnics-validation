## Logging

The backend emits logs to stdout. You can toggle between plain text and JSON formats via environment variables.

- LOG_FORMAT: JSON (default) or TEXT
- LOG_LEVEL: INFO (default), DEBUG, WARNING, ERROR
- APP_NAME: Name to include in log records (default: cnics-validation-backend)

When LOG_FORMAT=JSON, logs conform to a consistent schema and can be shipped to a log server:

- ts: ISO8601 timestamp in UTC
- level: Log level name
- logger: Logger name
- msg: Message string
- request_id: X-Request-ID or generated UUID
- method: HTTP method
- path: Request path (with query)
- status: HTTP status code (on access logs)
- duration_ms: Request duration in milliseconds (on access logs)
- remote_ip: Client IP (respects X-Forwarded-For)
- user_login: Authenticated username (if available)
- site: User site (if available)
- module, func, line: Call site information
- app: Application name

Access logs are emitted on every request in after_request.
# Flask Backend

This directory contains a minimal Flask implementation of the API used by the React frontend.

## Setup

Install dependencies:

```bash
pip install -r requirements.txt
```

Run the server:

```bash
python -m flask_backend.app
```

The API exposes `/api/tables/<name>` which returns all rows from the specified table. Results can be limited using optional `limit` and `offset` query parameters.

If the environment variable `KEYCLOAK_REALM` is set, requests are validated
against a Keycloak server. Configure `KEYCLOAK_URL`, `KEYCLOAK_CLIENT_ID` and
`KEYCLOAK_CLIENT_SECRET` accordingly.

The repo includes a sample CNICS dump `cnics.sql` for reference. When the
database container initializes it runs `init/04-create-patients.sql`, which
creates and populates the `patients` table from `uw_patients2` if it is missing.
