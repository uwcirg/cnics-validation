# Tasks: DEXA scan review form and on-site reviewer instructions

**Input**: Design documents from `/specs/012-dexa-review-form/`
**Prerequisites**: plan.md (rev. 4), spec.md (incl. Session 2026-10-16), research.md (D1–D19), data-model.md, contracts/review-api.md, contracts/review-ui.md, quickstart.md
**Constitution**: v1.5.1, already amended on this branch. Principle IV now covers:
- study DDL lives in `init/studies/<study>.sql`;
- a database holds only its own study's tables;
- only `reviews` is mandatory per study;
- the `reviews(id, event_id, reviewer_id)` invariant;
- the `-- DORMANT` marker;
- study components go in `frontend/src/studies/<study>/`.

**Naming**: Files and identifiers specific to the `scans` study use the string `scans`, never `dexa`. "DEXA" appears only in user-facing text.

**Tests**: Included. The plan's Constitution Check commits to pytest coverage of new endpoints and role decorators (constitution "Testing discipline"). The frontend has no test runner, so it is verified with `npm run lint`, `npm run build`, and the quickstart manual script.

**Environment note**: The developer laptop has no running stack. Tasks are verified locally with pytest, lint, and build. The operator runs the migration and the manual checks on the server, following quickstart.md.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: US1 = record DEXA regions (P1), US2 = correct entries before submit (P2), US3 = on-site instructions (P2)

## Path Conventions

Backend `flask_backend/`, frontend `frontend/src/`, DB init `init/`, static documents `app/webroot/files/`.

---

## Phase 1: Setup (baseline capture)

**Purpose**: Record current behavior before the refactor (constitution Principle VI).

- [X] T001 Run `pytest flask_backend/tests` on the unmodified branch and save the pass/fail summary to `specs/012-dexa-review-form/baseline-tests.txt`. Every later refactor task must keep this set passing.
- [X] T002 [P] Add a golden test for the MI export in `flask_backend/tests/test_mci_export_golden.py`.
  - Patch `table_service.get_events_export_rows` to return two fixed sample row dicts covering every key `events_export` reads (`flask_backend/app.py` ~L1030), with `STUDY_TYPE` unset.
  - Call `GET /api/events/export` through `admin_client` and store the exact CSV bytes as an inline expected string.
  - Copy the current SQL text of `table_service.get_events_export_rows` (~L582) into the test as the constant `EXPECTED_EXPORT_SQL`, for T010's SQL-unchanged check.
  - The test must pass **before** any refactor.

---

## Phase 2: Foundational (blocking prerequisites; no behavior change)

**Purpose**:
- Bring per-study schema, models, and frontend components online as the constitution prescribes.
- Move the MI code into its study module.
- Expose `review_form`.

After this phase an `mci` deployment must behave exactly as before. **No user-story work can start until this phase is complete.**

### Database: per-study schema (research D10)

- [X] T003 Create `init/studies/mci.sql` holding the MI `event_derived_datas` and `reviews` DDL.
  - Cut both blocks verbatim (each `DROP TABLE IF EXISTS` … `SET character_set_client = @saved_cs_client` block) out of `init/02-schema.sql`. They start at the `-- Table structure for table \`event_derived_datas\`` (~L54) and `-- Table structure for table \`reviews\`` (~L150) comments.
  - Add a header comment naming the study and pointing to constitution Principle IV.
  - Leave every other table in `init/02-schema.sql` untouched.
- [X] T004 [P] Move the dormant per-study schema files.
  - Run `git mv init/02-schema-vte.sql init/studies/vte.sql`, and the same for `afib`, `cva`, and `hf`.
  - In each moved file, insert `-- DORMANT` as a new first line.
  - Then add this comment line: `-- Not applied: 04-study-schema.sh falls back to mci.sql while this marker is present. Remove this marker and the /* */ wrapping to activate.`
  - Do not change the commented bodies.
- [X] T005 [P] Create `init/04-study-schema.sh`, modelled on the style and env handling of `init/06-create-patients-view.sh`.
  - Read `STUDY_TYPE`, trimmed and lowercased, defaulting to `mci`.
  - Set `file=/docker-entrypoint-initdb.d/studies/$STUDY_TYPE.sql`. If that file is missing, or its first line is exactly `-- DORMANT`, echo a warning and use `studies/mci.sql` instead.
  - Apply it with `mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < "$file"`.
  - Echo which file was applied. Use `set -eu`, and exit non-zero on a mysql error.
  - Make it executable with `git update-index --chmod=+x`, then confirm `git ls-files -s init/04-study-schema.sh` shows mode `100755`. A non-executable init script is *sourced* by the entrypoint, so `set -eu` would leak into it.
- [X] T006 [P] Add `STUDY_TYPE: ${STUDY_TYPE:-mci}` to the `mariadb` service `environment` in `docker-compose.yaml`. Add a comment saying it is read by `init/04-study-schema.sh` so that only this study's tables are created (constitution Principle IV).

### Backend: models package + study modules (research D4)

- [X] T007 Convert the models module into a package.
  - Run `git mv flask_backend/models.py flask_backend/models/__init__.py`.
  - In the moved file, delete the `Reviews` and `EventDerivedDatas` classes, and the `Events.reviews` and `Events.derived_data` relationships. Nothing reads them (research D1/D10).
  - Keep `Base`, `get_engine`, `get_session`, and all other models unchanged.
  - Confirm these still resolve: `from flask_backend import models`, `models.get_session`, and `from flask_backend.models import get_session, Users` (used by `create_test_users.py`).
- [X] T008 Create `flask_backend/models/studies/__init__.py`. It contains:
  - `class ReviewValidationError(Exception)`, carrying `message` and an optional `fields` list of `{index, field, message}`.
  - `get_study_module(study_type=None)`:
    - default to `study_config.get_study_type()`, trimmed and lowercased;
    - load the module with `importlib.import_module(f"flask_backend.models.studies.{name}")`;
    - on `ModuleNotFoundError`, or when the module has no `REVIEW_FORM`, log one WARNING per study name and return the `mci` module.
  - A module docstring documenting the study-module interface from data-model.md (`Reviews`, `REVIEW_FORM`, `parse_review`, `add_review`, `get_reviews`, `export_csv`, `check_schema`) and the `-- DORMANT` / no-`REVIEW_FORM` dormancy rule.
  - Leave `vte.py`, `afib.py`, `cva.py`, and `hf.py` untouched.
