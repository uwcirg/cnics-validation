# Phase 0 Research: Legacy-style Top Banner

**Feature**: 005-banner-restyle | **Date**: 2026-05-22

The spec carried no `[NEEDS CLARIFICATION]` markers. Research here records the
current observed behavior (Constitution Principle VI) and resolves the small
implementation unknowns so Phase 1 can proceed.

## Current observed behavior (recorded before change, per Principle VI)

The banner is rendered by `frontend/src/components/BaseLayout.jsx`, the single
layout every route renders through (`frontend/src/App.jsx` wraps `<Routes>` in
`<BaseLayout>`). Today it renders:

- A `<header className="header">` containing only a text link "CNICS
  Validation" and a logged-in line ("You are logged in as: {username} | Log
  Out"). `BaseLayout.css` styles `.header` as a flat light-grey strip.
- No logo. No study identity.
- `<MenuBar>` below the header, then page content.

`App.jsx` already calls `GET /api/config` on mount but only reads
`data.workflow`; `data.study_type` from the same response is discarded.

## Decision 1 — Source of the study type

**Decision**: Consume `data.study_type` from the existing `GET /api/config`
response. `App.jsx` already fetches this endpoint; extend its handler to store
`study_type` in state alongside `workflow`, and pass it to `BaseLayout`.

**Rationale**: `flask_backend/app.py` `get_config()` already returns
`{ data: { study_type, workflow: {...} } }`. The value originates from the
`STUDY_TYPE` environment variable via the shared `study_config` layer — exactly
the configuration-driven source Constitution Principle IV requires. No backend
change, no new endpoint, no schema change.

**Alternatives considered**:
- *New endpoint / new field* — rejected; the field already exists.
- *Build-time env var baked into the frontend bundle* — rejected; it would
  couple the static bundle to one study and break the "one build, configured
  per deployment" model.

## Decision 2 — Study title derivation rule

**Decision**: A pure helper `formatStudyTitle(studyType)` in a new module
`frontend/src/components/studyTitle.js`:

- Trim the input. If empty / null / undefined → return `null` (caller renders
  no title; see Decision 5).
- If the lower-cased value is `"scans"` → base label `"Scans"` (title case).
- Otherwise → base label is the input upper-cased (covers acronym studies:
  `cva` → `CVA`, `mci` → `MCI`, and any future study).
- Append `" Project"` to the base label → `"Scans Project"`, `"CVA Project"`,
  `"MCI Project"`.

**Rationale**: Matches the spec's casing rules (FR-004/005/006) and the
unknown-study fallback (FR-011). A single leaf-level pure function is trivially
correct and is *presentation formatting*, not workflow branching — it does not
belong to a pipeline module, so the lone `"scans"` exception is not the
study-`if/elif` code smell Principle IV warns against.

**Alternatives considered**:
- *Lookup table of `{study: displayName}`* — rejected; it would need an entry
  per study and silently mis-handle a new study type, whereas the uppercase
  default handles any future acronym study with no edit.
- *Backend returns a ready-made display title* — rejected as heavier than
  needed; the rule is pure presentation and belongs in the view layer. (May be
  revisited if non-frontend consumers ever need the same label.)

## Decision 3 — Logo asset

**Decision**: Use the existing `frontend/public/cnics_logo.png`, referenced as
`/cnics_logo.png`.

**Rationale**: The asset is already committed and already used elsewhere
(`Home.jsx` and the `studies/vte/*` pages reference `/cnics_logo.png`). It is
byte-identical (5226 bytes) to the legacy `app/webroot/files/cnics_logo.png` and
to the untracked `cnics_logo.png` the user dropped in the repo root — so the
root copy adds nothing and no new asset needs to be committed. The banner sizes
the logo up via CSS for prominence (FR-001).

**Alternatives considered**:
- *Commit the repo-root `cnics_logo.png`* — rejected; it duplicates an
  identical, already-served asset.

## Decision 4 — Where the banner is authored

**Decision**: Author the banner once in the shared `BaseLayout.jsx` /
`BaseLayout.css`. `App.jsx` passes `study_type` (and the existing `auth`) into
`BaseLayout`.

**Rationale**: Every route renders through `BaseLayout`, so editing it once
satisfies "consistent on every page" (FR-008) with no per-page work and no
study fork. This is consistent with the memory note *"Don't propagate the VTE
fork pattern"* — no `/vte/*` branch is involved.

## Decision 5 — Resilience: title not yet resolved, logo fails to load

**Decision**:
- While `/api/config` is unresolved or failed, `study_type` is empty;
  `formatStudyTitle` returns `null` and the banner renders the logo with **no**
  title text — never `"undefined Project"` (FR-010). When config resolves,
  React re-renders and the title appears.
- The logo `<img>` always has descriptive `alt` text, so a failed image load
  degrades to readable alt text; the title and Log Out control are independent
  DOM nodes and remain visible and usable (FR-012).

**Rationale**: `App.jsx`'s `study_type` state starts empty and is only set on a
successful config fetch — the existing pattern already used for `workflow`.
Returning `null` for empty input keeps the "no broken placeholder" guarantee in
the helper itself rather than scattering guards in the view.

**Alternatives considered**:
- *Fallback title "CNICS Validation" before config resolves* — rejected;
  showing then swapping a title is more jarring than briefly showing none, and
  `/api/config` resolves quickly on the same origin.

## Decision 6 — Styling approach

**Decision**: Restyle in the existing `BaseLayout.css` using plain CSS classes,
matching the legacy reference screenshot: logo + study title as a left-aligned
header block (logo prominent, title the largest text), and the logged-in line
as a subtle full-width strip beneath. No CSS framework or new dependency.

**Rationale**: The project styles components with co-located plain `.css` files
(`BaseLayout.css`, `MenuBar.css`, `Home.css`); the banner follows that
convention. The legacy screenshot is the visual acceptance reference (SC-003).

**Alternatives considered**:
- *Introduce a CSS framework / component library* — rejected; disproportionate
  to a single header and inconsistent with the existing styling approach.

## Testing approach

The frontend has no test runner (only ESLint). Per Constitution Principle VI
and the quickstart, verification is manual: load pages under representative
`STUDY_TYPE` values and compare against the legacy reference screenshot.
`formatStudyTitle` is written as an isolated, side-effect-free function so it
can be exercised by hand or by a future test runner without rework. ESLint MUST
remain clean.
</content>
