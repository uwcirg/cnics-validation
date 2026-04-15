# Implementation Plan: Mark FHIR Server References as Not Currently Used

**Branch**: `001-mark-fhir-unused` | **Date**: 2026-04-14 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-mark-fhir-unused/spec.md`

## Summary

No runtime code in this repository reads or consumes the `FHIR_SERVER`
environment variable, yet the variable is advertised in environment
templates, described in the README, drawn into the technical architecture
diagram, injected by CI, and propagated by the container entrypoint. This
feature edits those seven locations so the "not currently used" status is
obvious to contributors, architects, and operators, without breaking any
deployment that still sets the variable.

Technical approach: a purely editorial change. No Python, JavaScript, SQL,
or container-image changes. Each touched file is either annotated in place
("reserved / not currently used") or has its FHIR block removed, with the
choice made per-file in Phase 0. The CI workflow is altered to confirm the
stack passes tests with `FHIR_SERVER` unset.

## Technical Context

**Language/Version**: N/A — no code in a programming language is being
modified. The touched artifacts are Markdown, dotenv config, a shell
script, and a GitHub Actions YAML workflow.
**Primary Dependencies**: N/A. The repository's runtime stack (Flask +
SQLAlchemy backend, React/Vite frontend, MariaDB, Docker Compose) is
unchanged by this feature.
**Storage**: N/A. No database, schema, or file-system layout changes.
**Testing**: Manual verification via (a) local Docker Compose smoke test
with `FHIR_SERVER` unset, and (b) one CI run on this branch with the
`FHIR_SERVER` env entry removed from `.github/workflows/tests.yml`. The
existing `pytest flask_backend/tests/` suite is the regression net — no
new tests are added because no behavior is changing.
**Target Platform**: Linux / Docker Compose, same as the rest of the
repository. No platform-specific considerations.
**Project Type**: Documentation + configuration change in an existing web
application (Flask backend + React frontend). The surrounding project is
"web application" per the template taxonomy; this feature itself ships no
application code.
**Performance Goals**: N/A. Zero runtime impact.
**Constraints**: Must not break any deployment that currently sets
`FHIR_SERVER` — see Principle III in the constitution. Must keep the
variable harmless as a pass-through; it MAY be removed from templates and
CI but MUST continue to be tolerated if present in a real `.env`.
**Scale/Scope**: Seven files to edit (see Source Code section below). Zero
new files of project code. The only new files are the spec-kit
artifacts under `specs/001-mark-fhir-unused/`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Principles evaluated against `.specify/memory/constitution.md` v1.0.0:

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Single Codebase, Many Studies | ✅ PASS | Edits apply uniformly to shared files. No per-study forks introduced. |
| II. Study Data Isolation | ✅ PASS | No data access, DB, or auth changes. |
| III. Backwards Compatibility With Legacy Data | ✅ PASS | Deployments that still set `FHIR_SERVER` remain functional; the variable is inert today and stays inert after the change. No schema, API, or lifecycle changes. |
| IV. Configuration Over Code Forks | ✅ PASS | Change removes a dormant configuration artifact; no new per-study `if` chains introduced. |
| V. Workflow and Role Parity | ✅ PASS | No lifecycle, role, or auth changes. |

Security & Data Governance: unchanged. Development Workflow & Quality Gates:
the change is scoped to documentation, templates, CI, and an entrypoint
script; PR description will note it affects all studies' shared config.

**Result**: Initial gate PASSED. No complexity justifications required.

## Project Structure

### Documentation (this feature)

```text
specs/001-mark-fhir-unused/
├── plan.md              # This file
├── research.md          # Phase 0 output — per-file retain-vs-remove decisions
├── data-model.md        # Phase 1 output — minimal: single config variable
├── quickstart.md        # Phase 1 output — local verification recipe
├── contracts/           # Phase 1 output — env-var contract note
│   └── fhir-env-var.md
└── tasks.md             # Phase 2 output (/speckit.tasks command — NOT created here)
```

### Source Code (repository root)

This feature edits existing files only; no new directories or modules are
created. Seven files in scope:

```text
README.md                           # line 50 — FHIR env var description
.env.example                        # lines 9–10 — FHIR_SERVER entry
default.env                         # lines 25–26 — FHIR_SERVER entry
.env.backup                         # lines 13–14 (commented) and 51–52 (active)
docs/technical-architecture.md      # line 39 (graph node) and 68–70 (edges)
docker-entrypoint.sh                # lines 5–7 — FHIR env.json pass-through
.github/workflows/tests.yml         # line 25 — FHIR_SERVER injected into pytest env
```

Runtime code directories (`flask_backend/`, `frontend/`) were searched and
contain zero references to `fhir` or `FHIR_SERVER`. They are out of scope.

**Structure Decision**: No new structure. The repository's existing web
application layout (Flask backend under `flask_backend/`, React frontend
under `frontend/`, configuration templates at the repo root, docs under
`docs/`, CI under `.github/workflows/`) stays as-is. This feature is an
in-place edit of seven existing files.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified.**

None. The Constitution Check passed without reservations.
