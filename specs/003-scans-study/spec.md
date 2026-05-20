# Feature Specification: Implement the `scans` Study Type (Selective-Bypass Workflow)

**Feature Branch**: `003-scans-study`
**Created**: 2026-05-20
**Status**: Draft
**Input**: User description: "Update per the constitution changes; implement the 'scans' study."

## Context

The project constitution was amended to **v1.4.0** (commit `170c998`, 2026-05-20),
materially expanding **Principle V (Workflow and Role Parity Across Studies)** to
permit configuration-driven *selective bypass* of shared lifecycle stages,
alongside the already-permitted *extension* of the lifecycle. The amendment:

- Adds `scans` to the enumerated study list in Principle I (MI, VTE, CVA, Heart
  Failure, AFIB, `scans`).
- Introduces four named bypass controls — `ENABLE_SCRUBBING`, `ENABLE_SCREENING`,
  `ENABLE_SENDING`, and `REVIEWER_COUNT` — read through the shared configuration
  layer.
- Names `scans` as the canonical selective-bypass study: it deploys with
  `ENABLE_SCRUBBING=false`, `ENABLE_SCREENING=false`, `ENABLE_SENDING=false`,
  and `REVIEWER_COUNT=1`, yielding the lifecycle
  `created → uploaded → assigned → reviewer1_done → done`.
- Requires that bypassed states remain present in the schema and the shared
  state machine — "bypass means *not entered* for this deployment, not *deleted
  from the system*."

The amendment's Sync Impact Report explicitly **defers** the implementation to a
spec → plan → tasks → implement cycle: "Implementation work to wire
`STUDY_TYPE=scans` and the four flags through the shared configuration layer, the
event state machine, the assignment/send logic, and the frontend study factory
is out of scope for this amendment." It also flags two ⚠ *pending* documentation
updates — a `scans` worked example in `docs/template-setup-guide.md`, and the
four flags in `README.md` / `default.env`.

This feature is that deferred cycle: it implements the `scans` study and lands
the pending documentation updates.

## Clarifications

### Session 2026-05-20