- [X] T009 Create `flask_backend/models/studies/mci.py`.
  - Declare `class MciBase(DeclarativeBase)`.
  - Move the `Reviews` and `EventDerivedDatas` classes verbatim from the old `models.py`, rebased onto `MciBase`. Remove their `relationship(...)` lines and `ForeignKey(...)` arguments, and keep the columns, types, and comments.
  - Set `REVIEW_FORM = "mci"`.
  - **Move** `_coerce_flag` here from `flask_backend/app.py` (~L174). Its only callers are the review code (app.py ~L2492–2496).
  - `parse_review(data)`: move the body checks from `events_review` (app.py ~L2403–2510): `mci` required, `_REVIEW_FLAG_FIELDS`, and `_REVIEW_TEXT_FIELDS`. It returns the kwargs dict and raises `ReviewValidationError("mci is required")`.
  - `add_review(session, event_id, reviewer_id, parsed)` calls `session.add(Reviews(event_id=…, reviewer_id=…, **parsed))`.
  - `get_reviews(session, event_id)` returns a list of column dicts for the event's `Reviews` rows.
  - `check_schema(session)` returns `None`.
  - `_EXPORT_SQL`: a module constant holding the SQL text of `table_service.get_events_export_rows` (~L582), copied verbatim.
  - `_export_rows(session)` executes `_EXPORT_SQL` and returns a list of dicts.
  - `export_csv(session, writer)`: move the headings list and row loop from `events_export` (app.py ~L1030), and read rows through `_export_rows`.
- [X] T010 Rewire `flask_backend/app.py` and `flask_backend/table_service.py` to delegate to the study module, with no behavior change.
  - `events_review` resolves `study = get_study_module()` and calls `study.parse_review(data)`, mapping `ReviewValidationError` to a 400 with `error` and an optional `fields`. It then calls `study.add_review(...)`. Slot resolution and lifecycle advancement stay in the endpoint.
  - `events_export` writes through `get_study_module().export_csv(session, writer)`.
  - Remove the moved constants and code from `app.py`, and remove `get_events_export_rows` from `table_service.py`.
  - In `test_mci_export_golden.py`, point the mock at `flask_backend.models.studies.mci._export_rows`.
  - In the same file, add `test_mci_export_sql_unchanged`. It asserts that `mci._EXPORT_SQL`, whitespace-normalised, equals the T002 constant `EXPECTED_EXPORT_SQL`, also whitespace-normalised.
  - Run the T001 baseline and T002: both must pass unchanged.
- [X] T011 [P] Add `review_form` to `GET /api/config` in `flask_backend/app.py` (`get_config` ~L754).
  - Add `'review_form': get_study_module().REVIEW_FORM` next to `'study_title'`, and add the field to the docstring schema.
  - Update the exact-key assertions in `flask_backend/tests/test_config_endpoint.py` (~L98).
  - Add cases: unset → `"mci"`, `vte` → `"mci"`, and `scans` → `"scans"`. Mark the `scans` case xfail until T017 exists.
- [X] T012 [P] Add `flask_backend/tests/test_study_modules.py`. It covers:
  - `get_study_module()` returns `mci` for unset, `mci`, `vte` (dormant), and `nonexistent`, and warns for the last two.
  - `mci` and `scans` import together without a table-redefinition error. Add this case once T017 exists.
  - `flask_backend.models` is a package (`hasattr(models, '__path__')`).

### Frontend: study components + registry (research D6; constitution IV mechanism 3)

- [X] T013 Move the MI form out of `frontend/src/pages/EventReview.jsx` into `frontend/src/studies/mci/MciReviewForm.jsx`, verbatim.
  - The new component takes `{ eventId, apiUrl }`.
  - It contains all the MI state, option lists, the `show` memo, `handleSubmit`, and the JSX from `<h2>Step 2: Enter Decision</h2>` through the end of the `<form>` and the submit message.
  - Keep element ids, classes, and behavior identical.
- [X] T014 [P] Create `frontend/src/components/review/reviewForms.js` exporting `resolveReviewForm(reviewForm)`.
  - Import `MciReviewForm` from `../../studies/mci/MciReviewForm` and `ScansReviewForm` from `../../studies/scans/ScansReviewForm`.
  - The map is `{ mci: MciReviewForm, scans: ScansReviewForm }`, falling back to `MciReviewForm`.
  - Add a doc comment covering two points:
    - the key comes from `GET /api/config` `review_form`, so the backend and frontend agree (research D6);
    - `studies/<study>/` holds study *components*, not page copies (not the retired `studies/vte` pattern).
  - Create a placeholder `frontend/src/studies/scans/ScansReviewForm.jsx` returning `null`, so the build passes before US1.
- [X] T015 Wire the registry into the page.
  - In `frontend/src/App.jsx`, add `reviewForm` state (initially `''`), set it from `json.data.review_form` in `fetchConfig`, and pass `reviewForm={reviewForm}` to `<EventReview>` (~L306).
  - In `frontend/src/pages/EventReview.jsx`, keep the shell: instructions box, heading, date, and Step 1. Render `const Form = resolveReviewForm(reviewForm)` as `<Form eventId={eventId} apiUrl={apiUrl} />` **only when `configResolved`**, so the MI form never flashes on `scans`.
  - Change the Step 1 link text from "📥 Download Charts" to `Download charts for {headingText}` (research D11), keeping its styling.
  - Run `npm run lint && npm run build` in `frontend/`.

**Checkpoint**: Per-study schema, modules, and components are live. `mci` behaves exactly as before apart from the Step 1 label. The pytest baseline and both golden export tests pass.

---

## Phase 3: User Story 1: Reviewer records DEXA body regions and scores (Priority: P1) 🎯 MVP

**Goal**: On a `scans` deployment, a reviewer enters one or more regions, each with optional laterality, T score, and Z score, and submits. The system stores one `reviews` row and N `review_regions` rows, and the event goes to `done`. Saved reviews can be retrieved, and the admin Export page works.

**Independent test**: quickstart § A steps 1–6 and 10–12, plus `pytest flask_backend/tests/test_scans_review.py`.

### Tests for User Story 1

- [X] T016 [P] [US1] Create `flask_backend/tests/test_scans_review.py`.
  - Set `STUDY_TYPE=scans` via `monkeypatch`, and use the `FakeEvent`/`FakeSession` pattern from `test_review_endpoint.py`, extended to capture `add()`ed objects.
  - Valid 3-region body: expect 200 and `status == "done"`, with one `Reviews` and three `ReviewRegions` captured at positions 1–3.
    - Blank laterality and scores are stored as `None`.
    - `"-1.1"` is stored as `Decimal("-1.1")`.
    - An `Other` entry keeps its `region_other`; a non-Other entry's `region_other` is `None`.
  - A repeated region (two `Wrist` entries) is accepted.
  - The 400 matrix, each case asserting the expected `fields[index/field]`:
    - `regions` missing, empty, or with 21 entries;
    - unknown region;
    - `Other` with blank text;
    - bad laterality;
    - scores `abc`, `12`, `-10.01`, `1.234`, and `1..2`.
  - Accepted score forms: `-1.1`, `2.0`, `0`, `+0.5`, `.5`, `2`, `10`, and `-10`.
  - On any 400, nothing is added.
  - A caller who isn't assigned to the event gets 403.

