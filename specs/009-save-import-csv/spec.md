# Feature Specification: Archive Bulk-Import CSV Files and Report Import Outcomes Honestly

**Feature Branch**: `009-save-import-csv`
**Created**: 2026-08-13
**Status**: Draft
**Input**: User description: "for the CSV files uploaded at /events/addMany, save them per your last comment here."

**Amended 2026-08-17**: User description: "The events/addMany UI needs ease of use improvements" — the Add button is hard to see against the background (and so are buttons on other pages); there is no indication that a long import is being processed, so administrators re-upload the same file; and the result notification vanishes before it can be read, having in one case reported failure for an import that actually succeeded.

## Context

Administrators create events in bulk by uploading a CSV file on the "Add multiple events from a CSV file" page. Today that file is read, parsed, and discarded: the only lasting trace of a bulk import is the events it created. Nothing records what was submitted, who submitted it, when, or which rows were skipped and why. The list of skipped rows is shown once in an on-screen notification and is gone as soon as the administrator navigates away.

That gap has practical consequences. If a batch imports with the wrong dates or the wrong criteria, there is no submitted file to compare the created events against — only the events themselves and whatever copy the administrator still happens to have on their own machine. If a submission is rejected outright, the reasons are unrecoverable. This feature preserves each submitted file and the outcome of importing it, so that a bulk import can be audited and reconstructed after the fact.

The same page fails the administrator while the import is running and when it finishes. Submitting gives no sign that anything is happening — a file of 800 rows takes close to a minute, during which the page looks inert, and administrators have responded by submitting the same file again. When it does finish, the result appears in a notification at the lower right that removes itself after a few seconds, too briefly to read, copy, or screenshot. Worse, that notification has reported "CSV upload failed" for an import that in fact created every event and criterion it should have: the page treats any response it cannot parse as a failure, so a request that outlives the proxy's read timeout and comes back as the fallback HTML page is announced as an error while the import quietly succeeds. An administrator who believes a successful import failed will run it again, which is how duplicate events get created. Finally, the button that starts all of this is the unstyled framework default — a near-white fill on a white page — a legibility problem shared by every button in the application.

## Clarifications

### Session 2026-08-17

- Q: For long imports that currently time out at the proxy: what's in scope? → A: Report accurately + raise the proxy/server read timeout. The interface must never claim failure it cannot prove; when the outcome is unreadable it says so and points to the import history. The timeout is raised so a ~1-minute import completes normally.
- Q: What should the in-flight indication be during a synchronous import? → A: Indeterminate spinner plus a disabled/busy Add button and a running elapsed-time counter. No estimated percentage — the synchronous import cannot measure real progress, and a guessed bar would misreport it.
- Q: Which notifications become persistent and manually dismissible? → A: Application-wide in the shared notification component — warning and error notifications persist until the administrator dismisses them and their text is selectable for copying; success and informational notifications continue to auto-dismiss.
- Q: How should the app-wide button restyle be scoped? → A: A single accessible default button style applied application-wide, meeting WCAG AA (≥3:1 for the button against the page background, ≥4.5:1 for its label), with visible hover, focus, and disabled states. No primary/secondary variants and no per-page markup changes.
- Q: How should the import result present its skipped-row detail? → A: A summary headline with the counts, followed by every skipped row on its own line in a scrollable, selectable region, a copy-all control, and a link to this submission's record in the import history.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Every submitted file is preserved (Priority: P1)

An administrator uploads a CSV of new events. Whatever happens next — all rows import, some rows are skipped, or the whole file is rejected — the exact file they submitted is retained, unmodified, in the study's protected storage. Weeks later, when someone asks "what exactly was loaded on the 3rd?", the original file can be produced and compared against the events in the system.

**Why this priority**: This is the whole point of the feature and the only part that cannot be reconstructed later. Once a submission is discarded it is gone forever, so preserving the bytes is the irreducible minimum. Delivered alone, it already turns an unauditable operation into an auditable one.

**Independent Test**: Upload a CSV, then confirm a byte-for-byte identical copy exists in the study's archive. Repeat with a file that produces zero successful rows and confirm a copy is retained for that submission too.

**Acceptance Scenarios**:

1. **Given** an administrator on the bulk-import page, **When** they submit a CSV in which every row is valid, **Then** the events are created as before and an unmodified copy of the submitted file is retained.
2. **Given** an administrator on the bulk-import page, **When** they submit a CSV in which some rows fail validation, **Then** the valid rows import as before and the retained copy contains the whole file, including the failing rows.
3. **Given** an administrator on the bulk-import page, **When** they submit a file that is rejected in full (for example, unreadable text or no usable rows), **Then** no events are created and a copy of the rejected submission is still retained.
4. **Given** two administrators submitting files with the same name within the same minute, **When** both submissions complete, **Then** both files are retained and neither overwrites the other.

