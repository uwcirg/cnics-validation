<!--
SYNC IMPACT REPORT
==================
Version change: 1.1.3 → 1.2.0
Rationale: MINOR bump. Narrows Principle IV's "Configuration Over
Code Forks" preference list from four mechanisms to three by removing
item 4 (per-study `.env.<study>` files and per-study compose overrides
`docker-compose.<study>.yaml`), and adds a new normative paragraph
requiring each deployment to use a single canonical `.env` and a
single canonical `docker-compose.yaml`. Side-by-side deployments on
one host are now required to differentiate via `COMPOSE_PROJECT_NAME`,
not filename suffixes. The combination of removing a previously
permitted mechanism and adding a new MUST clause materially expands
existing normative guidance, hence MINOR rather than PATCH. No prior
constitutional principle is rescinded; no governance authority changes.
Prior SYNC IMPACT REPORTs for v1.1.0 → v1.1.1, v1.1.1 → v1.1.2, and
v1.1.2 → v1.1.3 are preserved below.

---- v1.1.3 → v1.2.0 (this amendment) ----

Modified principles:
  - IV. Configuration Over Code Forks — preference list narrowed
    from four mechanisms to three. Item 4 ("Docker-compose overrides
    (`docker-compose.<study>.yaml`) and per-study `.env.<study>`
    files") is removed; the deployment-overlay pattern is no longer
    a permitted mechanism for study-specific differentiation. A new
    normative paragraph is added requiring each deployment to use a
    single canonical `.env` and a single canonical `docker-compose.yaml`,
    with side-by-side deployments differentiated via
    `COMPOSE_PROJECT_NAME` rather than filename suffixes. The
    rationale paragraph is extended to explain why the overlay
    pattern is being ruled out.
  - I, II, III, V, VI. unchanged.

Source material: user direction 2026-05-08 ("I do not want the system
to use .env files that are named per-study; instead, I only want it
to use .env. The docs should indicate how to modify .env to accommodate
the specific study that the deployment is targeting"; "Drop both
docker-compose overrides (`docker-compose.<study>.yaml`) and per-study
`.env.<study>` files."). The narrowing reflects an architectural
decision made before any in-tree code or deployment depends on the
overlay pattern; the prior developer scaffolded overlay references
in docs but never wired them up at the application layer.

Modified sections: N/A (Principle IV body changes only).
Added sections: N/A
Removed sections: N/A

Templates requiring updates:
  - ✅ docs/template-setup-guide.md — four changes:
    (1) "Current implementation status" banner added at top of
    file explaining the single-`.env` / single-`docker-compose.yaml`
    rule; (2) "### 3. Deployment Configuration" subsection rewritten
    from overlay-pattern documentation to single-`.env` framing;
    (3) "Step-by-Step Multi-Study Deployment Process" and
    "Deployment Commands" sections rewritten around
    `cp default.env .env` and plain `docker compose up -d`, with
    VTE as the worked alt-study example. Steps that depend on
    runtime study selection logic (currently scaffolding-only)
    are tagged as such; (4) inline `stroke` → `vte` normalizations
    in scaffolding examples (`### 1. Study Configuration System`
    and `### 2. Study-Specific Components` directory listings,
    plus a stale `# or docker-compose overrides` Best Practices
    bullet replaced with a `COMPOSE_PROJECT_NAME` reference) so
    the file consistently uses VTE as the alt-study example.
  - ✅ default.env — already documents `COMPOSE_PROJECT_NAME` as the
    differentiation mechanism for side-by-side deployments on one
    host (added in earlier session work; no further change needed
    for this amendment).
  - ✅ docs/architecture-overview.md — Diagram 1's "Configuration
    Layer" subgraph had its `Docker Compose Overrides` node and
    associated edges removed, since deployment-overlay configs are
    no longer a permitted differentiation mechanism.

Deferred / TODO:
  - None.

---- v1.1.2 → v1.1.3 (prior amendment, preserved for history) ----

Modified principles:
  - I. Single Codebase, Many Studies — enumerated study list narrowed
    from "MI, VTE, CVA, Heart Failure, AFIB, Malignancy" to "MI, VTE,
    CVA, Heart Failure, AFIB". The principle's normative requirement
    is unchanged for the remaining studies.
  - II–VI. unchanged.

Source material: user direction 2026-05-08 ("remove the Malignancy
section altogether, as that study does not use this codebase");
`flask_backend/models/studies/malignancy.py` was committed with a
header stating the module "is commented out until Malignancy study
migration is needed" and was never imported by any runtime code;
`flask_backend/study_config.py`'s Malignancy block was already
commented out from inception.

Modified sections: N/A (only the Principle I enumerated list).
Added sections: N/A
Removed sections: N/A

Templates requiring updates:
  - ✅ docs/template-setup-guide.md — Malignancy "Example" deployment
    section removed; "Novel Systems" subheading removed (Malignancy
    was its only entry); Malignancy entry removed from the
    "Deployment Commands" block; illustrative 'cancer' references in
    scaffolding examples removed.
  - ✅ docs/architecture-overview.md — Malignancy nodes removed from
    the overall-system, configuration-flow, deployment, and
    migration-timeline mermaid diagrams.
  - ✅ flask_backend/models/studies/malignancy.py — dormant module
    removed; was never imported by any runtime code.
  - ✅ flask_backend/study_config.py — commented-out Malignancy entry
    removed.
  - ✅ init/02-schema-malignancy.sql — dormant schema file removed
    (was no longer referenced by any in-tree code or doc after the
    above changes).

Deferred / TODO:
  - None. RATIFICATION_DATE preserved at 2026-04-14.

---- v1.1.1 → v1.1.2 (prior amendment, preserved for history) ----

Modified principles: I–VI unchanged.

Modified sections:
  - Development Workflow & Quality Gates → "API contracts" bullet:
    command reference changed from `python scripts/generate_openapi.py`
    to `python -m flask_backend.generate_openapi`.

Source material: feature 002-constitution-sync follow-up — the
referenced `scripts/generate_openapi.py` had never been committed to
the repo; the script was created fresh during the implementation of
002 and subsequently moved into the `flask_backend` package so it
lives next to the code it introspects, which matches the project's
preference for reusing existing directory structures over creating
new top-level directories.

Templates requiring updates:
  - ✅ .github/workflows/update-openapi.yml — command and path-filter
    updated to `python -m flask_backend.generate_openapi`.
  - ✅ README.md — "OpenAPI Documentation" section updated to the new
    command.
  - ✅ .specify/templates/* — no change needed.
  - ✅ docs/* — no change needed.

Deferred / TODO:
  - None.

---- v1.1.0 → v1.1.1 (prior amendment, preserved for history) ----

Rationale: PATCH bump. Clarifies the first-release authentication mechanism
to match what `.htaccess` actually configures: HTTP Basic Auth at the Apache
edge with `AuthBasicProvider ldap` (plus an `ldap-group` require rule),
with Apache forwarding the authenticated identity to the Flask backend as
`X-Remote-User` after a successful bind. The prior wording ("header-based
authentication via `X-Remote-User` injected by an Apache/LDAP front-end")
was accurate at the Flask layer but obscured the basic+ldap edge, which is
where the first-release auth decision actually lives. No new rule is
introduced, no principle is added or removed, and no prior normative
guidance is rescinded — this is a precision fix, hence PATCH.

Source material: `.htaccess` (first-release Apache edge config:
`AuthType basic`, `AuthBasicProvider ldap`, `AuthLDAPURL`, `require
ldap-group ...`), `flask_backend/app.py` (reads `X-Remote-User`), user
amendment input 2026-04-15 ("Per the .htaccess file, for the first
release the system will use basic auth, with ldap as the
AuthBasicProvider").

Modified principles:
  - I–IV. unchanged
  - V. Workflow and Role Parity Across Studies — unchanged wording; the
    parenthetical "`X-Remote-User` injected by Apache/LDAP" remains
    accurate because Apache does inject that header after basic+ldap
    succeeds. No edit needed.
  - VI. Pre-Release Iteration and Discovery — unchanged.

Modified sections:
  - Security & Data Governance → "Authentication (first release)" bullet
    rewritten to explicitly cite basic auth + `AuthBasicProvider ldap` +
    `require ldap-group` at the Apache edge, and to describe the
    edge→backend handoff via `X-Remote-User` rather than presenting the
    header as the sole mechanism.

Added sections: N/A
Removed sections: N/A

Templates requiring updates:
  - ✅ .specify/templates/plan-template.md — no change needed.
  - ✅ .specify/templates/spec-template.md — no change needed.
  - ✅ .specify/templates/tasks-template.md — no change needed.
  - ⚠ README.md — "Authentication and Authorization" section describes
    the Flask-side contract correctly but does not mention the
    basic+ldap edge. Optional follow-up to add a one-line pointer; not
    blocking.
  - ⚠ docs/template-setup-guide.md — if it documents auth setup for new
    study deployments, verify it names basic+ldap (not Keycloak) as the
    first-release edge before the first release cut.

Deferred / TODO:
  - None. RATIFICATION_DATE preserved at 2026-04-14.
-->

# CNICS Validation Constitution

## Core Principles

### I. Single Codebase, Many Studies

The repository MUST host every supported clinical validation study (MI, VTE,
CVA, Heart Failure, AFIB, and any future studies) from one shared
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

**Scope note**: This principle governs compatibility with the *legacy
CNICS/CakePHP data and downstream consumers of that data*. Freedom to
iterate on this React/Flask app's own internal API shapes, new-table
columns, route URLs, and component structures is governed separately by
Principle VI while the app remains pre-release.

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

Adding a study-specific `if/elif` chain inside a shared module is a code smell
and MUST be refactored into a factory, a config flag, or a study-scoped file.

Each deployment MUST use a single canonical `.env` file and a single
canonical `docker-compose.yaml`. Per-study deployment overlays —
including filenames like `.env.<study>` or `docker-compose.<study>.yaml`,
and `--env-file` / `-f` flag combinations that select among them — are
NOT a permitted mechanism for study-specific differentiation. To target
a specific study, a deployment edits the contents of its `.env` (study
selector, study identity, feature flags); to differentiate side-by-side
deployments on the same host, set distinct `COMPOSE_PROJECT_NAME` values
in each deployment's `.env`.

**Rationale**: Configuration-driven differentiation is what makes the "one
codebase, many deployments" model maintainable. Sprinkling study checks across
shared code reproduces the fork-era problem in miniature. The single-`.env`
single-`docker-compose.yaml` rule eliminates a class of deployment confusion
where operators forget which `--env-file` or `-f` combination matches their
target study, and ensures the deployment artifact (`.env`) is unambiguous
about which study and which host the deployment is for.

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

### VI. Pre-Release Iteration and Discovery

This application has not yet had a tagged first release, and the original
developer is no longer on the team; parts of the runtime behavior are
under-documented and MUST be re-discovered as work touches them. Until the
first tagged release, the following rules apply:

- Internal API shapes, new-table column names, route URLs, frontend
  component hierarchies, and configuration keys introduced by the
  React/Flask rewrite MAY be changed without a deprecation window. Principle
  III's compatibility guarantee covers the *legacy CNICS/CakePHP data and
  its downstream consumers*, not this app's own in-progress contracts.
- Any change to code whose intended behavior is unclear MUST first record
  the current observed behavior (in the PR description, a linked issue, or
  an inline note) before modifying it. "I don't know what this did, so I
  rewrote it" is not acceptable; "I verified it did X by Y, and am
  replacing it with Z because…" is.
- Dead or apparently-unused subsystems (for example, code paths wired to
  environment variables that no current component reads) MUST be either
  marked as unused in documentation or removed — not silently left in
  place. The `FHIR_SERVER` handling is the template for this pattern.
- Once the first release is tagged, this principle stops granting latitude
  for this app's own APIs; from that point forward, breaking changes to
  the React/Flask contracts MUST follow a semver/migration process
  comparable to Principle III's treatment of legacy data.

**Rationale**: Pretending this codebase is production-stable when it has
never shipped would force us to preserve behavior we do not actually
understand, and would lock in the previous developer's undocumented
choices. At the same time, unbounded rewriting of opaque code is how
silent regressions happen. The rule is: freedom to iterate *plus*
obligation to document what was there first.

## Security & Data Governance

- **PHI handling**: All study databases contain protected health information.
  Code MUST NOT log patient identifiers, free-text review notes, or file
  contents at INFO level or above. Secrets (DB passwords, LDAP binds, session
  keys) MUST be sourced from environment variables or mounted secret files,
  never committed to the repo.
- **Authentication (first release)**: The first tagged release MUST
  authenticate users at the Apache edge using HTTP Basic Auth with LDAP
  as the `AuthBasicProvider`, exactly as configured in the repository's
  `.htaccess` (`AuthType basic`, `AuthBasicProvider ldap`, `AuthLDAPURL
  ldaps://…`, plus a `require ldap-group …` rule scoping access to the
  appropriate CNICS LDAP group). After a successful bind, Apache MUST
  forward the authenticated identity to the Flask backend as the
  `X-Remote-User` request header; the Flask backend's `@requires_auth`
  decorator reads that header and looks the user up in the `users`
  table. Both halves of this contract are load-bearing: the Apache edge
  is what enforces "you must be in the LDAP group to get anywhere", and
  `X-Remote-User` + `users.login` is what lets the backend attach roles
  to the request. Keycloak integration is *not* part of the first
  release. Any in-tree Keycloak code paths, dev shims, or permissive
  "no header required" fallbacks MUST be gated behind a non-default
  environment flag, MUST default to off, and MUST NOT be enabled in
  study deployments targeting the first release. Removing or
  short-circuiting either half of the basic+ldap→`X-Remote-User` chain
  in a study deployment is prohibited.
- **Authentication (future releases)**: Keycloak-based authentication is
  planned for a later release. When that work lands, it MUST be added as
  an additional authentication mode selectable by configuration — not as a
  replacement that breaks the header-based contract — and this
  constitution MUST be amended (MINOR bump at minimum) to describe the
  new mode, its role-mapping, and its rollout plan before it is enabled
  in any study deployment.
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
  (`python -m flask_backend.generate_openapi`, run from the repository
  root) whenever backend routes, request bodies, or response shapes
  change. The CI action that refreshes it on push is the authoritative
  source. Pre-release, changes to this app's own routes are permitted
  per Principle VI, but the regenerated `openapi.json` MUST land in
  the same PR so reviewers see the contract delta.
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
- **Unused subsystem hygiene**: Per Principle VI, environment variables,
  endpoints, or modules that exist in the tree but are not read by any
  runtime component MUST be either documented as unused (with a note on
  why they are retained) or removed. Silent dead code is not acceptable
  while the project is still mapping out its own behavior.

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
  violate Principles I–VI. If a violation is necessary (e.g., an emergency
  hotfix that breaks Principle IV temporarily), it MUST be recorded in the
  PR description with a follow-up issue to restore compliance.
- **Runtime guidance**: `docs/template-setup-guide.md` remains the
  operational reference for deploying new studies. This constitution governs
  *what* is allowed; that guide documents *how* to do it within those rules.

**Version**: 1.2.0 | **Ratified**: 2026-04-14 | **Last Amended**: 2026-05-08
