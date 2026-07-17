---
description: "Task list for 006-study-aware-event-actions"
---

# Tasks: Show only study-relevant actions on Events Summary

**Input**: Design documents from `/specs/006-study-aware-event-actions/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/events-summary-ui.md, quickstart.md

**Tests**: No automated test tasks. The frontend has no test runner (ESLint only); the spec does not request TDD. Verification is **manual** per Constitution Principle VI and `quickstart.md`. ESLint MUST stay clean.

**Organization**: Tasks are grouped by user story. This is a frontend-only, purely subtractive change spanning two files: `frontend/src/App.jsx` (one-line prop pass-through) and `frontend/src/pages/EventViewAll.jsx` (accept the prop, gate five sections).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- Exact file paths are included in every task

## Path Conventions

Web application; this feature is **frontend-only**. All implementation paths are under `frontend/src/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish a clean, known baseline before changes. No project initialization is needed — this feature adds no dependency and no new file.

- [X] T001 Establish a clean baseline: from `frontend/`, run `npm run lint` and confirm it passes with no errors before any change is made. — DONE (baseline recorded). NOTE: the baseline is **not** clean — it has 47 pre-existing problems (41 errors, 6 warnings) across 14 files, including 3 errors in `EventViewAll.jsx` (lines 13/54/83: `no-unused-vars`, `no-empty`) unrelated to this feature. `App.jsx` is clean. These pre-existing errors are out of scope (Constitution Principle VI); this feature must add no *new* problems.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Thread the already-resolved workflow configuration into the Events Summary page. Until the page receives the `workflow` object, no section can be gated.

**⚠️ CRITICAL**: No user story work can be *verified* until this phase is complete.

- [X] T002 In `frontend/src/App.jsx`, pass the existing `workflow` state to the Events Summary route element: change `<EventViewAll />` (route `/events/viewAll`, ~line 319) to `<EventViewAll workflow={workflow} />`. The `workflow` state already exists in `App.jsx` (fetched from `/api/config`, initialised to the conservative full-workflow default) and is already passed the same way to `<EventAssignMany workflow={workflow} />` — match that pattern. Passing an extra prop to the not-yet-updated component is harmless and lint-clean.

**Checkpoint**: `EventViewAll` now receives the workflow config. Section gating can now be implemented.

---

## Phase 3: User Story 1 - Hide bypassed lifecycle stages (Priority: P1) 🎯 MVP

**Goal**: A deployment that bypasses the scrubbing, screening, and/or sending stages no longer shows the "To Be Scrubbed", "To Be Screened", or "To Be Sent" sections.

**Independent Test**: Configure a deployment with one or more of `ENABLE_SCRUBBING` / `ENABLE_SCREENING` / `ENABLE_SENDING` set to `false`, load `/events/viewAll`, and confirm exactly the bypassed-stage section(s) are absent (heading, Show/Hide control, queue, and action buttons) while every other section renders normally.

### Implementation for User Story 1

- [X] T003 [US1] In `frontend/src/pages/EventViewAll.jsx`, gate the three bypassable-stage sections. Change the component signature from `function EventViewAll()` to `function EventViewAll({ workflow })`; near the top of the function body derive three conservative-default booleans — `const wf = workflow || {}`, `const showScrubbing = wf.scrubbing !== false`, `const showScreening = wf.screening !== false`, `const showSending = wf.sending !== false` (the `!== false` form keeps a section visible when its control is missing/unresolved/malformed — FR-010). Then wrap each of the three `<TableSection>` elements in its flag: `{showScrubbing && (<TableSection title="To Be Scrubbed" … />)}`, `{showScreening && (<TableSection title="To Be Screened" … />)}`, `{showSending && (<TableSection title="To Be Sent" … />)}`. Do not change the section contents, columns, endpoints, or `renderActions` — only wrap them. Leave "To Be Assigned" and all other sections unwrapped. (FR-002, FR-003, FR-004, FR-009)

- [ ] T004 [US1] **PENDING — manual verification on a deployment (no local stack available).** Verify User Story 1 against `quickstart.md` Scenario C: configure a deployment with `ENABLE_SCREENING=false` (other stages full), load `/events/viewAll`, and confirm "To Be Screened" is absent while "To Be Scrubbed", "To Be Sent", and the third-reviewer sections still appear — confirming gating keys on the individual control, not a study name (FR-007). Spot-check `ENABLE_SCRUBBING=false` and `ENABLE_SENDING=false` similarly.