---

### User Story 2 - The outcome of each import is preserved with the file (Priority: P2)

Alongside each retained file, the system keeps a record of the import it produced: who submitted it, when, the name of the file as submitted, how many events were created, and the exact list of skipped rows with the reason each was skipped. The administrator no longer has to screenshot a notification to keep the error report.

**Why this priority**: The file alone answers "what was submitted"; this answers "what the system did with it". It is separable from P1 — the file is preserved either way — but without it the skipped-row reasons survive only as long as the administrator keeps the result on screen and the page open, which is one of the two problems this feature exists to fix.

**Independent Test**: Submit a CSV with a known mix of valid and invalid rows, then confirm the stored record names the submitter, the submission time, the original file name, the count of created events, and one entry per skipped row matching the reasons shown on screen.

**Acceptance Scenarios**:

1. **Given** a CSV with 10 valid rows and 3 invalid rows, **When** it is submitted, **Then** the stored record shows 10 events created and 3 skipped rows, each with the same reason text the administrator saw on screen.
2. **Given** a submission that created no events, **When** it is stored, **Then** the record shows zero events created and the reason the submission failed.
3. **Given** a stored import record, **When** it is inspected, **Then** it identifies the submitting administrator and the date and time of submission.

---

### User Story 3 - Administrators can review past imports (Priority: P3)

An administrator opens a list of past bulk imports, newest first, showing who submitted each one, when, the file name, and how many events it created. Selecting an entry shows the skipped rows for that import and offers the original file for download.

**Why this priority**: Retrieval makes the archive usable without server access, but the archive has audit value the moment it exists. Deferring this slice does not lose any data — records written by P1 and P2 are equally visible in the list once it ships.

**Independent Test**: Perform two bulk imports, then open the import list and confirm both appear newest-first with correct submitter, time, file name, and event count, and that downloading either entry returns the file originally submitted.

**Acceptance Scenarios**:

1. **Given** several past bulk imports, **When** an administrator opens the import list, **Then** all of them are listed newest-first with submitter, submission time, file name, and count of events created.
2. **Given** a past import with skipped rows, **When** the administrator selects it, **Then** the skipped rows and their reasons are shown.
3. **Given** a past import, **When** the administrator downloads it, **Then** they receive the file exactly as submitted, under a name that identifies the import.
4. **Given** a signed-in user who is not an administrator, **When** they attempt to open the import list or download an archived file, **Then** access is refused.

---

---

### User Story 4 - The import tells the administrator what it is doing and what it did (Priority: P1)

An administrator submits a large CSV. The Add button immediately becomes unavailable and shows that it is working, a spinner appears with a message that the import is running and may take a minute, and a counter shows how long it has been going — so there is never a moment where the page looks inert. When the import finishes, the result stays on screen until the administrator closes it: a headline with the counts, every skipped row on its own line in a scrollable region they can select and copy, a copy-all control, and a link to this submission in the import history. If the system cannot determine the outcome — the response never arrives, or comes back as something it cannot read — it says exactly that and points to the import history, rather than declaring a failure it cannot prove.

**Why this priority**: This is the slice that is actively causing harm today. Administrators re-upload files because nothing tells them the first attempt is running, and they re-upload files because a successful import was announced as a failure. Both produce duplicate events in the study's data, which is a worse outcome than the missing audit trail the rest of this feature addresses. It is independent of Stories 1–3: the file is archived either way, and this changes only what the administrator is shown.

**Independent Test**: Submit a CSV large enough to take several seconds. Confirm the Add button is unavailable and a spinner with an elapsed-time counter is visible for the whole run, that a second submission cannot be started, and that the result remains on screen until dismissed. Separately, force a response the page cannot parse and confirm the result reports an undetermined outcome pointing to the import history, and never the word "failed".

**Acceptance Scenarios**:

1. **Given** an administrator who has chosen a CSV, **When** they submit it, **Then** the Add button becomes unavailable and shows a busy state, and a spinner with an elapsed-time counter appears, for as long as the import is running.
2. **Given** an import that is running, **When** the administrator attempts to submit again, **Then** no second submission is sent.
3. **Given** an import that created 787 events and skipped 13 rows, **When** it finishes, **Then** the result shows both counts and all 13 skipped rows, one per line, and remains on screen until the administrator dismisses it.
4. **Given** a displayed result with skipped rows, **When** the administrator selects its text or uses the copy control, **Then** they obtain the full list of skipped rows as text.
5. **Given** an import whose response cannot be parsed as a result — for example a proxy timeout returning the application's HTML page — **When** the page handles it, **Then** it reports that the outcome could not be determined and links to the import history to confirm, and does not report the import as failed.
6. **Given** an import that genuinely created no events, **When** it finishes, **Then** the result says so and gives the reason, distinguishing it from the undetermined case above.
7. **Given** a displayed result, **When** the administrator dismisses it, **Then** it is removed and does not reappear.

---

### User Story 5 - Buttons are legible everywhere in the application (Priority: P3)

Every button in the application — Add on the bulk-import page and its counterparts on every other page — is clearly distinguishable from the page behind it, with states that make it obvious when a button is focused, hovered, or unavailable.

**Why this priority**: It is a real usability defect and it affects every page, but nobody is losing data over it and no workaround is needed. It is also the most self-contained slice here: a change to the shared button styling, with no change to any page's markup or behavior.

**Independent Test**: Measure the contrast of a button against its page background and of its label against its own fill, on the bulk-import page and on two other pages, and confirm both meet the stated thresholds. Confirm the disabled state is visually distinct, using the Add button during an import.

**Acceptance Scenarios**:

1. **Given** any page with a button, **When** the button's contrast against the page background is measured, **Then** it is at least 3:1.
2. **Given** any button, **When** its label's contrast against the button's own fill is measured, **Then** it is at least 4.5:1.
3. **Given** a button that is unavailable — the Add button during an import — **When** it is displayed, **Then** it is visually distinct from the same button when available.
4. **Given** a button reached by keyboard, **When** it receives focus, **Then** the focus indicator is clearly visible.

### Edge Cases

