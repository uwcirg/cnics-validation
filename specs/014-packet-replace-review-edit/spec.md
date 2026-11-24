# Feature Specification: Packet Replacement Before Review, and Editable Reviews

**Feature Branch**: `014-packet-replace-review-edit`
**Created**: 2026-11-24
**Status**: Draft
**Input**: User description: "1. A user at clinical site X with the "uploader" role can replace packets for events for patients at their site, but only if there are no reviews for the event yet. 2. A reviewer should be able to edit their own prior reviews, if either of these conditions are met: the study type only has a single reviewer; or the study type has multiple reviews, and other reviews have not yet been completed for the event."

## Current Behavior (for context)

- A site uploader can upload or replace an event's packet only while the event is still at the very start of the workflow, before any scrubbing, screening, assignment, or sending has happened. After that only an administrator can replace the packet, and the uploader is told to "ask an administrator". This rule was added in spec 013.
- An administrator can replace a packet at any stage, even after reviews exist.
- A submitted review is final. A reviewer who opens an event they have already reviewed sees "You have already reviewed this event" and cannot change their answers. A second submission is refused.

This feature extends the uploader's replacement window and makes reviews editable, within the bounds below. It also *narrows* the administrator's replacement window to the same cutoff as the uploader's.

## Clarifications

### Session 2026-11-24

- Q: What happens to an event's progress when its packet is replaced after it has moved past upload? → A: Administrators and uploaders may replace a packet only up until it is sent to reviewers. After that, replacement is not possible in the application; it needs direct database or file-system action by the systems operator.
- Q: How much edit history should be kept for reviews? → A: Overwrite the answers, and store a "last edited" date alongside the original submission date.
- Q: When a replaced event already has a reviewer, does it return to "assigned" once the new packet has been scrubbed (and screened)? → A: Yes. The reviewer is kept, and the event becomes "assigned" again as soon as every required processing step is complete for the new packet.
- Q: Should a scrubbed copy, or a screening decision, made against the old packet be refused after a replacement? → A: Yes, refuse both.
- Q: How is a reviewer told that a packet they may already have downloaded was replaced? → A: Their review page shows a notice with the date and time of the replacement, telling them to download the packet again. No email is sent.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Site uploader replaces a packet before it reaches a reviewer (Priority: P1)

An uploader at clinical site X notices that the packet they uploaded for a site-X patient's event is wrong. They may have attached the wrong file, an incomplete chart, or a copy that was not redacted properly. The event may have moved on since the upload: an administrator may have scrubbed or screened it, or assigned it to a reviewer. As long as the packet has not yet been released to a reviewer (see FR-002), the uploader can replace the packet themselves without contacting an administrator. Administrators are held to the same cutoff.

**Why this priority**: A wrong packet that reaches a reviewer wastes reviewer time and can corrupt study data. Today, fixing it needs an administrator, which slows correction and adds administrator workload. This is the most direct data-quality fix in the request.

**Independent Test**: Sign in as a site-X uploader. Pick a site-X event that has been scrubbed and assigned but not sent. Replace its packet and confirm the new file is now the event's packet and the event needs scrubbing again. Then pick a sent event and confirm replacement is refused, for the uploader and for an administrator, with an explanation.

**Acceptance Scenarios**:

1. **Given** a site-X uploader and a site-X event that is uploaded, scrubbed, screened, or assigned but not yet sent, **When** the uploader uploads a replacement packet, **Then** the system accepts it, the new packet becomes the event's packet, and the uploader and upload date reflect the replacement.
2. **Given** a site-X uploader or an administrator and an event whose packet has been sent to reviewers, **When** they try to replace the packet, **Then** the system refuses, saves nothing, and says the packet has already been released to reviewers and can only be changed by the systems operator. In a study without the sending stage, the same refusal applies once a review has been submitted.
3. **Given** a site-X uploader and an event for a patient at site Y, **When** the uploader tries to replace its packet, **Then** the system refuses regardless of review state (unchanged from today).
4. **Given** a site-X uploader viewing the list of their site's events, **When** an event is eligible for replacement under this feature, **Then** the uploader can reach the replace action for it; **and when** it is not eligible, the action is not offered (or is shown as unavailable with the reason).
5. **Given** an event whose packet was replaced after it had been scrubbed, **When** the workflow continues, **Then** the new packet must be scrubbed (and screened, where screening is on) again before it can be sent (see FR-004). The old scrubbed copy is never sent to or shown to a reviewer as if it were the current packet. Any reviewer assignment is kept, and the event returns to "assigned" once the new packet has completed every required step.
6. **Given** an administrator who opened the scrub page for an event before its packet was replaced, **When** they upload a scrubbed copy, **Then** the system refuses it, saves nothing, and tells them the packet was replaced and must be downloaded and scrubbed again.
7. **Given** a study with scrubbing on and an event whose new packet has not yet been scrubbed, **When** an administrator records a screening decision, **Then** the system refuses it and saves nothing.
8. **Given** a study without the sending stage, and an event whose packet was replaced after a reviewer was assigned, **When** that reviewer opens the event's review page, **Then** they see a notice giving the date and time of the replacement and telling them to download the packet again.

---

### User Story 2 - Reviewer corrects their own review in a single-reviewer study (Priority: P1)

A reviewer on a study configured with one reviewer per event submits a review and later realizes they entered a wrong value. They reopen the event, see their previously submitted answers pre-filled in the review form, change them, and save. The saved review replaces their earlier answers.

**Why this priority**: In single-reviewer studies (for example scans and dischargesummaries), the one review is the study's result for that event, so an uncorrectable typo is a permanent data error. Today the only remedy is an administrator editing data by hand.

**Independent Test**: In a single-reviewer deployment, submit a review as the assigned reviewer. Reopen the event, change one answer, and save. Confirm the stored review and any export show the corrected answer, and that the event is still complete.

**Acceptance Scenarios**:

1. **Given** a single-reviewer study and a reviewer who has submitted a review for an event they are assigned to, **When** they open that event's review page, **Then** they see their submitted answers in an editable form instead of the "already reviewed" message.
2. **Given** the editable form from scenario 1, **When** the reviewer changes answers and saves, **Then** the system validates the answers by the same rules as a first submission and replaces the reviewer's previous answers with the new ones.
3. **Given** an edited review, **When** an administrator views or exports the event's reviews, **Then** only the current (edited) answers appear as the reviewer's review. The event's status does not move backward.
4. **Given** a reviewer who is not assigned to the event, or whose review belongs to someone else, **When** they try to edit it, **Then** the system refuses.

---

### User Story 3 - Reviewer corrects their own review in a two-reviewer study before the other reviewer finishes (Priority: P2)

On a study configured with two reviewers per event (for example MCI), reviewer A submits their review and then wants to change it. If reviewer B has not yet submitted their review for that event, reviewer A can edit and resave. Once reviewer B has submitted, reviewer A's review is locked. Reviewer B's review is locked as well, because A's review is complete.

**Why this priority**: This is the same value as Story 2 for two-reviewer studies. It carries the extra constraint that neither reviewer can change their answers after both independent reviews exist. That keeps the two reviews independent and keeps the agreement/disagreement outcome stable.

**Independent Test**: In a two-reviewer deployment, have reviewer A submit, then edit successfully. Have reviewer B submit, then confirm that both A's and B's reviews are no longer editable.

**Acceptance Scenarios**:

1. **Given** a two-reviewer study where reviewer A has submitted and reviewer B has not, **When** reviewer A opens the event, **Then** they can edit and resave their review.
2. **Given** a two-reviewer study where both reviewers have submitted, **When** either reviewer opens the event, **Then** they see their review as read-only with an explanation that both reviews are complete, and any save attempt is refused.
3. **Given** reviewer A is mid-edit and reviewer B submits in the meantime, **When** reviewer A saves, **Then** the save is refused and A is told the other review has been completed. A's earlier submitted answers stay unchanged.
4. **Given** an edit by reviewer A while B is still pending, **When** the edit is saved, **Then** the event's workflow status is unchanged (it remains "reviewer 1 done" or the equivalent).

---

### Edge Cases