- Q: When `STUDY_TYPE=scans` is selected, how are the four workflow-stage controls' values determined? → A: Selecting `scans` auto-supplies the bypass profile (scrubbing/screening/sending off, `REVIEWER_COUNT=1`) as defaults; an operator may still override any individual control in `.env`.
- Q: How should a bypassed stage appear in an individual event's history? → A: Per-event history omits bypassed states entirely — it records only states actually entered; the deployment configuration is the authoritative record of which stages are bypassed.
- Q: What triggers the final `reviewer1_done → done` transition in a single-reviewer (`scans`) configuration? → A: Automatic — submitting the single review advances the event straight to `done`, with no separate admin completion step.
- Q: Is the existing myocardial-infarction full-workflow study canonically `MI` or `MCI` in this spec? → A: `MCI` is canonical (enumerated as `MI` in the constitution's Principle I; both denote the same study).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Run a complete scan-validation from upload to completion (Priority: P1)

A study team operating a `scans` deployment needs to carry a scan-review event
from intake to a finished adjudication. An uploader uploads a packet; an admin
assigns the event to a reviewer; that reviewer completes their review; the event
is done. Because scan review has nothing to scrub, nothing to screen, no
separate dispatch step, and is staffed for single-reviewer adjudication, the
event must move straight through `created → uploaded → assigned →
reviewer1_done → done` with no scrubbing, screening, sending, or second-reviewer
ceremony.

**Why this priority**: This is the feature. Without the bypassed lifecycle
working end to end, a `scans` deployment cannot do its job. Every other story
(UI polish, documentation, regression safety) is in service of this one. It is
the minimum viable product: a working scan-review study.

**Independent Test**: Stand up a deployment configured as a `scans` study, then
carry one event from upload through single-reviewer adjudication to `done` —
confirming no scrubbing, screening, or send step was required and that no second
reviewer was needed. Fully exercisable through the application's workflow
actions, independent of UI cosmetics or documentation.

**Acceptance Scenarios**:

1. **Given** a deployment configured as a `scans` study, **When** an uploader
   uploads a packet for a `created` event, **Then** the event becomes
   `uploaded` and is eligible for assignment without passing through a
   `scrubbed` or `screened` state.
2. **Given** an `uploaded` scans event, **When** an admin assigns it to a
   reviewer, **Then** the event becomes available in that reviewer's work queue
   without a separate send/dispatch step.
3. **Given** an assigned scans event, **When** its single reviewer completes
   their review, **Then** the event advances to `done` without waiting for a
   second reviewer.
4. **Given** a scans deployment, **When** an admin opens the assignment action,
   **Then** only a single (first) reviewer slot is offered.
5. **Given** a deployment whose reviewer count is set to an unsupported value
   (e.g., 3), **When** the stack starts, **Then** it fails to start with a
   clear configuration error and serves no requests.

---

### User Story 2 - Reviewers and admins see an interface that matches the bypassed workflow (Priority: P2)

People working in a `scans` deployment — admins and reviewers — should see only
the stages that apply to scan review. Showing scrubbing queues, screening
actions, a "send" step, or second- and third-reviewer controls in a study that
never uses them is confusing and invites mistakes. The interface must present
upload, assignment, single review, and completion, and nothing for the bypassed
stages.

**Why this priority**: The backend workflow (Story 1) makes `scans` *function*;
this story makes it *usable* without training every operator to ignore
irrelevant controls. It is high-value but not the MVP — Story 1 can be
demonstrated and tested through workflow actions before the interface is tidied.

**Independent Test**: Log into a `scans` deployment as an admin and as a
reviewer and confirm that scrubbing, screening, and sending views and actions
are absent, that second- and third-reviewer controls are absent, and that
upload, assignment, single review, and completion are all present and usable.

**Acceptance Scenarios**:

1. **Given** a scans deployment, **When** an admin views the workflow
   dashboard, **Then** no scrubbing, screening, or sending queues or actions
   are shown.
2. **Given** a scans deployment, **When** an admin assigns an event, **Then**
   no second- or third-reviewer assignment controls appear.
3. **Given** a scans deployment, **When** a reviewer opens their queue,
   **Then** assigned events are available to pick up for review directly.
4. **Given** a full-workflow study (e.g., MCI), **When** an admin views the
   same screens, **Then** scrubbing, screening, sending, and multi-reviewer
   controls remain present — confirming the difference is driven by
   configuration, not by a hard-coded study name.

---

### User Story 3 - A deployment operator can stand up a scans study from the documentation (Priority: P2)

An operator deploying a new `scans` study should be able to configure it from
the canonical `.env` and the setup guide, without reading source code. The four
workflow-stage controls must be documented with their defaults, and the setup
guide must carry a worked `scans` example parallel to the existing VTE
alternative-study example.

**Why this priority**: This is the "update per the constitution changes" half of
the request — the Sync Impact Report flagged these documentation updates as
pending. It is essential for the study to be deployable by anyone other than the
implementer, but it depends on the behavior in Stories 1–2 being settled first.

**Independent Test**: A person who has read only `default.env`, `README.md`, and
`docs/template-setup-guide.md` — not the source code — can produce a correct
`scans` `.env` and bring the stack up as a working `scans` deployment.

**Acceptance Scenarios**:

1. **Given** the updated `default.env`, **When** an operator reads it, **Then**
   the four workflow-stage controls appear, each with its conservative default
   and a one-line description.
2. **Given** `docs/template-setup-guide.md`, **When** an operator looks for how
   to deploy `scans`, **Then** a worked example shows the study selector set to
   `scans` and the four controls set to their bypass values.
3. **Given** only the deployment documentation, **When** an operator prepares a
   `scans` `.env` and runs the stack, **Then** the deployment comes up as a
   `scans` study running the bypassed lifecycle.

---

### User Story 4 - Existing studies and cross-study tooling are unaffected (Priority: P3)

Adding `scans` must not change anything for the studies already running the full
workflow, and must not break shared tooling. The bypassed states must remain
defined in the schema and shared state machine so that admin views, logging, and
downstream consumers continue to recognize them; the `third_reviewer` role must
remain defined even though `scans` does not use it.

**Why this priority**: This is a safety/regression guarantee rather than a new
user journey. It is worth verifying explicitly in the same cycle so that the
"bypass ≠ delete" rule from the constitution is demonstrably honored, but it
does not block the value of Stories 1–3.

**Independent Test**: Inspect the schema and shared state machine and confirm
the bypassed state names are still defined; run an existing full-workflow study
(e.g., MCI) through its lifecycle and confirm no behavior change.

**Acceptance Scenarios**:

1. **Given** the shipped feature, **When** the schema and shared state machine
   are inspected, **Then** `scrubbed`, `screened`, `sent`, `reviewer2_done`,
   and the third-review states are all still defined.
2. **Given** an existing MCI deployment with the workflow-stage controls at
   their defaults, **When** its events are run through the workflow, **Then**
   behavior is identical to before this feature.
3. **Given** cross-study admin or logging tooling, **When** it encounters a
   bypassed state name, **Then** it still recognizes and handles that state.

---

### Edge Cases

- **Operator overrides a study-type default**: a `scans` deployment explicitly
  re-enables one control (e.g., turns screening back on). The system honors the
  explicit override — the controls are authoritative over the study-type
  default.
- **Unsupported reviewer count**: a deployment sets reviewer count to 3 (a
  future protocol). The stack fails fast at startup with a clear error rather
  than serving with undefined multi-reviewer behavior.
- **Non-`scans` study using bypass controls**: another study sets only some
  controls (e.g., screening-only, or single-reviewer-only). This is legitimate;
  the system applies exactly the requested bypasses — the four controls are
  independent and not hard-wired to the `scans` combination.
- **Sending disabled removes the dispatch notification**: with no send step,
  the dispatch-time reviewer-notification email is also bypassed; reviewers
  discover assigned work through their queue (see Assumptions).
- **Completion with a single reviewer**: an event reaches `reviewer1_done` in a
  single-reviewer configuration; it advances to `done` directly, with no
  reviewer-disagreement or third-review check, since no second reviewer exists.
- **Malformed control value**: a workflow-stage control is set to an
  unrecognized value (e.g., a non-boolean). The system treats this as a startup
  configuration error rather than silently coercing it.
- **Bypassed state encountered by shared tooling**: cross-study tooling
  encounters a `scrubbed`/`screened`/`sent` state name; it still resolves it
  because the state remains defined even though `scans` never enters it.

## Requirements *(mandatory)*

### Functional Requirements

**Configuration & startup**

- **FR-001**: System MUST recognize `scans` as a valid, selectable study type
  alongside the existing study types.
- **FR-002**: System MUST provide four workflow-stage controls — scrubbing,
  screening, sending, and reviewer count — and MUST read them through the
  shared configuration layer rather than via configuration reads scattered
  across modules.
- **FR-003**: System MUST drive bypass behavior from the workflow-stage
  controls, and MUST NOT determine it by branching on the study name inside
  workflow or pipeline logic.
- **FR-004**: When a workflow-stage control is not explicitly set, the system
  MUST default to the full-workflow value — scrubbing, screening, and sending
  enabled, and a reviewer count of 2 — so an unconfigured deployment runs the
  complete validation pipeline.
- **FR-005**: System MUST refuse to start, and MUST NOT serve any request, when
  reviewer count is set to a value other than 1 or 2.
- **FR-006**: Selecting the `scans` study type MUST yield the bypass profile
  (scrubbing, screening, and sending disabled; reviewer count 1) without
  requiring each control to be hand-set, while still allowing an operator to
  override any individual control.

**Event lifecycle**

- **FR-007**: When scrubbing is disabled, an `uploaded` event MUST become
  eligible for assignment without entering the `scrubbed` state.
- **FR-008**: When screening is disabled, an event MUST become eligible for
  assignment without entering the `screened` state.
- **FR-009**: When sending is disabled, an `assigned` event MUST become
  available to its assigned reviewer without entering the `sent` state.
- **FR-010**: When reviewer count is 1, an event MUST be treated as fully
  reviewed once the first reviewer completes (`reviewer1_done`), and the act of
  submitting that review MUST automatically advance the event to `done` — with
  no separate admin completion step — without entering `reviewer2_done` or any
  third-review state.
- **FR-011**: The reviewer work queue MUST present every event available for
  review under the active configuration, including `assigned` events when
  sending is disabled.
- **FR-012**: System MUST support the four workflow-stage controls in any
  independent combination, not only the canonical `scans` combination.

**Single-reviewer adjudication**

- **FR-013**: In a single-reviewer configuration, assignment MUST assign an
  event to one (first) reviewer, and second- and third-reviewer assignment MUST
  be unavailable.
- **FR-014**: In a single-reviewer configuration, the system MUST NOT perform
  any reviewer-disagreement comparison or third-review escalation.

**Parity preservation**

- **FR-015**: The shared event states `scrubbed`, `screened`, `sent`,
  `reviewer2_done`, and the third-review states MUST remain defined in the
  schema and the shared state machine; this feature MUST NOT remove, rename, or
  redefine them.
- **FR-016**: The shared roles (admin, uploader, reviewer, third_reviewer) MUST
  remain defined; a `scans` deployment leaves `third_reviewer` unused without
  removing or renaming it.
- **FR-017**: Enabling `scans` MUST NOT alter the workflow behavior of existing
  studies; with the workflow-stage controls at their defaults, the full
  pipeline behavior MUST be unchanged.

**Interface**

- **FR-018**: A `scans` deployment's interface MUST NOT present scrubbing,
  screening, or sending actions, queues, or views.
- **FR-019**: A `scans` deployment's interface MUST NOT present second- or
  third-reviewer assignment or review controls.
- **FR-020**: A `scans` deployment's interface MUST present the retained
  stages — upload, assignment, single review, and completion.
- **FR-021**: Interface differences between configurations MUST be driven by
  the shared workflow-stage controls, not by hard-coded study-name checks.

**Documentation**

- **FR-022**: `default.env` MUST document the four workflow-stage controls
  (`ENABLE_SCRUBBING`, `ENABLE_SCREENING`, `ENABLE_SENDING`, `REVIEWER_COUNT`),
  each with its conservative default and a one-line description.
- **FR-023**: `README.md` MUST list the four workflow-stage controls in its
  environment-variable documentation.
- **FR-024**: `docs/template-setup-guide.md` MUST include a `scans` worked
  deployment example showing the study selector set to `scans` and the four
  controls set to their bypass values, parallel to the existing VTE
  alternative-study example.

**Study isolation**

- **FR-025**: A `scans` deployment MUST satisfy study-data isolation — its own
  database, database user, container set, and domain — with no cross-study data
  access from within the running deployment.

### Key Entities

- **Study type**: the named configuration value identifying which clinical
  validation study a deployment serves. `scans` is the new value, joining MCI
  (enumerated as `MI` in the constitution's Principle I), VTE, CVA, Heart
  Failure, and AFIB.
- **Workflow-stage controls**: the four named settings governing selective
  bypass — scrubbing, screening, sending, and reviewer count — read through the
  shared configuration layer and overridable per deployment.
- **Event lifecycle**: the shared, ordered set of event states. The `scans`
  subset is `created → uploaded → assigned → reviewer1_done → done`; the
  bypassed states (`scrubbed`, `screened`, `sent`, `reviewer2_done`, the
  third-review states) remain part of the shared set even when a deployment
  never enters them. An individual event's history records only the states it
  actually entered — bypassed states do not appear in per-event history — and
  the deployment configuration is the authoritative record of which stages are
  bypassed.
- **Reviewer work queue**: the set of events a reviewer may pick up for
  adjudication; its membership depends on whether sending is enabled.
- **Roles**: admin, uploader, reviewer, and third_reviewer — shared across all
  studies. A `scans` deployment uses the first three and leaves third_reviewer
  unused.
- **Deployment configuration**: the single canonical `.env` that selects the
  study type and the workflow-stage controls for one deployment.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A deployment can be switched to the `scans` study through
  configuration changes only — with no source-code edits.
- **SC-002**: A `scans` event reaches completion in exactly four lifecycle
  transitions (`created → uploaded → assigned → reviewer1_done → done`), with
  zero scrubbing, screening, or send steps required.
- **SC-003**: A `scans` event requires exactly one reviewer to reach `done`,
  compared with two or three in the full workflow.
- **SC-004**: 100% of attempts to start a deployment with an unsupported
  reviewer count are refused before any request is served.
- **SC-005**: An operator who has read only the deployment documentation (no
  source code) can produce a correct `scans` configuration and bring the stack
  up successfully.
- **SC-006**: 100% of existing full-workflow study behaviors are unchanged —
  all pre-existing workflow tests pass without modification.
- **SC-007**: All five bypassed state names remain present and recognizable to
  cross-study admin and logging tooling after the feature ships.
- **SC-008**: A reviewer or admin using a `scans` deployment encounters zero
  interface elements for a bypassed stage (no scrubbing, screening, sending,
  second-reviewer, or third-reviewer controls visible).

## Assumptions

- The `scans` study reuses the shared database schema and shared tables; it
  requires no study-specific schema file and no study-specific review-form
  fields. Constitution v1.4.0 frames `scans` as pure *selective bypass*, not
  *extension*.
- Because `scans` only *removes* stages, it needs no study-specific frontend
  component directory; its interface is the shared interface with
  bypassed-stage elements hidden by the workflow-stage controls. (This contrasts
  with VTE, which *extends* the workflow and therefore carries study-specific
  components.)
- Selecting the `scans` study type supplies the bypass profile as the
  deployment's default control values; an operator may still override any
  individual control through `.env`. The setup-guide worked example shows the
  controls explicitly for documentation clarity. (Confirmed in Clarifications
  Session 2026-05-20.)
- When sending is disabled, the dispatch-time reviewer-notification email is
  bypassed along with the send step; reviewers discover assigned work through
  their queue. Re-introducing a notification at assignment time for `scans`
  would be a separate enhancement, out of scope here.
- This feature makes `STUDY_TYPE=scans` a working, deployable configuration and
  documents how to deploy it. Provisioning and going live with a specific
  production `scans` deployment (database, domain, IRB sign-off, data-isolation
  audit) is a separate operational task.
- The four workflow-stage controls are independent; the implementation supports
  arbitrary combinations, with the `scans` profile being one canonical
  combination.
- `docs/template-setup-guide.md` remains the operational "how" reference; this
  feature adds a `scans` worked example without restructuring the guide.
- `scans` deployments authenticate with the same first-release Apache
  basic+ldap → `X-Remote-User` contract as every other study; this feature does
  not alter authentication.
- The pre-release latitude of Principle VI applies: this app's own internal
  contracts (route shapes, configuration keys, component structure) may change
  as needed to wire `scans`, provided observed prior behavior is recorded before
  it is changed and any backend contract change is reflected in a regenerated
  `openapi.json` within the same change set.
