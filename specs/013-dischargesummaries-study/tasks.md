---
description: "Task list for 013-dischargesummaries-study"
---

# Tasks: Add the `dischargesummaries` Study Type

**Input**: Design documents from `/specs/013-dischargesummaries-study/`
**Prerequisites**: plan.md, spec.md, research.md (D1–D12), data-model.md, contracts/api-and-config.md, contracts/ui.md, quickstart.md

**Tests**: Included. research.md D12 and plan.md name the test files. The
project keeps a pytest suite per feature, and SC-006 requires the existing
suites to stay green.

**Organization**: Tasks are grouped by user story (spec.md US1–US4). The
**Phase B** section (the review form) is **BLOCKED on FR-011**: the review
questions are not known yet. Do not start it until the study owner supplies
them. Phase A (Phases 1–7) is complete and shippable without it.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: US1–US4 from spec.md
- Paths are relative to the repo root `/home/debadmin/cnics-validation/`

## Ground rules for every task

- Run backend tests with **system** `python3 -m pytest flask_backend/tests` (the `.venv` lacks pytest). Baseline before this feature: **309 passed**.
- Frontend checks are `npm run lint` and `npm run build` in `frontend/` only. **Never install packages.**
- Name study files by `STUDY_TYPE` (`dischargesummaries`), never by a domain word. Domain words ("discharge summary") go only in user-facing text.
- No `if study == ...` in shared modules (Constitution IV). Study differences go through the workflow flags, the keyed maps (`_PROFILES`, `STUDY_GUIDANCE`, `REVIEW_FORMS`) or the per-study files.
- New links target `/events/*` only. Do not copy the `studies/vte` / `isVtePage` pattern.
- Where a task changes shared behavior, add a code comment of one or two lines naming the spec decision (e.g. `# spec 013 D3`). Do not narrate.

---

## Phase 1: Setup

- [X] T001 Confirm the baseline: run `python3 -m pytest flask_backend/tests -q` and record the pass count (expect 309) in a comment at the top of `specs/013-dischargesummaries-study/tasks.md` under "Baseline". Run `npm run lint && npm run build` in `frontend/` and record whether they pass before any change.

---

## Phase 2: Foundational (blocks every user story)

**Purpose**: make `STUDY_TYPE=dischargesummaries` resolve to its own profile,
schema and study module, so nothing falls back to `mci`.

- [X] T002 [P] Add the `dischargesummaries` workflow profile in `flask_backend/study_config.py`. Define `_DISCHARGESUMMARIES_PROFILE = {"scrubbing": True, "screening": False, "sending": False, "reviewer_count": 1}` and add it to `_PROFILES` under the key `"dischargesummaries"`. Update the module docstring sentence "``scans`` bypasses every optional stage; every other (or unset) study runs the conservative full-workflow profile" to mention the new profile (research D1).
- [X] T003 [P] Create `init/studies/dischargesummaries.sql`, modeled on `init/studies/scans.sql` (same header comment style, InnoDB, utf8mb4, `CREATE TABLE IF NOT EXISTS`). Do **not** put `-- DORMANT` on line 1. Create `reviews` with `id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY`, `event_id int(11) NOT NULL`, `reviewer_id int(11) NOT NULL`, `created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`, `UNIQUE KEY event_reviewer (event_id, reviewer_id)`, `KEY event_id (event_id)`, and `COMMENT='dischargesummaries: one row per completed review'`. The header comment must say: Phase A holds only the shared columns, answer columns arrive with the review form (spec 013 FR-011, research D5), and there is no `event_derived_datas` because there is one reviewer.
- [X] T004 [P] Create `flask_backend/models/studies/dischargesummaries.py`, the Phase A study module, following the interface in `flask_backend/models/studies/__init__.py` and the structure of `scans.py`:
  - module docstring explaining Phase A (research D5);
  - its own `DischargesummariesBase(DeclarativeBase)`;
  - `Reviews` ORM class matching T003;
  - `REVIEW_FORM = "dischargesummaries"`, `REVIEW_TABLES = ("reviews",)`;
  - `parse_review(data)`, which always raises `ReviewValidationError("The discharge-summary review form is not available yet.")`;
  - `add_review(...)`, which raises `RuntimeError` (unreachable, because `parse_review` refuses);
  - `get_reviews(session, event_id)`, which returns `[]`;
  - `export_csv(session, writer)`, which writes the heading row `["Event ID", "Patient ID", "Site", "Site Patient ID", "Event Date", "Status", "Reviewer", "Review Date"]` and one row per event using the same join as scans' `_EXPORT_SQL` minus the region columns;
  - `check_schema(session)`, which returns `None`.
