<!--
SYNC IMPACT REPORT
==================
Version change: (initial) → 1.0.0
Rationale: First ratified constitution; no prior version to diff against, so the
bump is recorded as an initial MAJOR (1.0.0) establishing baseline governance.

Source material: docs/template-setup-guide.md (multi-study deployment guide),
README.md (backend/auth/file-handling notes).

Modified principles: N/A (initial adoption)
Added sections:
  - Core Principles (I–V)
  - Security & Data Governance
  - Development Workflow & Quality Gates
  - Governance

Removed sections: N/A

Templates requiring updates:
  - ✅ .specify/templates/plan-template.md — Constitution Check gate references
    this file generically; no edits needed (gates evaluated per-feature).
  - ✅ .specify/templates/spec-template.md — no constitution-specific sections;
    no edits needed.
  - ✅ .specify/templates/tasks-template.md — task categories (setup,
    foundational, polish) are compatible with the principles; no edits needed.
  - ⚠ docs/template-setup-guide.md — source doc remains authoritative for
    deployment mechanics; no edits needed but keep in sync on future amendments.
  - ⚠ README.md — does not yet reference the constitution; optional follow-up
    to add a pointer from the root README to .specify/memory/constitution.md.

Deferred / TODO:
  - None. RATIFICATION_DATE set to initial adoption (2026-04-14).
-->

# CNICS Validation Constitution

## Core Principles

### I. Single Codebase, Many Studies

The repository MUST host every supported clinical validation study (MI, VTE,
CVA, Heart Failure, AFIB, Malignancy, and any future studies) from one shared
codebase. Forking or long-lived study branches is prohibited. Study-specific
behavior MUST be expressed through configuration (environment variables,
docker-compose overrides, schema files, and study-scoped component
directories), never through divergent copies of shared modules.

**Rationale**: The legacy CakePHP v1.x systems fragmented into per-study forks
that drifted and became unmaintainable. Consolidating into one codebase is the
explicit motivation for this repository; any change that re-introduces
fork-style divergence contradicts the reason this project exists.

### II. Study Data Isolation (NON-NEGOTIABLE)

Each study deployment MUST run against its own database, its own container
set, and its own domain. Cross-study data access MUST NOT be possible from
within a running deployment. Authentication scopes, database users, and file
storage paths MUST be distinct per study. Code MUST NOT hard-code the
assumption that only one study exists in the process, but a given *deployment*
MUST serve exactly one study.

**Rationale**: Patient-level clinical data is sensitive and governed by
study-specific IRB and data-use agreements. A leak from one study's reviewers
into another study's records would be a compliance incident, not just a bug.
Enforcing isolation at the deployment boundary is the simplest durable guard.

### III. Backwards Compatibility With Legacy Data

Schema changes and API changes MUST preserve compatibility with existing
CNICS/CakePHP data unless an explicit, documented migration script is provided
alongside the change. The core event lifecycle
(`created → uploaded → scrubbed → screened → assigned → sent → reviewer1_done
→ reviewer2_done → (third_review_*) → done`), the `users` role-flag model
(`admin`, `uploader`, `reviewer`, `third_reviewer`), and the views over
`cnics_data.Patients` MUST remain usable by downstream consumers during and
after migration.

**Rationale**: The modernization effort is a migration, not a greenfield
rewrite. Existing validation data, adjudication state, and reviewer
assignments must survive the move from CakePHP v1.x to the React/Flask stack;
breaking them would invalidate in-flight studies.

### IV. Configuration Over Code Forks

Any new study-specific behavior MUST be added via one of the following
mechanisms, in order of preference:

1. Environment variables (e.g., `STUDY_TYPE`, `ENABLE_PRE_SCRUB`,
   `ENABLE_QUESTIONNAIRES`) consumed by shared code.
2. Study-specific schema files under `init/` (e.g., `02-schema-<study>.sql`).
3. Study-specific component directories under `frontend/src/studies/<study>/`
   and model files under `flask_backend/models/<study>.py`, loaded by a
   factory keyed on `STUDY_TYPE`.
4. Docker-compose overrides (`docker-compose.<study>.yaml`) and per-study
   `.env.<study>` files.

Adding a study-specific `if/elif` chain inside a shared module is a code smell
and MUST be refactored into a factory, a config flag, or a study-scoped file.

**Rationale**: Configuration-driven differentiation is what makes the "one
codebase, many deployments" model maintainable. Sprinkling study checks across
shared code reproduces the fork-era problem in miniature.

### V. Workflow and Role Parity Across Studies

