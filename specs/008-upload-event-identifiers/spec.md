# Feature Specification: Populate event identifiers on the upload page

**Feature Branch**: `008-upload-event-identifiers`
**Created**: 2026-08-13
**Status**: Draft
**Input**: User description: "At /events/upload?event_id=[n] these are all empty, but they should be populated: PatientID, Date, Criteria. The SitePatientID should be displayed here, too. All of the above items are needed so users can cross-reference fields to ensure that they upload the correct packet."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Uploader verifies the event before uploading a packet (Priority: P1)

An uploader opens the packet-upload page for a specific event. Before selecting a file, they need to confirm that the event on screen is the same event the packet in front of them belongs to. The page shows the event's identifying details — internal Patient ID, Site Patient ID, event date, and the criteria that flagged the event — so the uploader can compare them against the packet's cover sheet and chart. Only after that comparison do they attach the file.

**Why this priority**: This is the entire purpose of the request. Without these values the uploader is attaching a packet to an event they cannot verify, which risks associating a patient's chart with the wrong event — a data-integrity and privacy problem that is expensive to detect and correct downstream.

**Independent Test**: Navigate directly to the upload page for a known event ID and confirm all four identifying values render with the correct data for that event, without having arrived from any particular list page.

**Acceptance Scenarios**:

1. **Given** an uploader opens the upload page for an event that exists, **When** the page finishes loading, **Then** the event's internal Patient ID, Site Patient ID, event date, and criteria are all displayed with values drawn from that event's stored record.
2. **Given** an uploader opens the upload page for an event, **When** they compare the displayed Site Patient ID and event date to the packet in hand, **Then** the displayed values match the same event's values shown anywhere else in the application (event list, edit page, screening page).
3. **Given** an event with no criteria recorded against it, **When** the page loads, **Then** the criteria field renders a neutral placeholder, the other three identifying values still display normally, and the uploader may proceed with the upload.
4. **Given** an event with several criteria, **When** the page loads, **Then** each criterion is shown with both its name and its recorded value, so the uploader can check the measurement against the chart.

---

### User Story 2 - Identifiers are correct regardless of how the uploader arrived (Priority: P1)

Uploaders reach the upload page several ways: by pressing the "upload" action button on a row in the events-needing-packets list, by clicking the row itself, by following a link from the reupload list, and by opening a bookmarked or pasted URL. The identifying details must be present and correct in every one of these cases.

**Why this priority**: Same criticality as Story 1, and it is the reason the values are currently missing. The values today depend on what the originating link happened to carry, so the same event shows different information depending on the route taken to it. A verification aid that is only sometimes present cannot be relied upon, and an uploader who has learned to trust it is more at risk than one who never had it.

**Independent Test**: Reach the upload page for the same event by each entry route in turn and confirm the four identifying values are identical and correct every time.

**Acceptance Scenarios**:

1. **Given** an uploader on the events-needing-packets list, **When** they press the "upload" action button on a row, **Then** the upload page shows that event's identifying details in full.
2. **Given** an uploader on the events-needing-packets list, **When** they click the row body rather than the action button, **Then** the upload page shows the same identifying details as the action button produces.
3. **Given** an uploader following a link from the reupload list, **When** the upload page opens, **Then** the identifying details are shown in full.
4. **Given** an uploader who bookmarked or was sent an upload URL containing only the event identifier, **When** they open it, **Then** the identifying details are shown in full.
5. **Given** two uploaders open the same event through two different routes, **When** both pages are loaded, **Then** both display the same values for all four fields.

---

### User Story 3 - The page behaves predictably when the event cannot be shown (Priority: P2)

An uploader opens an upload URL whose event identifier does not correspond to an event they can see — it was mistyped, the event was removed, or their account lacks access. The page tells them plainly that the event could not be loaded, and does not offer to accept a file, instead of presenting an upload form with empty identifying fields.