- [X] T005 [P] Create `frontend/src/studies/dischargesummaries/DischargesummariesReviewForm.jsx` (props `{ eventId, apiUrl }`, like `ScansReviewForm`). It renders a `<p role="status">` reading "The review form for this study is not available yet." with no inputs and no submit button. Also create `frontend/src/studies/dischargesummaries/DischargesummariesReviewSummary.jsx` (props `{ review }`), which renders `<div>No review recorded</div>`. Each file gets a header comment naming spec 013 Phase A (research D5, contracts/ui.md).
- [X] T006 Register both components in `frontend/src/components/review/reviewForms.js`: add the imports and the `dischargesummaries` key to `REVIEW_FORMS` and `REVIEW_SUMMARIES` (depends on T005).
- [X] T007 [P] Add tests to `flask_backend/tests/test_study_config.py`: with `STUDY_TYPE=dischargesummaries` and no overrides, `get_workflow_config()` returns `scrubbing=True, screening=False, sending=False, reviewer_count=1`; an explicit `ENABLE_SCREENING=true` override still wins. Then add tests to `flask_backend/tests/test_study_modules.py`: `get_study_module("dischargesummaries")` returns the `dischargesummaries` module, logs no fallback warning, and exposes every interface name (`Reviews`, `REVIEW_FORM`, `REVIEW_TABLES`, `parse_review`, `add_review`, `get_reviews`, `export_csv`, `check_schema`).

**Checkpoint**: `python3 -m pytest flask_backend/tests` passes, and the
frontend builds.

---

## Phase 3: User Story 1 — Upload → scrub → assign → review queue (P1) 🎯 MVP

**Goal**: one event moves from "needs document" to "assigned to one
reviewer", and each role can act only on its own step. The three shared
guards (research D2, D3, D4) enforce this on the server.

**Independent test**: with the dischargesummaries profile, a same-site
uploader uploads, an admin scrubs and assigns, and the reviewer sees the
event and can download only the scrubbed file. Cross-site upload,
re-upload after scrub, assign-before-scrub, and unassigned or other-site
downloads are all refused. In Phase A the review submission is refused with
the "not available yet" message.

### Tests for US1 (write first; they must fail before T013–T016)

- [X] T008 [P] [US1] Create `flask_backend/tests/test_dischargesummaries_workflow.py`, modeled on `flask_backend/tests/test_scans_workflow.py` (same `mock_get_session` style and `monkeypatch.setenv("STUDY_TYPE", "dischargesummaries")`). Cover:
  - `table_service._ready_to_assign_predicate()` contains `scrub_date IS NOT NULL`, does not contain `screen_date`, and contains `assign_date IS NULL`;
  - `get_to_be_screened_with_total` returns the empty phase and `get_to_be_scrubbed_with_total` does not;
  - `get_events_for_review` gates on status `assigned`;
  - first-slot assignment sets status `assigned`;
  - assigning with `slot="second"` returns 400 (single reviewer).
- [X] T009 [P] [US1] Extend `flask_backend/tests/test_assign_many.py` (research D2, contracts/api-and-config.md):
  - under the dischargesummaries profile, `POST /api/events/assign_many` with `slot:"first"` returns 400 and changes no event when any event in the batch has `upload_date` or `scrub_date` NULL, and the error message lists the offending ids;
  - a fully scrubbed batch succeeds;
  - under the `scans` profile (scrubbing off), an uploaded-but-unscrubbed event **is** assignable;
  - under the full `mci` profile, an unscreened event is refused.
- [X] T010 [P] [US1] Add upload-guard tests (research D3) in a new `flask_backend/tests/test_upload_guard.py`:
  - a non-admin same-site uploader gets 200 for status `created` and `uploaded`;
  - the same uploader gets 409 with the error "This event has moved past upload; ask an administrator." for `scrubbed`, `assigned` and `done`, and the event's fields are unchanged;
  - an admin gets 200 for `scrubbed`;
  - the existing 403 (wrong site) and 400 (bad extension) still win over 409.
