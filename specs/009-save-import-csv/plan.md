# Implementation Plan: Archive Bulk-Import CSV Files

**Branch**: `009-save-import-csv` | **Date**: 2026-08-13 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/009-save-import-csv/spec.md`

## Summary

`POST /api/events/bulk` reads the submitted CSV into memory, parses it, creates
events, and lets the bytes fall out of scope. Non-persistence is not an
oversight — a test, `test_bulk_csv_upload_does_not_persist_file`, asserts it
today. This feature reverses that decision: the submitted file is written to
disk before it is parsed, and the outcome of parsing is written beside it.

Each submission produces two files in a new `imports/` subdirectory of the
existing writable uploads volume: `<id>.csv`, the verbatim bytes, and
`<id>.json`, a manifest holding submitter, timestamp, original file name,
counts, and the skipped-row reasons that today live only in a toast. The id is
a sortable UTC timestamp plus a random suffix, so concurrent submissions of
identically named files cannot collide, and the stored name carries no patient
identifier. Three admin-only read endpoints expose the list, one record, and
the archived file; one new admin page renders them.

Archiving happens before parsing and is fail-closed: if the file cannot be
written, the request is refused and no events are created (research D3).
Because the write precedes the decode, every submission the system tries to
process is retained — including ones that are not valid UTF-8, parse to
nothing, or blow up mid-import. The single exception is a submission over the
size cap, whose contents are refused to keep storage bounded; that attempt
still gets a manifest with `outcome: refused`, so it is recorded rather than
silently dropped (research D5). The
manifest is written after the import, and a `.csv` without a `.json` is
rendered as an incomplete record rather than treated as an error, because the
underlying tables are MyISAM and cannot be rolled back to match (research D8).

No schema change, no migration, no new dependency. The archive lives in the
volume study deployments already mount, so no deployment change beyond two
new optional environment variables.

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**; the archive uses `csv`, `json`, `os`, `uuid`, and `datetime` from the standard library
**Storage**: Filesystem only — `<DOWNLOADS_DIR>/imports/`, on the existing `cnics-downloads` named volume. **No schema change, no migration**; the import record is a JSON sidecar, not a table (research D2)
**Testing**: pytest (`flask_backend/tests/`, 11 modules). The `admin_client` fixture in `conftest.py` covers admin paths; the `FakeUser` pattern in `test_auth_header_and_roles.py` covers the non-admin refusal cases. **No frontend test infrastructure exists** — the new page is verified manually per quickstart.md
**Target Platform**: Linux server, Docker Compose stack
**Project Type**: Web application — Flask backend + React SPA frontend
**Performance Goals**: Two small file writes per bulk import (a few KB each); import wall-clock unchanged within noise (SC-005 allows 10%). The list endpoint scans one directory and reads N manifests — at the SC-007 target of 500 imports this is ~500 stat + read calls of a few hundred bytes each, served from page cache
**Constraints**: Archiving is fail-closed, so a full or read-only uploads volume refuses bulk imports (spec Assumptions, flagged for confirmation). The size cap must be per-endpoint, not Flask's global `MAX_CONTENT_LENGTH`, which would also cap event-packet uploads (research D5). Import-id inputs must be validated against a strict pattern before any path join (research D6). `openapi.json` must be regenerated in the same PR
**Scale/Scope**: 1 new backend module, 1 modified backend endpoint, 3 new backend endpoints, 2 test modules (1 new, 1 amended), 1 new frontend page, 3 modified frontend files, 2 config files, 1 regenerated contract. Study-agnostic — no `STUDY_TYPE` branch, no new flag in `study_config.py`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Evaluated against constitution v1.4.0.

| Principle | Status | Assessment |
|---|---|---|
| **I. Single Codebase, Many Studies** | ✅ PASS | All changes land in shared modules and shared pages. The VTE fork (`frontend/src/studies/vte/EventAddMany.jsx`) posts to the same `/api/events/bulk` endpoint and therefore inherits archiving with no code change; it is deliberately **not** given a copy of the new page (research D9) |
| **II. Study Data Isolation** | ✅ PASS | The archive lives inside the deployment's own uploads volume, which is already per-study. No cross-study path, no shared directory, no new DB grant. Isolation is inherited from the existing mount rather than newly asserted |
| **III. Backwards Compatibility With Legacy Data** | ✅ PASS | No schema change, no migration, no change to `events`, `criterias`, or the event lifecycle. The `/api/events/bulk` response is extended additively with `import_id`; existing consumers reading `imported` and `errors` are unaffected. Existing deployments with no `imports/` directory get one created on first use |
| **IV. Configuration Over Code Forks** | ✅ PASS | Two new optional environment variables (`IMPORT_ARCHIVE_DIR`, `MAX_IMPORT_CSV_BYTES`), both with working defaults derived from existing config, following the established `UPLOAD_DIR` / `DOWNLOADS_DIR` precedent. No `STUDY_TYPE` branch — nothing about this feature varies by study |
| **V. Workflow and Role Parity** | ✅ PASS | No state added, removed, renamed, or bypassed. Events created by a bulk import still enter at `created` exactly as today. No new role — the archive is scoped to the `admin` role that already owns bulk import |
| **VI. Pre-Release Iteration and Discovery** | ✅ PASS | Current behavior is recorded before modification in research.md (D1), including that non-persistence is asserted by an existing test and that the endpoint's "single transaction" docstring is false on MyISAM (D8). The obsolete test is rewritten to assert the new intent rather than deleted or left to pass vacuously (research D7) |

**Security & Data Governance**

| Rule | Status | Assessment |
|---|---|---|
| **PHI handling** | ✅ PASS | Archived CSVs contain site patient identifiers and event dates and are treated as PHI. No file content, original file name, or skipped-row text is logged at INFO or above; failures log the import id and an error class only. No new secret is introduced |
| **File storage** | ✅ PASS | Writes go to `<DOWNLOADS_DIR>/imports/`, never to `FILES_DIR`, which is read-only by rule and mounted `:ro` in `docker-compose.yaml`. The archive is a subdirectory rather than the `DOWNLOADS_DIR` root so archived files can never be matched by `events_download`'s candidate scan, nor event packets by the import list (research D4) |
| **Authorization** | ✅ PASS | All three new endpoints declare `@requires_auth` + `@requires_roles('admin')` at definition time, matching `events_bulk`. Nothing is open by default. The frontend route is wrapped in `ProtectedRoute requiredRoles={['admin']}`, but the backend decorator is the enforcement point |
| **Network exposure** | ✅ PASS | No new service, port, or binding. Archived files are reachable only through the authenticated API, never via the static `/files/<path>` route |
| **Data isolation audits** | ✅ PASS | No new DB user, schema, or origin |

**Development Workflow & Quality Gates**

| Gate | Status | Assessment |
|---|---|---|
| **Change review** | ✅ PASS | Affects all studies (shared code). To be stated in the PR description |
| **Schema changes** | ✅ N/A | None — no migration plan required (research D2) |
| **API contracts** | ⚠ ACTION | Three new endpoints and one changed response. `openapi.json` must be regenerated via `python -m flask_backend.generate_openapi` and land in the same PR. Tracked as a task |
| **Testing discipline** | ✅ PASS | New endpoints get pytest coverage including the non-admin refusal path; the archiving behavior of `events_bulk` gets coverage for success, partial, rejected, collision, oversize, and unwritable-archive cases |
| **Feature-flag discipline** | ✅ N/A | No workflow flag. `MAX_IMPORT_CSV_BYTES` is a limit, not a behavior switch; its default is the conservative documented value |
| **Deployment parity** | ✅ PASS | Works with the standard `docker compose up` stack; the archive directory is created on demand inside the existing volume |

**Result**: PASS. No violations, so Complexity Tracking is omitted. One action item (regenerate `openapi.json`) is carried into tasks.

**Post-design re-check** (after Phase 1): still PASS, unchanged. The design
added no table, no role, no study branch, and no service. Two points the design
work strengthened rather than weakened: the archive subdirectory keeps the
import namespace provably disjoint from the packet namespace (File storage),
and the strict id whitelist plus containment assertion closes the path-traversal
surface the new read endpoints introduce (research D6). One pre-existing gap was
found and deliberately left alone — `events_download` has `@requires_auth` but no
role decorator (research D6, quickstart Notes); fixing it belongs in its own
change, not this diff.

## Project Structure

### Documentation (this feature)

```text
specs/009-save-import-csv/
├── plan.md              # This file
├── research.md          # Phase 0 output — 9 decisions
├── data-model.md        # Phase 1 output — archive layout and record shape
├── quickstart.md        # Phase 1 output — manual verification walkthrough
├── contracts/
│   └── imports-api.yaml # Phase 1 output — OpenAPI fragment for the 4 endpoints
├── checklists/
│   └── requirements.md  # Spec quality checklist (/speckit.specify output)
└── tasks.md             # Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code (repository root)