**Why this priority**: Lower frequency than the primary flow, but it protects the guarantee the feature exists to provide. An upload form showing blank identifiers is precisely the state this feature is meant to eliminate, and it must not reappear as an unhandled error case. Because the identifying values are guaranteed to exist for any real event, their absence always signals that the event on screen is not the event the uploader thinks it is.

**Independent Test**: Open the upload page with a nonexistent event identifier and confirm the page states the problem, renders no empty identifying fields, and does not accept a file.

**Acceptance Scenarios**:

1. **Given** an upload URL naming an event that does not exist, **When** the page loads, **Then** the uploader sees a clear message that the event could not be found, and no identifying fields are shown as empty.
2. **Given** an upload URL naming an event the user is not permitted to view, **When** the page loads, **Then** the uploader sees a message consistent with how the rest of the application reports insufficient access, and is not shown partial event data.
3. **Given** the identifying details cannot be retrieved because of a temporary failure, **When** the page loads, **Then** the uploader is told the details are unavailable rather than being shown blank fields.
4. **Given** any of the above states, **When** the uploader looks for a way to attach a packet, **Then** submitting a file is not available to them, so no packet can be attached to an event that could not be verified.
5. **Given** a temporary failure that later clears, **When** the uploader retries, **Then** the identifying details display and the upload becomes available without further intervention.

---

### Edge Cases

- An event has no criteria recorded at all — the criteria field shows a neutral placeholder, not an empty line. This is a legitimate state, because criteria are optional; it must not block the upload or be presented as an error.
- An event has several criteria — all are shown with their values, in a stable and repeatable order, so two uploaders comparing the same event see the same text.
- A criterion has a name but no recorded value — the name is still shown, with a neutral placeholder in place of the value.
- A retrievable event is missing one of the three required identifying values — an anomaly, since all three are enforced upstream before an event can be created. The page treats this as a failure to verify the event rather than as an ordinary empty field, and does not accept a packet for it.
- The upload URL carries stale identifying values left over from an older link while the stored record has since changed — the page displays the stored record's current values, never the values carried in the URL.
- The upload URL carries identifying values that contradict the stored record — the stored record wins, with no silent merge of the two sources.
- The event identifier is absent from the URL entirely — the page continues to present the list of events needing packets, as it does today.
- An uploader leaves the page open for an extended period before submitting — the displayed identifiers remain those of the event named in the URL and do not silently change.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: When the upload page is opened for a specific event, the system MUST display that event's internal Patient ID, Site Patient ID, event date, and criteria.
- **FR-002**: The system MUST source all four displayed values from the event's stored record, identified solely by the event identifier in the address.
- **FR-003**: The system MUST NOT depend on identifying values carried in the page address to populate the display; where such values are present they MUST be disregarded in favour of the stored record.
- **FR-004**: The system MUST display the same values for a given event no matter which navigation route was used to reach the upload page.
- **FR-005**: The system MUST label the internal Patient ID and the Site Patient ID distinctly, so an uploader can tell which identifier they are cross-referencing against the packet.
- **FR-006**: The system MUST treat the internal Patient ID, Site Patient ID, and event date as always present for a retrievable event, since all three are required before an event can be created. Their absence MUST be handled as a failure to verify the event (FR-010) rather than as an ordinary empty field.
- **FR-006a**: The system MUST render a neutral placeholder when an event has no criteria, and MUST NOT render an empty value, an absent label, or a placeholder derived from missing data such as "undefined".
- **FR-006b**: The system MUST NOT prevent or discourage an upload solely because an event has no criteria recorded, as criteria are optional.
- **FR-007**: The system MUST present the event date in the same format used elsewhere in the application for event dates, so values can be compared across pages without reinterpretation.
- **FR-008**: The system MUST display every criterion recorded against the event as a name-and-value pair, in a stable order that does not vary between loads.
- **FR-009**: The system MUST display the identifying details before the uploader can submit a file, so the cross-reference can be performed as part of the upload task rather than after it.
- **FR-010**: When the named event cannot be retrieved — because it does not exist, is not accessible to the user, or retrieval failed — the system MUST tell the uploader that the event details are unavailable, and MUST NOT present the identifying fields as empty.
- **FR-011**: The system MUST NOT accept a packet for an event whose identifying details could not be retrieved or displayed. Because the three required identifiers always exist for a real event, an inability to show them means the event cannot be verified, and no cross-reference is possible.
- **FR-011a**: When a failure to retrieve the details is temporary, the system MUST allow the uploader to retry and MUST restore the upload capability once the details display, without requiring them to start over elsewhere.
- **FR-012**: The system MUST show each criterion's recorded value alongside its name, so the uploader can cross-reference the measurement against the chart and not only the criterion's label.
- **FR-013**: The system MUST keep the existing behaviour of the upload page when no event is named in the address — the list of events needing packets continues to be shown.

