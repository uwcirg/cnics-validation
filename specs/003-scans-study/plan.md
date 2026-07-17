# Implementation Plan: Implement the `scans` Study Type (Selective-Bypass Workflow)

**Branch**: `003-scans-study` | **Date**: 2026-05-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/003-scans-study/spec.md`

## Summary

This feature implements the `scans` study type defined by Constitution v1.4.0
(Principle V, "selective bypass"): a deployment that runs the lifecycle
`created → uploaded → assigned → reviewer1_done → done`, skipping scrubbing,
screening, sending, and second-/third-reviewer adjudication. Bypass is driven
by four configuration controls — `ENABLE_SCRUBBING`, `ENABLE_SCREENING`,
`ENABLE_SENDING`, `REVIEWER_COUNT` — resolved through a shared configuration
layer, never by branching on `STUDY_TYPE` inside pipeline code.

**Key discovery, recorded per Constitution Principle VI before any change:**
the back half of the event lifecycle is **not wired today for any study**.
`assign` and `send` set date columns but never advance `events.status`; there
is **no review-submission endpoint** (the frontend review form's submit handler
is a placeholder `alert()`); and no runtime code reads `STUDY_TYPE` or any
feature flag. The phase queues are date-driven, while the reviewer queue keys
on a `status='sent'` value nothing ever sets. Delivering the `scans` lifecycle
therefore means **building** the shared assign→review→done machinery — gated by
the four controls — not merely bypassing an existing pipeline. Full
observed-behavior notes are in [research.md](./research.md). This is
behavior *completion*, not behavior *change*: with the controls at their
conservative defaults no existing study's observed behavior regresses, so
FR-017 and SC-006 hold.

**Technical approach** (detailed in [research.md](./research.md)):
1. Make `flask_backend/study_config.py` the real, imported shared
   configuration layer — resolve the four controls with the `scans` profile as
   defaults and per-control `.env` overrides; validate at startup.
2. Wire flag-aware `status` transitions for assign/send and add a new shared
   review-submission endpoint with `REVIEWER_COUNT`-aware completion.
3. Make the phase/queue queries in `table_service.py` flag-aware.
4. Expose the resolved config to the frontend via a new `GET /api/config` so
   the UI hides bypassed-stage elements by flag, not by study name.
5. Land the pending documentation (`default.env`, `README.md`,
   `docs/template-setup-guide.md`) and regenerate `openapi.json`.

No database schema change and no migration are required (see research.md
Decision 9).

## Technical Context

**Language/Version**: Python 3.11 (Flask backend); JavaScript / JSX, React 19 (frontend)
**Primary Dependencies**: Flask, SQLAlchemy, mysql-connector-python; React 19, react-router-dom 6, Vite
**Storage**: MariaDB 10.11 — shared schema `init/02-schema.sql`; **no schema change in this feature**
**Testing**: pytest 7.2.1 (`flask_backend/tests/`); ESLint for frontend (no frontend unit-test runner present)
**Target Platform**: Linux server; Docker Compose stack (`mariadb` + `backend` + `web`) behind an Apache `.htaccess` edge
**Project Type**: Web application (Flask API backend + React SPA frontend)
**Performance Goals**: N/A — small internal clinical-validation tool; correctness over throughput
**Constraints**: PHI must not be logged at INFO+; non-edge services bind `127.0.0.1`; the basic+ldap → `X-Remote-User` auth chain must stay intact; one canonical `.env` and one `docker-compose.yaml` per deployment
**Scale/Scope**: ~6 study types, one study per deployment; modest event/user counts; ~30 backend routes, ~28 frontend pages

No NEEDS CLARIFICATION remain — all unknowns are resolved in
[research.md](./research.md).

## Constitution Check

*GATE: evaluated against `.specify/memory/constitution.md` v1.4.0. Initial
evaluation and post-design re-evaluation are identical — the design adds no
new violations.*

| Principle | Gate | Status | Notes |
|---|---|---|---|
| I. Single Codebase, Many Studies | `scans` added by configuration, no fork or study branch | ✅ PASS | `scans` is a `STUDY_TYPE` value + four controls; no divergent module copies |
| II. Study Data Isolation | `scans` deployment keeps own DB/containers/domain; no cross-study access | ✅ PASS | FR-025; deployment provisioning is a separate operational task (spec Assumption); no cross-study test introduced |
| III. Backwards Compatibility With Legacy Data | Legacy data, `events.status` enum, `users` roles, `patients_view` stay usable | ✅ PASS | No schema change, no migration (research Decision 9); enum/roles untouched (FR-015, FR-016) |
| IV. Configuration Over Code Forks | Study behavior via env vars through shared code; no `STUDY_TYPE` if/elif in shared modules | ✅ PASS | Four controls resolved in one shared config layer (FR-002); pipeline branches on resolved flags, never the study name (FR-003) |
| V. Workflow and Role Parity | Shared states/roles not redefined/removed/renamed; bypass via named flags | ✅ PASS | This feature *is* the implementation of the v1.4.0 Principle V amendment; bypassed states retained (FR-015) |
| VI. Pre-Release Iteration and Discovery | Observed prior behavior recorded before change; `openapi.json` regenerated with route changes | ✅ PASS | Prior behavior recorded in research.md + contracts; `openapi.json` regenerated in the change set; `study_config.py`'s "unused scaffolding" debt is discharged |

Security & Data Governance: the new `/api/events/<id>/review` endpoint carries
`@requires_auth` + `@requires_any_role('reviewer','admin')`; `/api/config`
carries `@requires_auth` + `@requires_any_role('admin','uploader','reviewer','third_reviewer')` and exposes only non-sensitive workflow flags. No PHI
logging is added. Auth chain unchanged.

Quality Gates: feature-flag discipline honored — conservative defaults are the
full-workflow values (`ENABLE_*=true`, `REVIEWER_COUNT=2`); all flags read
through the shared configuration layer, not scattered `os.environ` reads;
`openapi.json` regenerated; new endpoints get integration tests exercising the
role decorators.

**Result: PASS — no violations, no Complexity Tracking entries required.**

## Project Structure

### Documentation (this feature)

```text
specs/003-scans-study/
├── plan.md              # This file
├── research.md          # Phase 0 — observed behavior + design decisions
├── data-model.md        # Phase 1 — config entity + status state machine
├── quickstart.md        # Phase 1 — deploy & verify a scans study
├── contracts/
│   └── workflow-api.md  # Phase 1 — new/changed API contracts
├── checklists/
│   └── requirements.md  # Spec quality checklist (pre-existing)
└── tasks.md             # Phase 2 — created by /speckit.tasks (NOT here)
```

### Source Code (repository root)

Existing layout; this feature touches the files marked below. No new top-level
directories; `scans` adds no per-study schema file and no per-study frontend
directory (spec Assumptions).

```text
flask_backend/
├── app.py               # CHANGED — new POST /api/events/<id>/review and
│                         #   GET /api/config; assign/send now advance status;
│                         #   import the shared config layer at startup
├── study_config.py      # CHANGED — repurposed as the shared configuration
│                         #   layer: resolves the four controls + STUDY_TYPE,
│                         #   profile defaults, startup validation
├── table_service.py     # CHANGED — flag-aware assign/send transitions and
│                         #   flag-aware phase/queue eligibility queries
├── models.py            # UNCHANGED — events.status enum retained as-is
├── generate_openapi.py  # used to regenerate openapi.json
└── tests/               # NEW tests — config resolution/validation,
                          #   /review endpoint, flag-aware queues

