# Feature Specification: Mark FHIR Server References as Not Currently Used

**Feature Branch**: `001-mark-fhir-unused`
**Created**: 2026-04-14
**Status**: Draft
**Input**: User description: "This code references a FHIR server in several places, however there is no current need for it... Make edits to indicate that it's not currently used."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New Contributor Reading Configuration (Priority: P1)

A new contributor clones the repository, copies `.env.example` to `.env`, and
reads the README to figure out which environment variables they must set to
run the stack locally. Today, the FHIR server variable is listed alongside
database and file-path variables with no indication that it is dormant, so
contributors either hunt for a working FHIR URL, point it at a stranger's
server, or waste time tracing its usage through the codebase before
discovering nothing consumes it.

**Why this priority**: This is the highest-friction path. Onboarding and
local-dev setup touch these files first, and the confusion compounds because
the variable sits in the "required" visual group. Fixing the docs and
examples immediately removes the trap.

**Independent Test**: A contributor who has never seen this repo reads
`README.md` and `.env.example`, and can state without ambiguity that they do
not need to provide a FHIR server value to run the application. Verified by
asking a fresh reviewer to list required vs. optional env vars and confirming
they classify FHIR as "not currently used."

**Acceptance Scenarios**:

1. **Given** a fresh clone of the repository, **When** a contributor reads
   `.env.example`, **Then** the FHIR server entry is clearly labeled as not
   currently used and does not appear to be a required setting.
2. **Given** a contributor reading `README.md`'s environment variables
   section, **When** they encounter the FHIR entry, **Then** the description
   explicitly states the value is inert in the current codebase and lists no
   behavior that depends on it.
3. **Given** a contributor running the local development stack with the FHIR
   value left blank or removed, **When** the application starts, **Then** the
   application behaves identically to a run with the placeholder value set.

---

### User Story 2 - Architecture Doc Reader (Priority: P2)

A stakeholder (maintainer, reviewer, new team member, or auditor) reads
`docs/technical-architecture.md` to understand the system's runtime
dependencies and finds a "FHIR Server" box wired into multiple study
backends in a diagram. They reasonably conclude the system depends on an
external FHIR service, which is misleading because no runtime code actually
calls one.

**Why this priority**: Architecture docs shape decisions about deployment,
security review, and compliance scope. A phantom dependency can distort
those decisions (e.g., unnecessary network allowlisting, spurious
compliance concerns) even if it never breaks a build.

**Independent Test**: A reader of the architecture doc can accurately answer
"does this system currently talk to a FHIR server?" with "no, the reference
is historical / reserved for future use."

**Acceptance Scenarios**:

1. **Given** the technical architecture diagram, **When** a reader views the
   FHIR node, **Then** it is visually and textually marked as not currently
   in use (e.g., labeled "reserved / not currently used") or removed from
   the active-dependency view.
2. **Given** the architecture narrative, **When** FHIR is mentioned, **Then**
   the text states explicitly that no current runtime component calls a FHIR
   server and clarifies whether the reference is reserved for future work or
   purely historical.

---

### User Story 3 - CI/Deployment Operator (Priority: P3)

An operator maintaining CI (Continuous Integration) pipelines or preparing a
new study deployment reviews workflow files and deployment scripts. They see
`FHIR_SERVER` threaded into CI test environments and the container entrypoint
and assume it must be provided for the stack to boot or for tests to pass.

**Why this priority**: CI and deploy flows already work without a real FHIR
server (the test workflow uses a placeholder URL), so the risk here is
wasted effort rather than broken pipelines. Still, leaving the variable in
these files without comment perpetuates the misunderstanding each time
someone copies or adapts them for a new study.

**Independent Test**: An operator reading the CI workflow and container
entrypoint can correctly state that `FHIR_SERVER` has no functional effect
and that omitting it will not break tests or container startup.

**Acceptance Scenarios**:

1. **Given** the CI workflow file, **When** an operator inspects the env
   block, **Then** the FHIR entry is either removed or annotated as not
   currently used.
2. **Given** the container entrypoint script, **When** an operator reads the
   FHIR handling block, **Then** the behavior is documented as a dormant
   pass-through that does not affect runtime behavior, or the block is
   removed.
3. **Given** a new study deployment prepared from the default configuration
   templates, **When** the operator omits any FHIR value, **Then** the stack
   starts and passes CI without warnings about the missing variable.

---

### Edge Cases

- A historical deployment somewhere still sets `FHIR_SERVER` in its real
  environment file. The change must not cause that deployment to fail, even
  though the value is now documented as inert.
- A reader searches the repository for "FHIR" expecting to find runtime
  behavior. The remaining references must consistently point them to the
  "not currently used" explanation rather than suggesting live integration.
- A future feature genuinely needs a FHIR integration. The change must not
  destroy context so thoroughly that reviving FHIR support requires
  archaeology; the rationale for keeping or removing the placeholder should
  be discoverable.