- **Replacement race with sending or review**: An uploader starts a replacement and, before it is saved, the event is sent (or, in a no-send study, a review is submitted). The eligibility check uses the state at save time, so the replacement is refused and nothing is saved.
- **Edit race with packet replacement**: Packet replacement is blocked before any review can exist, so a reviewer never edits a review of a packet that has since been swapped out.
- **Event marked "no packet available"**: This feature does not change how a no-packet declaration is resolved. Replacement on such events follows existing rules.
- **Rejected at screening**: Today a non-administrator cannot replace the packet of an event rejected at screening; the server refuses with 409. Such an event has not been sent, so after this feature it is inside the replacement window, and replacing its packet returns it to the start of processing.
- **Third (tie-breaker) review**: Out of scope. Edit rights in this feature cover only the first and second review slots. A third review, where it exists, is not editable.
- **Administrator actions**: This feature *narrows* administrators' packet replacement. Today they can replace at any stage; after this feature they are held to the same cutoff as uploaders, and anything later is an operator task outside the application. Administrators do not gain a new ability to edit a reviewer's answers.
- **Studies without a sending stage** (e.g. scans, dischargesummaries): No event is ever "sent", so the cutoff is the first submitted review, as in the original request. In these studies an assigned reviewer can download the packet before reviewing, so they may hold a copy of the old packet. That is why FR-019 exists.
- **Scrubbing or screening the old packet**: An administrator may download a packet, take time to scrub it offline, and upload the scrubbed copy after the packet has been replaced. Likewise, a screening page can be left open across a replacement. FR-017 and FR-018 refuse both.
- **Studies with the sending stage** (e.g. mci): Reviewers cannot get the packet before it is sent, and sending ends the replacement window, so no reviewer notice is needed.
- **Study with no review form yet** (dischargesummaries Phase A): No review can be submitted, so there is nothing to edit. The edit capability takes effect for that study once its review form exists.
- **Reviewer reassigned**: If an event's reviewer assignment changes after a review was submitted, only the reviewer who currently holds the slot and authored the review may edit it.

## Requirements *(mandatory)*

### Functional Requirements

**Packet replacement**

*FR-017 to FR-020 were added during clarification. They are listed with the packet-replacement group, so the IDs are not in sequence.*

- **FR-001**: The system MUST allow a user with the uploader role to replace the packet of an event whose patient is at the same clinical site as the user, provided the event is within the replacement window (FR-002). Administrators MAY replace packets for events at any site within the same window.
- **FR-002**: The replacement window ends when the packet is released to reviewers. In studies with the sending stage on, that is when the event is sent. In studies with sending off, it is when the first review (either slot) is submitted. After the window ends, the system MUST refuse replacement for every user, administrators included. It MUST save nothing and say that the packet has been released to reviewers and can only be changed by the systems operator.
- **FR-003**: The site restriction on uploads MUST remain unchanged: a non-administrator may replace packets only for events whose patient is at their own site.
- **FR-004**: When a packet is replaced after it has been scrubbed or screened, the system MUST treat that scrub and screen as no longer current. The new packet MUST be scrubbed (and screened, where screening is on) again before it can be sent or shown to a reviewer. The event's reviewer assignment MUST be kept.
- **FR-005**: The uploader's event list MUST make replaceable events reachable for replacement. Ineligible events (sent, reviewed, or at another site) MUST NOT offer a replace action that would then fail without explanation.
- **FR-006**: Administrators MUST NOT be able to replace a packet in the application after the replacement window ends. This removes their current any-stage replacement ability.
- **FR-017**: The system MUST refuse an upload of a scrubbed copy if the event's packet has been replaced since the administrator's scrub page was loaded. It MUST save nothing and explain that the new packet must be downloaded and scrubbed.
- **FR-018**: Where scrubbing is on, the system MUST refuse any screening decision for an event whose current packet has not been scrubbed.
- **FR-019**: Where reviewers can access a packet before it is sent (the sending stage is off), the review page MUST show the assigned reviewer a notice when the event's packet was replaced after a reviewer was assigned. The notice gives the date and time of the most recent such replacement and tells them to download the packet again.
- **FR-020**: The system MUST record every packet replacement (event, user, time, and whether a reviewer was assigned at the time) in the existing audit log.