- **Two files, one name**: Nothing stops administrators from submitting `events.csv` every week. Retained copies must be distinguishable and non-overwriting, including for concurrent submissions.
- **Unreadable file**: A file that cannot be interpreted as text is rejected for import, but the submitted bytes are still what the administrator sent and are retained as-is.
- **Wrong file entirely**: An administrator selects a spreadsheet, an image, or a 200 MB export by mistake. A spreadsheet or image is small enough to retain and will simply fail to parse, so it is archived like any other rejection. A 200 MB export must not be retained — the system cannot be induced to store unbounded data — so its contents are refused, but the attempt is still recorded so the refusal is not invisible.
- **Archive storage unavailable or full**: If the submitted file cannot be retained, the import does not silently proceed — see Assumptions for the chosen behavior.
- **Empty submission**: A zero-byte or blank file produces no events; the submission is still recorded so the attempt is visible.
- **Identifiers in file names**: Administrators may name files after a site or a patient. Stored names and any surfaced list must not create a new, more visible copy of a patient identifier than the file itself already contains.
- **Long-running growth**: Imports accumulate indefinitely. The volume is small, but the list must remain usable after hundreds of entries.
- **Outcome cannot be determined**: The response to a long import may never arrive, or may arrive as something that is not a result at all — the proxy's fallback HTML page after a read timeout is the observed case. The import may well have succeeded. The administrator must be told the outcome is unknown and where to confirm it, never that it failed.
- **Impatient resubmission**: An administrator who sees no feedback submits the same file again. While an import is running, a second submission must not be possible.
- **Navigating away mid-import**: An administrator leaves the page before the import finishes. The import is already in progress on the server and completes regardless; the import history is the record of what happened, since the on-screen result is gone with the page.
- **Every row fails**: A file whose rows all reference unknown patients produces one skipped-row message per row — hundreds of them. The result must stay readable and copyable at that volume rather than collapsing into a single unreadable line.
- **Result competing with other notifications**: A persistent result may still be on screen when another notification appears. Both must remain readable; the persistent one must not be displaced or auto-removed.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST retain an unmodified copy of every file submitted through the bulk-import page, regardless of whether the import succeeded, partially succeeded, or was rejected in full.
- **FR-002**: The system MUST retain each submitted file under a name that is unique across all submissions, so that no submission can overwrite another, including when two submissions carry the same original file name or arrive simultaneously.
- **FR-003**: The system MUST record, for each submission: the submitting user, the date and time of submission, the file name as submitted, the number of events created, and the full list of skipped rows with the reason each was skipped.
- **FR-004**: The system MUST store retained files in the study deployment's writable, non-public storage — the same protected area used for event packets — and MUST NOT place them anywhere reachable without authentication.
- **FR-005**: Retained files and import records MUST be isolated per study deployment; no deployment may read another study's archived submissions.
- **FR-006**: The system MUST refuse submissions larger than a configured size limit before retaining their contents, MUST tell the administrator why the submission was refused, and MUST still record the attempt — submitter, time, file name as submitted, actual size, and the reason — so that a refused submission is visible in the import history even though its contents were not kept.
- **FR-007**: The system MUST NOT write patient identifiers, file contents, or skipped-row detail into routine application logs as a result of this feature.
- **FR-008**: The import's data behavior MUST be unchanged: the same rows import, the same rows are skipped, and each skipped row carries the same reason text as today. Only the presentation of that outcome changes, as stated in FR-013 through FR-019.
- **FR-009**: Administrators MUST be able to view a list of past bulk imports, newest first, showing submitter, submission time, file name, and number of events created.
- **FR-010**: Administrators MUST be able to view the skipped rows and reasons for any past import, and to download the file exactly as it was submitted.
- **FR-011**: Access to the import list, to import records, and to archived files MUST be restricted to administrators — the same audience already permitted to perform bulk imports.
- **FR-012**: Retained files and import records MUST remain available until deliberately removed by an operator; the system MUST NOT delete or overwrite them on its own.
- **FR-013**: While a bulk import is in progress, the system MUST show a continuous indication that it is working — a busy state on the submit control, an indeterminate progress indicator, and elapsed time since submission. It MUST NOT display an estimated percentage of completion, which a synchronous import cannot measure.
- **FR-014**: While a bulk import is in progress, the system MUST prevent a second submission of the same form.
- **FR-015**: The system MUST NOT report a bulk import as failed unless it has a result from the server saying so. A response that is absent, unreadable, or not a recognizable result MUST be reported as an outcome that could not be determined, together with a route to the import history where the true outcome is recorded.
- **FR-016**: The system MUST distinguish, in what it shows the administrator, between: every row imported; some rows imported and some skipped; no rows imported with a stated reason; the submission refused before processing; and the outcome undetermined.
- **FR-017**: Notifications that report a warning or an error MUST remain visible until the administrator dismisses them, MUST offer an explicit dismiss control, and MUST allow their text to be selected and copied. Notifications that report success or routine information MUST continue to dismiss themselves.
- **FR-018**: The result of a bulk import MUST present a summary of the counts followed by each skipped row on its own line in a scrollable region, a control that copies the full result as text, and a link to that submission's record in the import history.
- **FR-019**: The deployment MUST allow a bulk import to run to completion without the request being terminated by an intermediary; the read timeout of any proxy or server in front of the application MUST exceed the time a maximum-size import takes to process.
- **FR-020**: Buttons throughout the application MUST meet WCAG AA contrast — at least 3:1 for the button against the page background and at least 4.5:1 for its label against the button — and MUST have visually distinct hover, keyboard-focus, and unavailable states. This applies to every button in the application, not only those on the bulk-import page.

### Key Entities

- **Archived submission**: The verbatim copy of one uploaded CSV, stored under a unique, time-ordered, non-identifying name. Belongs to exactly one import record.
- **Import record**: The outcome of one bulk-import attempt — submitting user, submission timestamp, original file name, count of events created, and the ordered list of skipped rows with their reasons. References its archived submission and, through the created events, the data it produced.
- **Skipped row entry**: One rejected line from a submission — its position in the file and the reason it was rejected. Carries no more patient data than the reason text already requires.
- **Import outcome, as shown**: What the administrator sees when a submission finishes — one of *all imported*, *partly imported*, *nothing imported*, *refused*, or *undetermined* — carrying the counts, the ordered skipped-row entries, and a reference to the import record. Distinct from the stored import record: the record is always written, whereas the shown outcome may be *undetermined* when the page could not learn what the record says.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of bulk-import submissions that the system attempts to process — successful, partially successful, and rejected — leave a retrievable copy of the submitted file. Submissions refused for size leave a record of the attempt without the contents, so 100% of submissions are accounted for either way.
- **SC-002**: For any past import, an administrator can produce the original submitted file and the list of skipped rows in under 2 minutes, without server or database access and without help from the person who submitted it.
- **SC-003**: A retrieved archived file is byte-for-byte identical to the file the administrator submitted, verified by comparing against the source file.
- **SC-004**: Skipped-row reasons remain available indefinitely after the import, instead of being lost once the administrator dismisses the result or leaves the page.
- **SC-005**: Time to complete a bulk import, as experienced by the administrator, increases by no more than 10% relative to today.
- **SC-006**: No non-administrator can reach an archived submission or import record by any route, verified by attempting access as each other role.
- **SC-007**: The import list remains usable — sorted, readable, and responsive — with at least 500 archived imports present.
- **SC-008**: A bulk import is never reported as failed when it in fact created events. Verified by forcing an unparseable response on an import that succeeds and confirming the administrator is told the outcome is undetermined, not that it failed.
- **SC-009**: From the moment an administrator submits until the result appears, there is no interval longer than 1 second in which the page shows no sign of activity.
- **SC-010**: A duplicate submission cannot be started while an import is running, verified by attempting to submit repeatedly during a slow import and confirming exactly one request is sent.
- **SC-011**: An administrator can read, copy, and screenshot the complete result of an import — including all skipped rows for a file where every row fails — without racing a timer, and the result is removed only when they dismiss it.
- **SC-012**: Every button in the application meets at least 3:1 contrast against its page background and 4.5:1 for its label, verified by measurement on the bulk-import page and on a sample of other pages.
- **SC-013**: A maximum-size bulk import completes without the request being terminated by an intermediary.

