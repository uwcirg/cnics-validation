# Feature Specification: Archive Bulk-Import CSV Files

**Feature Branch**: `009-save-import-csv`
**Created**: 2026-08-13
**Status**: Draft
**Input**: User description: "for the CSV files uploaded at /events/addMany, save them per your last comment here."

## Context

Administrators create events in bulk by uploading a CSV file on the "Add multiple events from a CSV file" page. Today that file is read, parsed, and discarded: the only lasting trace of a bulk import is the events it created. Nothing records what was submitted, who submitted it, when, or which rows were skipped and why. The list of skipped rows is shown once in an on-screen notification and is gone as soon as the administrator navigates away.

That gap has practical consequences. If a batch imports with the wrong dates or the wrong criteria, there is no submitted file to compare the created events against — only the events themselves and whatever copy the administrator still happens to have on their own machine. If a submission is rejected outright, the reasons are unrecoverable. This feature preserves each submitted file and the outcome of importing it, so that a bulk import can be audited and reconstructed after the fact.

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

**Why this priority**: The file alone answers "what was submitted"; this answers "what the system did with it". It is separable from P1 — the file is preserved either way — but without it the skipped-row reasons still evaporate when the notification closes, which is one of the two problems this feature exists to fix.

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

### Edge Cases

- **Two files, one name**: Nothing stops administrators from submitting `events.csv` every week. Retained copies must be distinguishable and non-overwriting, including for concurrent submissions.
- **Unreadable file**: A file that cannot be interpreted as text is rejected for import, but the submitted bytes are still what the administrator sent and are retained as-is.
- **Wrong file entirely**: An administrator selects a spreadsheet, an image, or a 200 MB export by mistake. A spreadsheet or image is small enough to retain and will simply fail to parse, so it is archived like any other rejection. A 200 MB export must not be retained — the system cannot be induced to store unbounded data — so its contents are refused, but the attempt is still recorded so the refusal is not invisible.
- **Archive storage unavailable or full**: If the submitted file cannot be retained, the import does not silently proceed — see Assumptions for the chosen behavior.
- **Empty submission**: A zero-byte or blank file produces no events; the submission is still recorded so the attempt is visible.
- **Identifiers in file names**: Administrators may name files after a site or a patient. Stored names and any surfaced list must not create a new, more visible copy of a patient identifier than the file itself already contains.
- **Long-running growth**: Imports accumulate indefinitely. The volume is small, but the list must remain usable after hundreds of entries.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST retain an unmodified copy of every file submitted through the bulk-import page, regardless of whether the import succeeded, partially succeeded, or was rejected in full.
- **FR-002**: The system MUST retain each submitted file under a name that is unique across all submissions, so that no submission can overwrite another, including when two submissions carry the same original file name or arrive simultaneously.
- **FR-003**: The system MUST record, for each submission: the submitting user, the date and time of submission, the file name as submitted, the number of events created, and the full list of skipped rows with the reason each was skipped.
- **FR-004**: The system MUST store retained files in the study deployment's writable, non-public storage — the same protected area used for event packets — and MUST NOT place them anywhere reachable without authentication.
- **FR-005**: Retained files and import records MUST be isolated per study deployment; no deployment may read another study's archived submissions.
- **FR-006**: The system MUST refuse submissions larger than a configured size limit before retaining their contents, MUST tell the administrator why the submission was refused, and MUST still record the attempt — submitter, time, file name as submitted, actual size, and the reason — so that a refused submission is visible in the import history even though its contents were not kept.
- **FR-007**: The system MUST NOT write patient identifiers, file contents, or skipped-row detail into routine application logs as a result of this feature.
- **FR-008**: The import behavior visible to administrators today MUST be unchanged in every respect other than those stated here: the same rows import, the same rows are skipped, and the same on-screen result is shown.
- **FR-009**: Administrators MUST be able to view a list of past bulk imports, newest first, showing submitter, submission time, file name, and number of events created.
- **FR-010**: Administrators MUST be able to view the skipped rows and reasons for any past import, and to download the file exactly as it was submitted.
- **FR-011**: Access to the import list, to import records, and to archived files MUST be restricted to administrators — the same audience already permitted to perform bulk imports.
- **FR-012**: Retained files and import records MUST remain available until deliberately removed by an operator; the system MUST NOT delete or overwrite them on its own.

### Key Entities

- **Archived submission**: The verbatim copy of one uploaded CSV, stored under a unique, time-ordered, non-identifying name. Belongs to exactly one import record.
- **Import record**: The outcome of one bulk-import attempt — submitting user, submission timestamp, original file name, count of events created, and the ordered list of skipped rows with their reasons. References its archived submission and, through the created events, the data it produced.
- **Skipped row entry**: One rejected line from a submission — its position in the file and the reason it was rejected. Carries no more patient data than the reason text already requires.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of bulk-import submissions that the system attempts to process — successful, partially successful, and rejected — leave a retrievable copy of the submitted file. Submissions refused for size leave a record of the attempt without the contents, so 100% of submissions are accounted for either way.
- **SC-002**: For any past import, an administrator can produce the original submitted file and the list of skipped rows in under 2 minutes, without server or database access and without help from the person who submitted it.
- **SC-003**: A retrieved archived file is byte-for-byte identical to the file the administrator submitted, verified by comparing against the source file.
- **SC-004**: Skipped-row reasons remain available indefinitely after the import, instead of being lost when the on-screen notification closes.
- **SC-005**: Time to complete a bulk import, as experienced by the administrator, increases by no more than 10% relative to today.
- **SC-006**: No non-administrator can reach an archived submission or import record by any route, verified by attempting access as each other role.
- **SC-007**: The import list remains usable — sorted, readable, and responsive — with at least 500 archived imports present.

## Assumptions

- **Failure to archive blocks the import.** If a submitted file cannot be retained (storage unavailable, full, or read-only), the submission is refused with a clear message and no events are created, rather than importing without an archival copy. A bulk import that leaves no record is the exact situation this feature exists to prevent, and refusing is recoverable — the administrator can retry once storage is fixed — whereas a silent unarchived import is not. Flagged for confirmation: this makes a storage fault block event creation, which is a real operational trade-off.
- **Retention is indefinite.** Archived submissions are small text files; no automatic expiry or purge is defined. Removal is a deliberate operator action, out of scope here.
- **Size limit default.** Submissions above roughly 10 MB have their contents refused. Real bulk imports are a few kilobytes to a few hundred kilobytes; this bound exists to stop a mis-selected file from filling study storage, not to constrain legitimate use. The refusal itself is recorded, so the trade-off is "we did not keep 200 MB", never "nobody can tell this happened".
- **Audience is administrators only.** Bulk import is already restricted to administrators, so the archive inherits that same audience. No new role is introduced.
- **Archived CSVs contain PHI.** They carry site patient identifiers and event dates, so they are treated exactly like event packets: protected storage, authenticated access, never logged.
- **Storage location reuses existing infrastructure.** The archive lives in the deployment's existing writable storage area rather than a new mount, so no deployment or volume changes are required.
- **The three stories ship independently.** Retention (P1) is useful with no interface at all; the outcome record (P2) and the review interface (P3) layer on top without changing what P1 wrote.

## Out of Scope

- Editing, re-running, or rolling back a past import.
- Linking an individual created event back to the import that produced it.
- Automatic purging, archiving to cold storage, or retention policy enforcement.
- Extending archival to other uploads (event packets and scrubbed charts are already retained by existing behavior).
- Any change to CSV format, parsing rules, or validation messages.
