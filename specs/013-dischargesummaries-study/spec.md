# Feature Specification: Add the `dischargesummaries` Study Type

**Feature Branch**: `013-dischargesummaries-study`
**Created**: 2026-10-23
**Status**: Draft — review questions (FR-011) still open
**Input**: User description: "We need a new study type, 'dischargesummaries'. A user from each clinical site will log in and upload a redacted discharge summary. There is a scrub step. There will be one review. Before you implement, what questions do you have re workflow? Deployments of this will be hosted on the same VM's that host the 'scans' study (dev and prod VM's)."

## Context

The application already serves `mci` (full workflow: upload → scrub → screen →
assign → send → two reviews → done) and `scans` (upload → assign → one review →
done, with scrubbing, screening and sending all bypassed). Both are selected by
a deployment's study type, and each stage can be turned on or off by named
workflow settings.

`dischargesummaries` sits between them: like `scans` it has **one review**, but
unlike `scans` it **keeps the scrub step**. Clinical-site users upload discharge
summaries that the site has already redacted; the scrub step is the check, at
the coordinating center, that nothing identifying is left before a reviewer
sees the document.

A `dischargesummaries` deployment runs on the **same dev and prod VMs** that
already host the `scans` deployments, side by side, each with its own
database, containers, files and web address.

## Clarifications

### Session 2026-10-23

- Q: Where do events come from? → A: An admin creates them in advance (single add or CSV bulk import), and site users upload against their site's "needs document" list. **Only users with the admin role may create events, in every study.** This is already enforced for both event-creation paths (single and bulk) and must stay that way.
- Q: What happens at scrub if the upload still contains identifiers? → A: The admin fixes it and uploads the clean copy, which is the existing scrub behavior. There is no send-back-to-site or reject path at scrub.
- Q: What does the reviewer record? → A: Not yet known; the study owner is working it out. FR-011 stays open until then.
- Q: How does prod reach the patient roster? → A: It uses the same SSH tunnel the `scans` deployment on that VM already uses. No second tunnel.
- The defaults in Assumptions (screening and sending off, admin-only scrubbing, auto-complete on review, existing roles and file types) were confirmed.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Take a discharge summary from site upload to a completed review (Priority: P1)

A site user logs in, sees the discharge-summary events for their site that
still need a document, and uploads the redacted discharge summary for one of
them. A coordinating-center admin scrubs the upload (checks it and stores the
cleaned copy). The admin assigns the event to one reviewer. The reviewer opens
the scrubbed document, fills in the discharge-summary review form, and submits
it. The event is then complete.

**Why this priority**: This is the whole study. Without it, nothing else has
value.

**Independent Test**: On a `dischargesummaries` deployment with one site user,
one admin and one reviewer, take a single event from "needs document" to
"done" and confirm each person saw only the actions meant for their step.

**Acceptance Scenarios**:

1. **Given** an event for a patient at site A, **When** a site-A user uploads a discharge summary for it, **Then** the event moves to "uploaded" and appears in the admin's to-be-scrubbed list.
2. **Given** an event for a patient at site A, **When** a site-B user tries to upload for it, **Then** the upload is refused and the event is unchanged.
3. **Given** an uploaded event, **When** an admin stores the scrubbed copy, **Then** the event moves to "scrubbed" and becomes eligible for assignment.
4. **Given** a scrubbed event, **When** an admin assigns it to a reviewer, **Then** it appears in that reviewer's queue immediately (no separate "send" step).
5. **Given** an assigned event, **When** its reviewer opens it, **Then** the reviewer receives the scrubbed document, never the site's original upload.
6. **Given** an assigned event, **When** its reviewer submits a valid review, **Then** the review is stored and the event moves straight to "done".
7. **Given** an assigned event, **When** its reviewer submits a review with a required answer missing, **Then** nothing is stored and the form names the missing answer.
8. **Given** a site user or reviewer, **When** they try to create an event (singly or in bulk), **Then** they are refused; only admins create events.

---

### User Story 2 - Each person sees an interface that matches this workflow (Priority: P2)

Site users, admins and reviewers see only the pages, lists, buttons and
status labels that apply to a one-review, scrub-on workflow: scrubbing is
shown; screening, sending, second review and third review are not. Headings,
the banner, the browser tab title, emails and review instructions name the
discharge-summary study, not MCI or scans.

**Why this priority**: Showing steps that never happen (or hiding the scrub
step) confuses users and caused real support issues on the `scans` rollout.

**Independent Test**: Log in as each role on a `dischargesummaries`
deployment and walk every menu and list; no screening, sending, second- or
third-review element appears, and the scrub list does.

**Acceptance Scenarios**:

1. **Given** a `dischargesummaries` deployment, **When** an admin views all events, **Then** the to-be-scrubbed list is shown and no to-be-screened, to-be-sent or second/third-review list is shown.
2. **Given** a `dischargesummaries` deployment, **When** any user opens the home page or review page, **Then** the review-instructions link opens the discharge-summary instructions.
3. **Given** a site user has just uploaded a document, **When** the confirmation appears, **Then** it says the next step is scrubbing.

---