### Implementation for User Story 1

- [X] T017 [US1] Create `flask_backend/models/studies/scans.py` per data-model.md.
  - Declare `class ScansBase(DeclarativeBase)`.
  - `Reviews`: `id`, `event_id`, `reviewer_id`, `created`, plus `regions = relationship("ReviewRegions", order_by=…position, cascade="all, delete-orphan")`.
  - `ReviewRegions`: `id`, `review_id` (FK), `position`, `region`, `region_other`, `laterality`, and `t_score`/`z_score` as `Numeric(4, 2, asdecimal=True)`.
  - Set `REVIEW_FORM = "scans"`.
  - Constants:
    - `SCANS_REGIONS`, in the exact order from data-model.md, with stored value `Other`;
    - `SCANS_LATERALITIES`;
    - `SCORE_RE`;
    - `SCORE_MIN = Decimal(-10)` and `SCORE_MAX = Decimal(10)`;
    - `MAX_REGIONS = 20`.
  - Put the comment `keep in sync with frontend/src/studies/scans/ScansReviewForm.jsx` on the constants.
- [X] T018 [US1] Implement `parse_review(data)` and `add_review(session, event_id, reviewer_id, parsed)` in `flask_backend/models/studies/scans.py`.
  - Follow the validation table in data-model.md. Collect **all** field errors, then raise `ReviewValidationError("Invalid review", fields)`. Use the error messages exactly as given in data-model.md.
  - `add_review` adds one `Reviews` with a `regions` list, where each `position` is the index + 1.
  - Done when the T016 tests pass, along with the xfail cases in T011 and T012.
- [X] T019 [US1] Add the shared duplicate-submission guard in `events_review`, `flask_backend/app.py` (spec FR-024, research D5).
  - After slot resolution, if `e.review1_date` (slot 1) or `e.review2_date` (slot 2) is already set, return `409 {"error": "This event has already been reviewed by you"}` before parsing or writing anything.
  - Add tests to `flask_backend/tests/test_review_endpoint.py`: re-submission in slot 1 and in slot 2 each returns 409, with no `add()` called.
  - Update the endpoint docstring to include the 409, and update the comment block that says `scans` carries no study fields.
- [X] T020 [US1] Add a schema check so an unmigrated database fails clearly (research D10, quickstart migration).
  - Implement `check_schema(session)` in `flask_backend/models/studies/scans.py`. It queries `information_schema.COLUMNS` for table `reviews` in `DATABASE()`. If a column `mci` exists, or `created` is missing, it returns an error string naming `init/migrations/012-scans-reviews.sh`; otherwise it returns `None`.
  - In `flask_backend/app.py`, call `get_study_module().check_schema(session)` once per process, caching the result, in `events_review` (POST) and `events_export`.
  - On error, log at ERROR level and return `503 {"error": "Review tables are not migrated for this study; see init/migrations/012-scans-reviews.sh"}`.
  - Add a test in `flask_backend/tests/test_scans_review.py` that mocks an MI-shaped schema and expects 503.
- [X] T021 [P] [US1] Create `init/studies/scans.sql` with the scans `reviews` and `review_regions` DDL exactly as in data-model.md.
  - `CREATE TABLE IF NOT EXISTS`, InnoDB, utf8mb4.
  - `UNIQUE (event_id, reviewer_id)` and `UNIQUE (review_id, position)`.
  - FK `review_id → reviews.id ON DELETE CASCADE`.
  - No `event_derived_datas`; constitution v1.5.1 allows a study to omit it.
  - A header comment naming the study and constitution Principle IV.
- [X] T022 [P] [US1] Create `init/migrations/012-scans-reviews.sh`, a one-off script for existing `scans` databases, run inside the mariadb container per quickstart.md.
  - Use `set -eu`, `$MYSQL_ROOT_PASSWORD`, and `$MYSQL_DATABASE`.
  - For each pair (`reviews`, `mci`) and (`event_derived_datas`, `outcome`): query `information_schema.COLUMNS` for that column in that table. If it is present, run `RENAME TABLE <t> TO <t>_mi_pre012` and echo what was renamed.
  - Then apply `/docker-entrypoint-initdb.d/studies/scans.sql`.
  - The script must be idempotent: a second run renames nothing, and the `CREATE ... IF NOT EXISTS` statements do nothing.
  - Give it a header comment covering its purpose, that it is for scans only, how to run it, and that tables are renamed, never dropped.
  - Make it executable.
- [X] T023 [US1] Implement `get_reviews(session, event_id)` and `export_csv(session, writer)` in `flask_backend/models/studies/scans.py` (contracts/review-api.md, FR-016).
  - `get_reviews`: query `Reviews` with its `regions` for the event, joining `events` explicitly for `review1_date`. Serialize scores with `format(d, 'f')`, or `None` when missing.
  - `export_csv`: explicit SQL using LEFT JOINs across:
    - `events e`;
    - `patients_view p`;
    - `users r1 ON r1.id = e.reviewer1_id`;
    - `reviews rv ON rv.event_id = e.id AND rv.reviewer_id = e.reviewer1_id`;
    - `review_regions rr ON rr.review_id = rv.id`.
  - Export output: one row per region, or one row for an event with no review. Columns exactly as in the contract, ordered by `e.id` then `rr.position`, with empty cells for NULL.
- [X] T024 [US1] Add `GET /api/events/<int:event_id>/review` in `flask_backend/app.py`.
  - Decorate with `@requires_auth` and `@requires_any_role('reviewer', 'admin')`.
  - Return 404 for an unknown event. Return 403 unless the caller is admin or is the event's `reviewer1_id`/`reviewer2_id`.
  - Otherwise return `{"data": {"event_id", "reviews": get_study_module().get_reviews(session, event_id)}}`.
  - Write a docstring with the response schema for openapi.
- [X] T025 [US1] Extend `flask_backend/tests/test_scans_review.py`:
  - GET role cases: admin → 200, assigned reviewer → 200, other reviewer → 403, unknown event → 404.
  - GET serialization: score strings, `null`s, and position order.
  - `GET /api/events/export` with `STUDY_TYPE=scans` and mocked rows returns the expected headings and one row per region.
  - Confirm `test_mci_export_golden.py` still passes.