**Review editing**

- **FR-007**: The system MUST allow a reviewer to edit a review they submitted for an event when the deployment is configured for one reviewer per event. This applies regardless of the event's current status.
- **FR-008**: The system MUST allow a reviewer to edit a review they submitted for an event when the deployment is configured for two reviewers per event and the other reviewer slot has no submitted review.
- **FR-009**: The system MUST refuse a review edit when the deployment uses two reviewers and the other reviewer has already submitted. It MUST leave the stored review unchanged and explain why.
- **FR-010**: Only the author of a review, who is still the assigned reviewer in that slot, MUST be able to edit it.
- **FR-011**: An edited review MUST be validated by the same rules as a newly submitted review for that study.
- **FR-012**: Saving an edit MUST replace the reviewer's previous answers. Exactly one current review per reviewer slot is kept, and administrator views and exports show only the current answers.
- **FR-013**: Saving an edit MUST NOT move the event's workflow status backward and MUST NOT re-trigger completion side effects that already happened.
- **FR-014**: The system MUST record the date a review was last edited, separately from its original submission date, and make it visible to administrators in their review views and exports. Earlier versions of the answers are not kept.
- **FR-015**: When a reviewer opens an event they have reviewed, the review page MUST show their current answers in an editable form if editing is allowed. If not, it MUST show the answers read-only (or the existing "already reviewed" message) with the reason editing is unavailable.
- **FR-016**: Whether editing is allowed MUST be decided from the deployment's configured reviewer count. It MUST NOT be decided from the study's name.

### Key Entities

- **Event**: A clinical event for a patient at a site. It carries its current packet, workflow status, assigned reviewers, and per-slot review dates.
- **Packet**: The chart file uploaded for an event. Replacing it supersedes the previous file, and any scrubbed copy derived from the previous file is no longer current. Each upload has a version identifier, so a scrubbed copy can be matched to the packet it was made from.
- **Replacement record**: An audit-log entry for one packet replacement, giving the event, the user, the time, and whether a reviewer was assigned.
- **Review**: One reviewer's answers for one event in one reviewer slot. It has an original submission date and, once edited, a last-edited date. Edits replace the answers.
- **Reviewer count**: The deployment's configured number of reviewers per event (1 or 2). It decides which review-editing rule applies.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A site uploader can correct a wrong packet on any unsent site event without administrator help, in under 2 minutes from finding the event.
- **SC-002**: In 100% of attempts, packet replacement after the replacement window ends is refused for every user, administrators included, and nothing changes.
- **SC-003**: A reviewer in a single-reviewer study can correct a submitted review in under 2 minutes, and the corrected answers are what administrators see and export.
- **SC-004**: In two-reviewer studies, 0 reviews are changed after both reviewers have submitted.
- **SC-005**: Administrator requests to "fix a wrong packet" or "fix my review" for eligible events drop to zero after release.
- **SC-006**: No event regresses in workflow status as a result of a review edit.

## Assumptions

- In studies with sending on, an event is not offered for review before it is sent. The server does not enforce this, though: a review can be submitted directly while the event is still `assigned`. The replacement window therefore also checks that no review has been submitted (research D1).
- Replacing a packet after the window ends is a systems-operator task done directly on the database and file system. The application does not support it beyond the refusal message; a manual procedure is documented in quickstart.md.
- Existing reviewer assignments are kept on replacement. No email is sent to the assigned reviewer. Where the sending stage is on, the reviewer cannot yet have the packet. Where it is off, the review page shows a replacement notice instead (FR-019).
- The review-edit lock (FR-009) is checked at save time. The shared tables do not support transactions, so a save that starts within milliseconds of the other reviewer's submission could still succeed. SC-004 accepts this window, which is negligible for two people (research D6).
- In single-reviewer studies, editing is allowed even after the event is "done", following the request literally. Data already exported before an edit is not retroactively updated, and the next export reflects the edit.
- The third (tie-breaker) review and the admin-only disagreement workflow are out of scope.
- The roles and site matching for uploaders and reviewers are unchanged.
