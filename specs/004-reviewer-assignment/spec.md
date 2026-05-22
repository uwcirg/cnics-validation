# Feature Specification: Interactive reviewer-assignment page

**Feature Branch**: `004-reviewer-assignment`
**Created**: 2026-05-21
**Status**: Draft
**Input**: User description: "Build the interactive reviewer-assignment page for the event-validation workflow, replacing the current frontend/src/pages/EventAssignMany.jsx placeholder."

## Recorded Prior Behavior *(Constitution Principle VI)*

Today the reviewer-assignment page (`EventAssignMany.jsx`) is a static placeholder: it renders descriptive text only, never calls the backend, and cannot perform an assignment. The assignment endpoint exists but has no working user interface. As a result, events that reach the "To Be Assigned" stage cannot be moved forward, and the validation lifecycle stalls before the "assigned" status. This feature replaces that placeholder with a working page.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Assign a reviewer to awaiting events (single-reviewer deployment) (Priority: P1)

An administrator opens the "To Be Assigned" queue, sees the events currently awaiting reviewer assignment, selects one or more of them, chooses a reviewer, and confirms. The selected events advance to the "assigned" status, the admin sees a confirmation, and the assigned events leave the queue.

**Why this priority**: This is the minimum viable slice. It is the exact step that is missing today and that blocks the lifecycle for every study type. In particular, it unblocks a single-reviewer scans deployment so it can complete its upload → assign → review → done lifecycle. Without it, no event can ever progress past "To Be Assigned."

**Independent Test**: In a deployment configured for one reviewer, open the To Be Assigned queue, select one or more events, pick a reviewer, confirm, and verify the events advance to "assigned" and disappear from the queue. Delivers the core value on its own.

**Acceptance Scenarios**:

1. **Given** an administrator is viewing the To Be Assigned queue with events awaiting assignment, **When** they select one event, choose a reviewer, and confirm, **Then** the event advances to the "assigned" status, a confirmation is shown, and the event no longer appears in the queue.
2. **Given** an administrator has selected several events, **When** they choose one reviewer and confirm, **Then** that same reviewer is applied to every selected event and all of them leave the queue.
3. **Given** the deployment is configured for a single reviewer, **When** the administrator views the assignment controls, **Then** exactly one (first) reviewer slot is offered and no second- or third-reviewer slot is shown.
4. **Given** an administrator attempts to confirm with no events selected, or with no reviewer chosen, **When** they trigger the confirm action, **Then** the assignment is not submitted and the page indicates what is missing.
5. **Given** the assignment endpoint returns a validation, authorization, or server error, **When** the administrator confirms an assignment, **Then** the page surfaces a human-readable error message and does not claim success.

---

### User Story 2 - Assign first and second reviewers (two-reviewer deployment) (Priority: P2)

In a deployment configured for two reviewers, an administrator selects events from the queue, chooses a first reviewer and a second reviewer, and confirms. Both reviewers are recorded on every selected event and the events advance out of the queue.

**Why this priority**: It extends the same page to the two-reviewer study configurations. It is essential for those deployments but is not required to unblock the single-reviewer scans lifecycle, so it ranks below P1. The page is one shared page for all studies; the number of reviewer slots is driven entirely by the resolved `reviewer_count` workflow control, never by a study-name check.

**Independent Test**: In a deployment configured for two reviewers, open the queue, select events, choose two distinct reviewers, confirm, and verify both reviewer assignments are recorded and the events advance.

**Acceptance Scenarios**:

1. **Given** the deployment is configured for two reviewers, **When** the administrator views the assignment controls, **Then** a first-reviewer slot and a second-reviewer slot are both offered.
2. **Given** a two-reviewer deployment, **When** the administrator chooses the same person for both the first and second reviewer slots, **Then** the page prevents confirming and indicates the two reviewers must be different.
3. **Given** a two-reviewer deployment with a first and second reviewer selected, **When** the administrator confirms, **Then** both reviewer assignments are applied to every selected event and the events advance out of the queue.

---

### User Story 3 - Narrow and page through the queue (Priority: P3)

An administrator working with a large queue narrows it by site and pages through the results, the same way the "To Be Assigned" list behaves on the View All Events page.

