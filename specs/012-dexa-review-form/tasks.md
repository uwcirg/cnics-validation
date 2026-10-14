# Tasks: DEXA scan review form and on-site reviewer instructions

**Input**: Design documents from `/specs/012-dexa-review-form/`
**Prerequisites**: plan.md (rev. 3), spec.md, research.md (D1–D11), data-model.md, contracts/review-api.md, contracts/review-ui.md, quickstart.md
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

## Notes

- Commit after each task or logical group. Every refactor commit in Phase 2 must leave the T001 baseline green.
- Never write to `FILES_DIR` from code. `scans.html` is a file in the repo under `app/webroot/files/`.
- Never log region data or export rows above DEBUG (PHI rule).
- Keep the two copies of the scans constants (T017 and T026) identical. Each carries a keep-in-sync comment.
