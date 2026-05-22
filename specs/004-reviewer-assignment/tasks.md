# Tasks: Interactive reviewer-assignment page

**Input**: Design documents from `/specs/004-reviewer-assignment/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/assign_many.md

**Tests**: The frontend has no test runner configured, so there are no frontend test tasks. One **backend** test task is included for the two-reviewer atomic path — required by the plan and by the Constitution quality gate ("new backend endpoints SHOULD have at least one integration test").

**Organization**: Tasks are grouped by user story. The feature is concentrated in one rewritten frontend file (`frontend/src/pages/EventAssignMany.jsx`) plus a small additive backend extension; parallelism is therefore limited and is marked honestly.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different file, no dependency on an incomplete task)
- **[Story]**: US1 / US2 / US3 — maps to the spec's user stories
- All paths are repository-relative to `/home/debadmin/cnics-validation/`

## Path Conventions

Web application: `frontend/src/` (React 19) and `flask_backend/` (Flask). Paths below are exact.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Pre-flight confirmation. No new dependencies and no new files — the `/events/assignMany` route already exists.

- [X] T001 Pre-flight check (read-only, no code change): confirm in `frontend/src/App.jsx` that the `/events/assignMany` route is wrapped in `ProtectedRoute requiredRoles={['admin']}` and renders `<EventAssignMany workflow={workflow} />`, and that `workflow.reviewer_count` is populated from `GET /api/config`. Record any deviation before proceeding.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Build the page substrate (queue + reviewers + selectable table) that every user story extends, and make the page reachable from the menu.

**⚠️ CRITICAL**: T003 and T004 must complete before any user story phase — US1/US2/US3 all extend this page.

- [X] T002 [P] Add an admin-only "Assign Charts" link to `frontend/src/components/MenuBar.jsx` pointing to `/events/assignMany` (FR-003). Gate it on the `admin` prop, alongside the existing "Admin Tools" link. (The View All Events page already links to the page via its per-row "assign" button — no task needed there.)
- [X] T003 Replace the placeholder body of `frontend/src/pages/EventAssignMany.jsx` with a working component scaffold that keeps the `{ workflow }` prop: on mount, fetch the To-Be-Assigned queue from `GET /api/events/by_status/screened` (`credentials: 'include'`, with `limit`/`offset`) storing `rows` and `total`, and fetch reviewers from `GET /api/tables/users?limit=2000` filtered client-side to truthy `reviewer_flag`. Use `API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')`.
- [X] T004 In `frontend/src/pages/EventAssignMany.jsx`, render the queue as a selectable event table — a checkbox per row plus a select-all control — showing event id, event date, patient identifier, and site. Render a clear empty-state message when the queue is empty, and a notice when no users hold the reviewer role (spec Edge Cases).

**Checkpoint**: The page loads, lists awaiting events with checkboxes, and lists reviewers — but cannot yet assign.

---

## Phase 3: User Story 1 - Assign a reviewer to awaiting events (single-reviewer deployment) (Priority: P1) 🎯 MVP

**Goal**: An administrator selects awaiting events, picks one reviewer, confirms, and the events advance to `assigned` and leave the queue. Unblocks the scans upload→assign→review→done lifecycle.

**Independent Test**: In a deployment with `REVIEWER_COUNT=1`, open the page, select one or more events, choose a reviewer, confirm — verify exactly one reviewer slot is shown, the events advance to `assigned`, a confirmation appears, and the events disappear from the queue.

### Implementation for User Story 1

- [X] T005 [US1] In `frontend/src/pages/EventAssignMany.jsx`, render the first-reviewer `<select>` (always shown), populated from the fetched reviewers as `username (site)`. Drive slot rendering off `workflow.reviewer_count` so a single-reviewer deployment shows exactly one slot and no second/third slot (FR-008, FR-009).
- [X] T006 [US1] In `frontend/src/pages/EventAssignMany.jsx`, implement the confirm action for the single-reviewer path: `POST /api/events/assign_many` with body `{ event_ids, reviewer_id, slot: "first" }`. Disable the confirm button until at least one event is selected and a reviewer is chosen; indicate what is missing otherwise (FR-005, FR-007, FR-018).
- [X] T007 [US1] In `frontend/src/pages/EventAssignMany.jsx`, handle the assign response: on success show a confirmation via `showToast` (from `components/Toast`) and re-fetch the queue so assigned events leave it without a manual reload (FR-013, FR-014); on `400`/`401`/`403`/`500` surface the response's `error` message (or an auth/network message) as human-readable feedback and claim no success (FR-017, spec Edge Cases).

