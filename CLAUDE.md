# cnics-validation Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-08-17

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
- 009-save-import-csv: Added Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**; the archive uses `csv`, `json`, `os`, `uuid`, and `datetime` from the standard library
- 008-upload-event-identifiers: Added Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend) + Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**


<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
