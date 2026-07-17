# Feature Specification: Show only study-relevant actions on Events Summary

**Feature Branch**: `006-study-aware-event-actions`  
**Created**: 2026-05-22  
**Status**: Draft  
**Input**: User description: "when in 'scan' mode, events/viewAll lists actions that aren't relevant — see Screenshot 2026-05-22 124501.events.viewAll.TooManyActions.jpg. We need a general fix that accommodates the various studies/configs."

## User Scenarios & Testing *(mandatory)*

The Events Summary page (`/events/viewAll`) presents one collapsible section per
event-lifecycle status — each a queue of events plus the actions a coordinator
can take on them. Today the page shows the **same eleven sections for every
deployment**, regardless of which workflow stages that deployment actually uses.
In a bypassed-stage configuration such as the Scans study (no scrubbing, no
screening, no sending, a single reviewer), five of those sections describe work
that never happens — they are permanently empty and only add noise. This feature
makes the page show only the sections relevant to the active study/configuration.

### User Story 1 - Hide bypassed lifecycle stages (Priority: P1)

A study coordinator running a deployment that bypasses one or more lifecycle
stages (scrubbing, screening, or sending) opens the Events Summary page and sees
only the workflow-stage sections for stages that deployment actually performs.

**Why this priority**: This is the exact problem in the screenshot and the
largest source of noise — three of the five irrelevant sections in the Scans
deployment come from bypassed stages. Fixing it delivers the visible win on its
own and is a viable MVP.

**Independent Test**: Configure a deployment with scrubbing, screening, and
sending bypassed, load the Events Summary page, and confirm the "To Be Scrubbed",
"To Be Screened", and "To Be Sent" sections are absent while the remaining
sections render normally.

**Acceptance Scenarios**:

1. **Given** a deployment with the scrubbing stage bypassed, **When** a
   coordinator opens the Events Summary page, **Then** the "To Be Scrubbed"
   section (heading, Show/Hide control, and queue) does not appear.
2. **Given** a deployment with the screening stage bypassed, **When** a
   coordinator opens the Events Summary page, **Then** the "To Be Screened"
   section does not appear.
3. **Given** a deployment with the sending stage bypassed, **When** a
   coordinator opens the Events Summary page, **Then** the "To Be Sent" section
   does not appear.
4. **Given** a deployment with all three of those stages bypassed (the Scans
   configuration), **When** a coordinator opens the page, **Then** none of those
   three sections appear and the always-relevant sections still appear.

---

### User Story 2 - Hide third-reviewer sections for single-reviewer studies (Priority: P2)

A study coordinator running a single-reviewer deployment opens the Events Summary
page and does not see the third-reviewer escalation sections, which apply only to
multi-reviewer studies.

**Why this priority**: These are the remaining two irrelevant sections in the
Scans screenshot. They are gated by a different control (reviewer count) than the
bypassed stages, so the behavior is verified separately, but the user value is
the same — remove queues that can never receive events.

**Independent Test**: Configure a single-reviewer deployment, load the Events
Summary page, and confirm the "Third Review Needed" and "Third Reviewer Assigned"
sections are absent.

**Acceptance Scenarios**:

1. **Given** a single-reviewer deployment, **When** a coordinator opens the
   Events Summary page, **Then** neither the "Third Review Needed" nor the
   "Third Reviewer Assigned" section appears.
2. **Given** a multi-reviewer deployment, **When** a coordinator opens the
   Events Summary page, **Then** both third-reviewer sections appear.

---

### User Story 3 - Full-workflow studies keep every section (Priority: P3)

A study coordinator running a full-workflow deployment (all stages enabled,
multiple reviewers) opens the Events Summary page and sees every section exactly
as before — nothing is removed.

**Why this priority**: This is the no-regression guarantee. The fix must be
purely subtractive for configurations that already use every stage; existing
deployments must be unaffected.

**Independent Test**: Configure a full-workflow deployment, load the Events
Summary page, and confirm all eleven sections appear.

**Acceptance Scenarios**:

1. **Given** a full-workflow deployment, **When** a coordinator opens the Events
   Summary page, **Then** all eleven sections appear in their current order.
2. **Given** the deployment configuration has not yet resolved (or cannot be
   retrieved), **When** the page renders, **Then** it shows the full section set
   rather than hiding sections that may be relevant.

---

### Edge Cases

- **Stale events in a now-bypassed status**: if a deployment's configuration is
  changed after events already exist in a status whose stage is later disabled,
  the corresponding section is hidden and those events are not reachable from
  this page. Bypass configurations are expected to be set at deployment time
  (see Assumptions); this is treated as an operational concern, not a supported
  in-page workflow.