**Checkpoint**: User Story 1 is fully functional — a single-reviewer deployment can assign reviewers end to end. **This is the MVP.**

---

## Phase 4: User Story 2 - Assign first and second reviewers (two-reviewer deployment) (Priority: P2)

**Goal**: In a `REVIEWER_COUNT=2` deployment, an administrator assigns a first and a second (distinct) reviewer in one atomic action; the events advance out of the queue only when fully assigned.

**Independent Test**: In a deployment with `REVIEWER_COUNT=2`, open the page, select events, choose two distinct reviewers, confirm — verify both reviewers are recorded and the events advance; verify choosing the same person for both slots blocks confirmation.

> Backend tasks (T008–T011) and frontend tasks (T012–T013) touch different files and may be done by different developers in parallel; T013 is end-to-end testable only once the backend tasks land.

### Tests for User Story 2

- [X] T008 [P] [US2] Add a backend test in `flask_backend/tests/test_table_service.py` (or a new `test_assign_many.py`) for the atomic two-reviewer path: assert `table_service.assign_events(event_ids, reviewer_id, "first", assigner_id, reviewer2_id=<id>)` sets `reviewer1_id`, `reviewer2_id`, `assigner_id`, `assign_date`, and `status="assigned"` on every event; and assert `POST /api/events/assign_many` returns `400` when `reviewer2_id` is given with `reviewer_count==1`, with `slot != "first"`, and when `reviewer2_id == reviewer_id`. Write this before T009–T010 and confirm it fails first.

### Implementation for User Story 2

- [X] T009 [US2] Extend `table_service.assign_events` in `flask_backend/table_service.py` with a trailing optional parameter `reviewer2_id=None`. When `slot == "first"` and `reviewer2_id` is provided, set `reviewer1_id`, `reviewer2_id`, `assigner_id`, `assign_date`, and `status = "assigned"` for all matched events within the single existing `session.commit()` (atomic). Existing positional callers are unaffected.
- [X] T010 [US2] Extend `events_assign_many` in `flask_backend/app.py` to read optional `reviewer2_id` from the request body and pass it to `assign_events`. Return `400` with a human-readable `error` when: `reviewer2_id` is present and `get_workflow_config().reviewer_count == 1`; `reviewer2_id` is present and `slot != "first"`; or `reviewer2_id == reviewer_id` (server-side FR-011). Update the endpoint docstring's Swagger block to document `reviewer2_id`.
- [X] T011 [US2] Regenerate the API contract: run `python -m flask_backend.generate_openapi` from the repository root and commit the updated `openapi.json` (Constitution quality gate).
- [X] T012 [US2] In `frontend/src/pages/EventAssignMany.jsx`, render the second-reviewer `<select>` only when `workflow.reviewer_count >= 2` (never a third slot — FR-010, FR-020). Require both slots filled before confirm is enabled (FR-012), and block confirmation with an explanatory message when the same person is selected in both slots (FR-011).
- [X] T013 [US2] In `frontend/src/pages/EventAssignMany.jsx`, send the two-reviewer assignment as a single request `{ event_ids, reviewer_id, slot: "first", reviewer2_id }` and surface the new `400` validation messages from T010 (FR-017). Reuse the success/queue-refresh handling from T007.

**Checkpoint**: Both single-reviewer (US1) and two-reviewer (US2) deployments assign reviewers correctly and independently.

---

## Phase 5: User Story 3 - Narrow and page through the queue (Priority: P3)

**Goal**: An administrator working with a large queue narrows it by site and pages through results.

**Independent Test**: Open a queue spanning multiple pages with events from more than one site; apply a site filter and verify only that site's events show; page forward/back and verify the displayed set changes.

### Implementation for User Story 3