frontend/src/
├── App.jsx              # CHANGED — fetch GET /api/config; provide workflow
│                         #   config; guard bypassed-stage routes by flag
├── components/
│   └── MenuBar.jsx      # CHANGED — hide bypassed-stage nav entries by flag
├── pages/
│   ├── EventReview.jsx  # CHANGED — submit handler POSTs to /api/events/<id>/review
│   ├── Admin.jsx        # CHANGED — hide bypassed-stage queues/actions by flag
│   └── EventAssignMany.jsx  # CHANGED — single-reviewer-only assignment by flag
└── studies/             # UNCHANGED — vte/, cva/; scans adds NO directory

init/                    # UNCHANGED — no scans schema file, no migration
docker-compose.yaml      # CHANGED — inject STUDY_TYPE + the four controls into the backend service environment
default.env              # CHANGED — STUDY_TYPE + four controls documented
README.md                # CHANGED — four controls in Environment Variables
docs/template-setup-guide.md  # CHANGED — scans worked example added
openapi.json             # REGENERATED — new/changed routes
```

> **Post-Implementation Correction (2026-05-20)**: `docker-compose.yaml` was
> absent from this list as originally planned. `STUDY_TYPE` and the four
> controls were documented in `default.env` but never added to the `backend`
> service's `environment:` block, so Docker Compose read them only for
> `${...}` substitution within the compose file and never injected them into
> the container — every deployment silently resolved to the `mci`
> full-workflow profile regardless of `.env`. Remediated by passing the five
> variables through as `${VAR:-}` entries. Tracked as tasks.md Phase 8 / T029.

**Structure Decision**: Web-application layout (`flask_backend/` +
`frontend/`), used as-is. The feature is delivered by extending shared modules
and the shared frontend pages plus one repurposed configuration module — no new
directories, consistent with Constitution Principle IV (configuration over
forks) and the spec's Assumption that `scans` is pure selective bypass.

## Complexity Tracking

No Constitution Check violations — this section is intentionally empty.

> Scope note (not a constitution violation): the feature is larger than the
> spec's "selective bypass" framing implies, because the shared assign→review→
> done machinery must be built for the first time (research.md Decision 1).
> This is surfaced for `/speckit.tasks` sizing, not as a justified violation.

## Phase Status

- [x] Phase 0 — research.md (all unknowns resolved)
- [x] Phase 1 — data-model.md, contracts/workflow-api.md, quickstart.md
- [x] Phase 1 — agent context updated (`.specify/scripts/bash/update-agent-context.sh claude`)
- [ ] Phase 2 — tasks.md (run `/speckit.tasks`)
