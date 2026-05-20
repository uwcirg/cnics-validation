---
description: "Task list for 003-scans-study"
---

# Tasks: Implement the `scans` Study Type (Selective-Bypass Workflow)

**Input**: Design documents from `/home/debadmin/cnics-validation/specs/003-scans-study/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/workflow-api.md, quickstart.md

**Tests**: Test tasks ARE included — the plan's Constitution Check (Quality Gates)
explicitly requires integration tests for the new endpoints exercising their
role decorators, and config-layer validation tests. They are listed first
within each story phase per the template's TDD ordering note; because the
shared configuration layer is a Phase 2 (Foundational) blocker, its tests in
Phase 3 are written against the already-built layer.

**Organization**: Tasks are grouped by user story (US1–US4) to enable
independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1, US2, US3, US4 — maps to the spec's user stories
- All paths are absolute from the repository root `/home/debadmin/cnics-validation/`

## Path Conventions

Web application: Flask backend in `flask_backend/`, React SPA in `frontend/src/`.
No new top-level directories — `scans` is pure selective bypass (spec Assumptions).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish a clean baseline before any change.

- [ ] T001 Capture the SC-006 baseline: run `pytest flask_backend/tests/` from the repo root on branch `003-scans-study` and record that all pre-existing tests pass before any change.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Build the shared configuration layer that EVERY user story depends on — US1 (backend reads it), US2 (`/api/config` serves it), US3 (documents its controls), US4 (its full-workflow defaults are the regression guarantee).

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T002 Repurpose `flask_backend/study_config.py` as the shared configuration layer: remove the `SCAFFOLDING ONLY` banner and implement `get_workflow_config()` returning an immutable `{ study_type, scrubbing, screening, sending, reviewer_count }`; resolve `STUDY_TYPE` plus the four controls with per-study default profiles (`scans` → `scrubbing=false, screening=false, sending=false, reviewer_count=1`; anything else/unset → `true/true/true/2`) and per-control `.env` override of the profile (FR-002, FR-004, FR-006; research Decision 2; data-model.md "Default profiles").
- [ ] T003 Add fail-fast startup validation inside `get_workflow_config()` in `flask_backend/study_config.py`: parse the boolean controls only from the recognized token set `true/false/1/0/yes/no` (case-insensitive) and `REVIEWER_COUNT` as an integer in `{1, 2}`; on any invalid value raise an error naming the offending variable and value, never silently coercing (FR-005; research Decision 3; contracts "Startup contract").
- [ ] T004 Import the shared configuration layer at module top of `flask_backend/app.py` and invoke `get_workflow_config()` during app construction so an invalid config raises before the WSGI app object exists and serves no request (FR-005, SC-004; research Decision 3).

**Checkpoint**: `get_workflow_config()` resolves a validated config and a bad config aborts startup — user stories can now begin.

---

## Phase 3: User Story 1 - Run a complete scan-validation from upload to completion (Priority: P1) 🎯 MVP

**Goal**: A `scans` deployment carries one event straight through `created → uploaded → assigned → reviewer1_done → done` — no scrubbing, screening, sending, or second-reviewer ceremony — by building the shared assign→review→done machinery gated on the four controls.

**Independent Test**: Stand up a deployment configured as a `scans` study, carry one event from upload through single-reviewer adjudication to `done`, and confirm no scrubbing/screening/send step was required and no second reviewer was needed (spec US1 Independent Test).

### Tests for User Story 1

> Written against the Phase 2 configuration layer and the endpoints/queries built below; new files, so they do not modify any pre-existing test (SC-006).

- [ ] T005 [P] [US1] Configuration-layer tests in new `flask_backend/tests/test_study_config.py`: `scans` profile resolves to `false/false/false/1`; unset/other study resolves to `true/true/true/2`; an explicit per-control `.env` value overrides the profile; `REVIEWER_COUNT=3` raises a startup error naming the variable; a malformed boolean token raises (FR-004, FR-005, FR-006).
- [ ] T006 [P] [US1] Integration test for `POST /api/events/<id>/review` in new `flask_backend/tests/test_review_endpoint.py`: enforces `@requires_auth` + `@requires_any_role('reviewer','admin')`; reviewer 1 submission sets `review1_date` and `status='reviewer1_done'`; with `reviewer_count==1` the same call advances `status='done'`; `403` for a non-assigned submitter, `404` for a missing event, `400` for an invalid body (contracts "POST /api/events/{event_id}/review").
- [ ] T007 [P] [US1] Flag-aware lifecycle test in new `flask_backend/tests/test_scans_workflow.py`: assignment sets `status='assigned'`; send sets `status='sent'` only when `sending` is enabled; with `sending` disabled an `assigned` event is reviewer-queue eligible; with scrubbing+screening disabled an `uploaded` event is assignment-eligible without `scrubbed`/`screened` (FR-007, FR-008, FR-009, FR-011).

### Implementation for User Story 1

- [ ] T008 [US1] In `assign_events` in `flask_backend/table_service.py`, set `status='assigned'` on first-reviewer assignment in addition to the existing `reviewerN_id`/`assign_date`/`assigner_id` writes (research Decision 4; contracts "CHANGED POST /api/events/assign_many").
- [ ] T009 [US1] In `send_events` in `flask_backend/table_service.py`, set `status='sent'` in addition to the existing `send_date`/`sender_id` writes and emails (research Decision 4; contracts "CHANGED POST /api/events/send_many").
- [ ] T010 [US1] Make the eligibility/queue queries in `flask_backend/table_service.py` flag-aware via `get_workflow_config()`: the "ready to assign" predicate = all *enabled* pre-assignment stages complete AND `assign_date IS NULL`; `get_events_awaiting_review`/`get_events_for_review` include `status='assigned'` events for the assigned reviewer when `sending` is disabled (keep `status='sent'` when enabled); `by_status` phase mapping surfaces no queue for bypassed phases — no branching on `STUDY_TYPE` (FR-003, FR-007, FR-008, FR-009, FR-011; research Decision 6).
- [ ] T011 [US1] Add `POST /api/events/<int:event_id>/review` to `flask_backend/app.py` with `@requires_auth` + `@requires_any_role('reviewer','admin')`: determine the submitter's slot from `events.reviewer1_id`/`reviewer2_id`, insert one row into the shared `reviews` table, set the submitter's `reviewN_date`, advance `status` to `reviewer1_done` (reviewer 1) and immediately to `done` when `reviewer_count==1` with no `reviewer2_done`/third-review state and no disagreement comparison, or to `reviewer2_done` (reviewer 2) when `reviewer_count==2`; return `{ "data": { "event_id", "status" } }`; `404`/`403`/`400` per contract (FR-010, FR-012, FR-014; research Decision 5; contracts).
- [ ] T012 [US1] In the `assign_many` route in `flask_backend/app.py`, reject a `slot` of `second` or `third` with `400` when `reviewer_count==1`, so second-/third-reviewer assignment is unavailable in a single-reviewer configuration (FR-013; contracts "CHANGED POST /api/events/assign_many").
- [ ] T013 [US1] Replace the placeholder `alert('Review submitted (placeholder).')` submit handler in `frontend/src/pages/EventReview.jsx` so it `POST`s the collected review fields to `/api/events/<id>/review` and handles the success/error response (research Decision 5).

**Checkpoint**: A `scans`-configured event reaches `done` in four transitions with one reviewer — User Story 1 is independently demonstrable (MVP).

---

## Phase 4: User Story 2 - Reviewers and admins see an interface that matches the bypassed workflow (Priority: P2)

**Goal**: A `scans` deployment's UI presents only upload, assignment, single review, and completion — no scrubbing/screening/sending views or actions, no second-/third-reviewer controls — with every hide/show decision driven by the workflow controls, not a study-name check.

**Independent Test**: Log into a `scans` deployment as an admin and as a reviewer; confirm scrubbing/screening/sending and second-/third-reviewer views and actions are absent and that upload, assignment, single review, and completion are present and usable (spec US2 Independent Test).

### Tests for User Story 2

- [ ] T014 [P] [US2] Integration test for `GET /api/config` in new `flask_backend/tests/test_config_endpoint.py`: enforces `@requires_auth` + `@requires_any_role('admin','uploader','reviewer','third_reviewer')`; `200` returns `{ "data": { "study_type", "workflow": { "scrubbing", "screening", "sending", "reviewer_count" } } }` reflecting the resolved config; no secrets exposed (contracts "NEW GET /api/config").

### Implementation for User Story 2

- [ ] T015 [US2] Add `GET /api/config` to `flask_backend/app.py` with `@requires_auth` + `@requires_any_role('admin','uploader','reviewer','third_reviewer')`, returning the resolved `get_workflow_config()` as `{ "data": { "study_type", "workflow": { "scrubbing", "screening", "sending", "reviewer_count" } } }` (FR-021; research Decision 7; contracts).
- [ ] T016 [US2] In `frontend/src/App.jsx`, fetch `GET /api/config` once at load (alongside `/api/auth/me`), make the resolved workflow config available to the component tree (context or props), and conditionally register the route tree so a bypassed-stage *view* is not reachable by direct URL — omit/guard the `EventScrub.jsx`, `EventScreen.jsx`, `EventSendMany.jsx`, and `EventAssignThird.jsx` routes when `scrubbing`/`screening`/`sending` are disabled or `reviewer_count==1` (FR-018, FR-019, FR-021; research Decision 7).
- [ ] T017 [P] [US2] In `frontend/src/components/MenuBar.jsx`, hide the scrubbing, screening, and sending navigation entries when those controls are disabled, reading the config flags from T016 — never a hard-coded `study_type` check (FR-018, FR-021; research Decision 8).
- [ ] T018 [P] [US2] In `frontend/src/pages/Admin.jsx`, hide the scrubbing/screening/sending queues and actions when those controls are disabled, reading the config flags from T016; keep upload, assignment, single review, and completion present (FR-018, FR-020, FR-021; research Decision 8).
- [ ] T019 [P] [US2] In `frontend/src/pages/EventAssignMany.jsx`, offer only a single (first) reviewer slot — hide the second-/third-reviewer assignment controls — when `reviewer_count==1`, reading the config flag from T016 (FR-019, FR-021; research Decision 8).

**Checkpoint**: A `scans` deployment shows zero bypassed-stage UI; a full-workflow study still shows every control — User Stories 1 AND 2 both work independently.

---

## Phase 5: User Story 3 - A deployment operator can stand up a scans study from the documentation (Priority: P2)

**Goal**: An operator can configure and deploy a `scans` study from `default.env`, `README.md`, and `docs/template-setup-guide.md` alone, without reading source code.

**Independent Test**: A person who has read only `default.env`, `README.md`, and `docs/template-setup-guide.md` — not the source — can produce a correct `scans` `.env` and bring the stack up as a working `scans` deployment (spec US3 Independent Test).

### Implementation for User Story 3

- [ ] T020 [P] [US3] In `default.env`, document `STUDY_TYPE` and the four workflow-stage controls (`ENABLE_SCRUBBING`, `ENABLE_SCREENING`, `ENABLE_SENDING`, `REVIEWER_COUNT`), each with its conservative default (`true/true/true/2`) and a one-line description (FR-022; research Decision 10).
- [ ] T021 [P] [US3] In `README.md`, list the four workflow-stage controls in the Environment Variables documentation section (FR-023; research Decision 10).
- [ ] T022 [P] [US3] In `docs/template-setup-guide.md`, add a `scans` worked deployment example parallel to the existing VTE alternative-study example, showing the study selector set to `scans` and the four controls set to their bypass values (`false/false/false/1`) — document only the deltas from the canonical template, not the full content (FR-024; research Decision 10).

**Checkpoint**: The pending v1.4.0 Sync Impact Report documentation is landed — a `scans` study is deployable from docs alone.

---

## Phase 6: User Story 4 - Existing studies and cross-study tooling are unaffected (Priority: P3)

**Goal**: Adding `scans` changes nothing for full-workflow studies and leaves all bypassed states and roles defined.

**Independent Test**: Inspect the schema and shared state machine and confirm the bypassed state names are still defined; run an existing full-workflow study (e.g., MCI) through its lifecycle and confirm no behavior change (spec US4 Independent Test).

### Implementation for User Story 4

- [ ] T023 [US4] Verify the `events.status` enum in `flask_backend/models.py` still defines all 13 states — including `scrubbed`, `screened`, `sent`, `reviewer2_done`, `third_review_needed`, `third_review_assigned` — and that the `users` role flags (`admin`, `uploader`, `reviewer`, `third_reviewer`) are unchanged; no edit is expected (FR-015, FR-016, SC-007).
- [ ] T024 [P] [US4] Add a regression test in new `flask_backend/tests/test_full_workflow_defaults.py` asserting that with no controls set the resolved config is the full-workflow profile (`scrubbing/screening/sending=true`, `reviewer_count=2`) and that full-workflow `assign`/`send` transitions behave as before (FR-004, FR-017).
- [ ] T025 [US4] Run the complete `pytest flask_backend/tests/` suite from the repo root and confirm every pre-existing test passes without modification (SC-006).

**Checkpoint**: The "bypass ≠ delete" rule is demonstrably honored and no existing study regresses.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Contract regeneration and end-to-end validation across all stories.

- [ ] T026 [P] Regenerate `openapi.json` at the repo root via `python -m flask_backend.generate_openapi` so the new `/api/events/<id>/review` and `/api/config` routes and the changed `assign_many`/`send_many` behavior are reflected in the same change set (Constitution "API contracts" gate, Principle VI; research Decision 10).
- [ ] T027 [P] Run ESLint over the changed frontend files (`frontend/src/App.jsx`, `frontend/src/components/MenuBar.jsx`, `frontend/src/pages/Admin.jsx`, `frontend/src/pages/EventAssignMany.jsx`, `frontend/src/pages/EventReview.jsx`) and resolve any lint errors introduced.
- [ ] T028 Execute the `specs/003-scans-study/quickstart.md` walkthrough end-to-end against a `scans` deployment and a full-workflow deployment, confirming the Done criteria checklist.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — **BLOCKS all user stories** (the shared config layer underpins every story).
- **User Stories (Phases 3–6)**: All depend on Foundational completion.
  - US1 (P1) is the MVP and should be completed first.
  - US2 (P2), US3 (P2), US4 (P3) can then proceed in parallel or in priority order.
- **Polish (Phase 7)**: Depends on US1 + US2 (route changes) being complete.

### User Story Dependencies

- **US1 (P1)**: Depends on Phase 2 only. No dependency on other stories.
- **US2 (P2)**: Depends on Phase 2. Independent of US1 — but note `flask_backend/app.py` is touched by both US1 (T011, T012) and US2 (T015); sequence those edits if one developer, or merge-coordinate if parallel.
- **US3 (P2)**: Depends on Phase 2. Documentation only — fully independent of US1/US2 code; best written after US1/US2 behavior is settled so the worked example is accurate.
- **US4 (P3)**: Depends on Phase 2. Its `pytest` run (T025) is most meaningful after US1/US2 backend changes land.

### Within Each User Story

- Tests are listed before implementation; the config-layer code they test already exists from Phase 2.
- `table_service.py` tasks (T008 → T009 → T010) are sequential — same file.
- `app.py` tasks (T011 → T012, then T015) are sequential — same file.
- US2 frontend: T015 (endpoint) → T016 (`App.jsx` fetch) → T017/T018/T019 (consumers).

### Parallel Opportunities

- US1 tests T005, T006, T007 — all new, distinct files — run in parallel.
- US2 consumers T017, T018, T019 — distinct files — run in parallel after T016.
- US3 docs T020, T021, T022 — distinct files — run in parallel.
- Polish T026, T027 — distinct artifacts — run in parallel.
- With staff, US2/US3/US4 can be developed in parallel once Phase 2 is done.

---

## Parallel Example: User Story 1

```bash
# Launch all three User Story 1 test files together (distinct new files):
Task: "Config-layer tests in flask_backend/tests/test_study_config.py"
Task: "Review-endpoint integration test in flask_backend/tests/test_review_endpoint.py"
Task: "Flag-aware lifecycle test in flask_backend/tests/test_scans_workflow.py"
```

## Parallel Example: User Story 2

```bash
# After T016 makes the config available in the component tree:
Task: "Hide scrub/screen/send nav entries in frontend/src/components/MenuBar.jsx"
Task: "Hide scrub/screen/send queues+actions in frontend/src/pages/Admin.jsx"
Task: "Single-reviewer-only assignment in frontend/src/pages/EventAssignMany.jsx"
```

## Parallel Example: User Story 3

```bash
# All three documentation files are independent:
Task: "Document the four controls in default.env"
Task: "List the four controls in README.md Environment Variables"
Task: "Add the scans worked example to docs/template-setup-guide.md"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001 — baseline).
2. Complete Phase 2: Foundational (T002–T004 — the shared config layer; **blocks everything**).
3. Complete Phase 3: User Story 1 (T005–T013).
4. **STOP and VALIDATE**: carry one `scans` event upload → `done` in four transitions, one reviewer.
5. Deploy/demo — this is a working scan-review study.