### User Story 3 - An operator stands up the study next to `scans` on the same VM (Priority: P2)

A deployment operator, following the documentation, brings up a
`dischargesummaries` deployment on the existing dev VM and then the prod VM,
alongside the running `scans` deployment, without stopping, reconfiguring or
otherwise affecting `scans`.

**Why this priority**: Hosting is fixed (the scans VMs). If the two stacks
collide, neither study can run.

**Independent Test**: On a VM already running `scans`, start a
`dischargesummaries` deployment from its own checkout and `.env` only; both
deployments serve requests, and each sees only its own events, users and files.

**Acceptance Scenarios**:

1. **Given** a VM running `scans`, **When** the operator starts `dischargesummaries` with its own project name, ports and network settings in its `.env`, **Then** both deployments run at the same time and neither has to edit `docker-compose.yaml`.
2. **Given** both deployments are running, **When** a user of one logs in, **Then** they cannot see or reach any event, user, review or file of the other.
3. **Given** the prod VM reaches the patient roster through the `scans` deployment's SSH tunnel, **When** `dischargesummaries` is started there, **Then** it looks up patients through that same tunnel, without a second tunnel being created.

---

### User Story 4 - Existing studies keep working unchanged (Priority: P3)

Adding `dischargesummaries` leaves `mci` and `scans` deployments' workflows,
forms, exports and data as they were. The one intended difference is the
three tightened access rules in FR-022, which refuse actions that only the
interface used to prevent.

**Why this priority**: Regression protection; both are in use.

**Independent Test**: The existing automated test suite passes for `mci` and
`scans`, and each FR-022 access rule is tested under the `mci` and `scans`
settings as well as `dischargesummaries`.

**Acceptance Scenarios**:

1. **Given** an existing `scans` or `mci` deployment, **When** it is upgraded to the release containing this feature, **Then** its database needs no migration and every legitimate workflow behaves as before.
2. **Given** an upgraded `mci` or `scans` deployment, **When** a user tries a cross-site upload after scrubbing, assigns an event that hasn't finished its earlier stages, or downloads a document for an event that isn't theirs, **Then** the action is refused (FR-022 exception).

---

### Edge Cases

- A site user uploads the wrong file: they can replace it while the event is still "uploaded" (before it is scrubbed); after scrubbing, only an admin can act on it.
- The scrubber finds identifying information left in the upload: the admin removes it and stores the cleaned copy (FR-006). The event never goes back to the site.
- A site user or reviewer tries to create an event: refused (FR-003).
- A site has no discharge summary for an event: the existing "no document available" path is used, as in `mci`.
- A reviewer opens an event assigned to someone else: refused, as on `scans`.
- An admin tries to assign an event that has not been scrubbed: refused; only scrubbed events can be assigned.
- The deployment is started with a study type that has no usable files: it falls back to `mci` with a logged warning (existing behavior); the documentation tells operators to check for that warning.
- File types: the same types accepted today (PDF, Word, ZIP) are accepted; anything else is refused with a message listing the allowed types.

## Requirements *(mandatory)*

### Functional Requirements

**Study selection and workflow**

- **FR-001**: System MUST recognize `dischargesummaries` as a valid study type.
- **FR-002**: Selecting `dischargesummaries` MUST supply this default workflow: scrubbing **on**, screening **off**, sending **off**, one reviewer — giving the lifecycle `created → uploaded → scrubbed → assigned → reviewer1_done → done`. Any individual setting MAY still be overridden in the deployment's configuration, as for other studies.
- **FR-003**: Events MUST be created in advance by an admin, singly or by CSV bulk import, as in existing studies. Only users with the admin role MAY create events. This rule applies in every study, and site users (uploaders) and reviewers MUST be refused.
- **FR-004**: Only users at the same clinical site as an event's patient (or admins) MUST be able to upload a document for that event or mark it as having no document.
- **FR-005**: Only admins MUST be able to scrub an event. Scrubbing MUST store a scrubbed copy separately from the site's original upload and record who scrubbed it and when.
- **FR-006**: If the upload still contains identifying information, the admin MUST remove it and store the cleaned copy as the scrubbed document. Scrubbing has no send-back-to-site or reject outcome.
- **FR-007**: Only scrubbed events MUST be assignable. Assignment MUST assign exactly one reviewer, and the event MUST appear in that reviewer's queue without a separate send step.
- **FR-008**: Reviewers MUST receive only the scrubbed document. The site's original upload MUST be available only to admins and to uploaders at the event's site.
- **FR-009**: A reviewer MUST be able to view and submit a review only for events assigned to them, consistent with the reviewer restrictions already in place for `scans`.
- **FR-010**: Submitting a valid review MUST move the event directly to "done" with no separate completion step.

**Review form and data**

- **FR-011**: The study MUST have its own review form and its own review-answer storage, used only by `dischargesummaries` deployments. The questions it asks are: [NEEDS CLARIFICATION: Q3 — the discharge-summary review questions and allowed answers.]
- **FR-012**: The review form MUST validate required answers before storing anything, and report each problem next to the question it concerns.
- **FR-013**: The admin export of reviews MUST include every `dischargesummaries` review answer, one row per review, alongside the event and patient identifiers already included in exports.
- **FR-014**: Discharge-summary reviewer instructions MUST be available from the home page and the review page, and MUST be editable by an operator without rebuilding the application (as for `scans`).

