# cnics-validation Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-10-23

## Active Technologies
- N/A — documentation, Markdown, and inline comments + None added. `python-keycloak` is retained (already (002-constitution-sync)
- N/A — no schema changes, no data changes. (002-constitution-sync)
- Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite (003-scans-study)
- MariaDB 10.11 — shared schema `init/02-schema.sql`; **no schema change in this feature** (003-scans-study)
- Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy (backend); React 19, react-router-dom 6, Vite (frontend) (004-reviewer-assignment)
- MariaDB 10.11, shared schema (`init/`). **No schema change in this feature.** (004-reviewer-assignment)
- JavaScript / JSX, React 19 + react-router-dom 6, Vite 7 (build/dev); no new dependency (005-banner-restyle)
- N/A — no persisted data; the study type comes from runtime config (005-banner-restyle)
- N/A — no persisted data; section visibility is derived from runtime workflow config (006-study-aware-event-actions)
- JavaScript / JSX, React 19 (frontend); no backend change + react-router-dom 6, Vite 7 (build/dev) — no new dependency (007-study-aware-review-sections)
- N/A — content is static, derived from runtime `STUDY_TYPE` config; no persisted data (007-study-aware-review-sections)
- Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency** (008-upload-event-identifiers)
- MariaDB 10.11, shared schema under `init/`. **No schema change in this feature** — all four values already exist and are populated (008-upload-event-identifiers)
- Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**; the archive uses `csv`, `json`, `os`, `uuid`, and `datetime` from the standard library (009-save-import-csv)
- Filesystem only — `<DOWNLOADS_DIR>/imports/`, on the existing `cnics-downloads` named volume. **No schema change, no migration**; the import record is a JSON sidecar, not a table (research D2) (009-save-import-csv)
- Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy (backend); React 19, react-router-dom 6, Vite 7 (frontend) — **no new dependency** (010-study-aware-packet-heading)
- MariaDB 10.11, shared schema under `init/`. **No schema change** — every value here is runtime configuration or derived presentation state (010-study-aware-packet-heading)
- Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency** (011-no-packet-submit)
- MariaDB 10.11, shared schema under `init/`. **No schema change, no migration** — all eight fields exist and are populated in legacy data (011-no-packet-submit)
- Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency** (012-dexa-review-form)
- MariaDB 10.11. **Per-study `reviews` schema**: the `mci` table is unchanged and moved to `init/studies/mci.sql`. The new `scans` tables, `reviews` and `review_regions` (InnoDB), are in `init/studies/scans.sql`. They are selected by the new `init/04-study-schema.sh`. Existing `scans` DBs run a one-off migration, `init/migrations/012-scans-reviews.sh`. Instructions live in `app/webroot/files/instructions/scans.html` (read-only `FILES_DIR`) (012-dexa-review-form)
- Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7. **No new dependency.** (013-dischargesummaries-study)
- MariaDB 10.11. There is a new per-study schema file, `init/studies/dischargesummaries.sql`, holding `reviews` with the shared columns only in Phase A. **No change to shared tables. No migration for existing `mci`/`scans` databases.** Files go on the existing per-deployment downloads volume. (013-dischargesummaries-study)

- N/A — no code in a programming language is being + N/A. The repository's runtime stack (Flask + (001-mark-fhir-unused)

## Project Structure

```text
backend/
frontend/
tests/
```

## Commands

# Add commands for N/A — no code in a programming language is being

## Code Style

N/A — no code in a programming language is being: Follow standard conventions

## Recent Changes
- 013-dischargesummaries-study: Added Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7. **No new dependency.**
- 012-dexa-review-form: Added Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**
- 011-no-packet-submit: Added Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**


<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