**Why this priority**: For small queues the P1 flow works without filtering or paging. Filtering and pagination become necessary only as the queue grows, so this is a usability enhancement layered on top of the core flow.

**Independent Test**: Open a queue large enough to span multiple pages, apply a site filter, page forward and back, and verify the displayed events match the filter and page.

**Acceptance Scenarios**:

1. **Given** the queue contains events from more than one site, **When** the administrator applies a site filter, **Then** only events for that site are shown.
2. **Given** the queue contains more events than fit on one page, **When** the administrator moves to the next page, **Then** the next set of awaiting events is shown.

---

### Edge Cases

- **Empty queue**: When no events are awaiting assignment, the page shows a clear empty-state message rather than an error or a blank table.
- **No eligible reviewers**: When no users hold the reviewer role, the administrator cannot choose a reviewer; the page communicates this rather than failing silently.
- **Re-assignment**: Assigning a reviewer to an event that already has one is permitted; the system does not block re-assignment to a different reviewer.
- **Session or role lost**: When the administrator's session has expired or they lack the admin role, the assignment is rejected, the page surfaces the authorization error, and no success is claimed.
- **Endpoint rejects the slot**: When a second- or third-reviewer assignment is attempted in a single-reviewer deployment, the endpoint rejects it; the page surfaces that error. (The page already hides those slots in a single-reviewer deployment, so this is a defense-in-depth case.)
- **Partial failure across a batch or across slots**: When an assignment covering multiple events — or, in a two-reviewer deployment, multiple reviewer slots — partly succeeds, the page reports which events/slots were assigned and which were not, rather than reporting a blanket success.
- **Server or network failure**: When the request cannot complete, the page surfaces the failure and the administrator can retry; the page does not falsely report success.
- **Selection vs. filtering/paging**: When the administrator changes the site filter or page after selecting events, the confirmed assignment applies only to events the administrator selected and intends.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The reviewer-assignment page MUST replace the current placeholder with a working interface that performs assignments; descriptive-only text that cannot perform an action MUST NOT remain.
- **FR-002**: The page MUST display the "To Be Assigned" queue — the same flag-aware queue shown on the View All Events page — listing events currently awaiting reviewer assignment. When scrubbing and screening are bypassed, this queue includes events in the "uploaded" status.
- **FR-003**: The queue MUST be reachable from the "To Be Assigned" section of the View All Events page and from the application menu.
- **FR-004**: Performing an assignment MUST be restricted to administrators; non-administrators MUST NOT be able to assign reviewers.
- **FR-005**: The administrator MUST be able to select one or more events from the queue.
- **FR-006**: The administrator MUST be able to choose a reviewer from the users who hold the reviewer role; this includes administrators who also hold the reviewer role.
- **FR-007**: The reviewer the administrator chooses MUST be applied to every selected event in a single confirm action.
- **FR-008**: The number of reviewer slots the page offers MUST be driven by the resolved `reviewer_count` workflow control, never by a study-name check (Constitution Principle IV, Configuration Over Code Forks).
- **FR-009**: When `reviewer_count` is 1, the page MUST offer exactly one (first) reviewer slot and MUST NOT show a second- or third-reviewer slot.
- **FR-010**: When `reviewer_count` is 2, the page MUST offer a first-reviewer slot and a second-reviewer slot.
- **FR-011**: When two reviewer slots are offered, the same person MUST NOT be assignable to more than one reviewer slot on the same event; the page MUST prevent confirming such a combination.
- **FR-012**: When two reviewer slots are offered, the administrator MUST choose a reviewer for both slots before confirming, so that an event is fully assigned before it advances out of the queue.
- **FR-013**: Assigning the first reviewer MUST advance each affected event to the "assigned" status.
- **FR-014**: On a successful assignment, the page MUST show a confirmation, and the assigned events MUST leave the queue without requiring a manual page reload.
- **FR-015**: The queue MUST support filtering by site.
- **FR-016**: The queue MUST support pagination.
- **FR-017**: The page MUST surface the assignment endpoint's validation, authorization, and server errors to the administrator as human-readable messages.
- **FR-018**: The page MUST prevent submitting an assignment when no events are selected or no reviewer is chosen, and MUST indicate what is missing.
- **FR-019**: The system MUST permit re-assigning an event to a different reviewer; the page MUST NOT block an assignment solely because an event already has a reviewer.
- **FR-020**: Third-reviewer (post-disagreement) assignment is out of scope for this page and MUST NOT be offered here; it is a separate stage with its own page.