- A downstream fork or sibling repo (e.g., a study-specific deployment) may
  have copied `.env.example` or `default.env`. The change must flag the
  variable clearly enough that maintainers of those copies notice it on the
  next pull.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: All repository-level environment variable templates
  (`.env.example`, `default.env`, and any archived copies such as
  `.env.backup`) MUST clearly indicate that the FHIR server variable is not
  currently used by any runtime component.
- **FR-002**: The project README's environment variables section MUST
  describe the FHIR server variable as not currently used and MUST NOT imply
  it is required for the application to function.
- **FR-003**: The technical architecture documentation MUST NOT depict the
  FHIR server as an active runtime dependency of any study backend. If
  retained in the diagram or narrative, it MUST be explicitly annotated as
  reserved / not currently used.
- **FR-004**: The CI workflow that injects a placeholder FHIR URL MUST
  either remove the variable or annotate it as not currently used, so
  future maintainers of the workflow understand it is non-functional.
- **FR-005**: The container entrypoint handling of the FHIR variable MUST
  either be removed or documented (in the script itself) as a dormant
  pass-through that has no effect on runtime behavior.
- **FR-006**: Running the application stack locally MUST succeed when no
  value is provided for the FHIR server variable. Omitting it MUST NOT
  produce errors, warnings, or failed health checks.
- **FR-007**: CI runs MUST succeed when the FHIR environment variable is
  not set. No test MUST depend on its presence or its value.
- **FR-008**: The set of updated files MUST use consistent wording for the
  "not currently used" status, so a reader searching the repository finds
  the same explanation regardless of which file they land on first.
- **FR-009**: The change MUST NOT delete any FHIR-related runtime code,
  because none exists. This requirement is stated explicitly so the review
  scope stays limited to documentation, configuration templates, CI, and
  deployment scripts.
- **FR-010**: If the FHIR variable is retained anywhere (rather than
  removed), the retained reference MUST include, in-line, the reason it is
  being kept (e.g., "reserved for future integration" or "kept for backward
  compatibility with deployments that still set it").

### Key Entities

- **FHIR Server Environment Variable (`FHIR_SERVER`)**: A string value
  representing the URL of an external FHIR service. Currently declared in
  configuration templates, surfaced in documentation, and passed through
  CI and the container entrypoint, but not consumed by any backend or
  frontend runtime code.
- **Environment Variable Templates**: Files that contributors and
  operators copy when preparing a new environment. Their primary job is to
  communicate which variables matter and how to set them.
- **Architecture Documentation**: Diagrams and narrative that shape
  stakeholders' mental model of runtime dependencies and deployment scope.
- **CI / Deployment Scripts**: Workflow files and container entrypoints
  that propagate environment variables into running jobs and containers.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A contributor new to the repository can correctly classify
  the FHIR variable as "not currently used" within 60 seconds of opening
  either `.env.example` or the README environment variables section —
  without reading any source code.
- **SC-002**: 100% of repository files that mention FHIR communicate the
  same status ("not currently used"), verified by reading each hit from a
  case-insensitive search for "fhir" across the repository.
- **SC-003**: The local development stack starts successfully with the
  FHIR variable unset, demonstrated by a clean startup run and a smoke
  test of the primary application workflow.
- **SC-004**: The CI workflow passes without the FHIR variable being
  injected, demonstrated by one successful CI run with the value removed
  from the workflow's env block.
- **SC-005**: Zero runtime behavior changes: the set of features available
  to reviewers, uploaders, and admins before and after the change is
  identical, verified by a walk-through of the primary validation
  workflow on a running deployment.
- **SC-006**: Time spent by future contributors investigating whether
  FHIR is a real dependency trends to zero, measured by the absence of
  new questions or issues about FHIR configuration following the change.

## Assumptions

- No backend or frontend code currently reads, calls, or depends on a FHIR
  server. A repository-wide search confirmed references exist only in
  configuration templates, documentation, the CI workflow, and the
  container entrypoint.
- The project does not have an active roadmap item that requires a FHIR
  integration in the near term. If one exists, this change is still
  correct (it marks today's reality) but the retained references should
  include that context.
- Downstream deployments that set `FHIR_SERVER` in their private
  environment files will continue to function unchanged; the variable is
  passed through harmlessly today and will remain harmless after this
  change.
- "Mark as not currently used" is preferred over wholesale removal for at
  least the most visible references (README, architecture doc), because
  the historical context is useful for reviewers deciding whether to
  revive FHIR support later. Purely internal references (CI env block,
  entrypoint pass-through) MAY be removed outright at the implementer's
  discretion, provided the resulting state still satisfies the functional
  requirements above.
- The constitution's backwards-compatibility principle (Principle III)
  applies: this change must not break any existing deployment that
  currently sets `FHIR_SERVER`, even though the variable is inert.
