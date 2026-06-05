# Implementation Plan: Study-aware home review sections

**Branch**: `007-study-aware-review-sections` | **Date**: 2026-06-05 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/007-study-aware-review-sections/spec.md`

## Summary

The home page renders two fixed-header guidance boxes — "Review packets should contain:" and "Review Instructions:" — whose body content (list items, free text, and optional `.doc`/`.pdf` links) is currently hard-coded for the MI study. This feature makes that body content a per-study lookup keyed on the deployment's `STUDY_TYPE`, with `mci` as the default fallback. `mci` keeps its existing eight-item checklist and instruction links; `scans` shows a short DEXA-specific list and "No additional instructions" with no links.

Technical approach: a frontend-only change following the existing `frontend/src/components/studyTitle.js` precedent — a small pure presentation module maps `study_type` → guidance content, and `Home.jsx` consumes it. The study type already reaches the frontend on `GET /api/config` (`data.study_type`); `App.jsx` passes it into `Home`. No backend, schema, API-contract, or dependency changes.

## Technical Context

**Language/Version**: JavaScript / JSX, React 19 (frontend); no backend change  
**Primary Dependencies**: react-router-dom 6, Vite 7 (build/dev) — no new dependency  
**Storage**: N/A — content is static, derived from runtime `STUDY_TYPE` config; no persisted data  
**Testing**: No frontend unit-test runner is configured in this repo (mirrors `studyTitle.js`, which ships without a test). Verification is `npm run build` + manual check under at least one `STUDY_TYPE` per the constitution's testing-discipline rule.  
**Target Platform**: Browser (frontend served via Vite build); web application  
**Project Type**: Web application (existing `frontend/` + `flask_backend/`); this feature touches `frontend/` only  
**Performance Goals**: N/A — content is rendered inline; no measurable runtime budget beyond existing page render  
**Constraints**: Must not transiently flash one study's content then swap to another's (FR-010); must keep the two `h3` headers byte-identical across studies (FR-001)  
**Scale/Scope**: 2 boxes; 2 defined studies (`mci`, `scans`) + `mci` fallback; ~1 new module + edits to `Home.jsx` and the `Home` prop wiring in `App.jsx`

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Single Codebase, Many Studies** — PASS. Per-study content lives in one shared module keyed on `STUDY_TYPE`; no fork, no per-study copy of `Home.jsx`.
- **II. Study Data Isolation** — PASS / N/A. No data access; content is static presentation text. A given deployment serves exactly one study via its `STUDY_TYPE`.
- **III. Backwards Compatibility With Legacy Data** — PASS / N/A. No schema, no legacy-data contract, no event-lifecycle or role change. Existing MI file URLs under `/files/...` are preserved verbatim.
- **IV. Configuration Over Code Forks** — PASS. Differentiation is via the existing `STUDY_TYPE` env var consumed by shared code (mechanism 1, most-preferred). The content map is a data table, not an `if/elif` chain scattered across modules; `mci`/`scans`/fallback are entries in one table, consistent with `studyTitle.js`.
- **V. Workflow and Role Parity Across Studies** — PASS / N/A. No lifecycle state, role, or workflow primitive is added, removed, or renamed. This is cosmetic homepage guidance only.
- **VI. Pre-Release Iteration and Discovery** — PASS. Current observed behavior is recorded (hard-coded MI content in `Home.jsx:225-262`); the change documents what it replaces. No opaque code is rewritten blindly.

Security & Governance: No new endpoint (the boxes already render to the same home-page audience), no PHI logging, `/files` serving is unchanged (`FILES_DIR` read-only). No `openapi.json` regeneration needed — no backend route/shape change.

**Result: PASS — no violations, Complexity Tracking not required.**

## Project Structure

### Documentation (this feature)

```text
specs/007-study-aware-review-sections/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output (UI content contract)
│   └── review-guidance.md
└── checklists/
    └── requirements.md  # From /speckit.specify
```

### Source Code (repository root)

```text
frontend/
├── src/
│   ├── components/
│   │   ├── studyTitle.js          # EXISTING precedent — pure study-type → display mapping
│   │   └── reviewGuidance.js      # NEW — pure study-type → { packets, instructions } content map
│   ├── pages/
│   │   └── Home.jsx               # EDIT — render the two boxes from reviewGuidance, constant h3 headers
│   └── App.jsx                    # EDIT — pass resolved study_type into <Home>, gate render to avoid flash

app/webroot/files/                 # EXISTING file store served at /files/<name> (mci files already present;
                                   # scans adds none in this feature)
```

**Structure Decision**: Web application; this feature is contained entirely within `frontend/src/`. The new pure module `reviewGuidance.js` sits beside `studyTitle.js` in `components/` because it is the same kind of artifact (a study-type-keyed presentation helper with an `mci` fallback). `Home.jsx` becomes a thin renderer over that data; `App.jsx` threads `study_type` (already fetched from `/api/config`) into `Home` and gates the boxes until config resolves so no other study's content flashes first.

## Complexity Tracking

> No Constitution Check violations. Section intentionally empty.
