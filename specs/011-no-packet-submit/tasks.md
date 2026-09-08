---
description: "Task list for 011-no-packet-submit"
---

# Tasks: No-Packet Submission Records the Event Outcome

**Input**: Design documents from `/specs/011-no-packet-submit/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Backend integration tests ARE included — the constitution's testing-discipline
gate requires new endpoints to carry at least one integration test exercising the role
decorators, and plan.md commits to `test_mark_no_packet.py`. The frontend has **no test
runner** (research D10), so frontend tasks are verified by lint plus the manual script in
`quickstart.md`.

**Organization**: Grouped by user story so each ships as an independent increment.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 / US2 / US3 / US4 — maps to spec.md user stories

## Path Conventions

Web application, existing layout: `flask_backend/` (Flask API), `frontend/src/` (React SPA).
All paths below are repo-relative from `/home/debadmin/cnics-validation`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish a green baseline and the shared test scaffolding every story's tests build on.

- [ ] T001 Record the green baseline before any change: run `python -m pytest flask_backend/tests/ -q` and `cd frontend && npm run lint && npm run build`; note any pre-existing failures so they are not attributed to this feature
- [ ] T002 Create `flask_backend/tests/test_mark_no_packet.py` with the module docstring citing the FRs and contract, a local non-admin uploader fixture (site `TEST`, `uploader=True`, `admin=False`) in the style of `conftest.py`'s `admin_client`, and `FakeEvent` / `FakeSession` doubles modeled on `flask_backend/tests/test_review_endpoint.py`

**Checkpoint**: Baseline recorded; test module importable and collectible by pytest.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The shared authorization helper the new endpoint must use from its first commit. Shipping the endpoint without it would expose a cross-site write.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T003 Extract the same-site authorization check from `events_upload_raw` (`flask_backend/app.py:1863-1872`) into a module-level helper `_uploader_may_act_on_event(session, event, auth_user)` returning a bool, and call it from `upload_raw`; preserve the existing 403 message `'Uploader must match patient site'` verbatim (research D3, Principle IV)
- [ ] T004 Confirm the extraction is behavior-preserving: run `python -m pytest flask_backend/tests/ -q` and verify no change versus the T001 baseline

**Checkpoint**: One authorization rule, one implementation, existing upload path unchanged.

---

## Phase 3: User Story 1 - Document that no packet is available (Priority: P1) 🎯 MVP

**Goal**: A coordinator selects a reason, presses Submit, and the event is recorded as
`no_packet_available` with the reason, the actor, and the date — then shows that record on
the event detail page and leaves the needs-packet queue.

**Independent Test**: Mark an event with each of `Outside hospital`, `Ascertainment diagnosis error`,
and `Other`; reopen the detail page and confirm the reason and answers are shown; confirm the
event is gone from "Events That Need Packets" and present in "no packet available".

### Tests for User Story 1

- [ ] T005 [US1] Add persistence tests to `flask_backend/tests/test_mark_no_packet.py`: one per reason for `Outside hospital` (`two_attempts` true→`1`, false→`0`), `Ascertainment diagnosis error` (all four follow-ups NULL), and `Other` (`other_cause` set, others NULL); every case asserts `status='no_packet_available'`, `marker_id`, `markNoPacket_date=today`, and that `upload_date` / `uploader_id` remain NULL; plus a stale-field test (event carrying a prior `two_attempts_flag`, marked with reason `Other`, ends with that flag NULL — FR-006) and a 404 test for a missing event

### Implementation for User Story 1

- [ ] T006 [US1] Implement `POST /api/events/<int:event_id>/mark_no_packet` in `flask_backend/app.py` per `contracts/mark-no-packet-api.md`: `@requires_auth` + `@requires_any_role('uploader', 'admin')`, the T003 helper for the site check (403), 404 for a missing event, reason validation V1 (400), the field-applicability mapping from `data-model.md` writing NULL explicitly to every non-applicable column, the `status` / `marker_id` / `markNoPacket_date` stamp, single-transaction commit with `session.rollback()` on exception and `session.close()` in `finally` (mirroring `events_screen` at line 1249), a Flasgger docstring, and PHI-safe logging that records the event id and error class only — **never `other_cause`**
- [ ] T007 [P] [US1] Extend `get_event_details` in `flask_backend/table_service.py` (query at lines 884-926): select `e.no_packet_reason`, `e.two_attempts_flag`, `e.prior_event_date`, `e.prior_event_onsite_flag`, `e.other_cause`, and add `LEFT JOIN users mk ON mk.id = e.marker_id` yielding `mk.username AS marker_username` — following the export query's join at line 690 (research D7, FR-026)
- [ ] T008 [P] [US1] In `frontend/src/pages/EventUpload.jsx`: bind the `twoAttemptsFlag` radios (lines 353, 358) and the `otherCause` text input (line 429) to new React state following the `priorEventDateKnown` pattern at lines 369-386, and add `handleNoPacketSubmit` attached to the `<form>` at line 325 — `e.preventDefault()`, guard on `eventId`, POST to `/api/events/{eventId}/mark_no_packet` with `credentials: 'include'` and a JSON body carrying **only** the fields applicable to the selected reason, mirroring `handleUploadSubmit`'s error extraction (lines 190-229)
- [ ] T009 [P] [US1] In `frontend/src/pages/EventEdit.jsx`: beside the existing `markNoPacket_date` row (lines 209-211), render a conditional block when `details.no_packet_reason` is present, showing the reason, the applicable follow-up answers (flags as Yes/No), and `marker_username`
- [ ] T010 [US1] Verify User Story 1 against `specs/011-no-packet-submit/quickstart.md` steps 1-6, including the user's originally reported `Outside hospital` + "Yes, 2 attempts" and `Other` + "Data corruption" submissions

**Checkpoint**: The reported defect is fixed — submissions persist, are displayed, and move the event between lists. Shippable as MVP.

---

## Phase 4: User Story 2 - Record prior-event details (Priority: P2)

**Goal**: The "Ascertainment diagnosis referred to a prior event" reason stores its month/year
and on-site answers, preserving the three-state date encoding recovered from legacy data.

**Independent Test**: Select the prior-event reason, answer that the date is known, enter a
month and year, answer the on-site question, Submit; reopen the event and confirm every entered
value is shown.

### Tests for User Story 2

- [ ] T011 [US2] Add prior-event encoding tests to `flask_backend/tests/test_mark_no_packet.py`: month 11 + year 2011 → `'11-2011'`; month blank + year 2008 → `'00-2008'`; month 1 + year blank → `'01-0000'`; date-known answered **No** → `prior_event_date` NULL (distinct from `'00-0000'`); `prior_event_onsite_flag` stored as 1/0; and `two_attempts_flag` / `other_cause` NULL in every case

### Implementation for User Story 2

- [ ] T012 [US2] In `flask_backend/app.py`, add the prior-event branch to the `mark_no_packet` handler: build `prior_event_date` as zero-padded `MM-YYYY` using `00` for a blank month and `0000` for a blank year, store NULL when `prior_event_date_known` is false, and store `prior_event_onsite_flag` — per the encoding table in `data-model.md` (research D5)
- [ ] T013 [US2] In `frontend/src/pages/EventUpload.jsx`: bind the prior-event month and year inputs (line 404) and the `priorEventOnsite` radios (lines 415, 420) to React state, and include `prior_event_date_known`, `prior_event_month`, `prior_event_year`, and `prior_event_onsite` in the request body when that reason is selected
- [ ] T014 [US2] Verify the prior-event branch per `specs/011-no-packet-submit/quickstart.md` ("Prior-event blanks"), confirming `'00-2008'` for a blank month and NULL when the date is answered as not known

**Checkpoint**: All four reasons round-trip completely.

---

## Phase 5: User Story 3 - Be told when a submission is incomplete or fails (Priority: P2)

**Goal**: No submission is ever silent — incomplete forms are refused with a message naming the
missing answer, failures are reported, and Submit cannot be pressed twice.

**Independent Test**: Select `Outside hospital`, leave the attempts question unanswered, press
Submit — a specific message names the missing answer and nothing is recorded.

### Tests for User Story 3

- [ ] T015 [US3] Add validation-rejection tests to `flask_backend/tests/test_mark_no_packet.py` covering V2-V8 from `data-model.md`: missing `two_attempts`, empty and whitespace-only `other_cause`, `other_cause` at 100 chars (accepted) and 101 chars (rejected with the limit stated), missing `prior_event_onsite`, date-known with both month and year blank, month outside 1-12, and a non-four-digit year — each asserting HTTP 400, a message naming the offending field, and **no mutation of the event row** (FR-016)

### Implementation for User Story 3

- [ ] T016 [US3] In `flask_backend/app.py`, add validation rules V2-V8 to the `mark_no_packet` handler with the exact messages tabulated in `contracts/mark-no-packet-api.md`, all evaluated **before** any field assignment so a rejected request cannot partially mutate the row
- [ ] T017 [P] [US3] In `frontend/src/pages/EventUpload.jsx`, add the feedback layer per `contracts/no-packet-form-ui.md`: `noPacketStatus` (`idle | submitting | success | error`) and `noPacketError` state paralleling the existing `uploadStatus` / `uploadError`; client-side mirrors of V2-V8 that block submission with a field-specific message; Submit disabled while in flight; a green confirmation naming the event on success with the reason select and follow-ups disabled afterward; and a reason-change handler that clears `twoAttempts`, `otherCause`, `priorEventDateKnown`, `priorEventMonth`, `priorEventYear`, `priorEventOnsite`, and any error
- [ ] T018 [US3] Verify the feedback layer per `specs/011-no-packet-submit/quickstart.md` ("Silence is gone", "Reason switch clears answers", "Over-long cause", "No double submit")

**Checkpoint**: The silent-failure trap that caused the original report is closed on both sides.

---

## Phase 6: User Story 4 - Prevent conflicting or unauthorized resolutions (Priority: P3)

**Goal**: An event that already has a packet cannot be overwritten with a no-packet outcome, and
only those who may upload for an event may declare that no packet exists for it.

**Independent Test**: Submit as an uploader at a different site — refused. Submit against an
already-uploaded event — refused with its current status named.

### Tests for User Story 4

- [ ] T019 [US4] Add authorization and conflict tests to `flask_backend/tests/test_mark_no_packet.py` using the T002 non-admin uploader fixture: 403 for an uploader whose site differs from the event's patient site; 200 for an admin acting cross-site; 404 for a missing event; and 409 for each non-`created` status (`uploaded`, `scrubbed`, `screened`, `assigned`, `sent`, `done`, `rejected`, `no_packet_available`) asserting the current status appears in the message and the row is unchanged

### Implementation for User Story 4

- [ ] T020 [US4] In `flask_backend/app.py`, add the V11 status guard to the `mark_no_packet` handler: refuse any event whose `status != 'created'` with HTTP 409 and a message naming the event id and its current status, evaluated before any field assignment (research D4, FR-023)
- [ ] T021 [US4] Verify authorization and conflict per `specs/011-no-packet-submit/quickstart.md` ("Authorization & conflict"), including that an admin can still act across sites

**Checkpoint**: All four stories independently functional.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T022 Regenerate the API contract: `python -m flask_backend.generate_openapi` from the repository root, and commit the `openapi.json` delta in the same change (constitution API-contracts gate, research D11)
- [ ] T023 [P] Confirm `frontend/src/studies/vte/EventUpload.jsx` is left untouched and that its inert no-packet form remains recorded as a known gap in `specs/011-no-packet-submit/quickstart.md` (research D9, Principle VI unused-subsystem hygiene)
- [ ] T024 Run the full gate: `python -m pytest flask_backend/tests/ -q`, then `cd frontend && npm run lint && npm run build`; compare against the T001 baseline
- [ ] T025 Run the complete `specs/011-no-packet-submit/quickstart.md` validation end to end, including all six manual steps and all six "Also verify" checks
- [ ] T026 Write the PR description stating this affects **shared code, all studies** (no study-specific behavior, no workflow flag consulted), and include the Principle VI observed-behavior note from `specs/011-no-packet-submit/research.md` (D1) — that the form was unimplemented rather than broken, with the file:line evidence

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies — start immediately
- **Foundational (Phase 2)**: depends on Setup — **blocks all user stories**
- **User Stories (Phases 3-6)**: all depend on Phase 2
- **Polish (Phase 7)**: depends on all desired stories

### User Story Dependencies

- **US1 (P1)**: depends only on Foundational. Self-contained MVP.
- **US2 (P2)**: extends the US1 handler and form with the prior-event branch. Independently testable, but touches the same two files as US1 — see serialization note.
- **US3 (P2)**: adds validation and feedback on top of the US1 handler and form. Independently testable; same-file serialization applies.
- **US4 (P3)**: adds the conflict guard. Its authorization behavior already works at the end of US1 (the Foundational helper supplies it); US4 adds the 409 guard and proves both with tests.

### ⚠️ Same-file serialization (this feature's main constraint)

Four of the five implementation surfaces are single files, so **stories cannot be worked in
parallel by different people** the way the template's general advice assumes:

| File | Tasks touching it |
|------|-------------------|
| `flask_backend/app.py` | T003, T006, T012, T016, T020 |
| `frontend/src/pages/EventUpload.jsx` | T008, T013, T017 |
| `flask_backend/tests/test_mark_no_packet.py` | T002, T005, T011, T015, T019 |

Run the stories in priority order (US1 → US2 → US3 → US4). The `[P]` markers below are for
tasks that genuinely touch different files.

### Within Each User Story

- Tests before implementation — write them, watch them fail, then implement
- Backend handler before the frontend that calls it
- Manual verification task last

### Parallel Opportunities

- **Within US1**: T007 (`table_service.py`), T008 (`EventUpload.jsx`), and T009 (`EventEdit.jsx`) touch three different files and can run together once T006 defines the endpoint contract
- **Phase 7**: T023 is independent of T022/T024
- No cross-story parallelism — see the serialization table above

---

## Parallel Example: User Story 1

```bash
# After T006 lands the endpoint, these three touch different files:
Task: "Extend get_event_details in flask_backend/table_service.py"          # T007
Task: "Bind inputs and add handleNoPacketSubmit in EventUpload.jsx"         # T008
Task: "Render the no-packet block in EventEdit.jsx"                         # T009
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1 Setup (T001-T002)
2. Phase 2 Foundational (T003-T004) — **critical, blocks everything**
3. Phase 3 US1 (T005-T010)
4. **STOP and VALIDATE**: the reported bug is fixed — submissions persist and display
5. Shippable here

### Incremental Delivery

1. Setup + Foundational → shared auth helper in place
2. + US1 → **MVP**: the defect is fixed for three of four reasons
3. + US2 → the fourth reason round-trips completely
4. + US3 → no submission is ever silent again
5. + US4 → conflicting and unauthorized resolutions refused
6. + Polish → contract regenerated, full gate green

Each increment leaves the system in a better state than the one before, and none breaks its predecessor.

---

## Notes

- **26 tasks**: Setup 2, Foundational 2, US1 6, US2 4, US3 4, US4 3, Polish 5
- `other_cause` is PHI-adjacent free text — never logged, at any level (T006)
- No schema change, no migration, no new dependency in any task
- Non-applicable fields are written **NULL explicitly**, never left alone — a row may carry stale values (T006)
- Commit after each task or logical group; every checkpoint is a valid stopping point