All studies MUST share the same core validation workflow primitives: the
event lifecycle, role-based access control (admin/uploader/reviewer/
third_reviewer), file upload and download flows, and the header-based
authentication contract (`X-Remote-User` injected by Apache/LDAP). Studies MAY
extend the lifecycle (e.g., VTE's `prescrubbed`/`prescrub_rejected` states) or
add study-specific review fields, but they MUST NOT redefine, remove, or
rename shared states or roles.

**Rationale**: Reviewers, admins, and uploaders work across studies. A
consistent mental model and consistent API contracts reduce training cost,
reduce bug surface, and let shared infrastructure (auth middleware, logging,
monitoring) apply uniformly.

## Security & Data Governance

- **PHI handling**: All study databases contain protected health information.
  Code MUST NOT log patient identifiers, free-text review notes, or file
  contents at INFO level or above. Secrets (DB passwords, LDAP binds, session
  keys) MUST be sourced from environment variables or mounted secret files,
  never committed to the repo.
- **Authentication**: Header-based auth via `X-Remote-User` is the production
  contract. Any permissive dev/Keycloak fallback MUST be gated behind a
  non-default environment flag and MUST NOT be enabled in study deployments.
- **Authorization**: Endpoints MUST be protected with `@requires_auth` plus
  explicit role decorators (`@requires_roles` / `@requires_any_role`). New
  endpoints that touch events, reviews, users, or files MUST declare their
  role requirements at definition time — "open by default" is prohibited.
- **File storage**: `FILES_DIR` is read-only; `DOWNLOADS_DIR` is writable.
  Code that writes into `FILES_DIR` is a bug. Study deployments MUST mount
  these as separate volumes per study.
- **Data isolation audits**: Before a new study deployment goes live, the
  team MUST verify that its DB user cannot read other studies' schemas and
  that its frontend origin is distinct from any other study's origin.

## Development Workflow & Quality Gates

- **Change review**: Every PR MUST state which studies it affects (shared,
  one specific study, or all studies). PRs that touch shared code MUST be
  reviewed with the "will this break any other study's deployment?" question
  answered explicitly.
- **Schema changes**: Changes to `init/02-schema-<study>.sql` or to shared
  tables (`users`, `events`, `logs`) MUST ship with a migration plan for
  existing deployments and MUST preserve Principle III (legacy compatibility)
  or document the break.
- **API contracts**: `openapi.json` MUST be regenerated
  (`python scripts/generate_openapi.py`) whenever backend routes, request
  bodies, or response shapes change. The CI action that refreshes it on push
  is the authoritative source.
- **Testing discipline**: New backend endpoints SHOULD have at least one
  integration test that exercises the role decorators with representative
  user fixtures. Frontend study-specific components SHOULD be verified under
  at least one `STUDY_TYPE` configuration before merge. Tests that require
  cross-study data access are prohibited (Principle II).
- **Local development parity**: Docker Compose is the canonical local
  environment. Changes that only work outside the compose stack (e.g.,
  require a developer to run `flask` directly against a bespoke DB) MUST be
  accompanied by compose updates so other contributors can reproduce them.
- **Feature-flag discipline**: Study-specific feature flags
  (`ENABLE_PRE_SCRUB`, `ENABLE_QUESTIONNAIRES`, etc.) MUST default to the
  safer/off value for unknown studies and MUST be read through the shared
  configuration layer, not via direct `os.environ` reads scattered across
  modules.

## Governance

- **Authority**: This constitution supersedes ad-hoc conventions and informal
  agreements. Where this document and a template, README, or inline comment
  disagree, the constitution wins and the other artifact MUST be updated.
- **Amendments**: Amendments require (a) a PR that updates this file with a
  Sync Impact Report, (b) review by a project maintainer, and (c)
  corresponding updates to any templates, docs, or code paths the amendment
  invalidates. An amendment is not "done" until those downstream updates
  land in the same PR or in an explicitly tracked follow-up.
- **Versioning policy**: Constitution versions follow semantic versioning.
  - MAJOR: a principle is removed, redefined in a backward-incompatible way,
    or governance authority changes.
  - MINOR: a new principle or a new normative section is added, or existing
    guidance is materially expanded.
  - PATCH: clarifications, wording fixes, typo corrections, or non-semantic
    refinements.
- **Compliance review**: PR reviewers MUST verify that changes do not
  violate Principles I–V. If a violation is necessary (e.g., an emergency
  hotfix that breaks Principle IV temporarily), it MUST be recorded in the
  PR description with a follow-up issue to restore compliance.
- **Runtime guidance**: `docs/template-setup-guide.md` remains the
  operational reference for deploying new studies. This constitution governs
  *what* is allowed; that guide documents *how* to do it within those rules.

**Version**: 1.0.0 | **Ratified**: 2026-04-14 | **Last Amended**: 2026-04-14