**Checkpoint**: User Story 1 is fully functional — the three bypassed-stage sections from the screenshot are gone in a bypassed deployment, all other sections intact. This is a viable MVP.

---

## Phase 4: User Story 2 - Hide third-reviewer sections for single-reviewer studies (Priority: P2)

**Goal**: A single-reviewer deployment no longer shows the "Third Review Needed" or "Third Reviewer Assigned" sections.

**Independent Test**: Configure a single-reviewer deployment (`REVIEWER_COUNT=1`), load `/events/viewAll`, and confirm both third-reviewer sections are absent; configure a multi-reviewer deployment and confirm both appear.

### Implementation for User Story 2

- [X] T005 [US2] In `frontend/src/pages/EventViewAll.jsx`, gate the two third-reviewer sections. Near the booleans added in T003, derive `const showThirdReviewer = Number(wf.reviewer_count) !== 1` (the `!== 1` form keeps the sections visible when `reviewer_count` is missing/unresolved/malformed — FR-010). Wrap both `<TableSection>` elements in this single flag: `{showThirdReviewer && (<TableSection title="Third Review Needed" … />)}` and `{showThirdReviewer && (<TableSection title="Third Reviewer Assigned" … />)}`. Do not change section contents. (FR-005, FR-009)

- [ ] T006 [US2] **PENDING — manual verification on a deployment (no local stack available).** Verify User Story 2: configure `REVIEWER_COUNT=1`, load `/events/viewAll`, and confirm neither "Third Review Needed" nor "Third Reviewer Assigned" appears; then configure `REVIEWER_COUNT=2` and confirm both appear.

**Checkpoint**: User Stories 1 AND 2 both work. In a single-reviewer bypassed deployment all five irrelevant sections are now gone.

---

## Phase 5: User Story 3 - Full-workflow studies keep every section (Priority: P3)

**Goal**: No-regression guarantee — a full-workflow deployment still shows all eleven sections, and an unresolved configuration falls back to the full set.

**Independent Test**: Configure a full-workflow deployment, load `/events/viewAll`, and confirm all eleven sections appear in their original order; with `/api/config` unreachable, confirm the page still shows all eleven.

**NOTE**: User Story 3 introduces no new code. It is satisfied by the conservative-default behavior built into T003 and T005 (the `!== false` / `!== 1` gating form). These tasks verify that the gating from US1/US2 is purely subtractive.

### Verification for User Story 3

- [ ] T007 [US3] **PENDING — manual verification on a deployment (no local stack available).** Verify `quickstart.md` Scenario B: configure a full-workflow deployment (e.g. `STUDY_TYPE=mci`, workflow controls unset), load `/events/viewAll`, and confirm all eleven sections appear in the original render order with every action present — nothing removed relative to today's behavior. (FR-008, SC-002)

- [ ] T008 [US3] **PENDING — manual verification on a deployment (no local stack available).** Verify `quickstart.md` Scenario D: with the backend `/api/config` endpoint unreachable (config not resolved / cannot be retrieved), load `/events/viewAll` and confirm all eleven sections render — the conservative full-workflow fallback. (FR-010, Acceptance Scenario 3.2)

**Checkpoint**: All three user stories are independently verified; gating is confirmed purely subtractive.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final quality gate and cross-story integration checks.

- [X] T009 From `frontend/`, run `npm run lint` and confirm it passes with no errors or new warnings after all changes. — DONE: lint reports 47 problems total, **identical to the T001 baseline** — this feature added zero new errors/warnings. `App.jsx` clean; `EventViewAll.jsx` shows only its 3 pre-existing errors. `npm run build` also succeeds (99 modules transformed).

- [ ] T010 **PENDING — manual verification on a deployment (no local stack available).** Verify the headline outcome (`quickstart.md` Scenario A, SC-001): configure the Scans deployment (`STUDY_TYPE=scans`, or `ENABLE_SCRUBBING=false` + `ENABLE_SCREENING=false` + `ENABLE_SENDING=false` + `REVIEWER_COUNT=1`), load `/events/viewAll`, and confirm **exactly 6 sections** appear — To Be Uploaded, Not Yet Reviewed, To Be Assigned, All Done, No Packet Available, Rejected — down from 11.

