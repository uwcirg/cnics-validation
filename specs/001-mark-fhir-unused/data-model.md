# Phase 1 Data Model: Mark FHIR Server References as Not Currently Used

**Feature**: 001-mark-fhir-unused
**Date**: 2026-04-14

This feature does not add, remove, or modify any database tables,
SQLAlchemy models, or persisted entities. The only "entity" it touches is
a single environment variable that is not read by any runtime code.

## Entities

### `FHIR_SERVER` (environment variable)

- **Kind**: dotenv / shell environment variable, string type, optional.
- **Current producer surfaces**: `.env.example`, `default.env`,
  `.env.backup`, `README.md` (documentation), `.github/workflows/tests.yml`
  (CI injection), `docker-entrypoint.sh` (container pass-through).
- **Current consumer surfaces**: **none**. Verified by case-insensitive
  search across `flask_backend/` and `frontend/`.
- **Validation rules**:
  - Optional: no code path requires a value.
  - Unvalidated: no format, URL, or reachability check is performed.
- **State transitions**: none. The variable is read-only configuration.
- **Post-feature state**: the variable remains declared nowhere by
  default (templates comment it out), is tolerated if a deployment sets
  it, and is documented everywhere as "not currently used — retained
  for backward compatibility; no runtime component reads this value."

## Database model impact

None. No tables, columns, migrations, or views are affected.

## Frontend model impact

None. No React components, Redux/Zustand slices, or API clients are
affected.

## Contracts impact

See `contracts/fhir-env-var.md` for the single contract note describing
the variable's "inert pass-through" status.
