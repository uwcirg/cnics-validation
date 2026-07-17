# Implementation Plan: Interactive reviewer-assignment page

**Branch**: `004-reviewer-assignment` | **Date**: 2026-05-21 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/004-reviewer-assignment/spec.md`

## Summary

Replace the static `frontend/src/pages/EventAssignMany.jsx` placeholder with a working reviewer-assignment page. An administrator opens the "To Be Assigned" queue (the flag-aware queue already shown on View All Events), selects one or more events, chooses a reviewer (or, in a two-reviewer deployment, a first and a second reviewer), and confirms. On success the events advance — first-reviewer assignment moves an event to the `assigned` status — and leave the queue.

The page is **one shared page for all studies**; the number of reviewer slots is driven entirely by the resolved `reviewer_count` workflow control (already passed to the component as the `workflow` prop), never by a study-name check (Constitution Principle IV).

Technical approach: the single-reviewer path (P1, the MVP that unblocks the scans lifecycle) is **frontend-only** — it uses the existing `POST /api/events/assign_many` endpoint unchanged. The two-reviewer path (P2) requires a small, backward-compatible backend extension so that both reviewers are assigned in **one atomic transaction**; research below establishes that one-call-per-slot cannot satisfy FR-012 ("fully assigned before it advances out of the queue") because the queue is gated on `assign_date IS NULL` and any single-slot call sets `assign_date`.

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy (backend); React 19, react-router-dom 6, Vite (frontend)
**Storage**: MariaDB 10.11, shared schema (`init/`). **No schema change in this feature.**
**Testing**: `pytest` (backend, `flask_backend/tests/`). The frontend has no test runner configured — verification is `eslint` + manual checks against a running deployment.
**Target Platform**: Linux server, Docker Compose stack (`web` + `backend` + `mariadb`)
**Project Type**: Web application (React frontend + Flask backend)
**Performance Goals**: Interactive admin tooling — a batch assignment completes within ~2 s; queue pages are 20 events.
**Constraints**: Admin-only action; single canonical `.env` / `docker-compose.yaml`; no cross-study data access; the queue and slot count must be flag-driven, not study-name-driven.
**Scale/Scope**: One frontend page rewritten, one menu link added, one backend endpoint + one service function extended (additive), one backend test added, `openapi.json` regenerated. Queues of up to a few hundred events, paginated.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | Assessment | Status |
|-----------|------------|--------|
| **I. Single Codebase, Many Studies** | The page is a single shared `pages/EventAssignMany.jsx` serving every study. No fork, no new study branch. The pre-existing `studies/vte/EventAssignMany.jsx` is a separate, stale per-study copy — **not touched** by this feature and explicitly out of scope (see Complexity / note below). | ✅ Pass |
| **II. Study Data Isolation** | No cross-study access. The page operates only on the current deployment's events and users. | ✅ Pass |
| **III. Backwards Compatibility With Legacy Data** | No schema change. No change to legacy CNICS/CakePHP data contracts or to `cnics_data.Patients` views. The `assign_many` request-body extension touches *this app's own* pre-release API only — governed by Principle VI, not III. | ✅ Pass |
| **IV. Configuration Over Code Forks** | Reviewer-slot count is read from the resolved `reviewer_count` workflow control (`workflow` prop / `GET /api/config`). No `STUDY_TYPE` branch, no study-name `if/elif`. The queue uses the existing flag-aware `by_status/screened` endpoint. | ✅ Pass |
| **V. Workflow and Role Parity Across Studies** | Uses shared lifecycle state names (`assigned`) and shared roles (`reviewer`, `admin`) unchanged. Honors `REVIEWER_COUNT` selective bypass: a single-reviewer deployment shows one slot and the backend already rejects second/third slots. No shared state redefined, removed, or renamed. | ✅ Pass |
| **VI. Pre-Release Iteration and Discovery** | Recorded prior behavior (the static placeholder) is captured in the spec. The `assign_many` body extension is a permitted pre-release change to this app's own API; `openapi.json` MUST be regenerated in the same change (quality gate). Research records that the spec's "partial failure across slots" edge case is superseded by the atomic design, with the rationale. | ✅ Pass |
| **Security & Data Governance** | `POST /api/events/assign_many` already carries `@requires_auth` + `@requires_roles('admin')`; the extension adds no new endpoint and no new role surface. The `/events/assignMany` route is already admin-gated in `App.jsx`. The reviewer list is read from the existing `GET /api/tables/users` (`@requires_auth`); it exposes only low-sensitivity usernames/sites, no PHI. No patient identifiers logged. | ✅ Pass |
| **Quality Gates** | `openapi.json` regenerated after the request-body change; a backend integration test added for the two-reviewer atomic path exercising the admin role decorator. PR states scope = shared (all studies). | ✅ Pass |

**Result**: No violations. Complexity Tracking table is empty.

*Note (not a violation): `frontend/src/studies/vte/EventAssignMany.jsx` is a stale per-study duplicate of this page that posts an obsolete request shape. It is reached only via the separate `/vte/assignMany` route and is **out of scope** here. Converging or retiring it is pre-existing tech debt for a future change; this plan neither depends on it nor modifies it.*

## Project Structure

### Documentation (this feature)

```text
specs/004-reviewer-assignment/
├── plan.md              # This file (/speckit.plan command output)
├── spec.md              # Feature specification (/speckit.specify)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
│   └── assign_many.md   # Updated contract for POST /api/events/assign_many
├── checklists/
│   └── requirements.md  # Spec quality checklist (/speckit.specify)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created here)
```

### Source Code (repository root)

```text
frontend/
└── src/
    ├── pages/
    │   └── EventAssignMany.jsx     # REWRITE: placeholder → working assignment page
    ├── components/
    │   ├── MenuBar.jsx             # EDIT: add admin "Assign Charts" menu link (FR-003)
    │   ├── DataTable.jsx           # reused for the pageable queue display (read-only)
    │   └── Toast.js                # reused for success/error feedback (read-only)
    └── App.jsx                     # no change — /events/assignMany route already exists, admin-gated