### Key Entities *(include if feature involves data)*

- **Awaiting event**: An event in the "To Be Assigned" queue — an event that has not yet been assigned a reviewer. Identified to the administrator by event number, event date, patient identifier, and site. It carries a lifecycle status that, after a successful first-reviewer assignment, becomes "assigned."
- **Eligible reviewer**: A user who holds the reviewer role. May also hold the administrator role. Distinguished to the administrator by name and site.
- **Reviewer slot**: The position a reviewer fills on an event — first or second (third is out of scope for this page). The set of slots offered is determined by the resolved `reviewer_count` workflow control.
- **Reviewer-count workflow control**: A resolved per-deployment configuration value that determines how many reviewer slots the page offers (1 for a single-reviewer deployment such as scans, 2 for a two-reviewer deployment).
- **Assignment action**: The administrator's confirmed intent — a set of selected events plus the chosen reviewer(s) — applied together.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can take a batch of awaiting events and assign a reviewer in under 60 seconds, with no placeholder or descriptive-only text remaining on the page.
- **SC-002**: After a successful assignment, 100% of the assigned events disappear from the "To Be Assigned" queue without the administrator manually reloading the page.
- **SC-003**: In a single-reviewer deployment, the page presents exactly one reviewer slot and zero second- or third-reviewer slots.
- **SC-004**: Every error the assignment endpoint returns — invalid input, not authorized, or server failure — results in a visible, human-readable message; there are no silent failures.
- **SC-005**: A single-reviewer scans deployment can complete the full upload → assign → review → done lifecycle, where previously it stalled before the "assigned" status.
- **SC-006**: In a two-reviewer deployment, an assignment cannot be confirmed with the same person selected for both reviewer slots, and cannot be confirmed with either slot left empty.

## Assumptions

- **Reviewer eligibility**: A reviewer is any user holding the reviewer role, including administrators who also review. No additional site-matching or workload constraint is applied to reviewer eligibility for this feature.
- **Two-reviewer assignment is a single action**: Because assigning the first reviewer advances an event to "assigned" and removes it from the queue, a two-reviewer deployment must capture both reviewers in one confirm action; otherwise the second reviewer could never be assigned from this page (FR-012). The send page and review page are out of scope, so they cannot fill a missing second reviewer.
- **Queue source**: The "To Be Assigned" queue uses the same flag-aware status query that the View All Events page already uses for its "To Be Assigned" section, so bypassed scrubbing/screening behavior is consistent between the two pages.
- **Selection scope**: Event selection operates on the events the administrator can currently see; selecting events across many filtered/paginated views simultaneously is not required for this feature.
- **Re-assignment scope**: "Permit re-assignment" means the page and the system impose no "already has a reviewer" restriction. A dedicated flow for locating an already-assigned event (one that has left the queue) and changing its reviewer is not part of this page; such an event is reached through other existing routes.
- **No schema change**: This feature introduces no database schema change.

## Dependencies

- **Assignment endpoint**: The backend endpoint `POST /api/events/assign_many` already exists. It is admin-only and accepts a set of event ids, one reviewer id, and a slot of first/second/third. First-reviewer assignment advances status to "assigned," and the endpoint already rejects second/third slots when `reviewer_count` is 1. The endpoint currently records one reviewer and one slot per call. Planning must confirm whether assigning a first and a second reviewer in a two-reviewer deployment is done by issuing one call per slot, or whether the endpoint needs a change to accept both in a single operation — and, if one call per slot, how partial failure across slots is reported (see Edge Cases).
- **Reviewer-count workflow control**: The resolved `reviewer_count` value is already exposed to the frontend through the existing workflow configuration; this feature consumes it and does not define new configuration.
- **Reference UI only**: A study-specific assignment page exists at `frontend/src/studies/vte/EventAssignMany.jsx`. It is stale — it sends a request shape that does not match the current endpoint — and must be treated as a guide to page structure only, not to the request contract.