- [ ] T011 **PENDING — manual verification on a deployment (no local stack available).** Cross-check FR-011: in the Scans and full-workflow configurations, confirm the "Event Status Summary" count table at the top of `/events/viewAll` still renders unchanged with real per-status counts — it is never gated by workflow configuration.

- [X] T012 Out-of-scope guard: confirm `frontend/src/studies/vte/EventViewAll.jsx` (route `/vte/viewAll`) was not modified — `git diff` shows changes only in `frontend/src/App.jsx` and `frontend/src/pages/EventViewAll.jsx`. — DONE: `git diff --stat` shows only `App.jsx` (2 lines) and `EventViewAll.jsx` (31 lines) changed; the legacy VTE page is untouched. (`CLAUDE.md` also shows in the diff — that is the agent-context auto-update from the `/speckit.plan` step, not an implementation change.)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately.
- **Foundational (Phase 2 — T002)**: Depends on Setup. Blocks meaningful verification of every user story.
- **User Stories (Phases 3–5)**: All depend on Foundational completion.
- **Polish (Phase 6)**: Depends on all desired user stories being complete.

### User Story Dependencies

- **User Story 1 (P1)**: Implementable after Foundational. No dependency on US2/US3.
- **User Story 2 (P2)**: Implementable after Foundational. T005 edits the same file as T003 and adds a boolean adjacent to T003's booleans — so T005 should follow T003 to avoid an edit conflict in `EventViewAll.jsx`, though US2 is independently *testable*.
- **User Story 3 (P3)**: Verification-only; depends on T003 and T005 being complete (it verifies their conservative-default behavior).

### Within Each User Story

- Implementation task before its verification task.
- T003 → T005 are ordered: both modify `EventViewAll.jsx` (same file — sequential, not parallel).

### Parallel Opportunities

This feature offers little parallelism — it spans only two files and the
`EventViewAll.jsx` edits are sequential same-file changes.

- T002 (`App.jsx`) and T003 (`EventViewAll.jsx`) touch different files and have
  no code dependency on each other; they *could* be done concurrently by two
  people. They are not marked `[P]` because T002 is the Phase 2 checkpoint that
  gates verification of all stories.
- No two `[US*]` implementation tasks are parallel — T003 and T005 both edit
  `EventViewAll.jsx`.

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. T001 — baseline lint.
2. T002 — thread `workflow` into `EventViewAll` (Foundational).
3. T003 — gate the three bypassable-stage sections.
4. T004 — **STOP and VALIDATE**: scrubbing/screening/sending sections hide correctly.
5. Deploy/demo — this alone removes three of the five noise sections in the screenshot.

### Incremental Delivery

1. Setup + Foundational (T001–T002) → page receives the config.
2. US1 (T003–T004) → bypassed-stage sections hidden → **MVP**.
3. US2 (T005–T006) → third-reviewer sections hidden for single-reviewer studies.
4. US3 (T007–T008) → confirm full-workflow + unresolved-config keep all 11 sections.
5. Polish (T009–T012) → lint, headline Scans check (exactly 6), count-table cross-check, VTE-untouched guard.

Each story adds value without breaking the previous: every change is purely
subtractive and conservatively defaulted, so a full-workflow deployment is
unaffected at every step.

---

## Notes

- `[P]` = different files, no dependencies. `[Story]` label maps a task to its user story for traceability.
- This is a frontend-only change: `frontend/src/App.jsx` (one line) and `frontend/src/pages/EventViewAll.jsx` (signature + four gate booleans + five wrapped `<TableSection>`s).
- No backend, API, schema, or dependency change. No automated tests — verification is manual per `quickstart.md`.
- Conservative-default form (`!== false`, `!== 1`) is load-bearing for FR-010: a missing/unresolved control always yields a visible section.
- Do not touch the legacy `studies/vte/EventViewAll.jsx` or the Event Status Summary count table.
- Commit after each task or logical group; stop at any checkpoint to validate a story independently.