## Assumptions

- **Failure to archive blocks the import.** If a submitted file cannot be retained (storage unavailable, full, or read-only), the submission is refused with a clear message and no events are created, rather than importing without an archival copy. A bulk import that leaves no record is the exact situation this feature exists to prevent, and refusing is recoverable — the administrator can retry once storage is fixed — whereas a silent unarchived import is not. Flagged for confirmation: this makes a storage fault block event creation, which is a real operational trade-off.
- **Retention is indefinite.** Archived submissions are small text files; no automatic expiry or purge is defined. Removal is a deliberate operator action, out of scope here.
- **Size limit default.** Submissions above roughly 10 MB have their contents refused. Real bulk imports are a few kilobytes to a few hundred kilobytes; this bound exists to stop a mis-selected file from filling study storage, not to constrain legitimate use. The refusal itself is recorded, so the trade-off is "we did not keep 200 MB", never "nobody can tell this happened".
- **Audience is administrators only.** Bulk import is already restricted to administrators, so the archive inherits that same audience. No new role is introduced.
- **Archived CSVs contain PHI.** They carry site patient identifiers and event dates, so they are treated exactly like event packets: protected storage, authenticated access, never logged.
- **Storage location reuses existing infrastructure.** The archive lives in the deployment's existing writable storage area rather than a new mount, so no deployment or volume changes are required.
- **The import stays synchronous.** The request continues to do the work and return the result, rather than becoming a background job the page polls. This is why no true percentage progress is offered — the server has no way to report partial progress within a single request. Converting to a background job would enable real progress and remove the timeout class entirely; it was considered and rejected here as disproportionate to the problem.
- **The observed "CSV upload failed" was a reporting defect, not an import defect.** The events and criteria in the database confirm the import ran to completion; the page reported failure because it treats any unparseable response as one. The captured response was the application's own HTML shell, which is what an intermediary returns when it gives up on a slow request.
- **The proxy timeout may live outside this repository.** No proxy configuration is present in the repository, so satisfying FR-019 may require a change applied to the deployment rather than a code change. Flagged for confirmation: if the timeout cannot be raised, FR-015's undetermined-outcome reporting is the only protection, and long imports will keep reporting an undetermined outcome despite succeeding.
- **The five stories ship independently.** Retention (P1) is useful with no interface at all; the outcome record (P2) and the review interface (P3) layer on top without changing what P1 wrote; honest import feedback (P1, Story 4) changes only what is shown and nothing that is stored; and the button restyle (P3, Story 5) touches shared styling only.
- **Button restyling is presentation only.** No page's markup, layout, or behavior changes, and no primary/secondary button hierarchy is introduced — one accessible default style is applied to all buttons.

## Out of Scope

- Editing, re-running, or rolling back a past import.
- Linking an individual created event back to the import that produced it.
- Automatic purging, archiving to cold storage, or retention policy enforcement.
- Extending archival to other uploads (event packets and scrubbed charts are already retained by existing behavior).
- Any change to CSV format, parsing rules, or validation messages. The wording of a skipped-row reason is unchanged; only how those reasons are displayed changes.
- Converting bulk import to an asynchronous background job, and the true row-by-row percentage progress that would enable.
- Any broader visual redesign: only button styling changes, and only as needed to meet the stated contrast and state requirements.
- Resuming or reattaching to an import after the administrator navigates away from the page.
