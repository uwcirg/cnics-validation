# Feature Specification: No-Packet Submission Records the Event Outcome

**Feature Branch**: `011-no-packet-submit`  
**Created**: 2026-09-08  
**Status**: Draft  
**Input**: User description: "Seems like the 'no packet' workflow isn't implemented at /events/upload?event_id=[id]... I'm expecting the events record to be modified when I hit the \"If no packet is available:\" \"Submit\" form, but it isn't. I tried a couple of times, selecting different options there: 1) \"Outside hospital\" (doing so results in a follow-on question being presented \"Have you made 2 attempts to request the medical records...?\" - I chose \"Yes, 2 attempts were made\"), and 2) \"Other\", and entered into the subsequently displayed text field \"Data corruption\"."

## Overview

The event upload page offers two mutually exclusive ways to resolve an event that is waiting on a chart packet: attach the packet, or document why no packet exists. The first path works. The second path is presented in full — the reason menu, the reason-specific follow-up questions, and a **Submit** button — but the submission goes nowhere. Nothing is recorded, no confirmation or error is shown, and the event stays in the "needs packet" queue exactly as it was.

The result is a silent data-loss trap. A site coordinator who has exhausted the record-request protocol answers every question honestly, presses Submit, sees no complaint, and reasonably concludes the event is resolved. It is not. The event remains open indefinitely, the documented justification is discarded, and the same event is re-worked by the next person to pick up the queue.

Every downstream consumer of this outcome already exists and is waiting for data that never arrives: the event record carries fields for the reason and each follow-up answer, the event's lifecycle includes a "no packet available" outcome, the "View All Events" page offers a list filtered to that outcome, the event detail page renders the date the packet was marked unavailable, and the data export reserves columns for every one of these values. This feature closes the single missing link — persisting the submission — so those existing surfaces stop showing an empty set.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Document that no packet is available (Priority: P1)

A site coordinator opens an event that needs a packet, determines that no packet can be obtained, selects the governing reason, answers any follow-up questions the reason raises, and presses Submit. The system records the reason and answers against the event, marks the event as resolved-without-a-packet, notes who did it and when, and tells the coordinator it worked. The event leaves the "needs packet" queue.

**Why this priority**: This is the entire defect. Without it the feature does not exist, and the current behavior actively misleads users into believing documented work has been saved. Every other story in this spec is a refinement of this one.

**Independent Test**: Open an event that needs a packet, select any reason, complete the follow-ups, and Submit. Then reopen the event's detail page and confirm the reason and answers are shown, and confirm the event no longer appears in the "needs packet" list and does appear in the "no packet available" list. This alone delivers the complete user value.

**Acceptance Scenarios**:

1. **Given** an event awaiting a packet, **When** the coordinator selects "Other", types "Data corruption" into the cause field, and presses Submit, **Then** the event is recorded as having no packet available with reason "Other" and cause "Data corruption", and a success confirmation is displayed.
2. **Given** an event awaiting a packet, **When** the coordinator selects "Outside hospital", answers "Yes, 2 attempts were made", and presses Submit, **Then** the event is recorded with that reason and that attempts answer, and a success confirmation is displayed.
3. **Given** an event awaiting a packet, **When** the coordinator selects "Ascertainment diagnosis error" and presses Submit, **Then** the event is recorded with that reason and no follow-up values, and a success confirmation is displayed.
4. **Given** a submission has just succeeded, **When** the coordinator returns to the list of events needing packets, **Then** that event is absent from it.
5. **Given** a submission has just succeeded, **When** anyone opens that event's detail page, **Then** the reason, the applicable follow-up answers, the date it was marked unavailable, and the person who marked it are all visible.

---

### User Story 2 - Record the prior-event details when the diagnosis referred to an earlier event (Priority: P2)

When the coordinator selects "Ascertainment diagnosis referred to a prior event", the page asks whether the approximate month/year of that prior event is known, collects the month and year when it is, and asks whether the prior event occurred while the patient was in care at the coordinator's site. Submitting stores those answers alongside the reason.