- [X] T026 [US1] Build the core scans form in `frontend/src/studies/scans/ScansReviewForm.jsx`, replacing the T014 placeholder. Follow states S0/S1+ and behaviors U1 and U4–U7 in contracts/review-ui.md.
  - Constants:
    - `SCANS_REGIONS` with display labels; `Other` is shown as "Other: please specify";
    - `SCANS_LATERALITIES`;
    - `SCORE_RE`, range ±10, and a maximum of 20 entries.
  - Put the comment `keep in sync with flask_backend/models/studies/scans.py` on the constants.
  - S0: `<h2>Step 2: Enter Body Region</h2>`, a "Body region" select, and no Submit.
  - Choosing a region appends `{key, region, regionOther:'', laterality:'', tScore:'', zScore:''}`.
  - S1+: each entry is a "Region N" group of rows in `<table id="reviewForm">` style, containing:
    - Body region;
    - "Please specify" text input, shown when the region is `Other`;
    - "Laterality (optional)" select with an empty first option;
    - "T score" and "Z score" text inputs with `inputMode="decimal"`.
  - After the entries, show `<h2>Step 3: Is there another body region? If not, click "Submit".</h2>`, then an "Another body region" select that appends an entry and resets to "Select…", then Submit.
  - Client validation runs on blur and on submit: Other text required, and the score regex and range. Show inline messages and set `aria-invalid`.
  - Submit POSTs `{regions:[{region, region_other, laterality, t_score, z_score}]}`, with trimmed strings, to `${apiUrl}/api/events/${eventId}/review` with `credentials:'include'`.
  - While submitting, the button is disabled and reads "Submitting…". On 200, show "Review submitted. Event status: {status}." and lock the form. On error, show `json.error`.
  - The layout stays one column with no horizontal scroll at 8 or more entries.
  - Run `npm run lint && npm run build`.

**Checkpoint**: US1 is fully functional on `scans`, including retrieval and the admin Export page, and the MVP is deliverable. Run quickstart § A steps 1–6 and 10–12, and § C steps 3–4.

---

## Phase 4: User Story 2: Reviewer corrects entries before submitting (Priority: P2)

**Goal**: The reviewer can change an entry's region, remove an entry, and see server-reported field errors on the specific inputs.

**Independent test**: quickstart § A steps 7–9.

- [X] T027 [US2] Make each entry's region changeable in `frontend/src/studies/scans/ScansReviewForm.jsx` (U2 / FR-011).
  - Each entry's "Body region" row is an editable select. Changing it updates only `region` and keeps laterality and scores.
  - Clear `regionOther` and its error only when the entry stops being `Other`.
- [X] T028 [US2] Add a "Remove" button to each entry in `frontend/src/studies/scans/ScansReviewForm.jsx` (U3).
  - Remove by stable `key`, never by index, so other entries keep their values. Re-key the errors map to match.
  - Renumber the "Region N" labels.
  - Removing the last entry returns the form to S0, with no Submit (FR-012).
- [X] T029 [US2] Map a server 400 `fields` array onto inputs in `frontend/src/studies/scans/ScansReviewForm.jsx` (U5/U6).
  - Each `{index, field}` refers to the entry at that index and to one of `region`, `region_other`, `laterality`, `t_score`, `z_score`. Show its message inline on that input.
  - On any failed submit, client-side or server-side, focus the first invalid input.
  - Run `npm run lint && npm run build`.

**Checkpoint**: US1 and US2 both work, and quickstart § A passes in full.

---

## Phase 5: User Story 3: Reviewer reads instructions on-site and can print them (Priority: P2)

**Goal**: The DEXA instructions are a print-friendly HTML document that opens in a new tab from the review page and from the home page. The MCI reviewer `.doc`/`.pdf` links are removed, and there is no MCI content yet.

**Independent test**: quickstart § B, and § C steps 1–2. This story depends only on Phase 2, so it can run in parallel with Phase 3.

- [X] T030 [P] [US3] Create `app/webroot/files/instructions/scans.html`, a self-contained document (contracts/review-ui.md "Instructions document"; research D7).
  - `<!doctype html>`, `lang="en"`, charset and viewport metas, and `<title>DEXA Chart Review Instructions</title>`.
  - An inline `<style>`:
    - a readable serif or system font;
    - `max-width: 48rem; margin: auto` on screen;
    - `ol ol { list-style-type: lower-alpha }`;
    - `@media print` with `max-width: none`, about 11pt, black on white, no link decoration, and `li { page-break-inside: avoid }`.
  - Content: convert the "DEXA CHART REVIEW INSTRUCTIONS" section of `docs/DEXA CHART REVIEW INSTRUCTIONS and PROGRAMMING INSTRUCTIONS.20260922.docx`. It is the same text as the repo-root `DEXA.scans.review.instructions.md`.
    - `<h1>` and the intro paragraph;
    - `<ol>` with steps 1–3, where step 3 contains a nested `<ol type="a">` for items a–d;
    - "Notes:" followed by an `<ol>` of notes 1–6;
    - wording verbatim throughout.
  - Leave out the "PROGRAMMING INSTRUCTIONS" section.
  - No scripts and no external resources.
- [X] T031 [P] [US3] Update `frontend/src/components/reviewGuidance.js` (research D8).
  - Set `scans.instructions` to `{ items: [], links: [{ label: 'View review instructions', href: '/files/instructions/scans.html', download: false }] }`. This replaces 'No additional instructions'.
  - Set `mci.instructions` to `{ items: [], links: [] }`.
  - Leave `packets` and `scrubbing` unchanged.
  - Update the comments to cite spec 012 FR-017a and FR-021, and to note that MCI will use the same mechanism once `instructions/mci.html` exists.
- [X] T032 [P] [US3] Add `rel="noopener"` to the new-tab `<a target="_blank">` in `frontend/src/components/GuidanceLinks.jsx`. Change nothing else.
- [X] T033 [US3] Verify the instructions boxes against FR-021 and FR-022.
  - `scans`: the link appears in the box on `frontend/src/pages/Home.jsx` and in the box on `EventReview.jsx` (via the `showInstructionDocs` gate).
  - `mci`: the home box shows only its header, and the review page box is hidden.
  - Change code only if a gate misbehaves.
  - Run `npm run lint && npm run build`.

**Checkpoint**: All three user stories work independently.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T034 [P] Update `docs/template-setup-guide.md`.
  - In "Study-Specific Components", rewrite the init tree (~L142–161) and the models reachability note (~L189–202) to cover:
    - `models.py` is now a package;
    - `models/studies/<study>.py` is live, with the module interface;
    - study DDL lives in `init/studies/<study>.sql`, applied by `04-study-schema.sh`, and only `reviews` is mandatory;
    - study form components live in `frontend/src/studies/<study>/`, selected by `components/review/reviewForms.js`;
    - the `-- DORMANT` marker and the `mci` fallback.
  - Update the per-study schema headings (~L431, 463, 502, 531) and the "add a study" steps (~L310–331) to the `init/studies/` paths.
  - Replace the ~L579 statement that `scans` has no schema or model file.
