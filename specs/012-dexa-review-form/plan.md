# Implementation Plan: DEXA scan review form and on-site reviewer instructions

**Branch**: `012-dexa-review-form` | **Date**: 2026-10-14 (rev. 3) | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/012-dexa-review-form/spec.md`, plus rev. 2 direction from the user: each study gets its own `reviews` schema (in `flask_backend/models/studies/`), and the instructions are written in HTML.

## Summary

**Part A: DEXA review form.** Each study now defines its own `reviews` table
schema. This follows the legacy CakePHP approach and is possible because every
deployment has its own database. The per-study model scaffolding is switched
on to support this:

- `flask_backend/models.py` becomes the package `flask_backend/models/__init__.py`.
  This ends the shadowing that made `models/studies/` impossible to import.
- `models/studies/mci.py` holds the existing MI `Reviews` model, moved
  verbatim, along with its form handling and export.
- `models/studies/scans.py` holds the new scans `reviews` table (one row per
  review), a `review_regions` child table (one row per body-region entry), and
  the scans handler.
- A resolver keyed on `STUDY_TYPE` picks the module at call time.
- On the database side, `init/04-study-schema.sh` applies
  `init/studies/<STUDY_TYPE>.sql`.
- Existing `scans` databases get a one-off idempotent migration. It renames
  the old MI-shaped table rather than dropping it.
- The shared review endpoint, the event export, and the new review GET all
  delegate to the study module, so none of them contain study names.
- In the frontend, the form is chosen by the `review_form` value from
  `/api/config`. The MI form moves unchanged into its own component, and the
  new scans form sits beside it.

**Part B: Instructions.** Each study's reviewer instructions are a
self-contained, print-styled HTML file in `FILES_DIR`
(`instructions/<study>.html`), served by the existing `/files/` route and
linked in a new tab. `scans.html` is converted from the DEXA Word document.
`mci` gets no file yet, and its `.doc`/`.pdf` reviewer links are removed.

**Also:** a shared 409 guard against duplicate review submissions.

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**
**Storage**: MariaDB 10.11. **Per-study `reviews` schema**: the `mci` table is unchanged and moved to `init/studies/mci.sql`. The new `scans` tables, `reviews` and `review_regions` (InnoDB), are in `init/studies/scans.sql`. They are selected by the new `init/04-study-schema.sh`. Existing `scans` DBs run a one-off migration, `init/migrations/012-scans-reviews.sh`. Instructions live in `app/webroot/files/instructions/scans.html` (read-only `FILES_DIR`)
**Testing**: pytest (`flask_backend/tests/`, with the `admin_client` fixture, the `FakeSession` pattern, and `monkeypatch` for `STUDY_TYPE`). The frontend has no test runner, so it is covered by `npm run lint`, `npm run build`, and the quickstart manual script
**Target Platform**: Linux server, Docker Compose (mariadb, backend, web), desktop browsers
**Project Type**: Web application (Flask API + React SPA)
**Performance Goals**: The form reacts instantly. An 8-region submit is one transaction
**Constraints**: No study-name branching in shared modules (Principle IV). The `mci` `reviews` schema, data, and export output must stay byte-identical (Principle III). `FILES_DIR` is never written. No PHI is logged above DEBUG. Scores are exact (`DECIMAL`, sent as strings). `openapi.json` is regenerated in the same PR
**Scale/Scope**: One reviewer per event. Typically 1–4 regions, at least 8 supported, capped at 20. Hundreds to low thousands of events per deployment

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design (below).*

Checked against constitution **v1.5.1**, amended 2026-10-14 for this feature (Principle IV: study DDL in `init/studies/<study>.sql`, study-specific tables only in their own study's database, `reviews(id, event_id, reviewer_id)` invariant, dormancy marker).

| Principle / gate | Assessment | Status |
|---|---|---|
| **I. Single codebase** | No fork. Study variation lives in `models/studies/<study>.py`, `init/studies/<study>.sql`, and `frontend/src/studies/<study>/` form components chosen by a registry. These are components, not page forks: no copied pages or parallel routes | ✅ PASS |
| **II. Data isolation** | Per-study `reviews` schemas depend on, and reinforce, one database per deployment. No cross-study access | ✅ PASS |
| **III. Legacy compatibility** | MI `reviews` DDL, model columns, data, and export output are unchanged (moved, not altered). `scans` has no legacy data. The old MI-shaped table in existing `scans` databases is **renamed, not dropped**. The migration ships with this PR | ✅ PASS |
| **IV. Configuration over forks** | Uses exactly mechanisms 2 (`init/studies/<study>.sql` via `04-study-schema.sh`) and 3 (`models/studies/<study>.py` via a `STUDY_TYPE` factory, and study form components in `frontend/src/studies/<study>/` selected by `components/review/reviewForms.js` keyed on `review_form`). `scans` has no `event_derived_datas`, which v1.5.1 permits. Each database holds only its own study's tables. The dormant studies are moved and marked `-- DORMANT`, so there is one approach for all studies | ✅ PASS |
| **V. Workflow & role parity** | No states or roles change. Study-specific review fields are the allowed "extension". The shared invariant `reviews(id, event_id, reviewer_id)`, one row per reviewer per event, is kept in every study | ✅ PASS |
| **VI. Pre-release discovery** | Observed behavior is recorded before each change: the shadowing and dormant scaffolding (D4), unused `Events.reviews` (D1), the endpoint accepting duplicates (D5), MI-only export SQL (D9), and init running only at first boot (D10) | ✅ PASS |
| Security: authorization | New `GET .../review` declares `admin` or an assigned reviewer. POST and export keep their roles | ✅ PASS |
| Security: PHI logging / `FILES_DIR` | Region data is not logged above DEBUG. `FILES_DIR` is only read | ✅ PASS |
| Quality: schema changes | Migration plan is in quickstart.md. The script is idempotent and non-destructive | ✅ PASS |
| Quality: API contracts | `openapi.json` is regenerated (adds `review_form`, the new GET, and 409) | ✅ PASS (task) |
| Quality: testing | Covers resolver and fallback tests, the scans validation matrix, role tests on the new GET, the 409 guard, and MI export unchanged (golden comparison) | ✅ PASS (task) |
| Change review: studies affected | **All studies**: models package move, export and review endpoint delegation, 409 guard, Step 1 label, instructions links, and the new mariadb `STUDY_TYPE` env. **`scans` only**: scans schema and form. **Existing `scans` deployments need the migration** | ✅ noted for PR |

**Post-design re-check**: passes. The design adds no state, no fork, and no
new env flag. `STUDY_TYPE` is only passed through to mariadb. Naming
deviations are tracked below.

## Project Structure

### Documentation (this feature)

```text
specs/012-dexa-review-form/
├── plan.md  research.md  data-model.md  quickstart.md
├── contracts/review-api.md  contracts/review-ui.md
├── checklists/requirements.md
└── tasks.md            # /speckit.tasks (not created here)
```

### Source Code (repository root)

```text
docker-compose.yaml                       # mariadb.environment += STUDY_TYPE: ${STUDY_TYPE:-mci}
init/
├── 02-schema.sql                         # − reviews, − event_derived_datas DDL (moved to studies/mci.sql)
├── 02-schema-{vte,afib,cva,hf}.sql       # REMOVED — git mv'd into studies/ (below)
├── 04-study-schema.sh                    # NEW — applies studies/${STUDY_TYPE}.sql; missing or "-- DORMANT" → mci
├── studies/
│   ├── mci.sql                           # NEW — MI reviews + event_derived_datas, verbatim from 02-schema.sql
│   ├── scans.sql                         # NEW — scans reviews + review_regions
│   └── vte.sql afib.sql cva.sql hf.sql   # MOVED — still commented out; first line "-- DORMANT"
└── migrations/
    └── 012-scans-reviews.sh              # NEW — existing scans DBs: rename MI table, apply scans.sql