```text
flask_backend/
├── import_archive.py            # NEW — archive read/write, id generation and validation
├── app.py                       # MODIFIED — archive in events_bulk; 3 new admin endpoints
└── tests/
    ├── test_import_archive.py   # NEW — archiving, listing, access control, path safety
    └── test_app.py              # MODIFIED — non-persistence test rewritten

frontend/src/
├── pages/
│   ├── EventImports.jsx         # NEW — admin list of past imports + detail + download
│   ├── Admin.jsx                # MODIFIED — link to the import list
│   └── Home.jsx                 # MODIFIED — link in the admin section
└── App.jsx                      # MODIFIED — admin-protected /events/imports route

docker-compose.yaml              # MODIFIED — pass the two new env vars to `backend`
default.env                      # MODIFIED — document the two new env vars
openapi.json                     # REGENERATED — contract for the new endpoints
```

**Structure Decision**: The existing Flask-backend + React-SPA split is kept
unchanged. The one structural addition is `flask_backend/import_archive.py`:
the archive's filename grammar, id validation, manifest serialization, and
directory scan are pure functions over a directory path, and putting them in a
module keeps them unit-testable without the Flask app while holding the line on
`app.py`, which is already ~1,750 lines. The endpoints in `app.py` stay thin —
auth decorators, request parsing, and response shaping only.

## Phase 2 Notes (for `/speckit.tasks`)

Work decomposes along the spec's three priorities, and each is independently
shippable:

- **P1 — retention**: `import_archive.py` write path + `events_bulk` change +
  its tests + `default.env` / `docker-compose.yaml`. Delivers FR-001 to FR-008
  with no interface at all.
- **P2 — outcome record**: manifest write + manifest tests. Delivers FR-003.
  Layered on P1 without changing what P1 wrote.
- **P3 — review interface**: three read endpoints, `openapi.json`, the new
  page, the route, and the two links. Delivers FR-009 to FR-011.

FR-012 (no automatic deletion) is satisfied by omission and needs no task; it
is verified by inspection in the quickstart.