- [X] T035 [P] In `docs/legacy-repository-analysis.md` (~L39–54), update the `init/02-schema-vte.sql` and `init/02-schema-hf.sql` paths to `init/studies/vte.sql` and `init/studies/hf.sql`.
- [X] T036 [P] In the v1.4.0 → v1.5.0 Sync Impact Report in `.specify/memory/constitution.md`, change the three "⚠ … pending" lines to ✅ once T034, T035, and the implementation have landed.
- [X] T037 [P] Record the Principle VI follow-up for `flask_backend/models2.py`.
  - The file is unused and holds an MI `Reviews`/`EventDerivedDatas` copy.
  - Add a note to `flask_backend/README.md` saying it doesn't follow the per-study model layout and is a removal candidate.
  - Don't delete it in this feature.
- [X] T038 Regenerate `openapi.json` with `python -m flask_backend.generate_openapi` from the repository root, and commit it. Check that the diff shows `review_form`, the new GET, and the 409 and 503 responses.
- [X] T039 Run the full suite and the frontend checks: `pytest flask_backend/tests`, then `cd frontend && npm run lint && npm run build`. Compare against `specs/012-dexa-review-form/baseline-tests.txt`; no regressions are allowed.
- [X] T040 Write the PR description per the constitution's "Change review" gate. Cover:
  - Studies affected:
    - **all studies**: models package, export and review delegation, the 409 guard (FR-024), the Step 1 label, instructions links, and mariadb `STUDY_TYPE`;
    - **`scans` only**: the scans schema and form.
  - **Existing scans deployments must run `init/migrations/012-scans-reviews.sh`.** Link quickstart.md, and mention the 503 hint they will see if they don't.
  - Constitution v1.5.0 and v1.5.1.
  - The observed-behavior notes from research D1, D4, D5, D9, and D10 (Principle VI).
  - `/files/` relies on the Apache gate and has no Flask auth decorator. That is unchanged; the instructions file contains no PHI.
  - The repo-root `DEXA.scans.review.instructions.md` is superseded by `instructions/scans.html` and is untracked; the user decides whether to delete it.
- [X] T041 Hand the operator the quickstart.md server steps: migration, rebuild, and manual script §§ A–C. The operator runs them, because they cannot be run from the dev laptop.

---

## Rev. 4 (2026-10-16): post-deployment feedback

These phases implement FR-025 – FR-033 (spec Clarifications, Session
2026-10-16), following plan.md rev. 4 and research D12–D18. There is **no
schema change and no migration**. Everything here is study-agnostic, except
the two summary components. Contracts: review-api.md and review-ui.md, the
"Rev. 4" sections.

---

## Phase 7: Foundational (rev. 4): backend access and scoping

**Purpose**: Make the backend the access boundary before any UI relies on it
(FR-025, FR-027, FR-028, FR-029). This phase blocks Phases 9 and 10.

### Tests (write first; they must fail before T044–T050)

- [X] T042 Create `flask_backend/tests/test_review_access.py`.
  - Reuse the `FakeEvent` and `FakeSession` patterns and the
    `reviewer_client` fixture from `flask_backend/tests/test_scans_review.py`.
    Add `uploader_client` (an uploader, not admin, not reviewer, id 9) and
    `admin_client` (from conftest).
  - Give `FakeEvent` `reviewer3_id`, `review1_date`, `review2_date`, and
    `review3_date` (default `None`).
  - Cover each of the following:
    1. **for_review scoping (D14)**: with
       `flask_backend.table_service.get_events_for_review` patched,
       - an admin GET `/api/events/for_review?limit=3&offset=0` calls it as
         `(3, 0)` (unchanged);
       - reviewer id 7 calls it with `reviewer_id=7`.
    2. **Table-service slot filter**: unit-test
       `table_service.get_events_for_review(…, reviewer_id=7)` with a
       capturing fake session, as in `test_table_service.py`. The executed SQL
       must contain both slot conditions,
       `(e.reviewer1_id = :reviewer_id AND e.review1_date IS NULL)` and the
       `reviewer2_id`/`review2_date` equivalent. With `reviewer_id=None`,
       neither condition appears.
    3. **GET …/review (D15, D16.1)**, via `GET /api/events/5/review` with
       `scans.get_reviews` patched to return two reviews (reviewer_id 7 and
       8):
       - admin gets both reviews, each with `slot`. The slot is 1 for
         `reviewer1_id=7`, 2 for `reviewer2_id=8`, and `None` for a reviewer
         id in no slot.
       - reviewer 7 assigned in slot 1 with `review1_date=None` gets
         `reviews == []` and `mine is None`.
       - with `review1_date=date(2026,10,16)`, reviewer 7 gets
         `mine == {"slot": 1, "review_date": "2026-10-16"}` and
         `reviews == []`.
       - the uploader gets 403.
    4. **GET `/api/events/5` (D16.4)**, with
       `table_service.get_event_details` patched to return `{"id": 5}` and the
       event looked up through the patched session:
       - admin → 200; uploader → 200;
       - reviewer 7 assigned (any of the three slots) → 200;
       - reviewer 7 not assigned → 403;
       - unknown event → 404.
    5. **`/api/tables/<name>` (D16.5)**:
       - a reviewer requesting `reviews` or `review_regions` with
         `STUDY_TYPE=scans` (monkeypatch as other tests do) → 403;
       - an admin requesting `review_regions` → 200 (patch
         `table_service.get_table_data`);
       - any user requesting `events;drop` or `1abc` → 404, and
         `get_table_data` is never called;
       - a reviewer requesting `criterias` → 200 (unchanged).
    6. **Role decorators (analyze C1)**: a signed-in user with no roles
       (all four flags false, id 11) gets 403 from `GET /api/events/5` and
       `GET /api/tables/criterias`.
    7. **POST slot 3 refused (analyze I1)**: with `reviewer3_id=7` and
       slots 1 and 2 held by others, reviewer 7's
       `POST /api/events/5/review` gets 403, and nothing is added to the
       session.
    8. **`mine` slot 3 not exposed (analyze I2)**: an admin whose id is
       `reviewer3_id` with `review3_date` set gets `mine is None`.
    9. **Admin-only stages (FR-033)**:
       - `POST /api/events/5/screen` as reviewer → 403;
       - `POST /api/events/5/upload_scrubbed` as reviewer → 403, and as the
         uploader → 403;
       - as admin, neither returns 403. Patch the session/handler internals
         as needed; assert only that the decorator lets the request through.
- [X] T043 Update the existing GET tests in
  `flask_backend/tests/test_scans_review.py`:
  - `test_get_review_admin` now expects each stored review to also carry
    `slot` (1 for `reviewer1_id=7`), plus a `mine` key.
  - `test_get_review_assigned_reviewer` now asserts `reviews == []`.
  - Leave `test_get_reviews_serialization` and `test_get_reviews_none`
    unchanged: `get_reviews` itself does not change.