**Why this priority**: This reason carries the richest follow-up data and is the one most likely to be silently truncated, but it is a narrower slice of the queue than the general case in Story 1. Story 1 is viable without it; this completes coverage of the fourth reason.

**Independent Test**: Select this reason, answer that the date is known, enter a month and year, answer the on-site question, and Submit. Reopen the event and confirm every entered value is shown.

**Acceptance Scenarios**:

1. **Given** the "prior event" reason is selected and the date is known, **When** the coordinator enters a month and year, answers the on-site question, and presses Submit, **Then** the prior-event month/year and the on-site answer are recorded with the event.
2. **Given** the "prior event" reason is selected, **When** the coordinator answers that the approximate month/year is not known and presses Submit, **Then** the event is recorded with the reason and the on-site answer, and no prior-event date.
3. **Given** the coordinator entered a prior-event month and year and then changed the "is the date known" answer to "No", **When** they press Submit, **Then** no prior-event date is recorded.

---

### User Story 3 - Be told when a submission is incomplete or fails (Priority: P2)

The coordinator cannot submit a partially answered form without being told what is missing, and a submission that fails for any reason produces a visible error rather than silence. The Submit button communicates that work is in progress and cannot be pressed twice.

**Why this priority**: The reported defect is as much about the silence as about the missing write — an unreported failure is what let the coordinator believe the work was saved. Story 1 is testable without this, but shipping Story 1 without it recreates a milder version of the same trap.

**Independent Test**: Select "Outside hospital", leave the attempts question unanswered, and press Submit; confirm a specific message names the missing answer and nothing is recorded. Separately, cause a submission to fail and confirm an error message appears.

**Acceptance Scenarios**:

1. **Given** "Outside hospital" is selected and the attempts question is unanswered, **When** the coordinator presses Submit, **Then** a message identifies the unanswered question and the event is unchanged.
2. **Given** "Other" is selected and the cause field is empty or only whitespace, **When** the coordinator presses Submit, **Then** a message asks for the cause and the event is unchanged.
3. **Given** a submission that the system cannot complete, **When** the coordinator presses Submit, **Then** an error message explains that the event was not updated.
4. **Given** a submission is in flight, **When** the coordinator presses Submit again, **Then** no second submission is sent.

---

### User Story 4 - Prevent conflicting or unauthorized resolutions (Priority: P3)

An event that already has a packet, or that has already moved past the packet-collection stage, cannot be quietly overwritten with a no-packet outcome; and only the people permitted to upload a packet for an event are permitted to declare that no packet exists for it.

**Why this priority**: This protects data integrity in a narrow set of races and misuse cases, none of which are the reported defect. It is the last slice to add.

**Independent Test**: Attempt the submission as a user not authorized to upload for the event's site and confirm it is refused. Separately, attempt it against an event that has already advanced past packet collection and confirm it is refused with an explanation.

**Acceptance Scenarios**:

1. **Given** a user who is not permitted to upload packets for the event's site, **When** they submit the no-packet form, **Then** the request is refused and the event is unchanged.
2. **Given** an event that has already progressed beyond packet collection, **When** a no-packet submission arrives for it, **Then** it is refused with a message explaining the event's current state, and the event is unchanged.
3. **Given** an event identifier that does not exist, **When** a no-packet submission arrives for it, **Then** it is refused and reported as not found.

---

### Edge Cases

- **Reason changed after follow-ups were answered**: the coordinator picks "Outside hospital", answers the attempts question, then switches to "Other" and types a cause. Only the values belonging to the finally selected reason are recorded; the abandoned attempts answer is not.
- **No event selected**: the page is reached without an event identifier, so it shows the "events that need packets" list instead of the form. The no-packet form is not offered in that state.
- **Overlong free text**: the "Other" cause is longer than the record can hold. The coordinator is told the limit before or at submission rather than having the text silently truncated.
- **Implausible prior-event date**: a month outside 1-12, or a year that is not a four-digit year, or a month with no year. The coordinator is told what is wrong rather than storing an unusable value.
- **Whitespace-only "Other" cause**: treated as empty, not as a valid cause.
- **Double resolution race**: two coordinators submit for the same event, or one uploads a packet while the other submits no-packet. The event ends in one coherent state, and the loser of the race is told what happened rather than seeing a false success.
- **Success then navigate back**: after a successful submission the form does not remain in a state that invites a second, now-invalid submission for the same event.

