---
description: "Task list for 009-save-import-csv"
---

# Tasks: Archive Bulk-Import CSV Files

**Input**: Design documents from `/specs/009-save-import-csv/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/imports-api.yaml, quickstart.md

**Tests**: Test tasks are included. The spec does not request TDD, but the
constitution's Development Workflow & Quality Gates requires that new backend
endpoints ship with pytest coverage, and this feature adds three. Tests are
placed **after** the code they cover within each phase, matching how
`flask_backend/tests/` is already written — this repo does not practice
test-first. No frontend test infrastructure exists, so the new page is verified
manually per quickstart.md.

**Organization**: Grouped by user story so each is independently implementable,
testable, and shippable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel — different file, no dependency on incomplete work
- **[Story]**: US1 / US2 / US3, mapping to the spec's prioritized stories

## Path Conventions

Web application, per plan.md: Flask backend under `flask_backend/`, React SPA
under `frontend/src/`. All paths below are repository-root-relative.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Configuration plumbing. Both variables have working defaults, so
nothing downstream is blocked by these — they exist so operators can see and
override the new knobs.

- [X] T001 [P] Document `IMPORT_ARCHIVE_DIR` (default `<DOWNLOADS_DIR>/imports`) and `MAX_IMPORT_CSV_BYTES` (default `10485760`) in `default.env`, commented out at their defaults, following the existing block style with a note that archived CSVs contain PHI
- [X] T002 [P] Add `IMPORT_ARCHIVE_DIR: ${IMPORT_ARCHIVE_DIR:-}` and `MAX_IMPORT_CSV_BYTES: ${MAX_IMPORT_CSV_BYTES:-}` to the `backend` service's `environment:` block in `docker-compose.yaml` (near `UPLOAD_DIR` at line 47) — Compose does not auto-inject `.env` variables into containers

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The archive module's skeleton and the config resolution every
story builds on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T003 Create `flask_backend/import_archive.py` with the id and path primitives: `new_import_id()` returning `<YYYYMMDD>T<HHMMSS>Z-<8 hex>` from `datetime.now(timezone.utc)` and `uuid4().hex[:8]`; `IMPORT_ID_RE = re.compile(r'^\d{8}T\d{6}Z-[0-9a-f]{8}$')`; `is_valid_import_id(s)`; `ensure_archive_dir(dir)` creating with mode `0o750` and letting `OSError` propagate; `submission_path(dir, id)` and `record_path(dir, id)` which both validate the id first and assert the resolved real path is inside the resolved archive dir. No Flask import — this module must be testable without the app (plan.md Structure Decision, research D4/D6)
- [X] T004 In `flask_backend/app.py`, beside the `DOWNLOADS_DIR` block (lines 101-111), resolve `IMPORT_ARCHIVE_DIR` (env override, else `os.path.join(DOWNLOADS_DIR, 'imports')`) and `MAX_IMPORT_CSV_BYTES` (env override, else `10485760`). Do **not** pre-create the directory at import time and do **not** swallow `OSError` the way `DOWNLOADS_DIR` does — creation happens per-request so it can fail the request closed (research D3)
- [X] T005 Create `flask_backend/tests/test_import_archive.py` with unit tests for the Phase 2 primitives: `new_import_id()` matches the grammar and two calls differ; `is_valid_import_id` rejects `../../etc/passwd`, `/etc/passwd`, `20260813T144512Z-ZZZZZZZZ`, and the empty string; `submission_path` raises rather than returning a path outside the archive dir for each of those

**Checkpoint**: Id generation and path safety are proven. Story work can begin.

---

## Phase 3: User Story 1 - Every submitted file is preserved (Priority: P1) 🎯 MVP

**Goal**: Every CSV submitted at `/events/addMany` is written to disk verbatim
before it is parsed, whatever the import's outcome. Delivers FR-001, FR-002,
FR-004, FR-005, FR-006, FR-007, FR-008.

**Independent Test**: Upload a CSV and confirm a byte-for-byte identical copy
exists under `<DOWNLOADS_DIR>/imports/`. Repeat with a file that produces zero
events and confirm a copy is retained for it too. No interface is needed — this
is verifiable with `ls` and `md5sum` alone.

### Implementation for User Story 1

- [X] T006 [US1] Add `write_submission(dir, import_id, data)` to `flask_backend/import_archive.py`: `ensure_archive_dir`, then `os.open(path, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o640)` and write the raw bytes. Exclusive creation so an id collision raises instead of overwriting (FR-002). Let `OSError` propagate to the caller
- [X] T007 [US1] Rework the head of `events_bulk` in `flask_backend/app.py` (line 1249), preserving the existing parse and import logic below it verbatim (FR-008): read `request.files['events_csv']` into bytes; if `request.content_length` or the actual byte length exceeds `MAX_IMPORT_CSV_BYTES`, return 413 with a message naming the limit and the actual size, storing **no contents** but still writing a `refused` record (research D5 — the record itself is US2's `write_record`, so under US1 alone this path simply returns 413); generate an id and call `write_submission`, returning 500 `{'error': 'Import archive is not writable; no events were created'}` on `OSError` **before** any decode or parse (research D3); decode the bytes already in hand rather than re-reading the stream; add `import_id` to the response `data` on every path that returns one. Log failures with the import id and error class only — never the file name or contents (FR-007)
- [X] T008 [US1] Add archiving tests to `flask_backend/tests/test_import_archive.py`, monkeypatching `app_mod.IMPORT_ARCHIVE_DIR` to `tmp_path` and using the `admin_client` fixture: all rows valid → 201 and exactly one `.csv`; some rows invalid → the archived file contains the whole submission including the bad rows; every row invalid → 400 **and the file is still archived**; a non-UTF-8 body → 400 and still archived (proves the write precedes the decode); the same original filename submitted twice → two distinct files, neither overwritten; a body over `MAX_IMPORT_CSV_BYTES` → 413 with **no `.csv`** in the archive directory; `ensure_archive_dir` patched to raise `OSError` → 500 and no events created
- [X] T009 [P] [US1] Rewrite `test_bulk_csv_upload_does_not_persist_file` in `flask_backend/tests/test_app.py` (line 152) as `test_bulk_csv_upload_archives_rejected_file`, asserting a rejected submission leaves exactly one `.csv` in the archive directory. Its docstring must record that this reverses a deliberate prior decision. **Do not simply delete it or leave it alone** — its current assertion looks for a file literally named `events.csv`, which the new naming scheme never produces, so it would keep passing while asserting the opposite of shipped behavior (research D7)

**Checkpoint**: Bulk imports are auditable. Shippable on its own — the archive
has value with no interface at all.

---

## Phase 4: User Story 2 - The outcome of each import is preserved (Priority: P2)

**Goal**: A JSON manifest beside each archived CSV records submitter,
timestamp, original name, counts, and every skipped row with its reason.
Delivers FR-003.

**Independent Test**: Submit a CSV with a known mix of valid and invalid rows,
then confirm the sidecar `.json` names the submitter, the submission time, the
original file name, the count created, and one `errors` entry per skipped row
matching the text shown on screen.

### Implementation for User Story 2

- [X] T010 [US2] Add `build_record(...)` and `write_record(dir, import_id, record)` to `flask_backend/import_archive.py`, emitting the shape in data-model.md § Import record: `record_version: 1`, `import_id`, `submitted_at` (ISO 8601 UTC, `Z`-suffixed), `submitted_by_id`, `submitted_by`, `original_name` truncated to 255 chars, `size_bytes`, `outcome`, `file_available`, `imported_count`, `skipped_count`, `errors`. Derive `outcome` per the table in data-model.md: over the cap → `refused` with `file_available: false`; else `>0`/`0` → `imported`, `>0`/`>0` → `partial`, `0`/any → `rejected`, all with `file_available: true`
- [X] T011 [US2] Call `write_record` from `events_bulk` in `flask_backend/app.py` on **every** outcome — success, partial, the 400 rejection paths, and the 413 refusal, which writes a manifest with no `.csv` beside it so an oversize attempt is not invisible (research D5). Take `submitted_by_id` / `submitted_by` from `g.auth_user`. Wrap the call so a failure logs a warning carrying the import id and error class only and does **not** fail the request — the bytes are already safe and MyISAM cannot roll the events back to match (research D8)
- [X] T012 [US2] Add manifest tests to `flask_backend/tests/test_import_archive.py`: a partial import writes `outcome: partial` with `errors` exactly matching the response `errors`; an all-rejected submission writes `outcome: rejected` with `imported_count: 0`; a clean import writes `outcome: imported` with `errors: []`; an oversize submission writes `outcome: refused` with `file_available: false`, the actual size, and **no** `.csv` in the directory; the record carries the submitter id, login, and original file name; `write_record` patched to raise still returns the normal 201 and leaves the `.csv` in place

**Checkpoint**: Skipped-row reasons now outlive the toast. US1 and US2 both
work independently.

---

## Phase 5: User Story 3 - Administrators can review past imports (Priority: P3)

**Goal**: An admin-only page listing past imports newest-first, with skipped
rows and a download of the original submission. Delivers FR-009, FR-010, FR-011.

**Independent Test**: Perform two bulk imports, open `/events/imports`, and
confirm both appear newest-first with correct submitter, time, file name, and
event count, and that downloading either returns the file originally submitted.

### Backend for User Story 3

- [X] T013 [US3] Add `read_record(dir, import_id)` and `list_records(dir, limit, offset)` to `flask_backend/import_archive.py`: glob `*.csv` **and** `*.json` and take the **union** of stems — globbing only `*.csv` would hide every `refused` record, which has a manifest and no file (research D5) — then sort descending (chronological by the grammar, so ordering costs no manifest reads), slice, and read each sibling `.json`. On a missing or unparseable manifest synthesize the degraded record in data-model.md § Derived read model — `outcome: 'unknown'`, `incomplete: true`, with `submitted_at` recovered from the id and `size_bytes` from `stat` (research D8). Return `(records, total)`
- [X] T014 [US3] Add `GET /api/events/imports` to `flask_backend/app.py` with `@requires_auth` and `@requires_roles('admin')`, honoring `limit` (default 100, capped at 500) and `offset`, returning `{'data': [...], 'total': n}` per `contracts/imports-api.yaml`
- [X] T015 [US3] Add `GET /api/events/imports/<import_id>` to `flask_backend/app.py` with the same two decorators, returning `{'data': record}`. A malformed id returns 404, not 400 — invalid and missing must be indistinguishable to the caller (research D6)
- [X] T016 [US3] Add `GET /api/events/imports/<import_id>/file` to `flask_backend/app.py` with the same two decorators, streaming the stored bytes via `send_file` as `text/csv`, `as_attachment=True`, `download_name=f'{import_id}.csv'`. Return 404 when the record exists but its contents were never archived (a `refused` record, `file_available: false`). Use the generated id for the download name, never the submitted name — it is untrusted input in a response header and may carry identifying text. Do **not** add `.csv` to `ALLOWED_PACKET_EXTENSIONS`; that set governs event packets and must not gain a CSV entry
- [X] T017 [US3] Add the docstring YAML blocks from `contracts/imports-api.yaml` to all four handlers in `flask_backend/app.py` — the three new ones plus `events_bulk`, whose path is an empty `{}` placeholder at `openapi.json:149` today because it has no YAML block
- [X] T018 [US3] Regenerate the contract with `python -m flask_backend.generate_openapi` and commit the resulting `openapi.json`. Do not hand-edit it
- [X] T019 [US3] Add read-endpoint tests to `flask_backend/tests/test_import_archive.py`: three archived imports list newest-first; `limit`/`offset` page correctly and `total` is the full count; a `.csv` with no `.json` lists as `outcome: 'unknown'` with `incomplete: true` and is still downloadable; a `.json` with no `.csv` (a `refused` record) **appears in the list** and its `/file` request returns 404; `GET /api/events/imports/../../etc/passwd` and other malformed ids return 404 with no file read outside the archive; the download returns bytes identical to what was posted; a non-admin caller (per the `FakeUser` pattern in `flask_backend/tests/test_auth_header_and_roles.py`) gets 403 from all three endpoints

### Frontend for User Story 3

- [X] T020 [P] [US3] Create `frontend/src/pages/EventImports.jsx`: fetch `/api/events/imports` with `credentials: 'include'`, render through `components/DataTable.jsx` with columns Submitted, Submitted by, File, Events created, Skipped, Outcome; row selection reveals that import's skipped rows and, when `file_available` is true, a download link to `/api/events/imports/<id>/file`; label `incomplete` rows so a degraded record is not mistaken for a genuine zero-event import, and label `refused` rows as "too large — contents not kept" with no download offered. Follow the fetch and toast conventions in `frontend/src/pages/EventAddMany.jsx`
- [X] T021 [US3] Add the `/events/imports` route to `frontend/src/App.jsx` beside the `/events/addMany` route (line 165), wrapped in `<ProtectedRoute requiredRoles={['admin']}>`. Add **no** `/vte/imports` route and no copy under `frontend/src/studies/vte/` — the VTE bulk-import page posts to the same endpoint and is archived without frontend change (research D9)
- [X] T022 [P] [US3] Add a "View past CSV imports" link to `/events/imports` in the Events list of `frontend/src/pages/Admin.jsx`
- [X] T023 [P] [US3] Add the same link to the admin section of `frontend/src/pages/Home.jsx` (near the `/events/addMany` link at line 175)

**Checkpoint**: All three stories independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T024 [P] Verify FR-012 by inspection: `grep -rn "os.remove\|os.unlink\|shutil.rmtree" flask_backend/` must return no hit in `import_archive.py` or the new endpoints
- [X] T025 [P] Review every new log statement for the PHI rule: no file contents, no `original_name`, no skipped-row text, no patient identifier at INFO or above — id and error class only
- [X] T026 [P] Add a "Bulk-import archive" section to `docs/file-handling-improvements.md` covering the storage layout, the id grammar, and the two new environment variables
- [ ] T027 Run the full `quickstart.md` walkthrough on the deployed stack, including §3 fail-closed (`chmod a-w` the archive directory → import refused, zero events created) and §2 step 7 (non-admin gets 403)
- [ ] T028 In the PR description, state that this affects **all studies** (shared code) per the constitution's change-review gate, and call out the fail-closed trade-off so a reviewer confirms it deliberately

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies. Both tasks are pure documentation/config with working defaults, so they can also land last without blocking anything
- **Foundational (Phase 2)**: T003 and T004 block every story. T005 depends on T003
- **US1 (Phase 3)**: Depends on Phase 2. Blocks nothing else logically, but US2 and US3 have nothing to record or list until it lands
- **US2 (Phase 4)**: Depends on Phase 2. Independently testable, though in practice sequenced after US1
- **US3 (Phase 5)**: Depends on Phase 2. Its list renders degraded records for archives with no manifest, so it works even if US2 has not shipped
- **Polish (Phase 6)**: After the stories being shipped are complete

### Within-Phase Dependencies

- T005 after T003
- T007 after T003, T004, T006 — T007 is the only task touching `events_bulk` in US1
- T008 after T007; T009 is [P] with T008 (different file)
- T011 after T010; T012 after T011
- T014, T015, T016, T017, T018 are strictly sequential — all edit `flask_backend/app.py`
- T018 after T017 (regeneration reads the docstrings T017 writes)
- T019 after T016
- T021 after T020 (the route imports the component)
- T022 and T023 are [P] with each other and with all backend work

### Parallel Opportunities

- T001 and T002 together (Setup)
- T008 and T009 together (US1 tests, different files)
- T020, T022, T023 together, and all three in parallel with the US3 backend chain
- T024, T025, T026 together (Polish)
- Backend tasks in `flask_backend/app.py` are never parallel with each other — one file, one editor

---

## Parallel Example: User Story 3

```bash
# Frontend and backend proceed independently once T013 defines the read model:
Task: "Create frontend/src/pages/EventImports.jsx"          # T020
Task: "Add link in frontend/src/pages/Admin.jsx"            # T022
Task: "Add link in frontend/src/pages/Home.jsx"             # T023
# ...while the app.py chain runs sequentially: T014 → T015 → T016 → T017 → T018
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 2 Foundational (T003–T005)
2. Phase 3 US1 (T006–T009)
3. **STOP and VALIDATE**: submit a CSV, confirm the archived copy matches by
   `md5sum`, confirm a rejected submission is archived too