- **Configuration unavailable**: if the deployment configuration cannot be
  retrieved, the page falls back to showing the full set of sections so no
  relevant queue is hidden.
- **Always-relevant sections in a minimal configuration**: even the most
  bypassed configuration still shows "To Be Uploaded", "Not Yet Reviewed",
  "To Be Assigned", "All Done", "No Packet Available", and "Rejected", because
  those statuses exist in every workflow.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Events Summary page MUST show a workflow-stage section only
  when that stage is part of the active deployment's resolved workflow
  configuration.
- **FR-002**: When the scrubbing stage is bypassed, the page MUST omit the
  "To Be Scrubbed" section in its entirety — heading, Show/Hide control, and
  queue.
- **FR-003**: When the screening stage is bypassed, the page MUST omit the
  "To Be Screened" section in its entirety.
- **FR-004**: When the sending stage is bypassed, the page MUST omit the
  "To Be Sent" section in its entirety.
- **FR-005**: When the deployment is configured for a single reviewer, the page
  MUST omit both the "Third Review Needed" and "Third Reviewer Assigned"
  sections.
- **FR-006**: The page MUST always show the sections that are not tied to a
  bypassable stage: "To Be Uploaded", "Not Yet Reviewed", "To Be Assigned",
  "All Done", "No Packet Available", and "Rejected".
- **FR-007**: Section visibility MUST be derived from the resolved workflow
  configuration (the stage controls and reviewer count) and MUST NOT be keyed on
  a hardcoded study name. Introducing or reconfiguring a study MUST require no
  change to per-section visibility logic.
- **FR-008**: For a full-workflow deployment, the page MUST show all eleven
  sections in their current order — no section is lost relative to today's
  behavior.
- **FR-009**: When a section is omitted, the action controls it contains
  (the Show/Hide toggle and any per-event action buttons, e.g. "upload
  scrubbed", "screen", "send") MUST be omitted with it.
- **FR-010**: Until the deployment configuration has resolved — or if it cannot
  be retrieved — the page MUST show the full section set, consistent with the
  application's existing conservative default for workflow-driven UI.
- **FR-011**: The Event Status Summary count table at the top of the page MUST
  remain unchanged; it reports actual stored event counts and is not gated by
  workflow configuration.

### Key Entities *(include if feature involves data)*

- **Resolved Workflow Configuration**: the active deployment's set of workflow
  controls — whether the scrubbing, screening, and sending stages are in use,
  and how many reviewers an event requires. This is existing configuration the
  feature consumes; it is not defined or changed here.
- **Workflow-Stage Section**: a collapsible area of the Events Summary page that
  represents one event-lifecycle status — a label, a Show/Hide control, a queue
  of events, and the actions available on them.
- **Event Status**: the lifecycle state of an event. Each gated section
  corresponds to one status; bypassed stages correspond to statuses a given
  deployment never enters.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In a deployment with scrubbing, screening, and sending all
  bypassed and a single reviewer (the Scans configuration), the Events Summary
  page presents exactly 6 sections, down from 11.
- **SC-002**: In a full-workflow deployment, the Events Summary page presents
  all 11 sections, with no section or action removed.
- **SC-003**: Every workflow-stage section visible on the page corresponds to a
  stage the deployment actually performs — zero sections are empty solely
  because their stage is not part of the workflow.
- **SC-004**: A new or reconfigured study can be introduced by changing only
  deployment configuration; no edit to the page's section list is required for
  it to display the correct sections.
- **SC-005**: A coordinator can locate their pending-work queue without
  scrolling past sections that do not apply to the active study.

## Assumptions

- The feature targets the shared Events Summary page at `/events/viewAll`. The
  legacy VTE-forked equivalent (`/vte/viewAll`) is a separate, deprecated code
  path and is out of scope.
- The deployment's workflow configuration — which lifecycle stages are active
  and how many reviewers are required — is already resolved and available to the
  application. This feature consumes that configuration; it does not add or
  change any configuration mechanism.
- The Event Status Summary count table is data-driven (it reflects actual event
  counts) and is intentionally left unchanged.
- Bypassed-stage configurations are established at deployment time, so events do
  not accumulate in a status whose stage is subsequently disabled.
- The "Not Yet Reviewed" section remains visible in every configuration, because
  review always occurs; only the third-reviewer-specific sections are gated by
  reviewer count.
- Until the configuration resolves, showing the full section set is the safe
  default — consistent with how the application already defers other
  workflow-driven UI decisions.
- No backend, API, or database schema change is needed: the existing per-event
  status queues and the resolved workflow configuration are sufficient inputs.
