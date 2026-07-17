# Phase 0 Research: Study-aware home review sections

The spec carried no `[NEEDS CLARIFICATION]` markers; the one open decision (unknown-study fallback) was resolved by a documented assumption. The research below records the design decisions and the existing-code facts they rest on, so Phase 1 and `/speckit.tasks` have a single source of truth.

## Decision 1 — Where per-study content lives

**Decision**: A new pure JS module `frontend/src/components/reviewGuidance.js` exports a function that maps a study type to the two boxes' content (items + optional links), with `mci` as the built-in fallback. `Home.jsx` imports and renders it.

**Rationale**: This is the exact shape of the existing `frontend/src/components/studyTitle.js` (a pure, side-effect-free study-type → presentation mapping with an `mci`/derived fallback), added one commit ago for STUDY_TITLE branding. Reusing that precedent satisfies Constitution Principle IV (config-driven, single shared module, no `if/elif` scattered across modules) and keeps the diff small and idiomatic.

**Alternatives considered**:
- *Inline `if (studyType === 'scans')` in `Home.jsx`* — rejected: reproduces the fork-era per-study branching Principle IV forbids, and does not scale to the P2 "add a new study" story.
- *Deliver content from the backend on `/api/config`* — rejected: adds a backend route-shape change (forcing `openapi.json` regeneration) and server-side content management for what is static presentation text. The frontend already knows the study type; no server round-trip buys anything. Keeps the feature frontend-only.
- *Per-study component file under `frontend/src/studies/<study>/`* — rejected as heavier than needed: that mechanism (Principle IV item 3) is for whole study-scoped components, not a two-field content table. A single keyed data map is the lighter, lower-risk fit and matches `studyTitle.js`.

## Decision 2 — How the study type reaches `Home`

**Decision**: `App.jsx` already fetches `GET /api/config` and holds `study_type` in state (`App.jsx:101-102`). Pass it to `Home` as a prop (`<Home auth={auth} studyType={studyType} />`).

**Rationale**: The plumbing exists; `Home` is currently rendered without the value. Threading the existing state in is minimal and avoids a second fetch. `study_type` is non-sensitive and already exposed to the same authenticated home-page audience.

**Alternatives considered**:
- *`Home` fetches `/api/config` itself* — rejected: duplicates the fetch `App.jsx` already performs and risks two sources of truth.

## Decision 3 — Avoiding the content flash (FR-010)

**Decision**: Render the two boxes only once the config has resolved (study type is known), rather than rendering `mci` immediately and swapping. Concretely, gate on the study type being populated (it is `''` until `/api/config` resolves; the backend always returns a non-empty value — default `mci` — once resolved, per `get_study_type()` in `flask_backend/study_config.py:74-76`).

**Rationale**: `App.jsx` initializes `studyType` to `''` and fills it after the async config fetch. Without gating, a `scans` deployment would paint MI content for one frame and then swap — the exact transient the spec forbids. Gating on "resolved" shows the boxes a beat later but always with the correct study's content. The rest of the home page already renders progressively, so a brief absence of these bottom boxes is consistent with existing behavior.

**Alternatives considered**:
- *Default `''` → `mci` and accept the flash* — rejected: violates FR-010 for non-mci deployments.
- *Server-side render the correct content* — rejected: out of scope; the app is a client-rendered Vite SPA.

## Decision 4 — `scans` content and file links

**Decision**: `scans` defines, for "Review packets should contain:", the two items "DEXA scan reports" and "Please redact their PHI (names, birthdate, etc)", with **no** links; and for "Review Instructions:", the single text line "No additional instructions", with **no** links. `mci` retains its current eight items and the existing `/files/CNICS MI ...` `.doc`/`.pdf` links verbatim.

**Rationale**: Taken directly from the user's instruction and the annotated screenshot. The current MI links target real files already present in `app/webroot/files/` (verified: `CNICS MI Review packet assembly instructions.{doc,pdf}`, `CNICS MI reviewer instructions.{doc,pdf}`), served at `/files/<name>` via `flask_backend/app.py:1551`. No DEXA/scans files exist in the repo today; `scans` intentionally links none, so no missing-file links are introduced.

**Alternatives considered**:
- *Add placeholder scans files now* — rejected: none were provided; the spec says scans has no links. Adding empty placeholders would create broken downloads.

## Decision 5 — Fallback for unrecognized / unset study type

**Decision**: Any study type without a defined entry (including `''` before resolution conceptually, and studies like `vte`/`cva` that define no entry here) falls back to the `mci` content.

**Rationale**: Mirrors the system-wide default study type (`STUDY_TYPE` defaults to `mci`) and guarantees usable guidance rather than empty boxes (FR-008). Matches `studyTitle.js`'s "uppercase fallback" philosophy of always producing something sensible. Documented as a revisitable assumption in the spec should a blank-box fallback ever be preferred.

## Existing-code facts relied upon

| Fact | Location |
|------|----------|
| Two hard-coded MI boxes (the code being replaced) | `frontend/src/pages/Home.jsx:225-262` |
| `Home` rendered without study context | `frontend/src/App.jsx:129` |
| `study_type` fetched and held in `App` state | `frontend/src/App.jsx:96-102` |
| `/api/config` returns `data.study_type` (non-sensitive) | `flask_backend/app.py:657-700` |
| Default study type is `mci` | `flask_backend/study_config.py:74-76` |
| Pure study-type → presentation precedent | `frontend/src/components/studyTitle.js` |
| MI instruction files exist on disk | `app/webroot/files/CNICS MI *.{doc,pdf}` |
| Files served at `/files/<name>` | `flask_backend/app.py:1551` |
