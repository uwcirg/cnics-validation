---
description: "Task list for 008-upload-event-identifiers"
---

# Tasks: Populate event identifiers on the upload page

**Input**: Design documents from `/specs/008-upload-event-identifiers/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Backend test tasks are included. Not because TDD was requested, but
because research D6 and the constitution's testing-discipline gate call for
coverage of the changed service function, which has none today. There are no
frontend test tasks — the repository has no frontend test infrastructure, and
standing one up is explicitly out of scope (research D6).

**Organization**: Tasks are grouped by user story so each can be implemented
and verified independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on incomplete work)
- **[Story]**: Which user story the task serves (US1, US2, US3)

## Path Conventions

Web application layout, per plan.md: Flask backend in `flask_backend/`, React
SPA in `frontend/src/`, backend tests in `flask_backend/tests/`. All paths below
are repository-relative.

---

## Phase 1: Setup

**Purpose**: Establish the baseline. No project initialization is needed — this
feature adds no file, directory, or dependency.

- [ ] T001 Reproduce and record the current behavior on the running stack: open `/events/upload?event_id=[n]` via the "upload" action button and confirm Patient ID, Date, and Criteria are blank; open the same event by clicking a list row and confirm the address bar contains `patient_id=undefined`. Constitution Principle VI requires observed behavior be recorded before modification — capture both in the PR description (research.md already documents the code-level findings)
- [X] T002 Confirm the backend suite is green before any change: run `python -m pytest flask_backend/tests/ -q` from the repository root, so later failures are attributable to this feature

> **T001 blocked — needs a running stack.** No deployment is reachable from
> this workstation. The code-level baseline is fully recorded in research.md
> (the `searchParams` reads, the per-route table, and the `row['Patient ID']`
> column that no list query returns), which satisfies the substance of
> Principle VI. The browser observation still needs to be made by someone with
> stack access before the PR is opened.

**Checkpoint**: Suite green (82 passed), no dependency change needed. Baseline
recorded at code level; browser confirmation outstanding.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Expose criteria from the event-details endpoint. This is the only
backend gap — `patient_id`, `site_patient_id`, and `event_date` already come
back from `GET /api/events/{id}`. Every user story that renders criteria depends
on this phase.

**⚠️ CRITICAL**: No frontend story work can be verified against real data until
T003 lands.

- [X] T003 Extend `get_event_details` in `flask_backend/table_service.py` (~line 874) to return a `criteria` key: a list of `{"name": ..., "value": ...}` dicts read from the `criterias` table with a second query filtered on `event_id` and `ORDER BY name, id`. Return `[]` — never `None` — when the event has no criteria. Do not join `criterias` into the existing single-row query; that would multiply the row by the criteria count. Leave every existing key in the return dict untouched, including the ISO date serialization at lines 934-937
- [X] T004 [P] Add tests to `flask_backend/tests/test_table_service.py` covering `get_event_details`: (a) an event with several criteria returns both `name` and `value` for each, ordered by name; (b) two criteria sharing a name order deterministically by `id`; (c) an event with no criteria returns `[]` and not `None`; (d) a regression assertion that `patient_id`, `site_patient_id`, `site`, and `event_date` still serialize exactly as before, so `EventScrub` and `EventScreen` are provably unaffected
- [X] T005 [P] Update the docstring of `get_event_details` in `flask_backend/app.py` (route at line 614) to document the new `criteria` array in the Swagger response schema, following the docstring style used by `events_need_packets` at line 455. Do not change the decorators — the authorization gap is a documented follow-up, not part of this feature (research D5)
- [X] T006 Regenerate the API contract by running `python -m flask_backend.generate_openapi` from the repository root and commit the resulting `openapi.json` delta. The constitution requires this land in the same PR as the route change. Do not hand-edit the file (depends on T005)

**Checkpoint**: `GET /api/events/{id}` returns all four identifying values. The
backend half of the feature is complete and independently verifiable with
`curl` before any frontend work begins.

---

## Phase 3: User Story 1 - Uploader verifies the event before uploading (Priority: P1) 🎯 MVP

**Goal**: The upload page shows Patient ID, Site Patient ID, Date, and Criteria
for the named event, sourced from the stored record rather than the address bar.

**Independent Test**: Open `/events/upload?event_id=[n]` directly for a known
event and confirm all four values render correctly, having arrived from no
particular list page.

- [X] T007 [US1] In `frontend/src/pages/EventUpload.jsx`, add a `details` state and a `useEffect` that fetches `${API_BASE}/api/events/${eventId}` with `credentials: 'include'` when `eventId` is present, mirroring the pattern in `frontend/src/pages/EventScrub.jsx:15-24`. Guard on `res.ok` and store `json.data`. Do not fetch when no `event_id` is in the address — the browse list must keep working unchanged (FR-013)
- [X] T008 [US1] In `frontend/src/pages/EventUpload.jsx`, delete the `patientId`, `date`, and `criteria` reads from `searchParams` at lines 121-123. Keep the `eventId` read at line 120 — it is the only parameter the page may consult (FR-003). Old bookmarks carrying the removed parameters must be ignored, not rejected
- [X] T009 [US1] In the info box at `frontend/src/pages/EventUpload.jsx:211-218`, render Patient ID from `details.patient_id`, Site Patient ID from `details.site_patient_id`, and Date from `details.event_date`. Give Patient ID and Site Patient ID **distinct labels** on separate lines (FR-005) — do not collapse them into one line the way `EventScrub.jsx:78` does with `site_patient_id || patient_id`
- [X] T010 [US1] In `frontend/src/pages/EventUpload.jsx`, render `details.criteria` as `name: value` pairs (FR-012), iterating the array in the order the response supplies. Do not re-sort on the client — ordering is already total and stable from T003 (FR-008)
- [X] T011 [US1] In `frontend/src/pages/EventUpload.jsx`, render the em-dash placeholder `—` when `details.criteria` is empty, and when an individual criterion's `value` is an empty string, matching the convention at `EventScrub.jsx:78` and `EventScreen.jsx:58` (FR-006a). No identifying field may ever render blank, absent, or as the string `"undefined"`

**Checkpoint**: Opening the upload page directly for an event shows all four
values correctly. This is the MVP and resolves the reported bug for the direct
and action-button routes.

---

## Phase 4: User Story 2 - Identifiers correct regardless of entry route (Priority: P1)

**Goal**: All four entry routes converge on one URL shape and produce identical
values.

**Independent Test**: Reach the same event by the action button, both list row
clicks, and a pasted URL; confirm the four values are identical every time.

**Note**: Once T008 lands, the page already ignores URL-borne values, so route
parity is largely achieved. This phase removes the now-dead parameters so the
links stop asserting something the page no longer reads (research D4,
Principle VI's unused-subsystem rule).

- [X] T012 [P] [US2] In `frontend/src/pages/EventUpload.jsx`, simplify the `navigate()` call at line 78 to `/events/upload?event_id=${row['ID']}` — drop `patient_id`, `date`, and `criteria`. This also removes the source of the `patient_id=undefined` string, since `row['Patient ID']` is a column no list query returns
- [X] T013 [P] [US2] In `frontend/src/pages/EventReupload.jsx`, apply the same simplification to the link at line 68, leaving `event_id` only
- [ ] T014 [US2] Verify route parity for one known event id across all four entry points from `quickstart.md`: the "upload" action button (`EventUpload.jsx:201`), the needs-packets row body, the reupload list row, and a pasted URL. All four must display identical values (SC-002). Additionally open a legacy-shaped URL carrying `&patient_id=undefined&date=…&criteria=…` and confirm the extra parameters are ignored and correct values render from the record (depends on T012, T013)

> **T014 blocked — needs a running stack.** Verified statically instead: no
> reference to `patient_id`, `date`, or `criteria` query parameters remains in
> either page, so all four routes now emit and read the same `?event_id=` shape,
> and a legacy URL's extra parameters are simply never consulted. The
> four-route walkthrough still needs to be performed against a deployment.

**Checkpoint**: User Stories 1 and 2 both work. Every route into the page is
correct, and no link constructs parameters the page does not read.

---

## Phase 5: User Story 3 - Predictable behavior when the event cannot be shown (Priority: P2)

**Goal**: When the event cannot be verified, the page says so and does not offer
to accept a packet.

**Independent Test**: Open the page with a nonexistent event id and confirm a
clear message, no empty identifying fields, and no upload control.

**Design reference**: The four states and their invariants are specified in
`contracts/upload-page-ui.md`.

- [ ] T015 [US3] In `frontend/src/pages/EventUpload.jsx`, add explicit state tracking for the details request so the page can distinguish LOADING, VERIFIED, NOT VERIFIED, and UNAVAILABLE rather than inferring from a single nullable `details` value. Render no identifying values and no upload control while the request is in flight
- [ ] T016 [US3] In `frontend/src/pages/EventUpload.jsx`, implement the NOT VERIFIED state for three conditions collapsed into one user-facing outcome: a 404, a 403, and a 200 whose `patient_id`, `site_patient_id`, or `event_date` is missing or null. All three mean the event could not be verified. Show a clear message plus a route back to the events list, render no identifying fields as empty (FR-010), and follow the app's existing convention for reporting insufficient access on the 403 case
- [ ] T017 [US3] In `frontend/src/pages/EventUpload.jsx`, implement the UNAVAILABLE state for a network error or a 500: state that details are temporarily unavailable and offer a retry that re-issues the request in place. A successful retry must transition to VERIFIED without the uploader leaving the page (FR-011a)
- [ ] T018 [US3] In `frontend/src/pages/EventUpload.jsx`, gate the packet form so the file input and submit control render **only** in the VERIFIED state (FR-011, SC-003a). Exclude `criteria` from the gate condition — criteria are optional, so an event with none must remain fully uploadable (FR-006b). The gate is a view-layer guard only: it must not set status, write anything, or change which events are eligible for upload (depends on T015, T016, T017)

**Checkpoint**: All three user stories are independently functional. No packet
can be attached to an event the uploader could not verify.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T019 [P] Walk the four edge cases from `quickstart.md` against the running stack: an event with no criteria (shows `—`, upload still enabled), an event with several criteria (`name: value` pairs, same order as the events list `Criteria` column), a nonexistent event id, and a legacy bookmark URL
- [ ] T020 [P] Confirm the additive API change broke nothing: load `/events/scrub?event_id=[n]` and `/events/screen?event_id=[n]` and verify both still render their patient and date details as before. Both consume the same endpoint (`EventScrub.jsx:17`, `EventScreen.jsx`) and must ignore the new `criteria` key
- [ ] T021 [P] Confirm no VTE file was modified: `git diff --name-only main | grep 'studies/vte'` must return nothing. The VTE fork is not extended by this feature (spec Assumptions, Principle I)
- [ ] T022 Run the full backend suite `python -m pytest flask_backend/tests/ -q` from the repository root and confirm it is green, matching the T002 baseline plus the new tests
- [ ] T023 Write the PR description with what the constitution's change-review gate requires: state that this touches **shared code affecting all studies**; answer "will this break any other study's deployment?" with the T020 and T004 evidence; include the T001 baseline observation per Principle VI; and record the pre-existing authorization finding on `GET /api/events/<id>` from research D5 as a tracked follow-up, not as something fixed here

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies
- **Foundational (Phase 2)**: Depends on Setup — **blocks all user stories**, because criteria is one of the four values every story displays
- **User Story 1 (Phase 3)**: Depends on Phase 2
- **User Story 2 (Phase 4)**: Depends on Phase 3 — T008 is what makes route parity real; T012/T013 remove the dead parameters afterward
- **User Story 3 (Phase 5)**: Depends on Phase 3 for the render path it gates. Independent of Phase 4
- **Polish (Phase 6)**: Depends on all desired stories

### Story Dependencies

- **US1 (P1)**: The MVP. Depends only on Phase 2
- **US2 (P1)**: Builds on US1's T008. Not independent of US1 — route parity is a property of sourcing from the record, which US1 establishes
- **US3 (P2)**: Depends on US1's render path but is independent of US2. Can be worked in parallel with Phase 4 by a second developer

### Within Each Story

Most frontend tasks touch the single file `EventUpload.jsx` and are therefore
sequential, not parallel. The `[P]` markers are concentrated in the backend
phase and in polish, where tasks genuinely span different files.

### Parallel Opportunities

- **T004 and T005** — different files (`tests/test_table_service.py` vs `app.py`), both depend only on T003
- **T012 and T013** — different files (`EventUpload.jsx` vs `EventReupload.jsx`)
- **T019, T020, T021** — independent verification passes
- **Phase 4 and Phase 5** — two developers can split them once Phase 3 is done

---

## Parallel Example: Phase 2

```bash
# After T003 lands, these two are independent:
Task: "Add get_event_details criteria tests in flask_backend/tests/test_table_service.py"
Task: "Update get_event_details docstring in flask_backend/app.py"
# T006 (openapi regeneration) must wait for the docstring task.
```

## Parallel Example: Phase 4

```bash
# Different files, no shared state:
Task: "Simplify the row link at frontend/src/pages/EventUpload.jsx:78"
Task: "Simplify the row link at frontend/src/pages/EventReupload.jsx:68"
```

---

## Implementation Strategy

### MVP First (Phases 1–3)

1. Phase 1 — record the baseline, confirm the suite is green
2. Phase 2 — backend criteria; verify with `curl` before touching the frontend
3. Phase 3 — the upload page renders from the record
4. **STOP and VALIDATE**: open the page directly for a known event; all four
   values populate

This alone fixes the reported bug for the action-button and direct-URL routes,
which are the routes in the report.

### Incremental Delivery

1. Phase 2 → backend verifiable standalone
2. + Phase 3 → **MVP**, bug resolved for the primary routes
3. + Phase 4 → all four routes converge; dead parameters gone
4. + Phase 5 → verification guarantee enforced; no packet attaches to an
   unverified event
5. + Phase 6 → cross-page regression checks and PR gates

### Suggested MVP Scope

Phases 1–3 (T001–T011). Eleven tasks, of which four are backend and five are a
single frontend file.

---

## Notes

- `[P]` means different files with no incomplete dependency
- Most of Phases 3 and 5 touch `frontend/src/pages/EventUpload.jsx`; sequence
  them within the file rather than attempting parallel edits
- No task touches `init/` — there is no schema change and no migration
- No task touches `frontend/src/studies/vte/` — T021 asserts this
- T006 must not be skipped or hand-edited; the regenerated contract is a
  constitution gate, not a convenience
