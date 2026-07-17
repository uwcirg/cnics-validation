---
description: "Task list for 005-banner-restyle"
---

# Tasks: Legacy-style Top Banner

**Input**: Design documents from `/specs/005-banner-restyle/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/banner-ui.md, quickstart.md

**Tests**: No automated tests — the frontend has no test runner (ESLint only).
Per Constitution Principle VI and `quickstart.md`, verification is manual. No
test tasks are generated; `formatStudyTitle` is written as an isolated pure
function so it can be checked by hand or by a future test runner.

**Organization**: Tasks are grouped by user story for independent delivery.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on incomplete tasks)
- **[Story]**: US1 / US2 — maps to the user stories in `spec.md`
- All paths are repository-relative; this feature touches `frontend/` only.

## Path Conventions

Web application — frontend-only change. All source paths are under
`frontend/src/`. No backend directory is touched.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm a clean starting point before any change.

- [X] T001 Establish a clean baseline: from `frontend/`, run `npm install` (if needed), then `npm run lint` and `npm run build`; confirm both pass before making changes.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The study-title helper and the config plumbing that the banner content depends on.

**⚠️ CRITICAL**: Both user stories build on this phase — complete it first.

- [X] T002 [P] Create the pure helper `formatStudyTitle(studyType)` in `frontend/src/components/studyTitle.js`, implementing the derivation rule in `data-model.md`: trim input; empty/null/undefined → return `null`; lower-cased `"scans"` → base `"Scans"`; otherwise base = upper-cased input; return base + `" Project"`. Export it as a named export.
- [X] T003 [P] In `frontend/src/App.jsx`, capture `data.study_type` from the existing `GET /api/config` response into component state alongside `workflow` (default to `""`), and pass it as a `study_type` prop to `<BaseLayout>`. Do not add a new fetch — extend the existing `fetchConfig` handler.

**Checkpoint**: The title helper exists and `BaseLayout` receives `study_type`.

---

## Phase 3: User Story 1 - See which study the deployment is serving (Priority: P1) 🎯 MVP

**Goal**: The shared banner shows the CNICS logo prominently plus a study-typed title ("CVA Project", "MCI Project", "Scans Project") on every page.

**Independent Test**: Start the app under a given `STUDY_TYPE` (e.g. `cva`, then `scans`); on any page confirm the banner shows a prominent logo and the correctly-cased `<X> Project` title. Verifiable before any restyling (contract checks C1–C9 in `contracts/banner-ui.md`).

### Implementation for User Story 1

- [X] T004 [US1] In `frontend/src/components/BaseLayout.jsx`, accept the `study_type` prop, import `formatStudyTitle` from `./studyTitle`, and render in the header: the CNICS logo as an `<img src="/cnics_logo.png">` with descriptive `alt` text (top-left), and the study title from `formatStudyTitle(study_type)` beside it. When the helper returns `null`, render no title element (no `"undefined Project"`) — satisfies FR-001, FR-002, FR-003, FR-010, FR-011, FR-012.
- [X] T005 [P] [US1] Remove the now-redundant per-page logo so the banner is the only logo: delete the `<img className="cnics-logo" src="/cnics_logo.png" .../>` from `frontend/src/pages/Home.jsx` and the `.cnics-logo` rule from `frontend/src/pages/Home.css`.

**Checkpoint**: Every page shows the logo and the correct study title. MVP is functional.

---

## Phase 4: User Story 2 - A banner that looks like the legacy application (Priority: P2)

**Goal**: The banner is styled to be visually faithful to the legacy reference — logo + title as a header block, logged-in line as a subtle strip.

**Independent Test**: With the logo and title present (US1), place the banner side by side with `CVA.Screenshot 2026-05-21 103208.jpg` and confirm layout, spacing, and styling match (contract check C10, SC-003).

**Depends on**: User Story 1 — US2 styles the markup US1 introduces (`spec.md` notes US2 "builds on User Story 1").

### Implementation for User Story 2

- [X] T006 [US2] In `frontend/src/components/BaseLayout.jsx`, restructure the header markup so the logo + study title form one left-aligned header block and the logged-in line ("logged in as …" + Log Out) is a separate subtle strip; keep the logged-in wording unchanged; apply class names for the styling in T007. Satisfies FR-007, FR-009.
- [X] T007 [US2] In `frontend/src/components/BaseLayout.css`, restyle `.header` and the new banner classes to be visually faithful to the legacy reference `CVA.Screenshot 2026-05-21 103208.jpg`: logo prominent, study title the largest text in the banner, logged-in line a subtle full-width strip; ensure the title and Log Out control stay readable and unclipped on a narrow viewport. Satisfies FR-007, FR-008, narrow-viewport edge case.

**Checkpoint**: Banner is a faithful match to the legacy reference on every page.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Validate the change.

- [X] T008 From `frontend/`, run `npm run lint` and `npm run build`; confirm ESLint is clean and the production build succeeds.
- [ ] T009 Execute the `specs/005-banner-restyle/quickstart.md` acceptance walkthrough — contract checks C1–C10 and Success Criteria SC-001…SC-006 — including the side-by-side comparison with `CVA.Screenshot 2026-05-21 103208.jpg`. **Partially done**: the `formatStudyTitle` derivation (C1–C5) was verified — 10/10 cases pass. The visual checks (C4, C6–C10) and the legacy side-by-side need a running deployment and remain for the user to confirm.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS both user stories.
- **User Story 1 (Phase 3)**: Depends on Foundational. Delivers the MVP.
- **User Story 2 (Phase 4)**: Depends on Foundational **and** User Story 1 (it styles the markup US1 introduces in `BaseLayout.jsx`).
- **Polish (Phase 5)**: Depends on all desired user stories being complete.

### Within / across user stories

- T004 and T005 are both US1; they touch different files and may run in parallel.
- T006 depends on T004 (same file, `BaseLayout.jsx`). T007 depends on T006 (it styles the classes T006 adds).
- T002 and T003 touch different files (`studyTitle.js`, `App.jsx`) — parallelizable.

### Parallel Opportunities

```bash
# Phase 2 — Foundational (different files):
Task T002: "Create formatStudyTitle helper in frontend/src/components/studyTitle.js"
Task T003: "Thread study_type into BaseLayout in frontend/src/App.jsx"

# Phase 3 — User Story 1 (different files):
Task T004: "Render logo + study title in frontend/src/components/BaseLayout.jsx"
Task T005: "Remove redundant per-page logo from frontend/src/pages/Home.jsx + Home.css"
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1: Setup (T001).
2. Phase 2: Foundational (T002, T003) — CRITICAL, blocks both stories.
3. Phase 3: User Story 1 (T004, T005).
4. **STOP and VALIDATE**: confirm every page shows the logo and correct study
   title (contract checks C1–C9). This is a shippable increment on its own.

### Incremental Delivery

1. Setup + Foundational → plumbing ready.
2. User Story 1 → study identity visible on every page → demo (MVP).
3. User Story 2 → legacy-faithful styling → demo.
4. Polish → lint/build + quickstart walkthrough.

---

## Notes

- [P] = different files, no dependency on an incomplete task.
- This feature is frontend-only: no backend, schema, or API change. `study_type`
  already exists on the `GET /api/config` response.
- Do not re-introduce a `/vte/*`-style study fork — the banner stays in the one
  shared `BaseLayout` (Constitution Principle I).
- The repo-root `cnics_logo.png` (untracked) is byte-identical to the already
  served `frontend/public/cnics_logo.png`; do not commit the root copy.
- Commit after each task or logical group; stop at the Phase 3 checkpoint to
  validate the MVP independently.
</content>