- [X] T011 [P] [US1] Extend `flask_backend/tests/test_event_download.py` with the access table from research D4 and contracts/api-and-config.md. Parametrize over admin, same-site uploader, other-site uploader, assigned reviewer (`reviewer1_id`), unassigned reviewer, and a user with no role flags. Run each case with scrubbing on (scrubbed file present; raw only) and scrubbing off (raw only). Check for each case: 200 with the right file, 403 `{"error": "You do not have access to this event's packet"}`, or 404.
- [X] T012 [P] [US1] Extend `flask_backend/tests/test_review_endpoint.py`: with `STUDY_TYPE=dischargesummaries`, a `POST /api/events/<id>/review` from the assigned reviewer returns 400 with "The discharge-summary review form is not available yet.", writes no row, and leaves the status unchanged. `GET /api/events/<id>/review` returns an empty reviews list.

### Implementation for US1

- [X] T013 [US1] Assignment guard in `flask_backend/table_service.py` (research D2):
  - refactor `_ready_to_assign_predicate()` so the enabled-stage terms (upload, plus scrub and screen when on) come from a helper, e.g. `_pre_assignment_terms(cfg)`, reused by both;
  - in `assign_events`, when `slot == "first"`, check every loaded event in Python against the same flags: `upload_date` set, `scrub_date` set if scrubbing, `screen_date` set if screening. If any fail, raise `ValidationError(f"These events are not ready for assignment: {ids}")` **before** changing any row;
  - do not check `assign_date`;
  - add a comment `# spec 013 D2`.
- [X] T014 [US1] Upload guard in `flask_backend/app.py` `events_upload_raw` (research D3): after the `_uploader_may_act_on_event` check and before saving the file, if the caller is not an admin and `e.status not in ('created', 'uploaded')`, return `409 {'error': 'This event has moved past upload; ask an administrator.'}`. Do not save the file in that case. Add the observed-behavior note from D3 to the docstring.
- [X] T015 [US1] Download access in `flask_backend/app.py` (research D4):
  - add `_may_download_event_packet(session, event, auth_user, raw: bool) -> bool`. It returns true for an admin, or when `_uploader_may_act_on_event` holds and the user is an uploader. It returns true for a reviewer only when `auth_user['id']` equals one of `reviewer1_id`, `reviewer2_id` or `reviewer3_id` **and** (`raw` is false, or scrubbing is off);
  - in `events_download`, load the event (404 if missing), resolve the scrubbed path, then the raw path, and choose the file per the contract table. Return 403 `{'error': "You do not have access to this event's packet"}` when a file exists but the caller may not have it;
  - replace the `_may_receive_raw_packet` call with the new helper, and remove `_may_receive_raw_packet` if nothing else uses it (grep first);
  - put the D4 observed-behavior note in the docstring.
- [X] T016 [US1] Surface the new refusals in the UI (contracts/ui.md "Errors surfaced"): make sure `frontend/src/pages/EventUpload.jsx` and `frontend/src/pages/EventReupload.jsx` show the server's `error` text for a 409. Make sure the download link on `frontend/src/pages/EventReview.jsx` (and wherever `/api/events/download/` is linked; grep `frontend/src`) shows "You do not have access to this event's document." on 403 instead of failing silently. Change only what does not already display the server error.
- [X] T017 [US1] Run `python3 -m pytest flask_backend/tests`. T008–T012 now pass, and all pre-existing tests pass unchanged. If any pre-existing test relied on the old open download or upload behavior, update it only when the test asserted the gap itself, and note the change in the PR description (T036).

**Checkpoint**: US1 works with the API alone; Phase A refuses the review
submission by design.

---

## Phase 4: User Story 2 — Interface matches the workflow (P2)

**Goal**: scrub is visible; screening, sending, second and third review are
not. Wording names the discharge-summary study.

**Independent test**: with the dischargesummaries config, walking every page
as admin, uploader and reviewer shows no screen, send or second/third-review
element. Headings read "… Discharge Summary <id>". The banner shows
`STUDY_TITLE`.

- [X] T018 [P] [US2] Add `STUDY_LABEL` to `get_study_label()` in `flask_backend/study_config.py` (research D6). If `os.getenv("STUDY_LABEL", "").strip()` is non-empty, return it verbatim. Otherwise keep the current behavior. Update the docstring examples, and keep the "unset → empty" rule for `STUDY_TYPE`.
- [X] T019 [P] [US2] Tests in `flask_backend/tests/test_study_config.py` and `flask_backend/tests/test_emailer_study_naming.py`:
  - `STUDY_LABEL="Discharge Summary"` yields that exact label, and the assignment email subject/body use it;
  - blank `STUDY_LABEL` falls back to upper-cased `STUDY_TYPE`;
  - `GET /api/config` (`flask_backend/tests/test_config_endpoint.py`) reports `study_label`, `review_form: "dischargesummaries"` and the workflow flags from contracts/api-and-config.md.