app/webroot/files/instructions/
└── scans.html                            # NEW — self-contained, print-styled DEXA instructions

flask_backend/
├── models.py  →  models/__init__.py      # git mv; − Reviews, − EventDerivedDatas, − unused Events.reviews/derived_data relationships
├── models/studies/
│   ├── __init__.py                       # NEW — get_study_module(), interface docstring, ReviewValidationError
│   ├── mci.py                            # NEW — MI Reviews + EventDerivedDatas (moved) + parse/add/get/export (moved from app.py, table_service.py)
│   ├── scans.py                          # NEW — scans Reviews, ReviewRegions + handlers
│   └── vte.py afib.py cva.py hf.py       # UNCHANGED (dormant; resolver falls back to mci)
├── app.py                                # /api/config += review_form; POST /review delegates + 409;
│                                         #   + GET /api/events/<id>/review; /api/events/export delegates
├── table_service.py                      # − MI export query (moved to studies/mci.py)
└── tests/
    ├── test_study_modules.py             # NEW — resolver, fallback, both modules importable together
    ├── test_scans_review.py               # NEW — validation matrix, rows/positions/NULLs, GET roles, export
    └── test_review_endpoint.py           # + 409 guard; MI path unchanged

frontend/src/
├── App.jsx                               # read review_form from /api/config; pass to EventReview
├── pages/EventReview.jsx                 # shell only; Step 1 label; renders resolved form
├── components/
│   ├── GuidanceLinks.jsx                 # + rel="noopener" on new-tab links
│   ├── reviewGuidance.js                 # scans → instructions/scans.html link; mci reviewer links removed
│   └── review/
│       └── reviewForms.js                # NEW — registry { mci: MciReviewForm, scans: ScansReviewForm }, default mci
└── studies/                              # constitution IV mechanism 3: study-specific *components* only
    ├── mci/MciReviewForm.jsx             # NEW — MI form moved verbatim from EventReview.jsx
    └── scans/ScansReviewForm.jsx         # NEW
                                          # (no page copies, no parallel routes, no isVtePage-style branching)

