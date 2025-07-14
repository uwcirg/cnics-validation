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

The API exposes `/api/tables/<name>` which returns up to 100 rows from the specified table.

If the environment variable `KEYCLOAK_REALM` is set, requests are validated
against a Keycloak server. Configure `KEYCLOAK_URL`, `KEYCLOAK_CLIENT_ID` and
`KEYCLOAK_CLIENT_SECRET` accordingly.
