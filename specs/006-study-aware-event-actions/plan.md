# Implementation Plan: Show only study-relevant actions on Events Summary

**Branch**: `006-study-aware-event-actions` | **Date**: 2026-05-22 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/006-study-aware-event-actions/spec.md`

## Summary

The Events Summary page (`/events/viewAll`) renders the same eleven
collapsible lifecycle-status sections for every deployment. In a
bypassed-stage configuration such as the Scans study (no scrubbing, no
screening, no sending, a single reviewer), five of those sections describe
work that never happens and are permanently empty.

This feature makes the page show only sections relevant to the active
deployment. It is **frontend-only** and purely subtractive. The resolved
workflow configuration (`{scrubbing, screening, sending, reviewer_count}`)
is already exposed by `GET /api/config` and already fetched into `App.jsx`'s
`workflow` state — the same state that drives conditional route registration
for the bypassed-stage *pages*. This plan threads that existing `workflow`
object into `EventViewAll` as a prop (exactly as `EventAssignMany` already
receives it) and wraps the five gated `TableSection`s in flag checks. No
backend, API, or schema change is required.

## Technical Context

**Language/Version**: JavaScript / JSX, React 19
**Primary Dependencies**: react-router-dom 6, Vite 7 (build/dev); no new dependency
**Storage**: N/A — no persisted data; section visibility is derived from runtime workflow config
**Testing**: No frontend test runner is configured (the frontend has `eslint` only). Verification is manual per Constitution Principle VI and the quickstart; ESLint MUST stay clean.
**Target Platform**: Modern browsers; SPA built by Vite and served behind the Apache edge
**Project Type**: Web application (frontend + backend). This feature is **frontend-only**.
**Performance Goals**: No measurable change — section gating is a render-time boolean check on already-fetched config; no new network request.
**Constraints**: No schema change; no backend API change — `GET /api/config` already returns the workflow controls. Gating MUST key on the resolved controls, never on a study name (FR-007, Constitution Principle IV). Until config resolves, the full section set MUST show (FR-010). Out of scope: the legacy `/vte/viewAll` fork (`VTEEventViewAll`) — see memory note "Don't propagate the VTE fork pattern".
**Scale/Scope**: One page component (`EventViewAll.jsx`, 11 `TableSection`s) plus a one-line prop pass-through in `App.jsx`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Assessment |
|-----------|-----------|
| I. Single Codebase, Many Studies | **PASS** — gating is driven by the `STUDY_TYPE`-backed workflow controls from `/api/config`, rendered by the one shared `EventViewAll` page. No per-study component, no new `/vte/*`-style branch. |
| II. Study Data Isolation | **PASS** — no data access change; the feature only decides which already-existing `TableSection`s to render. The Event Status Summary count table is left untouched (FR-011). |
| III. Backwards Compatibility With Legacy Data | **PASS** — no schema change, no API change. The plan consumes existing fields of an existing endpoint. |
| IV. Configuration Over Code Forks | **PASS** — section visibility is derived from the resolved workflow controls (`scrubbing`, `screening`, `sending`, `reviewer_count`), never from a hardcoded study name (FR-007). No study `if/elif` chain is introduced. |
| V. Workflow and Role Parity Across Studies | **PASS** — no lifecycle state or role is redefined, removed, or renamed. Bypassed sections remain present in the component source and render whenever the workflow includes their stage — "not rendered for this deployment", not "deleted" (Principle V, selective bypass). |
| VI. Pre-Release Iteration and Discovery | **PASS** — frontend component iteration is permitted pre-release. The current observed behavior (11 sections, always) is recorded in spec.md and research.md before being changed. |

**Result**: All gates pass. Complexity Tracking is empty.

## Project Structure

### Documentation (this feature)

```text
specs/006-study-aware-event-actions/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── events-summary-ui.md   # UI rendering contract for the gated page
├── checklists/
│   └── requirements.md  # Spec quality checklist (/speckit.specify output)
└── tasks.md             # Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code (repository root)

```text
frontend/
└── src/
    ├── App.jsx                  # MODIFIED — pass the existing workflow state to <EventViewAll>
    └── pages/
        └── EventViewAll.jsx     # MODIFIED — accept workflow prop; gate the 5 stage sections
```

**Structure Decision**: Web application, frontend-only change. All work lands in
`frontend/src/`: the shared `pages/EventViewAll.jsx` page and a one-line
prop pass-through in `App.jsx`. `App.jsx` already holds the `workflow` state
(fetched from `/api/config`, defaulted conservatively to the full workflow)
and already passes it to `EventAssignMany`; this feature passes the same
object to `EventViewAll`. No backend directory, no new file, no new
dependency. The legacy `studies/vte/EventViewAll.jsx` is deliberately
untouched (out of scope per spec Assumptions).

## Complexity Tracking

No constitution violations — this section is intentionally empty.
