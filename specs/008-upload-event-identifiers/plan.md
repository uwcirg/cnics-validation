# Implementation Plan: Populate event identifiers on the upload page

**Branch**: `008-upload-event-identifiers` | **Date**: 2026-08-13 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/008-upload-event-identifiers/spec.md`

## Summary

The packet-upload page shows Patient ID, Date, and Criteria as blank, and does
not show Site Patient ID at all, because it reads those values from the query
string rather than from the event record. The most-used entry point — the
"upload" action button — passes only `event_id`, and Patient ID is broken on
every route because neither list query returns the column the link is built
from.

The fix is to source all four values from the stored record via the existing
`GET /api/events/{id}` endpoint, which already returns three of them. Only
criteria needs backend work: it is absent from that endpoint, and the list
queries that do expose it flatten names via `GROUP_CONCAT` and discard values,
which the clarified name-and-value requirement rules out. The endpoint gains a
structured `criteria` array; the page fetches on mount and renders from the
response, ignoring URL-borne values entirely.

Because the three required identifiers are enforced upstream at event creation,
their absence can only mean the event could not be verified — so the upload
control is withheld unless all three are present. Criteria are optional and
never gate the upload.

No schema change, no migration, no new dependency.

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite 7 — **no new dependency**
**Storage**: MariaDB 10.11, shared schema under `init/`. **No schema change in this feature** — all four values already exist and are populated
**Testing**: pytest (`flask_backend/tests/`, 10 modules). **No frontend test infrastructure exists**; page states are verified manually per quickstart.md (research D6)
**Target Platform**: Linux server, Docker Compose stack
**Project Type**: Web application — Flask backend + React SPA frontend
**Performance Goals**: One additional indexed single-event query per upload-page load (`criterias` is indexed on `event_id`). Identifiers visible no later than the rest of the page becomes usable (SC-005)
**Constraints**: Additive API change only — `EventScrub` and `EventScreen` consume the same endpoint and must be unaffected. `openapi.json` must be regenerated in the same PR
**Scale/Scope**: 2 backend files, 2 frontend files, 1 test module, 1 regenerated contract. Single shared page; no study-specific behavior

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Evaluated against constitution v1.4.0.

| Principle | Status | Assessment |
|---|---|---|
| **I. Single Codebase, Many Studies** | ✅ PASS | Changes land in shared `pages/` and shared backend modules. The VTE copies under `frontend/src/studies/vte/` are explicitly out of scope — the fork is not extended, consistent with Principle I's ban on divergent copies |
| **II. Study Data Isolation** | ✅ PASS | No cross-study access. Reads one event by id within the deployment's own database. No change to DB users, scopes, or storage paths |
| **III. Backwards Compatibility With Legacy Data** | ✅ PASS | Not engaged. No schema change, no migration, no change to the event lifecycle, role model, or the `patients_view` shape. The API change is to this app's own route, not a legacy contract, and is purely additive |
| **IV. Configuration Over Code Forks** | ✅ PASS | No new flag, no `STUDY_TYPE` branch, no study-specific `if/elif`. The four fields are study-agnostic |
| **V. Workflow and Role Parity** | ✅ PASS | No state added, removed, renamed, or bypassed. The event lifecycle is untouched — this feature performs no write. Withholding the upload control is a view-layer guard, not a state-machine change; an event's eligibility to be uploaded is unchanged |
| **VI. Pre-Release Iteration and Discovery** | ✅ PASS | Current behavior recorded before modification in research.md, including the exact `undefined` interpolation and the per-route breakdown. The now-dead query parameters are removed rather than left silently in place (research D4), per the unused-subsystem rule |

**Security & Data Governance**

| Rule | Status | Assessment |
|---|---|---|
| PHI handling | ✅ PASS | No new logging. Patient identifiers are rendered to an authorized uploader in the browser, not logged. No secrets involved |
| Authorization | ⚠️ PASS with documented finding | No new endpoint is created, so the "declare roles at definition time" rule is not triggered. However `GET /api/events/<id>` carries `@requires_auth` with **no role decorator** (`app.py:614-616`), unlike `need_packets` and `upload_raw` which scope non-admins by site. This feature neither creates nor widens the gap, but surfaces patient identifiers on one more page. Recorded in research D5 and quickstart as a follow-up; deliberately not changed here because it would alter behavior for `EventScrub` and `EventScreen`, which this feature does not otherwise touch |
| File storage | ✅ PASS | No change to `FILES_DIR` / `DOWNLOADS_DIR` handling |

**Development Workflow & Quality Gates**

| Gate | Status | Assessment |
|---|---|---|
| Change review — which studies affected | ✅ PASS | **Shared code, all studies.** The blast radius is `GET /api/events/<id>`'s response shape; the additive-only change keeps `EventScrub` and `EventScreen` working under every `STUDY_TYPE` |
| Schema changes | ✅ N/A | None |
| API contracts | ✅ PASS | `openapi.json` regenerated in the same PR — tracked as an explicit task, not left to CI |
| Testing discipline | ✅ PASS | No new endpoint, so no new role-decorator test is required. Backend coverage added for the changed service function, which has none today |
| Local development parity | ✅ PASS | Nothing added that works outside the compose stack |
| Feature-flag discipline | ✅ N/A | No flag introduced |
| Unused subsystem hygiene | ✅ PASS | Dead query parameters removed rather than retained (research D4) |

**Result**: PASS. No violation requiring justification; Complexity Tracking is
therefore omitted. One pre-existing authorization finding is documented and
routed to a follow-up rather than silently inherited or opportunistically
patched.

### Post-design re-check (after Phase 1)

Re-evaluated against the completed design artifacts. **Still PASS**, with two
things the design work confirmed rather than changed:

- The `criteria` addition is strictly additive (contracts/event-details.md), so
  the Principle III and cross-study blast-radius assessments hold as written —
  `EventScrub` and `EventScreen` ignore the new key and are unaffected.
- The state machine in contracts/upload-page-ui.md withholds the upload control
  in the view layer only. It sets no status, writes nothing, and does not change
  which events are eligible for upload, so Principle V's ban on redefining or
  bypassing lifecycle states remains untouched. Worth stating explicitly because
  "block the upload" could otherwise be read as a workflow change.

No new dependency, flag, endpoint, table, or study-specific branch was
introduced during design, so no gate assessed above changes status.

## Project Structure

### Documentation (this feature)

```text
specs/008-upload-event-identifiers/
├── plan.md                       # This file
├── spec.md                       # Feature specification
├── research.md                   # Phase 0 — observed behavior + decisions D1–D6
├── data-model.md                 # Phase 1 — entities (no schema change)
├── quickstart.md                 # Phase 1 — files, data flow, verification
├── contracts/
│   ├── event-details.md          # Phase 1 — GET /api/events/{id} additive change
│   └── upload-page-ui.md         # Phase 1 — page state machine and invariants
├── checklists/
│   └── requirements.md           # Spec quality checklist — all items pass
└── tasks.md                      # Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code (repository root)

