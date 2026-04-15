# Feature Specification: Align Repo Docs & Code With Constitution v1.1.1

**Feature Branch**: `002-constitution-sync`
**Created**: 2026-04-15
**Status**: Draft
**Input**: User description: "update per recent changes to the constitution."

## Context

The project constitution was amended twice in quick succession:

- **v1.1.0** added Principle VI (Pre-Release Iteration and Discovery),
  codified that the project is pre-release and the original developer has
  left, and expanded the Security & Data Governance authentication guidance
  to state that Keycloak is deferred to a later release and that any in-tree
  Keycloak paths MUST default off.
- **v1.1.1** clarified that the first release's authentication mechanism is
  HTTP Basic Auth at the Apache edge with `AuthBasicProvider ldap` (per the
  repository's `.htaccess`), after which Apache forwards the authenticated
  identity to the Flask backend as `X-Remote-User`.

Both Sync Impact Reports flagged downstream artifacts (README.md,
flask_backend/README.md, docs/*) as "pending" because the constitution
edits alone do not propagate to the places a new contributor or a
deployment operator actually reads. This feature closes that gap.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New contributor learns first-release auth without reading the Apache config (Priority: P1)

A new contributor joins the project, reads `README.md`, and needs to
answer "how does a request get authenticated in production for the first
release?" Today, the root README's "Authentication and Authorization"
section describes only the Flask-side `X-Remote-User` decorator contract
and still lists an open question about "whether to require header auth
in all environments or keep the permissive dev/Keycloak fallback." That
open question was already resolved by the constitution (basic+ldap at
the edge, Keycloak deferred), but the README does not reflect the
resolution. The contributor is left uncertain about which side of that
decision the project actually landed on.

**Why this priority**: Onboarding friction is the most visible symptom
of doc drift, and the auth model is the single most consequential thing
a new contributor must understand before touching an endpoint. This is
the story that most directly prevents wrong-by-default implementations.

**Independent Test**: A reader who has seen only the constitution and
the updated root README (not `.htaccess`, not Flask source) can
correctly describe the two-layer contract (Apache basic+ldap edge →
`X-Remote-User` → Flask `@requires_auth` → `users.login` lookup) and
can state without ambiguity that Keycloak is not supported in the
first release.

**Acceptance Scenarios**:

1. **Given** a reader with no prior project context, **When** they
   read the root README's Authentication section, **Then** they can
   identify both the Apache edge (basic+ldap) and the Flask-side
   header contract as load-bearing halves of the same production
   auth path.
2. **Given** the same reader, **When** they look at the "Outstanding
   next steps" list in the README, **Then** they see no open
   question about a "dev/Keycloak fallback" — that decision is
   shown as resolved (or the bullet is removed entirely in favor of
   an affirmative statement).
3. **Given** a reader skimming the README for "Keycloak", **When**
   they hit any mention of it, **Then** the surrounding text
   explicitly says Keycloak is deferred to a later release and is
   not supported in first-release deployments.

---

### User Story 2 - Keycloak code in the tree carries an explicit "not for first release" marker (Priority: P2)

The Flask backend still contains Keycloak integration code
(`flask_backend/app.py` init block, the fallback branch inside
`requires_auth`), the `python-keycloak` dependency in
`flask_backend/requirements.txt`, and a `flask_backend/README.md`
paragraph describing Keycloak env vars as if they were a supported
mode. A maintainer scanning the code today has no way to tell —
without reading the constitution — that this path is deferred,
default-off, and not intended for any study deployment in the first
release. Per Principle VI, subsystems that are not part of the
current release MUST be either marked as deferred/unused in
documentation *or* removed; silent dead code is not allowed.

**Why this priority**: Unmarked deferred code is a foot-gun. A
future contributor could set `KEYCLOAK_REALM` in a `.env` file and
re-enable an untested auth path in a production study deployment
without any warning from the surrounding code or docs. The marker
closes that door.

**Independent Test**: A maintainer greps the repo for "keycloak"
and, at every user-facing hit (README, backend README, inline
comments near the init block and the fallback branch), finds an
unambiguous "deferred to a later release — not supported for first
release" note. The markers reference the constitution's
authentication section so the reasoning is traceable.

**Acceptance Scenarios**:

1. **Given** `flask_backend/README.md`, **When** a maintainer reads
   the auth paragraph that mentions `KEYCLOAK_REALM`, **Then** the
   paragraph explicitly states Keycloak support is not part of the
   first release and is retained only as a placeholder for a
   future constitution amendment.
2. **Given** `flask_backend/app.py`, **When** a maintainer opens
   the Keycloak init block or the fallback branch inside
   `requires_auth`, **Then** an inline comment on each marks it as
   deferred to a later release and points to the constitution's
   authentication section.
3. **Given** `flask_backend/requirements.txt`, **When** a
   maintainer sees the `python-keycloak` line, **Then** a short
   comment (or a note in `flask_backend/README.md`) explains why
   the dependency is retained despite not being used for
   first-release auth.

---

### User Story 3 - Auth-adjacent docs under `docs/` agree with each other and with the constitution (Priority: P2)

Several files under `docs/` currently reference auth mechanisms:
`docs/WORKFLOW_AUTH.md`, `docs/frontend-auth-implementation.md`,
`docs/technical-architecture.md`, `docs/architecture-overview.md`,
and `docs/template-setup-guide.md`. Because these pre-date the
constitution amendments, they may contradict each other or the
constitution on whether Keycloak is supported, whether there is a
dev fallback, and whether the auth contract is purely header-based
or actually a basic+ldap edge with header forwarding. A study-team
member deploying for the first release should not have to reconcile
conflicting docs.

**Why this priority**: Deployments are driven by the setup guide
and workflow docs, not the root README. Drift between these files
and the constitution is where a misconfiguration (e.g., forgetting
the LDAP group require rule) can actually happen in production.

**Independent Test**: An auditor reading only the files under
`docs/` that mention authentication can describe the first-release
auth model consistently across files, and the description matches
the constitution's Security & Data Governance "Authentication
(first release)" bullet.

**Acceptance Scenarios**:

1. **Given** `docs/template-setup-guide.md`, **When** an auditor
   reads any section describing how to stand up a new study
   deployment's auth, **Then** the guide names basic+ldap at the
   Apache edge plus `X-Remote-User` forwarding as the expected
   configuration and does not name Keycloak as a supported option.
2. **Given** the set of `docs/WORKFLOW_AUTH.md`,
   `docs/frontend-auth-implementation.md`,
   `docs/technical-architecture.md`, and
   `docs/architecture-overview.md`, **When** an auditor compares
   their auth descriptions, **Then** no two files disagree on
   whether Keycloak is in scope for the first release or on
   whether the auth contract includes the Apache edge.
3. **Given** any doc that predates the constitution amendments
   and still says Keycloak is the intended or supported mode,
   **When** this feature lands, **Then** that doc has been
   updated or has a pointer to the constitution that supersedes
   it.

---

### User Story 4 - "Unused subsystem" hygiene is applied consistently (Priority: P3)

The precedent set by the 001-mark-fhir-unused branch — mark an
unused subsystem explicitly in docs rather than silently leaving
it in the tree — is exactly what Principle VI now requires for
all unused subsystems. This story makes the application of that
pattern to Keycloak explicit so that future amendments (e.g., if
another legacy subsystem is discovered) have a pattern to follow.

**Why this priority**: This is primarily about consistency and
future maintainability, not immediate risk. It is worth doing in
the same PR as stories 1–3 so the repo has one coherent pattern
for "deferred or unused subsystem" markers, but it is not a
blocker on its own.

**Independent Test**: A reader can identify a single, repeatable
pattern across `FHIR_SERVER` and Keycloak markers ("named, with a
short why and a pointer to the authoritative doc") and can apply
that pattern to a hypothetical third unused subsystem without
guessing.

**Acceptance Scenarios**:

1. **Given** the FHIR markers introduced in 001-mark-fhir-unused
   and the Keycloak markers introduced in this feature, **When**
   a reader compares them, **Then** they follow the same shape
   (name the subsystem, state the current status, point to the
   authoritative doc).
2. **Given** any other subsystem that a future audit finds to
   be unused or deferred, **When** a contributor looks for a
   template to follow, **Then** the FHIR and Keycloak markers
   together serve as that template without further guidance.

### Edge Cases

- A doc file mentions Keycloak only in a historical or changelog
  context (e.g., "originally we planned Keycloak"). The update
  MUST leave the historical note intact and add a pointer to the
  constitution's current decision, not rewrite history.
- A reader sets `KEYCLOAK_REALM` in a local `.env` for
  experimentation and expects the backend to still start. The
  updated code markers MUST NOT change runtime behavior — the
  Keycloak path must still activate when the env var is set, so
  local experiments continue to work; only the documentation and
  comment posture changes.
- `docs/template-setup-guide.md` is the operational reference that
  the constitution explicitly points to as the "how" companion.
  Any edit there MUST preserve its operational clarity and MUST
  NOT duplicate the constitution verbatim — a pointer is
  preferred over a copy.
- Tests that set `app_mod.keycloak_openid = object()` to exercise
  the fallback branch are internal test wiring, not user-facing
  documentation, and are out of scope for this feature. They
  should not be rewritten.
- `docs/legacy-repository-analysis.md` and `docs/EMAIL_TEMPLATE.md`
  are unlikely to reference current-auth decisions, but if they
  do, the same consistency rule applies.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The root `README.md` "Authentication and
  Authorization" section MUST describe the first-release auth
  mechanism as HTTP Basic Auth at the Apache edge with
  `AuthBasicProvider ldap` and a `require ldap-group` rule (per
  `.htaccess`), followed by Apache forwarding the authenticated
  identity to the Flask backend via `X-Remote-User`. Both halves
  MUST be presented as a single contract, not as alternatives.
- **FR-002**: The root `README.md` "Outstanding next steps" list
  MUST no longer present "whether to require header auth in all
  environments or keep the permissive dev/Keycloak fallback" as
  an open decision. Either the bullet is removed entirely or it
  is rewritten as a resolved statement pointing to the
  constitution.
- **FR-003**: The root `README.md` MUST include (or link to) a
  one-sentence note that Keycloak is deferred to a later release
  and is not supported in first-release deployments.
- **FR-004**: `flask_backend/README.md` MUST update the paragraph
  describing Keycloak environment variables to state that
  Keycloak is not part of the first release, is default-off, and
  that enabling it in a study deployment requires a prior
  constitution amendment.
- **FR-005**: `flask_backend/app.py` MUST carry inline comments
  at both the Keycloak initialization block and the Keycloak
  fallback branch inside `requires_auth` indicating they are
  deferred to a later release and are not part of the
  first-release auth path. The comments MUST reference the
  constitution's Security & Data Governance authentication
  section.
- **FR-006**: `flask_backend/requirements.txt` — a short
  explanatory note MUST exist (either as a comment next to
  `python-keycloak` or in `flask_backend/README.md`) explaining
  why the dependency is retained despite not being used for
  first-release auth.
- **FR-007**: `docs/template-setup-guide.md` MUST be audited
  and, if any section describes how to configure auth for a new
  study deployment, that section MUST name basic+ldap at the
  Apache edge plus `X-Remote-User` forwarding as the expected
  configuration, and MUST NOT name Keycloak as a supported
  option for the first release.
- **FR-008**: `docs/WORKFLOW_AUTH.md`,
  `docs/frontend-auth-implementation.md`,
  `docs/technical-architecture.md`, and
  `docs/architecture-overview.md` MUST be audited and updated
  so that no two files disagree on the first-release auth
  model, and so that no file presents Keycloak as in-scope for
  the first release. A pointer to the constitution is
  acceptable in lieu of an in-place rewrite for historical
  context.
- **FR-009**: No existing runtime behavior MUST change as a
  result of this feature. Keycloak code paths MUST continue to
  activate when `KEYCLOAK_REALM` is set so that local
  experimentation still works; only documentation, comments,
  and prose change.
- **FR-010**: The `FHIR_SERVER` "not currently used" markers
  (introduced in 001-mark-fhir-unused) and the new Keycloak
  "deferred to a later release" markers SHOULD share a
  consistent shape — name the subsystem, state its current
  status, point to the authoritative doc — so future
  unused-subsystem markers have a pattern to follow.
- **FR-011**: Every doc edit MUST be reviewable against the
  constitution's Sync Impact Reports for v1.1.0 and v1.1.1. A
  short Sync Impact Report addendum (in the PR description or
  as a note at the top of this feature's `plan.md`) MUST list
  which files changed and which constitution decisions each
  change traces back to.

### Key Entities

- **First-release auth contract**: the two-layer model of
  Apache edge (basic+ldap with group require rule) plus Flask
  backend (`X-Remote-User` + `@requires_auth` + `users.login`
  lookup). Described authoritatively in `.htaccess` and the
  constitution; must be described derivatively (but
  consistently) in README, flask_backend/README, and docs/*.
- **Deferred subsystem marker**: a short, repeatable
  doc/comment pattern that names a subsystem (e.g., Keycloak,
  FHIR), states its current status (deferred / unused), and
  points to the authoritative decision (constitution section
  or similar). This feature produces the second instance of
  the pattern and cements it as a convention.
- **Outstanding-next-steps list**: the bulleted list in the
  root README that previously tracked open auth decisions. One
  of its items is now obsolete and must be removed or
  rewritten.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A reader who has seen only the updated
  `README.md` and `flask_backend/README.md` — not `.htaccess`,
  not the Flask source — can describe the first-release auth
  path in one sentence that matches the constitution's
  Security & Data Governance "Authentication (first release)"
  bullet.
- **SC-002**: Zero files in the repository (excluding
  `node_modules/`, test fixtures, and the constitution itself)
  present a "permissive dev/Keycloak fallback" as an open
  decision or as a supported first-release option.
- **SC-003**: Every user-facing mention of Keycloak in
  `README.md`, `flask_backend/README.md`, and `docs/*.md`
  carries or is adjacent to an explicit "deferred to a later
  release, not supported for first release" note.
- **SC-004**: The five auth-adjacent files under `docs/`
  (`WORKFLOW_AUTH.md`, `frontend-auth-implementation.md`,
  `technical-architecture.md`, `architecture-overview.md`,
  `template-setup-guide.md`) agree pairwise on whether
  Keycloak is in scope for the first release and on whether
  the auth contract includes the Apache edge.
- **SC-005**: No runtime behavior changes. Existing Flask
  tests (including the ones that set `keycloak_openid` to
  exercise the fallback branch) continue to pass without
  modification.
- **SC-006**: A PR description (or the feature's `plan.md`)
  contains a short Sync Impact Report addendum mapping each
  edited file to the constitution decision it traces back to
  (v1.1.0 Principle VI / Security auth bullet, or v1.1.1
  auth clarification).

## Assumptions

- Keycloak code paths are retained in the tree, not deleted.
  Principle VI allows either "mark unused" or "remove"; the
  precedent set by 001-mark-fhir-unused, plus the constitution's
  explicit "later releases will" language about Keycloak,
  argues for marking over deletion. If the team prefers
  removal, this assumption must be revisited before planning.
- The `KEYCLOAK_REALM` env var already functions as the
  "non-default environment flag" the constitution requires.
  No new flag is introduced by this feature.
- `.htaccess` is authoritative for the first-release Apache
  edge configuration. If the operational deployment diverges
  from `.htaccess` (e.g., adds a `RequestHeader set
  X-Remote-User` directive in the vhost), that divergence is
  out of scope for this feature — the docs describe the
  contract `.htaccess` + the Flask backend together define.
- `docs/template-setup-guide.md` remains the operational "how
  to deploy" reference per the constitution's Governance
  section. This feature updates it only where its content
  conflicts with the constitution; it does not restructure it.
- Tests under `flask_backend/tests/` are internal wiring and
  out of scope. `keycloak_openid` manipulation in those tests
  is not documentation and does not need to change.
- The feature produces a single PR whose diff is reviewable
  against the two Sync Impact Reports in the constitution. A
  reviewer should be able to ask "does this file change trace
  back to a specific constitution decision?" and get a yes
  for every change.