### Key Entities *(include if data involved)*

- **Event**: The clinical event a packet is being uploaded for. Identified by an event identifier that appears in the page address. Carries the event date, a link to the patient it belongs to, and its position in the review workflow. The event date is mandatory at creation.
- **Patient**: The subject of the event. Carries two distinct identifiers relevant here — an internal identifier used to relate records within the application, and a Site Patient ID assigned by the clinical site, which is the identifier an uploader will find on the packet itself. Both are mandatory before an event can be created, so both are always available for a real event.
- **Criterion**: An ascertainment rule that flagged the event for review. Each has a name and an associated value. Criteria are optional — an event may have none, one, or several — so their absence is a normal state rather than an error.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For any event reachable from the upload page, all four identifying values are populated from the stored record in 100% of page loads where the event exists and the user may view it.
- **SC-002**: The four identifying values are identical across every navigation route into the upload page, verified for all four routes currently in use.
- **SC-003**: Zero occurrences of a blank, absent, or "undefined" identifying field on an upload page for an event that exists.
- **SC-003a**: Zero packets are accepted for an event whose identifying details were not displayed to the uploader.
- **SC-003b**: Events with no criteria recorded remain fully uploadable — no measurable drop in upload completion for those events relative to events that have criteria.
- **SC-004**: An uploader can complete the cross-reference between the on-screen details and the packet in hand without navigating away from the upload page.
- **SC-005**: The identifying details are visible to the uploader within the time it takes the rest of the upload page to become usable, so verification adds no perceptible wait to the upload task.
- **SC-006**: Packets attached to the wrong event, as reported through the existing rescrub and reject paths, decline relative to the rate recorded before this change.

## Assumptions

- The four values requested are those already recorded against the event and its patient; this feature displays existing data and introduces no new data collection.
- "PatientID" in the request refers to the application's internal patient identifier, and "SitePatientID" to the clinical site's own identifier for the same patient. The request asks for both to be visible because they serve different cross-referencing purposes.
- "Date" refers to the event date, the value labelled "Date" on the events lists — not the date the event record was created or the date of upload.
- "Criteria" refers to the ascertainment criteria that flagged the event, the same set summarised in the "Criteria" column of the events lists. The upload page shows their values as well as their names, so it deliberately carries more detail than that column.
- The internal Patient ID, Site Patient ID, and event date are mandatory upstream — an event cannot be created without them. The specification therefore treats them as guaranteed present for any retrievable event, and treats their absence as a verification failure rather than as missing-but-acceptable data.
- Criteria are optional by contrast, so an event with none is ordinary and must remain uploadable.
- The clinical site is not among the fields requested and is therefore out of scope, even though it is displayed on comparable pages elsewhere in the application.
- The reupload list links to the upload page rather than duplicating it, so correcting the upload page covers uploaders arriving from reupload without separate work there.
- The legacy VTE-specific copies of these pages are out of scope. Per standing project guidance the VTE fork is not to be extended, and new work targets the shared pages only.
- Existing access rules for viewing an event are unchanged; this feature displays details only to users already entitled to see that event, and adds no new exposure of patient identifiers.
- The neutral placeholder for absent criteria follows the convention already used on the screening and scrubbing pages, so the pages read consistently.
- No change to how packets are stored, validated, or processed after upload is intended; the change is confined to what the uploader is shown before submitting.