## Requirements *(mandatory)*

### Functional Requirements

**Submission and persistence**

- **FR-001**: The system MUST treat the "If no packet is available" Submit action as a real submission that transmits the coordinator's answers for the selected event, rather than a no-op.
- **FR-002**: The system MUST record the selected reason against the event, limited to the four recognized reasons: "Outside hospital", "Ascertainment diagnosis error", "Ascertainment diagnosis referred to a prior event", and "Other".
- **FR-003**: The system MUST record the two-attempts answer when, and only when, the selected reason is "Outside hospital".
- **FR-004**: The system MUST record the free-text cause when, and only when, the selected reason is "Other".
- **FR-005**: The system MUST record the prior-event month/year and the prior-event on-site answer when, and only when, the selected reason is "Ascertainment diagnosis referred to a prior event"; the month/year is omitted when the coordinator indicates it is not known.
- **FR-006**: The system MUST NOT record follow-up answers belonging to any reason other than the one submitted, including answers entered before the coordinator changed their selection.
- **FR-007**: The system MUST mark the event's outcome as "no packet available" on a successful submission, using the existing lifecycle outcome of that name rather than a new one.
- **FR-008**: The system MUST record the date of the submission and the identity of the person who made it, using the event record's existing fields for the marking date and the marking user.
- **FR-009**: The system MUST persist the submission as a single all-or-nothing change: either the reason, all applicable follow-up answers, the outcome, the date, and the actor are all recorded, or none of them are.

**Validation**

- **FR-010**: The system MUST reject a submission with no reason selected. (The interface already withholds the Submit button until a reason is chosen; the rejection is the backstop.)
- **FR-011**: The system MUST reject a submission for "Outside hospital" that carries no answer to the two-attempts question, and MUST name the missing answer.
- **FR-012**: The system MUST reject a submission for "Other" whose cause is empty or whitespace-only, and MUST ask for the cause.
- **FR-013**: The system MUST reject a submission for the prior-event reason that carries no answer to the on-site question, and — when the coordinator indicated the date is known — no usable month/year.
- **FR-014**: The system MUST reject a prior-event month outside 1-12 or a year that is not a plausible four-digit year, and MUST say which value is wrong.
- **FR-015**: The system MUST reject an "Other" cause longer than the 100 characters the record holds, and MUST state the limit rather than truncating silently.
- **FR-016**: The system MUST leave the event completely unchanged whenever a submission is rejected for any reason.

**Feedback**

- **FR-017**: The system MUST display a clear confirmation when the submission succeeds, naming the event that was marked.
- **FR-018**: The system MUST display a specific, human-readable message when the submission is rejected or fails, and MUST never leave the coordinator unable to tell whether the submission took effect.
- **FR-019**: The system MUST indicate that a submission is in progress and MUST prevent a second submission from being sent while one is in flight.
- **FR-020**: After a successful submission the system MUST leave the page in a state that reflects the event's new outcome and does not invite an immediate duplicate submission.

**Authorization and conflict**

- **FR-021**: The system MUST restrict no-packet submission to the same people permitted to upload a packet for that event — that is, users holding the uploader role at the event's own site, and administrators across all sites.
- **FR-022**: The system MUST refuse a submission for an event that does not exist, and report it as not found.
- **FR-023**: The system MUST refuse a submission for an event that has already advanced beyond the packet-collection stage, and MUST explain the event's current state rather than silently overwriting it.

**Consistency with existing surfaces**

- **FR-024**: An event marked as having no packet available MUST disappear from the list of events needing packets.
- **FR-025**: An event marked as having no packet available MUST appear in the existing "no packet available" list on the View All Events page.
- **FR-026**: The event detail page MUST display the recorded reason, the applicable follow-up answers, the marking date, and the marking user for such an event, using the fields already present there.
- **FR-027**: The data export MUST carry the recorded reason, follow-up answers, and marking date in the columns already reserved for them.