4. Shippable. The unauditable-import problem is solved at this point; the rest
   is convenience

### Incremental Delivery

1. Setup + Foundational → primitives proven
2. US1 → bytes preserved → **MVP**
3. US2 → outcomes preserved alongside them
4. US3 → retrievable without server access
5. Polish → docs, log review, quickstart run

Each increment is additive: US2 writes a new file type into a directory US1
already owns, and US3 only reads. Nothing in US2 or US3 changes what US1 wrote,
so a stall at any checkpoint leaves a coherent system.

---

## Notes

- The fail-closed behavior in T007 is the one flagged assumption in the spec.
  It makes a storage fault block CSV event creation. Confirm it before T007
  rather than after — reversing it later means touching the endpoint again
- Nothing here varies by study: no `STUDY_TYPE` branch, no new flag in
  `flask_backend/study_config.py`, no VTE copy
- `MAX_IMPORT_CSV_BYTES` must never become Flask's global `MAX_CONTENT_LENGTH`
  — that would cap event-packet uploads, which are legitimately large ZIPs
- Out of scope and deliberately untouched: `GET /api/events/download/<event_id>`
  (`flask_backend/app.py:1059`) has `@requires_auth` but no role decorator, so
  any authenticated user can fetch any event's packet. Worth its own issue