- [X] T014 [US3] In `frontend/src/pages/EventAssignMany.jsx`, add a site-filter `<select>` whose options derive from the distinct `Site` values in the loaded queue rows (the View All Events pattern). On change, re-fetch the queue with the `site` query parameter and clear the current event selection so a confirm never acts on hidden events (FR-015, spec Edge Cases / Assumptions).
- [X] T015 [US3] In `frontend/src/pages/EventAssignMany.jsx`, add Previous/Next pagination (page size 20) driven by the endpoint's `total`, re-fetching with `limit`/`offset`; clear the event selection on page change (FR-016).

**Checkpoint**: All three user stories are independently functional.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Verification and quality gates across the whole feature.

- [X] T016 [P] Run `npm run lint` in `frontend/` and resolve any issues introduced in `EventAssignMany.jsx` and `MenuBar.jsx`.
- [X] T017 [P] Run `pytest flask_backend/tests/` and confirm the new T008 test passes and the existing `test_scans_workflow.py` and `test_full_workflow_defaults.py` still pass (the `assign_events` signature change is additive).
- [X] T018 Validate the feature against `specs/004-reviewer-assignment/quickstart.md`: single-reviewer path, two-reviewer path, error cases (no selection, not admin, server error, empty queue, no reviewers), and site filter + pagination.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: After Setup. **Blocks all user stories.**
- **User Story 1 (Phase 3)**: After Foundational. The MVP.
- **User Story 2 (Phase 4)**: After Foundational. Frontend tasks (T012–T013) extend US1's page code (same file); functionally independently testable in a `REVIEWER_COUNT=2` deployment.
- **User Story 3 (Phase 5)**: After Foundational. Functionally independent of US1/US2; shares the page file so it serializes against their edits.
- **Polish (Phase 6)**: After all desired user stories are complete.

### User Story Dependencies

- **US1 (P1)**: Depends only on Foundational. No dependency on US2 or US3.
- **US2 (P2)**: Backend (T008–T011) depends only on Foundational. Frontend (T012–T013) builds on US1's selector/confirm code in the shared page file.
- **US3 (P3)**: Depends only on Foundational. Independent of US1/US2 behavior.

### File-level serialization (important)

`frontend/src/pages/EventAssignMany.jsx` is edited by T003, T004, T005, T006, T007, T012, T013, T014, T015 — these **cannot run in parallel with each other**; do them in task-ID order. Genuine parallelism exists only across different files (see below).

### Within Each User Story

- US2: T008 (test) written first and expected to fail → T009 (service) → T010 (endpoint, depends on T009) → T011 (openapi regen, depends on T010). Frontend T012 → T013.

---

## Parallel Opportunities

True parallelism is limited because most work is in one file. The real opportunities:

- **T002** (`MenuBar.jsx`) is independent of every page-file task — run it any time during/after Foundational.
- **US2 backend vs frontend**: T008/T009/T010/T011 (`flask_backend/*`, `openapi.json`) and T012/T013 (`EventAssignMany.jsx`) are different files — a two-developer split.
- **T016** (frontend lint) and **T017** (backend pytest) are independent.

```bash
# Example: after Foundational, split US2 across two developers
Developer A (backend): T008 → T009 → T010 → T011
Developer B (frontend): T012 → T013   # after US1's page code (T005–T007) is in
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1: Setup (T001)
2. Phase 2: Foundational (T002–T004)
3. Phase 3: User Story 1 (T005–T007)
4. **STOP and VALIDATE**: in a `REVIEWER_COUNT=1` deployment, assign reviewers end to end — this unblocks the scans lifecycle (SC-005).

### Incremental Delivery

1. Setup + Foundational → page loads with queue and reviewers.
2. + US1 → single-reviewer assignment works → **MVP, deploy/demo.**
3. + US2 → two-reviewer deployments supported (atomic backend extension).
4. + US3 → site filter and pagination for large queues.
5. Polish → lint, tests, quickstart validation.

---

## Notes

- `[P]` = different file, no dependency on an incomplete task.
- `[Story]` label maps each task to a spec user story for traceability.
- The single-reviewer MVP (US1) is **frontend-only** — no backend change.
- The backend change is **additive and backward-compatible**: existing `assign_many` callers (`pages/EventAssignThird.jsx`, backend tests) are unaffected.
- Per Constitution Principle VI, `openapi.json` MUST be regenerated in the same change as the request-body extension (T011).
- Commit after each task or logical group; stop at any checkpoint to validate a story independently.