```text
flask_backend/
├── app.py                        # EDIT — docstring only, route 614 (OpenAPI source)
├── table_service.py              # EDIT — get_event_details (~874): add criteria query
└── tests/
    └── test_table_service.py     # EDIT — new coverage for the criteria key

frontend/src/
└── pages/
    ├── EventUpload.jsx           # EDIT — fetch on mount, 4 states, gate form, link
    └── EventReupload.jsx         # EDIT — simplify row link (line 68)

openapi.json                      # REGENERATE — python -m flask_backend.generate_openapi
```

**Structure Decision**: Web application layout, matching the repository's
existing split — Flask backend in `flask_backend/`, React SPA in
`frontend/src/`, backend tests colocated in `flask_backend/tests/`. This
feature adds no directory and no file; every change is an edit to an existing
one plus a regenerated artifact.

Explicitly **not** in the tree above: anything under
`frontend/src/studies/vte/` (fork not extended, per spec Assumptions and
Principle I) and anything under `init/` (no schema change).

## Phase 2 notes for `/speckit.tasks`

Suggested sequencing, backend before frontend so the page has a real response
to render against:

1. **Backend criteria query** — `get_event_details` returns
   `criteria: [{name, value}]` ordered by `name, id`, `[]` when none. Verify
   the existing keys are untouched so the two sibling pages keep working.
2. **Backend tests** — event with several criteria (order, both fields), event
   with none (`[]` not `null`), and a regression assertion that the pre-existing
   keys still serialize as before.
3. **Docstring + `openapi.json`** — document the new key, regenerate, commit
   the delta.
4. **Frontend fetch and render** — replace the three `searchParams` reads with
   a fetch of `/api/events/<event_id>`; render the four values with Patient ID
   and Site Patient ID distinctly labelled; criteria as `name: value` pairs in
   response order without re-sorting.
5. **Frontend state gating** — the four states from
   `contracts/upload-page-ui.md`; the upload control exists only in VERIFIED;
   retry available in UNAVAILABLE.
6. **Link cleanup** — `EventUpload.jsx:78` and `EventReupload.jsx:68` carry
   `event_id` only; confirm old bookmarks with the extra parameters still work.
7. **Manual verification** — the four routes and four edge cases in
   quickstart.md.

Story mapping: tasks 1–4 deliver User Story 1 (P1); task 6 with 4 completes
User Story 2 (P1); task 5 delivers User Story 3 (P2) and is independently
testable once 4 is in place.