docs/template-setup-guide.md              # update "Study-Specific Components" + per-study schema sections to init/studies, dormancy marker
docs/legacy-repository-analysis.md        # update 02-schema-<study>.sql paths
.specify/memory/constitution.md           # DONE — amended to v1.5.0 (Principle IV: init/studies, study-specific tables, reviews invariant, dormancy), then v1.5.1 (only `reviews` mandatory)
openapi.json                              # regenerated
```

**Structure decision**: This is the existing web-app layout. Per-study
backend code goes in `flask_backend/models/studies/`, as the constitution
prescribes and the user expects. Per-study DDL goes in `init/studies/`. The
frontend uses a registry, not a study directory.

### Implementation notes for /speckit.tasks

1. **Refactor first, with no behavior change**:
   (a) `git mv flask_backend/models.py flask_backend/models/__init__.py`;
   (b) move MI `Reviews`, the body parsing, and the export into `studies/mci.py`
   behind `get_study_module()`;
   (c) move the MI form into `MciReviewForm.jsx`.
   The existing test suite must pass unchanged. Add a golden test showing MI
   export output is identical before and after. These steps come before any
   scans work.
2. **`flask_backend/__init__.py` / `SQLA_MODELS=models2`**: leave as is.
   `models2.py` is unused and keeps its own MI `Reviews` copy. Record this as
   a Principle VI follow-up (remove `models2.py`). It is not part of this
   feature.
3. **Migration script**: it runs inside the mariadb container. It uses
   `$MYSQL_ROOT_PASSWORD`/`$MYSQL_DATABASE`, checks
   `information_schema.COLUMNS` for `reviews.mci`, and applies
   `/docker-entrypoint-initdb.d/studies/scans.sql`. `init/` is already mounted
   there, so no copying is needed. Exit with a non-zero status on any SQL
   error.
4. **`04-study-schema.sh`** must run after `02-schema.sql` and before
   `05-add-indexes.sql`. `05` does not touch the study tables (verified). It
   logs which study file it applied, and logs a warning whenever it falls
   back to `mci`.
   When moving the dormant files, use `git mv` and add `-- DORMANT` as the
   first line, leaving the commented bodies untouched.
5. Both copies of the validation constants (in `scans.py` and
   `ScansReviewForm.jsx`) must match data-model.md exactly, and each must
   carry a keep-in-sync comment.
6. Order: refactor (note 1) → schema, migration, and scans module with tests
   → Scans form (User Story 1) → edit/remove (User Story 2) → instructions
   HTML and links (User Story 3) → GET and export → docs and openapi.

## Complexity Tracking

| Deviation | Why needed | Simpler / prescribed alternative rejected because |
|---|---|---|
| Study modules each declare their own `DeclarativeBase` instead of sharing `models.Base` (as the dormant `..base` scaffolding assumed) | Both `mci` and `scans` define a `reviews` table. One shared metadata cannot hold both, and the test suite imports both in one process | Importing only one module per process would break tests that switch `STUDY_TYPE`. The cost is that study models use explicit joins and have no ORM relationships to `Events`/`Users`. Nothing used those relationships (D1, D10) |

The rev. 2 deviation (`init/studies/` instead of `init/02-schema-<study>.sql`)
is resolved. Constitution v1.5.0 now prescribes `init/studies/`, and the
dormant study files move there as well.