- [X] T020 [P] [US2] Add the `dischargesummaries` entry to `STUDY_GUIDANCE` in `frontend/src/components/reviewGuidance.js` (research D7):
  - `packets.items`: `['The hospital discharge summary for the event', 'Please redact personal identifiers (name, birth date, medical record number) before uploading']`, `links: []`;
  - `instructions`: `{ items: [], links: [] }`, with a comment that Phase B adds `/files/instructions/dischargesummaries.html`;
  - `scrubbing`: `{ items: [], links: [] }`, with a comment that no scrubbing protocol document exists yet.
- [X] T021 [US2] Check, by reading the code (no local stack), that the admin pages render exactly the lists in contracts/ui.md for `workflow = {scrubbing:true, screening:false, sending:false, reviewer_count:1}`: To Be Uploaded, To Be Scrubbed, To Be Assigned, awaiting review, done, and **no** To Be Screened, To Be Sent or second/third-reviewer columns or actions. Files: `frontend/src/pages/EventViewAll.jsx`, `frontend/src/pages/EventAssignMany.jsx`, `frontend/src/components/MenuBar.jsx`, `frontend/src/pages/Home.jsx`, `frontend/src/pages/EventEdit.jsx`. Fix only places that key on a study name or on a combination of flags instead of the single relevant flag. Record the result in the PR description, including "no change needed" where that is the finding.
- [X] T022 [US2] Check that the post-upload confirmation in `frontend/src/pages/EventUpload.jsx` names scrubbing as the next step when `workflow.scrubbing` is true (PR #108 logic). Check that the scrub-page and review-page headings use `headingSuffix(studyLabel, id)` from `frontend/src/components/studyHeading.js`. Change nothing if both already hold.
- [X] T023 [US2] Run `npm run lint && npm run build` in `frontend/`. Both pass.

**Checkpoint**: US2 holds under the dischargesummaries config, and the UI is
unchanged for `mci`/`scans`.

---

## Phase 5: User Story 3 — Deploy beside `scans` on the same VM (P2)

**Goal**: an operator can bring up `dischargesummaries` on the dev and prod
scans VMs from `.env` alone, sharing the prod SSH tunnel.

**Independent test**: `docker compose config` shows the interpolated network
values, and the defaults equal today's. Following the docs alone, an
operator can produce the quickstart `.env`.

- [X] T024 [P] [US3] In `docker-compose.yaml` `networks.internal.ipam.config`, replace the literals with `subnet: ${INTERNAL_SUBNET:-172.31.222.0/24}` and `gateway: ${INTERNAL_GATEWAY:-172.31.222.1}` (research D8). Rewrite the comment above it: the range is set per deployment in `.env`, the defaults are unchanged, and side-by-side deployments must use non-overlapping ranges. Remove the "change both here" instruction.
- [X] T025 [P] [US3] Update `default.env`:
  - add `dischargesummaries` to the recognized `STUDY_TYPE` values, with one line on its profile (scrub on, screen off, send off, one reviewer);
  - document `STUDY_LABEL` (optional; shown in headings and emails; e.g. `Discharge Summary`) next to `STUDY_TITLE`;
  - document `INTERNAL_SUBNET` / `INTERNAL_GATEWAY` near `COMPOSE_PROJECT_NAME` and the ports, saying they must differ per deployment on one host;
  - in the tcp-mode bridge block, note that `CNICS_DATA_DB_HOST` is where the tunnel listens. It may be another deployment's gateway when a tunnel is shared; see `docs/cnics_data-bridge.md`.
- [X] T026 [P] [US3] Add a "Sharing one tunnel between deployments on the same VM" section to `docs/cnics_data-bridge.md` (research D9):
  - the new stack keeps `CNICS_DATA_DB_HOST=172.31.222.1`;
  - the extra `iptables -I INPUT ... -s 172.31.223.0/24 -d 172.31.222.1 -p tcp --dport 13306 -j ACCEPT` rule, kept durable in puppet;
  - why INPUT rather than FORWARD;
  - the `docker compose down` vs `stop` dependency on the scans network;
  - the optional rebind to `172.17.0.1` as an operator decision;
  - a troubleshooting row for errno 110 from the second stack.
  
  Also update the "Keep these four references to `172.31.222.x` in lockstep" note to refer to `INTERNAL_SUBNET`/`INTERNAL_GATEWAY`.
- [X] T027 [P] [US3] Add a `dischargesummaries` worked example to `docs/template-setup-guide.md`, next to the scans one: the `.env` from `specs/013-dischargesummaries-study/quickstart.md` §2, the startup checks in §3–4, the Apache vhost note in §5, the data-isolation check before go-live in §6 (required by the constitution's "Data isolation audits" gate), and the prod gate (no reviewers until Phase B). Follow the memory rule for variant docs: show only what differs from the canonical template.
- [X] T028 [P] [US3] Update `README.md`: add `dischargesummaries` to the study list and add `STUDY_LABEL`, `INTERNAL_SUBNET` and `INTERNAL_GATEWAY` to the settings table, with links to the guide and bridge doc.
- [X] T029 [US3] If `docker` is available locally, run `docker compose config` twice: once with no `INTERNAL_*` set (expect `172.31.222.0/24` / `172.31.222.1`) and once with `INTERNAL_SUBNET=172.31.223.0/24 INTERNAL_GATEWAY=172.31.223.1` (expect those). If docker is not available, say so in the PR description and list the command for the operator.

**Checkpoint**: docs and compose are ready for the operator.

---

## Phase 6: User Story 4 — Existing studies unchanged (P3)

**Goal**: `mci` and `scans` keep working; the only intended differences are
the documented D2–D4 tightenings.

**Independent test**: the full pytest suite passes. The D2–D4 tests pass
under the `mci` and `scans` profiles too.

- [X] T030 [P] [US4] Make sure T009–T011 include `scans` and `mci` cases. Add any that are missing: a scans assigned reviewer can still download the raw packet (scrubbing off); an mci assigned reviewer gets only the scrubbed packet; an mci same-site uploader can still replace an `uploaded` packet.
- [X] T031 [P] [US4] Confirm that `flask_backend/tests/test_mci_export_golden.py` and `flask_backend/tests/test_scans_review.py` pass unchanged. Confirm that `init/04-study-schema.sh` needs no change: it picks up `dischargesummaries.sql` because the file exists and is not marked dormant.
- [X] T032 [US4] Run `python3 -m pytest flask_backend/tests`. Everything passes, and the count is 309 plus the new tests.

---

## Phase 7: Polish & cross-cutting (Phase A)

- [X] T033 Update the swagger YAML docstrings in `flask_backend/app.py` for every changed response:
  - `GET /api/config`: `dischargesummaries` as a `review_form` value;
  - `POST /api/events/assign_many`: the new 400 "not ready for assignment" refusal (D2);
  - `POST /api/events/<id>/upload_raw`: the new 409 (D3);
  - `GET /api/events/download/<id>`: the new 403 (D4).
  
  Then **regenerate `openapi.json`** from the repository root with `python3 -m flask_backend.generate_openapi`. This is required by the constitution's "API contracts" gate. Commit the regenerated `openapi.json` in the same PR, and confirm with `git diff openapi.json` that it shows the new responses.
- [X] T034 [P] Grep for docs or comments that say there are exactly two usable studies ("mci, scans") and add `dischargesummaries` where the list is meant to be complete: `flask_backend/models/studies/__init__.py` docstring, `frontend/src/components/review/reviewForms.js` comment, `default.env`.
- [X] T035 Walk through `specs/013-dischargesummaries-study/quickstart.md` against the final code and settings names, and fix any drift. Make sure §6 (data-isolation check) uses the real container and service names from `docker-compose.yaml` and the real `.env` variable names.
- [X] T036 Write `specs/013-dischargesummaries-study/pr-description.md`:
  - summary;
  - **behavior changes for mci and scans** (D2 assign guard, D3 re-upload guard, D4 download access), each with its observed-before behavior;
  - new settings;
  - the T021/T022 findings;
  - test counts;
  - the operator checklist (prod firewall rule, scans `down` dependency, prod gate until Phase B, and the **data-isolation check before go-live**, quickstart §6);
  - the D11 note that reviewers get no assignment email when sending is off.
  
  End with the Claude Code attribution line.

---

## Phase B: Review form (BLOCKED on FR-011, the review questions)

**Do not start until the study owner supplies the questions, allowed
answers and required/optional rules.** Then:

1. Run `/speckit.clarify` to fill FR-011 in `spec.md`.
2. Update `data-model.md` (the "Phase B" section) and `contracts/api-and-config.md` (the review body and export columns).
3. Replace these placeholder tasks with concrete ones.

- [ ] T037 [US1] BLOCKED: define the answer columns, or child tables if answers repeat, in `init/studies/dischargesummaries.sql`.
- [ ] T038 [US1] BLOCKED: write `init/migrations/013-dischargesummaries-reviews.sh`, modeled on `init/migrations/012-scans-reviews.sh`. It refuses unless `STUDY_TYPE=dischargesummaries`, checks that `reviews` is empty (guaranteed by Phase A), then applies the new DDL idempotently.
- [ ] T039 [US1] BLOCKED: implement real `parse_review`, `add_review`, `get_reviews`, `export_csv` (answer columns) and `check_schema` (detect an un-migrated Phase A table and name the migration) in `flask_backend/models/studies/dischargesummaries.py`.
- [ ] T040 [US1] BLOCKED: build the review form in `frontend/src/studies/dischargesummaries/DischargesummariesReviewForm.jsx`, mirroring the backend validation, with per-field errors and accessibility matching `ScansReviewForm.jsx`. Build the read-only summary in `DischargesummariesReviewSummary.jsx`.
- [ ] T041 [US2] BLOCKED: write the reviewer instructions page at `app/webroot/files/instructions/dischargesummaries.html` (self-contained, no scripts, like `scans.html`). Link it in the `instructions` box of the `dischargesummaries` entry in `frontend/src/components/reviewGuidance.js`.
- [ ] T042 BLOCKED: write tests: `flask_backend/tests/test_dischargesummaries_review.py` (validation, storage, export, check_schema); update T012 to expect success; end-to-end lifecycle to `done`.
- [ ] T043 BLOCKED: update `pr-description.md` and `docs/template-setup-guide.md` to lift the prod gate and add the migration step for existing Phase A databases.

---

## Dependencies & execution order

```text
Phase 1 (T001)
   └─> Phase 2 Foundational (T002–T007)  ── blocks all stories
          ├─> Phase 3 US1 (T008–T017)    🎯 MVP
          ├─> Phase 4 US2 (T018–T023)    independent of US1
          ├─> Phase 5 US3 (T024–T029)    independent of US1/US2 (compose + docs)
          └─> Phase 6 US4 (T030–T032)    after US1 (verifies its guards across profiles)
                 └─> Phase 7 Polish (T033–T036)
Phase B (T037–T043)  ── BLOCKED on FR-011; after Phase A is merged
```

Within US1, tests T008–T012 come before implementation T013–T016. T013, T014
and T015 touch different functions, but T014 and T015 are both in
`app.py`, so do them one after the other. T016 follows T014 and T015. T017
is last.

Within Phase 2, T006 depends on T005; the rest are parallel.

## Parallel opportunities

- **Phase 2**: T002, T003, T004, T005 and T007 run together (different files).
- **US1 tests**: T008, T009, T010, T011 and T012 run together.
- **US2**: T018, T019 and T020 together; then T021 and T022.
- **US3**: T024–T028 run together (compose, `default.env`, three docs).
- **Across stories**: once Phase 2 is done, US2 and US3 can proceed alongside US1.

Example, US3 in parallel:

```text
Task: "T024 Interpolate INTERNAL_SUBNET/INTERNAL_GATEWAY in docker-compose.yaml"
Task: "T025 Document new settings in default.env"
Task: "T026 Shared-tunnel section in docs/cnics_data-bridge.md"
Task: "T027 Worked example in docs/template-setup-guide.md"
Task: "T028 README study list and settings"
```

## Implementation strategy

1. **MVP**: Phases 1–3. Server-side, the study resolves correctly and the
   lifecycle and privacy rules hold.
2. Add US2 (UI and wording) and US3 (deployment docs and compose). Deploy to
   the **dev** VM beside scans and run the quickstart smoke test, steps 1–5
   and 7.
3. US4 and polish, then a PR for Phase A. Prod may host upload and scrub
   only.
4. When the review questions arrive, do Phase B, migrate dev and prod, and
   lift the prod gate.

## Baseline

Recorded by T001 on 2026-10-23, before any change (branch `013-dischargesummaries-study` @ a2b444f):

- Backend: `python3 -m pytest flask_backend/tests -q` → **309 passed**, 1 warning.
- Frontend: `npm run build` **passes**. `npm run lint` **already fails**: 31 problems (25 errors, 6 warnings), all in files outside this feature's scope (`DevAuthTester.jsx`, `MenuBar.jsx`, `ReviewQueue.jsx`, `EventDownload.jsx`, `EventEdit.jsx`, `EventUpload.jsx`, `EventViewAll.jsx`, `Home.jsx`, and `studies/vte/*`). The bar for this feature is: no new lint problems.
