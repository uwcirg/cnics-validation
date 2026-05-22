# Implementation Plan: Legacy-style Top Banner

**Branch**: `005-banner-restyle` | **Date**: 2026-05-22 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/005-banner-restyle/spec.md`

## Summary

Replace the current thin, unstyled top banner with a legacy-faithful header:
a prominent CNICS logo paired with a study-typed title ("CVA Project",
"MCI Project", "Scans Project"), plus a subtle logged-in strip. The work is
entirely in the frontend's shared layout. The deployment's study type is
already exposed by the existing `GET /api/config` endpoint (`data.study_type`)
and already fetched by `App.jsx` — that response is simply not yet consumed for
display. The plan threads `study_type` from `App.jsx` into the shared
`BaseLayout`, derives a display title from it with a small pure helper, and
restyles the header to match the legacy reference screenshot. No backend,
schema, or API change is required.

## Technical Context

**Language/Version**: JavaScript / JSX, React 19  
**Primary Dependencies**: react-router-dom 6, Vite 7 (build/dev); no new dependency  
**Storage**: N/A — no persisted data; the study type comes from runtime config  
**Testing**: No frontend test runner is configured (the frontend has `eslint` only). Verification is manual per Constitution Principle VI and the quickstart; ESLint MUST stay clean.  
**Target Platform**: Modern browsers; SPA built by Vite and served behind the Apache edge  
**Project Type**: Web application (frontend + backend). This feature is **frontend-only**.  
**Performance Goals**: Banner renders on first paint; the study title fills in once `/api/config` resolves with no disruptive layout shift  
**Constraints**: No schema change; no backend API change — `GET /api/config` already returns `study_type`. Must not re-introduce the `/vte/*` study fork (Constitution Principle I; see memory note "Don't propagate the VTE fork pattern").  
**Scale/Scope**: ~30 routed pages, all rendered inside the single shared `BaseLayout`; the banner is authored once there.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Assessment |
|-----------|-----------|
| I. Single Codebase, Many Studies | **PASS** — the study title is derived from the `STUDY_TYPE`-backed `/api/config` value, rendered by the one shared `BaseLayout`. No per-study component, no `/vte/*`-style fork. |
| II. Study Data Isolation | **PASS** — no data access; presentational only. |
| III. Backwards Compatibility With Legacy Data | **PASS** — no schema change, no API change. The plan consumes an existing field of an existing endpoint. |
| IV. Configuration Over Code Forks | **PASS** — the displayed title is config-driven (`study_type`). The title helper has a single casing exception ("scans" → "Scans"); this is a pure presentation-formatting rule in a leaf helper, not workflow branching inside a pipeline module, and is documented as such. |
| V. Workflow and Role Parity Across Studies | **PASS** — no lifecycle, state, or role change. |
| VI. Pre-Release Iteration and Discovery | **PASS** — frontend component/style iteration is permitted pre-release. The current banner's behavior is recorded in spec.md (Overview) and research.md before being changed. |

**Result**: All gates pass. No entries in Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/005-banner-restyle/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── banner-ui.md     # UI rendering contract for the banner
├── checklists/
│   └── requirements.md  # Spec quality checklist (/speckit.specify output)
└── tasks.md             # Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code (repository root)

```text
frontend/
├── public/
│   └── cnics_logo.png            # existing logo asset, served at /cnics_logo.png (unchanged)
└── src/
    ├── App.jsx                   # MODIFIED — capture study_type from /api/config, pass to BaseLayout
    └── components/
        ├── BaseLayout.jsx        # MODIFIED — render logo + study title + restyled login strip
        ├── BaseLayout.css        # MODIFIED — legacy-faithful banner styling
        └── studyTitle.js         # NEW — formatStudyTitle(study_type) pure helper
```

Out of scope but noted for cleanup: `frontend/src/pages/Home.jsx` / `Home.css`
carry a per-page top-right `.cnics-logo`. Once the shared banner owns the logo,
that per-page logo is redundant; removing it is a small follow-on task captured
in tasks, not a banner requirement.

**Structure Decision**: Web application, frontend-only change. All work lands in
`frontend/src/` — the shared `components/BaseLayout.*` (the single layout every
route renders through) plus a one-line-of-intent change in `App.jsx` to pass the
already-fetched `study_type` down, and one new pure helper module in the
existing `components/` directory. No backend directory is touched.

## Complexity Tracking

No constitution violations — this section is intentionally empty.
</content>