### Implementation

- [X] T044 [P] Add the `REVIEW_TABLES` module constant to both study modules.
  - In `flask_backend/models/studies/mci.py`:
    `REVIEW_TABLES = ("reviews", "event_derived_datas")`.
  - In `flask_backend/models/studies/scans.py`:
    `REVIEW_TABLES = ("reviews", "review_regions")`.
  - Add a one-line comment to each: "tables `/api/tables/<name>` refuses to
    non-admins (spec 012 FR-028)".
  - Add `REVIEW_TABLES` to the module interface docstring in
    `flask_backend/models/studies/__init__.py`.
- [X] T045 Add the slot filter in `flask_backend/table_service.py`.
  - Give `get_events_by_status_with_total` an optional keyword
    `reviewer_id: Optional[int] = None`. When it is set, append to `where`:
    `"((e.reviewer1_id = :reviewer_id AND e.review1_date IS NULL) OR (e.reviewer2_id = :reviewer_id AND e.review2_date IS NULL))"`,
    and set `params["reviewer_id"]`.
  - Thread the same keyword through `get_events_by_status` and
    `get_events_for_review(limit=None, offset=0, reviewer_id=None)`.
  - Update `get_events_for_review`'s docstring: non-admin callers are scoped
    to their unreviewed slots (spec 012 FR-025, research D14). The rule
    matches the POST 409 guard.
- [X] T046 In `flask_backend/app.py` `events_for_review`:
  - read `g.auth_user`;
  - admins call `table_service.get_events_for_review(limit, offset)`, exactly
    as now;
  - everyone else calls
    `table_service.get_events_for_review(limit, offset, reviewer_id=int(auth_user['id']))`;
  - update the docstring's description.
