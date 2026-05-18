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

**Keycloak is deferred — not part of the first release.** Keycloak
integration code remains in this package (gated on `KEYCLOAK_REALM`
being set, default off) but is **not supported** for any first-release
study deployment. The `KEYCLOAK_URL`, `KEYCLOAK_CLIENT_ID`, and
`KEYCLOAK_CLIENT_SECRET` environment variables are retained so local
experimentation continues to work; they MUST NOT be set in a
first-release deployment. Enabling Keycloak in any study deployment
requires a prior amendment to
[`.specify/memory/constitution.md`](../.specify/memory/constitution.md)
under **Security & Data Governance → Authentication (future
releases)**. The first-release authentication mechanism is HTTP Basic
Auth at the Apache edge with `AuthBasicProvider ldap` (per the
repository-root [`.htaccess`](../.htaccess)), with the authenticated
identity forwarded to this backend as the `X-Remote-User` header. See
the root [`README.md`](../README.md) Authentication and Authorization
section for the full contract.

When the database container initializes it runs `init/06-create-patients-view.sh`,
which creates the `patients_view` view as a UNION over the locally-owned
`uw_patients2` table and a FEDERATED proxy of the upstream `cnics_data.Patient`
table. The application reads patient identity via that view; it never writes to
either side (both halves are treated as read-only).