flask_backend/
├── app.py                         # EDIT: events_assign_many() accepts optional reviewer2_id
├── table_service.py               # EDIT: assign_events() — atomic two-reviewer path
└── tests/
    └── test_*.py                  # ADD: two-reviewer atomic assignment test + slot validation

openapi.json                       # REGENERATE: python -m flask_backend.generate_openapi
```

**Structure Decision**: Existing web-application layout (`frontend/` React + `flask_backend/` Flask). This feature is concentrated in one rewritten frontend page plus a small additive backend extension. The frontend route and the backend endpoint already exist; no new files are created in source code — only the placeholder page is rewritten and four existing files are edited.

## Phase 0: Research

See [research.md](./research.md). Key decisions:

1. **Queue source** — reuse `GET /api/events/by_status/screened`, which dispatches to the flag-aware `get_to_be_assigned_with_total`. With scrubbing/screening bypassed it surfaces `uploaded` events; identical to View All Events' "To Be Assigned" section.
2. **Reviewer list** — reuse `GET /api/tables/users?limit=2000`, filtering `reviewer_flag` client-side (the pattern already used by `pages/EventAssignThird.jsx`). No new endpoint.
3. **Single-reviewer assignment (P1)** — one `POST /api/events/assign_many` call with `{event_ids, reviewer_id, slot:"first"}`. **No backend change.**
4. **Two-reviewer assignment (P2)** — requires an **atomic** backend extension. One-call-per-slot is rejected: the queue predicate is `assign_date IS NULL`, and every single-slot call sets `assign_date`, so the event would leave the queue after the first call, violating FR-012. The endpoint gains an optional `reviewer2_id` field (valid only with `slot:"first"`); `assign_events` sets both reviewers + status in one transaction.
5. **Reference implementation** — `pages/EventAssignThird.jsx` is the structural model (it already uses the *current* endpoint contract). `studies/vte/EventAssignMany.jsx` is used for layout ideas only — its request shape is stale.
6. **Spec edge case superseded** — with the atomic design, "partial failure across slots" cannot occur; the whole assignment succeeds or fails together. Recorded per Principle VI.

## Phase 1: Design & Contracts

- **Data model**: [data-model.md](./data-model.md) — no new entities or schema; documents the existing `events` columns the assignment writes (`reviewer1_id`, `reviewer2_id`, `assigner_id`, `assign_date`, `status`) and the queue/reviewer read shapes.
- **Contracts**: [contracts/assign_many.md](./contracts/assign_many.md) — the updated `POST /api/events/assign_many` contract (existing single-slot form + new optional `reviewer2_id`).
- **Quickstart**: [quickstart.md](./quickstart.md) — how to exercise both the single-reviewer and two-reviewer paths.
- **Agent context**: refreshed via `.specify/scripts/bash/update-agent-context.sh claude`.

## Complexity Tracking

> No Constitution Check violations. No entries required.