- [X] T047 In `flask_backend/app.py` `events_review_get` (GET
  `/api/events/<id>/review`):
  - Add a small shared helper beside it, `_reviewer_slot(e, user_id)`. It
    returns 1, 2 or 3 by matching `e.reviewer1_id`, `e.reviewer2_id` and
    `e.reviewer3_id`, or `None`.
  - Use the helper in POST `events_review` too, replacing the inline slot
    `if`/`elif`, **without changing POST behavior**. POST must still refuse
    slot 3 and `None` with the existing 403 ("Submitter is not an assigned
    reviewer for this event"): `if slot not in (1, 2): return … 403`. Third
    review submission is not part of this feature (analyze I1).
  - Compute `mine` for **slots 1 and 2 only** (analyze I2). The GET's
    existing non-admin access check admits only `reviewer1_id` and
    `reviewer2_id`, and that check is unchanged. If the caller's slot `N` is 1
    or 2 and `getattr(e, f'review{N}_date')` is set, `mine` is
    `{'slot': N, 'review_date': <date>.isoformat()}`; otherwise `None`. That
    includes an admin who holds no slot, or holds slot 3.
  - Admins: `reviews` is `study.get_reviews(...)` with
    `review['slot'] = _reviewer_slot(e, review['reviewer_id'])` added to each
    item.
  - Non-admin assigned reviewers: `reviews` is `[]`. Do not call
    `get_reviews`.
  - The response is `{'data': {'event_id', 'reviews', 'mine'}}`.
  - Keep the existing 403, 404 and 503 paths.
  - Update the docstring and `responses` to match
    `contracts/review-api.md` rev. 4.
- [X] T048 In `flask_backend/app.py` `get_event_details` (GET
  `/api/events/<int:event_id>`):
  - Add `@requires_any_role('admin', 'uploader', 'reviewer', 'third_reviewer')`
    under `@requires_auth`. The constitution's "Authorization" gate requires
    an explicit role decorator; this is the set `/api/config` uses (analyze
    C1).
  - If `g.auth_user` has neither `admin` nor `uploader`:
    - open a session;
    - `session.query(models.Events).get(event_id)` → 404 if missing;
    - 403 `{'error': 'Not authorized for this event'}` unless the caller's
      id equals one of `reviewer1_id`, `reviewer2_id`, `reviewer3_id`;
    - close the session in `finally`.
  - Then continue as now. Add `403` to the docstring responses.
- [X] T049 In `flask_backend/app.py` `get_table` (`/api/tables/<name>`):
  - Add `@requires_any_role('admin', 'uploader', 'reviewer', 'third_reviewer')`
    under `@requires_auth` (analyze C1). The existing `test_app.py`
    `/api/tables/events` tests use `admin_client`, so they stay green.
  - Before querying, return 404 `{'error': 'Not found'}` unless
    `re.fullmatch(r'[A-Za-z_][A-Za-z0-9_]*', name)`.
  - Return 403 `{'error': 'Not authorized'}` when the caller isn't admin and
    `name.lower()` is in `get_study_module().REVIEW_TABLES` (use
    `getattr(..., 'REVIEW_TABLES', ())` for safety).
  - Add a `# TODO(follow-up, spec 012 research D16.5)` comment: a full
    allowlist with per-table roles is still to come.
  - Add 403 and 404 to the docstring.
- [X] T050 In `flask_backend/app.py`, change two decorators to
  `@requires_roles('admin')` (FR-033, research D19):
  - `events_screen` (POST `/api/events/<id>/screen`), currently
    `@requires_any_role('reviewer', 'admin')`;
  - `events_upload_scrubbed` (POST `/api/events/<id>/upload_scrubbed`),
    currently `@requires_any_role('reviewer', 'uploader', 'admin')`.
  Update each docstring's description, and add a 403 response noting
  "admin only (spec 012 FR-033)". Change no body logic.
- [X] T051 Run `pytest flask_backend/tests`. `test_review_access.py`, the
  updated `test_scans_review.py`, `test_review_endpoint.py`, `test_app.py`,
  and `test_auth_header_and_roles.py` must all pass.

**Checkpoint**: The backend enforces FR-025 scoping, FR-028, FR-029's
`mine`, and FR-033. No UI has changed yet.

---

## Phase 8: User Story 3 (rev. 4): instructions reachable

**Goal**: `/files/instructions/scans.html` shows the instructions, not the
SPA shell (FR-032, research D12).

**Independent test**: quickstart §D step 1.

- [X] T052 [P] [US3] Proxy `/files` in `frontend/vite.config.js`.
  - In `server.proxy`, add
    `'/files': { target: apiProxyTarget, changeOrigin: false }` beside
    `'/api'`.
  - Extend the comment above `apiProxyTarget`: `/files` (reviewer
    instructions, packet-assembly and scrubbing documents) needs the same
    proxy, or it falls through to the SPA fallback and renders a blank page
    (spec 012 FR-032).

---

## Phase 9: User Story 1 (rev. 4): review page, queue, and already-reviewed state

**Goal**:
- The "Review Events" menu link shows the reviewer's queue (FR-025).
- A reviewed event shows a notice instead of the form (FR-029).
- The instructions box sits top right in legacy style, and H1 is smaller
  (FR-030, FR-031).

**Independent test**: quickstart §D steps 2–4.

- [X] T053 [P] [US1] Make the style changes in `frontend/src/index.css`
  (research D18).
  - Change `h1` to `font-size: 1.6em; line-height: 1.2;`.
  - Add `.boxright`:
    - `float: right; width: 300px; margin: 0 0 1em 1.5em;`
    - `background-color: #f2e8c2; border: 1px solid #f1d676; border-radius: 12px; padding: 5px 10px;`
      (the same values as `.infobox` in `frontend/src/pages/Home.css`; add a
      comment saying so).
    - `.boxright h3 { margin: 0.4em 0; }`
  - Add `@media (max-width: 640px) { .boxright { float: none; width: auto; margin: 0 0 1em 0; } }`.
- [X] T054 [US1] In `frontend/src/pages/EventReview.jsx`:
  - remove the inline `style={{ width: '300px', fontSize: '.95em' }}` from
    the `boxright` div;
  - drop the inner `<div style={{ marginTop: '8px' }}>` wrapper if it is no
    longer needed for spacing.
- [X] T055 [US1] Create `frontend/src/components/review/ReviewQueue.jsx` by
  moving `TableWrapper` out of `frontend/src/pages/Home.jsx`.
  - Export `TableWrapper` (named) unchanged, except for a new optional
    `onRowClick(row)` prop. When it is given, it replaces the built-in
    `/events/upload?...` navigation in `handleClick`.
  - The default export, `ReviewQueue`, renders:
    - `<h3>Events to Review</h3>`;
    - `<p>Events with packets ready for review.</p>`;
    - `TableWrapper` with endpoint `/api/events/for_review`, columns
      `['ID', 'Patient ID', 'Date', 'Criteria', 'Site']`, the existing
      "review" button action, and
      `onRowClick={(row) => navigate(`/events/review?event_id=${row['ID']}`)}`.
  - When the first page loads with zero rows, show "No events are awaiting
    review." This is neutral wording, since admins see every event
    (analyze U3). in place of the table. Add a `onLoaded(rows, totalCount)`
    callback prop to `TableWrapper`, or an equivalent minimal hook.
  - Move the `API_BASE`/`PAGE_SIZE` constants and the `DataTable` import
    along with it.
  - The comment cites spec 012 FR-025 and research D13.
- [X] T056 [US1] In `frontend/src/pages/Home.jsx`:
  - import `{ TableWrapper }` and `ReviewQueue` from
    `../components/review/ReviewQueue`;
  - delete the local `TableWrapper`;
  - replace the "Events to Review" `<section>` body with `<ReviewQueue />`,
    keeping the `auth.reviewer || auth.admin` gate and the `<section>`
    wrapper.
  - The other home tables keep using `TableWrapper` with no `onRowClick`, so
    their behavior is unchanged.
  - Run `npm run lint`.
- [X] T057 [US1] In `frontend/src/pages/EventReview.jsx`, when there is no
  `event_id`:
  - If `eventId` is falsy, return `<ReviewQueue />` (wrapped in the page's
    outer `<div>`) before any heading, instructions box, Step 1, or form
    renders.
  - Keep hooks unconditional: place the early return after all `useState` and
    `useEffect` calls. The existing effect already skips its fetch without an
    id.
- [X] T058 [US1] In `frontend/src/pages/EventReview.jsx`, add the
  already-reviewed state (FR-029, contract review-ui rev. 4).
  - Add state `reviewStatus`, one of `'loading' | 'open' | 'reviewed' | 'error'`,
    and `mine`.
  - In a `useEffect` keyed on `eventId`, fetch
    `${apiUrl}/api/events/${eventId}/review` with `credentials: 'include'`:
    - 200 with `data.mine` → `'reviewed'`;
    - 200 without → `'open'`;
    - otherwise → `'error'`.
  - Below Step 1, render by state:
    - `'loading'`: nothing.
    - `'reviewed'`: `<p>You have already reviewed this event (submitted {mine.review_date}).</p>`
      and no `ReviewForm`.
    - `'open'`: `<ReviewForm …/>` as now.
    - `'error'`: `<p>This review could not be loaded.</p>`.
  - The heading, date, instructions box, and Step 1 download stay in every
    state.
  - Run `npm run lint && npm run build`.

**Checkpoint**: The review flow matches the user's feedback for all studies.

---

## Phase 10: Event page — review content and access (FR-026 – FR-028; cross-cutting)

**Purpose**:
- Admins see each review's answers on `/events/edit`.
- Uploaders don't see the answers.
- Reviewer-only users can't reach the page.
- Nothing edits a review.

Depends on Phase 7 (T047).

- [X] T059 Add `resolveReviewSummary(reviewForm)` to
  `frontend/src/components/review/reviewForms.js`.
  - It uses a `REVIEW_SUMMARIES = { mci: MciReviewSummary, scans: ScansReviewSummary }`
    map, the same key normalization as `resolveReviewForm`, and falls back to
    `MciReviewSummary`.
  - Update the file's header comment.
- [X] T060 [P] Create `frontend/src/studies/scans/ScansReviewSummary.jsx`.
  - Props: `{ review }`, a `get_reviews` item with `regions`.
  - Render one `<div>` per region, sorted by `position`:
    `{region}{region_other ? `: ${region_other}` : ''}`, then ` — ` followed
    by the non-null parts joined by `, `: the laterality, `T: {t_score}`,
    and `Z: {z_score}`. Omit the ` — ` when every part is null.
  - Never render `null` or `0` for a missing score.
  - Zero regions renders "No regions recorded".
  - Header comment: spec 012 FR-027, research D17.
- [X] T061 [P] Create `frontend/src/studies/mci/MciReviewSummary.jsx`.
  - Props: `{ review }`, a column dict from `mci.get_reviews`.
  - Render one `<div>` per non-null, non-empty answer as `Label: value`, in
    the order the questions appear in
    `frontend/src/studies/mci/MciReviewForm.jsx`.
  - Take labels and option text from that form. Export the label map from
    `MciReviewForm.jsx` if needed, rather than duplicating strings.
  - Render flag columns (0/1) as "No"/"Yes".
  - Skip `id`, `event_id`, `reviewer_id`, `slot`, and `review_date`.
  - Reference: `mci.event.edit.Screenshot.1.2026-10-14.jpg` (e.g. "MI: No",
    "Cardiac intervention: No", "Cardiac Cath: No").
- [X] T062 Add the review content rows to `frontend/src/pages/EventEdit.jsx`
  (FR-027).
  - Accept a `reviewForm` prop and an `auth` prop; pass both from
    `frontend/src/App.jsx`'s `/events/edit` route, as `EventReview` gets
    `reviewForm`.
  - Only when `auth?.admin`: fetch `${apiUrl}/api/events/${eventId}/review`
    and keep `data.reviews`.
  - After each `Review N Date` row (N = 1, 2, 3), when a review with
    `slot === N` exists, add
    `<tr><th>Review N:</th><td><Summary review={r} /></td></tr>`, using
    `resolveReviewSummary(reviewForm)`.
  - Non-admins: no fetch and no content rows.
  - Add no edit control for reviews (FR-026).
- [X] T063 In `frontend/src/App.jsx`:
  - change the `/events/edit` route to
    `requiredRoles={['uploader', 'admin']}` (FR-028);
  - change the `/events/scrub`, `/events/screen` and `/events/viewAll` routes
    to `requiredRoles={['admin']}` (FR-033, research D19). Leave the `/vte/*`
    routes untouched (legacy fork, not linked);
  - pass `reviewForm` and `auth` to `<EventEdit />` (T062).
- [X] T064 [P] In `frontend/src/components/MenuBar.jsx`, show "View All
  Events" only when `admin` (FR-033, research D19).
  - Update its comment ("for admin users only; spec 012 FR-033").
  - Run `npm run lint && npm run build`.

**Checkpoint**: Quickstart §D steps 5–8 pass on the server.

---

## Phase 11: Polish (rev. 4)

- [X] T065 Regenerate `openapi.json` with
  `python -m flask_backend.generate_openapi`. The diff should show `slot` and
  `mine` on GET `…/review`, and 403 on `/api/events/{id}` and
  `/api/tables/{name}` (404 too).
- [X] T066 [P] Update `specs/012-dexa-review-form/pr-description.md` with a
  rev. 4 section. Cover:
  - the all-study behavior changes:
    - the home "Events to Review" table narrows to the reviewer's own
      unreviewed events (admins unchanged);
    - reviewer-only users lose `/events/edit` and other events' details;
    - scrub, screen and `/events/viewAll` are admin-only (FR-033), so
      non-admin `mci` scrubbers and screeners lose access;
    - review answers are admin-only;
  - the `/api/tables` SQL-injection fix and the remaining allowlist
    follow-up, plus the D19 follow-ups (`by_status`, `assignThird`,
    `events/download`, `need_*`);
  - the `/files` dev-proxy fix and its deployment caveat (quickstart §D);
  - the H1/`.boxright` restyle;
  - "no migration".
- [X] T067 Run `pytest flask_backend/tests` and
  `cd frontend && npm run lint && npm run build`. Expect no regressions
  against `specs/012-dexa-review-form/baseline-tests.txt` plus the new tests.
  Install no packages.
- [X] T068 Hand the operator quickstart.md §D, steps 1–8, to run on the dev
  server after a rebuild. No migration is needed.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies. T002 must pass on unmodified code.
- **Foundational (Phase 2)**: depends on Phase 1, and blocks all later phases. It has three independent tracks:
  - database: T003, then T004, T005, and T006 in parallel;
  - backend: T007 → T008 → T009 → T010, with T011 and T012 able to start after T008;
  - frontend: T013 → T014 → T015.
- **US1 (Phase 3)**: depends on Phase 2.
- **US2 (Phase 4)**: depends on T026, because it extends the same component.
- **US3 (Phase 5)**: depends only on Phase 2 (T015 for the review-page box), so it can run in parallel with US1 and US2.
- **Polish (Phase 6)**: depends on everything above.
- **Rev. 4** (Phases 7–11):
  - Phase 7 (backend access) comes first: T042 and T043 (failing tests) →
    T044 [P] → T045 → T046 → T047 → T048 → T049 → T050 → T051.
  - Phase 8 (T052) is independent and can run at any time.
  - Phase 9: T053 [P] at any time. T054 → T055 → T056 → T057 → T058, and
    T058 needs T047.
  - Phase 10 needs T047: T059 → T060 [P] and T061 [P] → T062 → T063.
    T064 [P] can run at any time.
  - Phase 11 comes last.

### Within User Story 1

Backend order: T016 (tests first, failing) → T017 → T018 → T019 → T020 → T023 → T024 → T025.

T021 and T022 can run at any time after T003. T026 needs T015, and should follow T018 so that the request contract is settled.

### Parallel Opportunities

- Phase 2: the database track (T004–T006), the backend track, and the frontend track (T013–T015) run side by side.
- US1: T016, T021, and T022 can run in parallel. T026 (frontend) can run in parallel with T019–T025 (backend).
- US3: T030, T031, and T032 touch different files.
- Polish: T034–T037 can run in parallel.

## Parallel Example: User Story 1

```text
Task: "T016 Create flask_backend/tests/test_scans_review.py (validation matrix, rows, 403)"
Task: "T021 Create init/studies/scans.sql (reviews + review_regions)"
Task: "T022 Create init/migrations/012-scans-reviews.sh (rename MI tables, apply scans.sql)"
# then T017 → T018 → T019 → T020 → T023 → T024 → T025 on the backend while T026 builds ScansReviewForm.jsx
```

## Parallel Example: User Story 3

```text
Task: "T030 Create app/webroot/files/instructions/scans.html"
Task: "T031 Update frontend/src/components/reviewGuidance.js"
Task: "T032 Add rel=noopener in frontend/src/components/GuidanceLinks.jsx"
```

## Implementation Strategy

### MVP First (User Story 1)

1. Phase 1, then Phase 2. Stop and confirm MCI is unchanged: the baseline and both golden tests pass.
2. Phase 3 (US1), which includes retrieval, export, and the schema check.
3. Stop and validate: run the migration on a `scans` server, then quickstart § A steps 1–6 and 10–12.

### Incremental Delivery

1. Foundation: no visible change except the Step 1 label.
2. Add US1: DEXA reviews are recorded, retrievable, and exportable (MVP).
3. Add US3: on-site instructions, with the MCI `.doc`/`.pdf` links removed. This can come before US2.
4. Add US2: correction controls.
5. Polish: docs, openapi, and the PR.
6. Rev. 4:
   - backend access (Phase 7);
   - then the quick wins: `/files` proxy and CSS (T052, T053);
   - then the queue and already-reviewed state (Phase 9);
   - then the `/events/edit` review content (Phase 10);
   - then openapi and the PR (Phase 11).
   Stop after Phase 9 to have the user re-check the review page on dev.

## Notes

- Commit after each task or logical group. Every refactor commit in Phase 2 must leave the T001 baseline green.
- Never write to `FILES_DIR` from code. `scans.html` is a file in the repo under `app/webroot/files/`.
- Never log region data or export rows above DEBUG (PHI rule).
- Keep the two copies of the scans constants (T017 and T026) identical. Each carries a keep-in-sync comment.