### Key Entities

- **Event**: the unit of work being resolved. Already carries every field this feature writes — the no-packet reason, the two-attempts flag, the prior-event date, the prior-event on-site flag, the other-cause text, the marking date, the marking user, and a lifecycle outcome that already includes "no packet available". No new field is introduced.
- **No-packet declaration**: the coordinator's submitted statement — one reason plus exactly the follow-up answers that reason calls for. It is not a separate stored record; it is the set of values written onto the event.
- **Coordinator (uploader)**: the person resolving the event, scoped to their own site unless they are an administrator. Recorded as the marking user.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of successful no-packet submissions are reflected on the event's detail page immediately afterward, with the reason and every applicable follow-up answer intact.
- **SC-002**: Zero submissions complete without visible feedback — every press of Submit results in either a confirmation or a specific error message, with no silent outcome.
- **SC-003**: An event resolved as having no packet available disappears from the "needs packet" queue and appears in the "no packet available" list within one refresh of each.
- **SC-004**: A coordinator can document an unavailable packet, from opening the event to seeing confirmation, in under 60 seconds.
- **SC-005**: Zero events are left with a partial record — no event exists carrying a no-packet reason without the outcome, date, and actor, or carrying follow-up answers that do not belong to its recorded reason.
- **SC-006**: Zero re-work: the same event does not reappear in the "needs packet" queue after being documented as having no packet, so no coordinator repeats another's completed work.
- **SC-007**: Every one of the four reasons, and every follow-up branch within them, can be submitted and retrieved without loss.

## Assumptions

- **The visible interface is correct and stays as-is.** The four reasons, the follow-up questions each one raises, and their wording all match the stored record's permitted values and the study protocol. This feature makes the existing form work; it does not redesign it. The one interface change in scope is the addition of validation messages, progress indication, and a success confirmation, which the form has no equivalent of today.
- **The outcome is the existing "no packet available" lifecycle state**, already present in the event lifecycle and already surfaced by the View All Events page. No new state is introduced, and no state is renamed or removed.
- **Marking date and marking user use the event record's existing fields** for exactly that purpose, mirroring how a successful packet upload records its date and uploader.
- **Authorization mirrors packet upload**: the right to declare that no packet exists is the same right as the right to upload one, since both resolve the same queue item. This is stated as FR-021 rather than assumed silently, because it is a security-relevant default.
- **A missing prior-event month/year is a legitimate outcome, not an error**, when the coordinator has indicated the approximate date is unknown — the record explicitly permits an absent date for that case.
- **This feature is study-agnostic.** Packet collection is a shared lifecycle stage; the no-packet outcome belongs to every study whose configured workflow includes packet upload, and nothing here is specific to one study.
- **The separate VTE-specific upload page is out of scope.** It carries an identical non-functioning form, but it is a legacy fork slated for removal, and duplicating this work into it would deepen that fork. Only `/events/upload` is in scope.
- **Reversing a no-packet declaration is out of scope** for this feature. A mistaken entry is corrected through the existing event-editing surfaces, as other lifecycle mistakes already are; adding an undo path here would be a separate decision about who may reopen a resolved event.
- **No schema change and no data migration are required.** Every field this feature writes already exists and is already read by the detail page, the filtered list, and the export.

## Out of Scope

- Undo or self-service correction of a no-packet declaration from the upload page.
- Any change to the packet-upload path, which works today.
- The VTE-specific upload page's copy of this form.
- New reasons, new follow-up questions, or rewording of existing ones.
- Notifications to other users when an event is marked as having no packet.
- Bulk or multi-event no-packet marking.

## Dependencies

- The event record's existing no-packet fields, marking-date field, marking-user field, and "no packet available" lifecycle outcome.
- The existing "events that need packets" list, whose membership rule must exclude events with this outcome.
- The existing "no packet available" list on the View All Events page.
- The existing event detail page and data export, which already read these fields.
- The existing role and site authorization used for packet upload.