**Interface and wording**

- **FR-015**: The interface MUST show the scrub step and MUST NOT show screening, sending, second-review or third-review pages, lists, buttons or status labels. This MUST follow from the workflow settings, not from checks on the study name.
- **FR-016**: Headings, banner, tab title, emails and upload confirmations MUST name the discharge-summary study (using the existing study-title setting where one is configured).

**Deployment**

- **FR-017**: A `dischargesummaries` deployment MUST be able to run on the same VM as a `scans` deployment, each from its own checkout and `.env`, with distinct project name, host ports, database, file storage and web address, without editing `docker-compose.yaml`.
- **FR-018**: The internal network address range that is currently fixed in `docker-compose.yaml` MUST be settable from `.env`, so two deployments on one VM do not collide. The default MUST stay the current value so existing deployments are unaffected.
- **FR-019**: On the prod VM, `dischargesummaries` MUST reach the patient roster through the **same SSH tunnel** the `scans` deployment already uses. No second tunnel is set up. The documentation MUST show the settings that point the new deployment at that tunnel. It MUST also state what the shared tunnel depends on, such as whether it stops working for `dischargesummaries` while the `scans` stack is down.
- **FR-020**: `default.env`, `README.md` and the setup guide MUST document the new study type, its default workflow, and a worked example of running it beside `scans` on one VM.

**Isolation and compatibility**

- **FR-021**: A `dischargesummaries` database MUST contain only its own study-specific review tables, never those of `mci` or `scans`.
- **FR-022**: This feature MUST NOT change the data or schema of existing `mci` or `scans` deployments, MUST NOT require any migration for them, and MUST NOT change any legitimate workflow in them. **Exception:** three shared access rules are tightened for every study, because FR-007 and FR-008 cannot hold otherwise and a study-only rule would fork shared code (Constitution IV). Each one closes a gap where only the interface, not the server, stopped the action:
  - assigning a first reviewer is refused unless the event has passed every enabled stage before assignment (research D2);
  - a non-admin cannot upload over an event once it has moved past "uploaded" (research D3);
  - a document download is refused unless the caller is an admin, an uploader at the event's site, or a reviewer assigned to the event, and reviewers never receive the original upload when scrubbing is on (research D4).
  
  These changes MUST be listed in the PR description as behavior changes for `mci` and `scans`.

### Key Entities

- **Event**: One patient hospitalization whose discharge summary is to be validated. Belongs to one patient (and so to one clinical site). Moves through the lifecycle in FR-002.
- **Discharge summary (original upload)**: The site-redacted document a site user uploads for an event. Visible only to admins and the uploading site.
- **Scrubbed discharge summary**: The admin-checked copy that reviewers see. One per event.
- **Discharge-summary review**: One reviewer's answers for one event. One per event (single reviewer). Its fields come from Q3.
- **Site user (uploader)**: A user tied to one clinical site who uploads documents for that site's events.
- **Admin**: Coordinating-center user who creates events, scrubs, assigns and exports.
- **Reviewer**: User who reviews only the events assigned to them.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A single event can be taken from upload to "done" by three people (site user, admin, reviewer) in one sitting, with each person needing no instructions beyond the review instructions page.
- **SC-002**: 100% of site-user upload attempts for another site's events are refused.
- **SC-003**: 0 reviewers can obtain a site's original (unscrubbed) upload.
- **SC-004**: A `dischargesummaries` deployment is brought up on a VM already running `scans` with 0 changes to `docker-compose.yaml` and 0 minutes of `scans` downtime.
- **SC-005**: On a `dischargesummaries` deployment, no page shows a screening, sending, second-review or third-review element (checked by walking every page as each role).
- **SC-006**: All existing automated tests for `mci` and `scans` pass. The only tests that may change are those that asserted one of the three gaps closed under FR-022, and each such change is listed in the PR description.

## Assumptions

- **Screening is off.** The description names only upload, scrub and one review. If the study needs a screening pass (an admin accept/reject before assignment), it is a setting change, not new work.
- **Sending is off.** As on `scans`, an assigned event goes straight to the reviewer.
- **The scrub step is the existing one**: an admin downloads the site upload, checks/cleans it, and uploads the scrubbed copy. No automatic redaction is in scope.
- **Roles are the existing ones** (admin, uploader, reviewer). Site users are uploaders tied to a site; the coordinating center does scrubbing. No new role is added.
- **Events are tied to patients in the CNICS patient roster**, as in the other studies, so a site user's access follows the patient's site.
- **Accepted file types** are the existing ones (PDF, Word, ZIP).
- **Each deployment has its own web address** on the shared VM, fronted by the same Apache/LDAP login used by `scans`; site users get accounts in the `dischargesummaries` deployment separately from any `scans` account.
- **Default deployment settings** for a second stack on one VM (project name, ports, network range) are chosen by the operator; the docs give a working example.