### Incremental Delivery

1. Setup + Foundational → config layer ready.
2. US1 → test independently → demo (MVP: working bypassed lifecycle).
3. US2 → test independently → demo (UI matches the bypassed workflow).
4. US3 → docs landed → operator can self-serve a deployment.
5. US4 → regression confirmed → "bypass ≠ delete" demonstrably honored.
6. Polish → `openapi.json` regenerated, quickstart validated.

### Parallel Team Strategy

1. One developer completes Setup + Foundational.
2. Then: Developer A → US1; Developer B → US2; Developer C → US3; Developer D → US4.
3. Coordinate the shared `flask_backend/app.py` edits between US1 (T011/T012) and US2 (T015).

---

## Notes

- **MVP scope**: User Story 1 (Phases 1–3).
- This feature *builds* the shared assign→review→done back half for the first time (research Decision 1) — it is behavior *completion*, not behavior *change*; with controls at their conservative defaults no existing study regresses (FR-017, SC-006).
- No database schema change, no migration (research Decision 9).
- All bypass behavior is driven by the four resolved controls — never by branching on `STUDY_TYPE` in pipeline code (FR-003, Constitution Principle IV).
- `[P]` tasks = different files, no incomplete dependencies.
- Commit after each task or logical group; `openapi.json` must be regenerated within the same change set as the route changes.
