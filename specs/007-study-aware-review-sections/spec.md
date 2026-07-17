# Feature Specification: Study-aware home review sections

**Feature Branch**: `007-study-aware-review-sections`  
**Created**: 2026-06-05  
**Status**: Draft  
**Input**: User description: "The two home-page infoboxes near the bottom — 'Review packets should contain:' and 'Review Instructions:' — should be configurable per STUDY_TYPE. The h3 headers stay constant. For some study types there will be .doc/.pdf files in the repo linked from these boxes (specific to the study type). The current content is appropriate for 'mci'. For 'scans', the 'Review packets should contain:' box should list 'DEXA scan reports' and 'Please redact their PHI (names, birthdate, etc)', and 'Review Instructions:' should say 'No additional instructions'."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Scans reviewer sees DEXA-appropriate packet guidance (Priority: P1)

A reviewer working on a deployment configured for the `scans` study opens the home page. The two guidance boxes near the bottom of the page show content appropriate to scans review — not the cardiology/MI packet checklist that belongs to the `mci` study.

**Why this priority**: This is the concrete problem the feature exists to solve. Today every deployment shows MI-specific guidance regardless of study type, which is wrong and confusing for scans reviewers. Delivering correct scans content is the minimum viable outcome.

**Independent Test**: Configure a deployment with study type `scans`, open the home page, and confirm the "Review packets should contain:" box lists the DEXA scan items and the "Review Instructions:" box reads "No additional instructions" — with no MI file links present.

**Acceptance Scenarios**:

1. **Given** a deployment configured for study type `scans`, **When** a user views the home page, **Then** the "Review packets should contain:" box shows the items "DEXA scan reports" and "Please redact their PHI (names, birthdate, etc)".
2. **Given** a deployment configured for study type `scans`, **When** a user views the home page, **Then** the "Review Instructions:" box shows "No additional instructions".
3. **Given** a deployment configured for study type `scans`, **When** a user views either box, **Then** no `.doc`/`.pdf` links are shown (scans defines no linked files).
4. **Given** any study type, **When** a user views the home page, **Then** the two box headers still read exactly "Review packets should contain:" and "Review Instructions:".

---

### User Story 2 - MCI reviewer keeps existing guidance (Priority: P1)

A reviewer on a deployment configured for the `mci` study opens the home page and sees the same packet checklist and reviewer-instruction links they see today, unchanged.

**Why this priority**: The feature must not regress the existing, correct experience for the primary study. Equal-priority with Story 1 because shipping scans content while breaking mci content would be a net loss.

**Independent Test**: Configure (or default to) study type `mci`, open the home page, and confirm both boxes match the current production content, including the `.doc`/`.pdf` download links.

**Acceptance Scenarios**:

1. **Given** a deployment configured for study type `mci`, **When** a user views the home page, **Then** the "Review packets should contain:" box shows the existing eight-item checklist (physician's notes, cardiology consultations, ECGs, related procedures, laboratory evidence, redaction reminder).
2. **Given** a deployment configured for study type `mci`, **When** a user views the "Review packets should contain:" box, **Then** the "Full instructions" `.doc` and `.pdf` links are present and point to the MI packet-assembly instruction files.
3. **Given** a deployment configured for study type `mci`, **When** a user views the "Review Instructions:" box, **Then** the `.doc` and `.pdf` links are present and point to the MI reviewer-instruction files.

---

### User Story 3 - Operator adds guidance for a new study (Priority: P2)

An operator standing up a deployment for a study other than `mci` or `scans` can define the packet and instruction content (text items and any linked `.doc`/`.pdf` files) for that study without changing the page layout or the box headers.

**Why this priority**: The feature's stated intent is that these boxes are "configurable per STUDY_TYPE," implying extensibility beyond the two named studies. Valuable, but not required to solve the immediate scans problem, so it follows the two P1 stories.

**Independent Test**: Define content for a third study type, configure a deployment with that study type, and confirm the home page renders the defined items and links under the unchanged headers.

**Acceptance Scenarios**:

1. **Given** content has been defined for a new study type, **When** a deployment is configured with that study type, **Then** the home page boxes render that study's items and links.
2. **Given** a new study type defines linked files, **When** a user activates a link, **Then** the corresponding study-specific `.doc`/`.pdf` file is downloaded or opened.

---

### Edge Cases

- **Unrecognized or unset study type**: When the deployment's study type has no defined content, the boxes fall back to the default (`mci`) content so reviewers always see usable guidance rather than empty boxes. (See Assumptions.)
- **A study defines text items but no linked files**: The box shows the items and omits the links entirely — no empty "Full instructions:" / "View as:" label with no targets. (`scans` is exactly this case.)
- **A study defines linked files but the file is missing from the repo**: The link is still shown; activating a missing file yields the deployment's standard not-found response. Ensuring referenced files exist is an operator/deployment responsibility, not a runtime guarantee of this feature.
- **Study type resolves after initial page render**: While the study type is still being resolved, the page must not flash one study's content and then swap to another's; it should settle on the correct study's content without showing another study's guidance.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The home page MUST render two guidance boxes near the bottom of the page whose headers are exactly "Review packets should contain:" and "Review Instructions:", and these headers MUST remain identical across all study types.
- **FR-002**: The body content of each box (the list of items and any free text) MUST be determined by the deployment's configured study type.
- **FR-003**: Each box MUST be able to optionally display one or more downloadable/viewable file links (e.g. `.doc` and `.pdf`) that are specific to the configured study type.
- **FR-004**: When the configured study type defines no file links for a box, that box MUST omit the link area entirely (no orphaned "Full instructions:" / "View as:" label).
- **FR-005**: For study type `mci`, the "Review packets should contain:" box MUST display the existing eight-item checklist and the existing "Full instructions" `.doc`/`.pdf` links, and the "Review Instructions:" box MUST display the existing reviewer-instruction `.doc`/`.pdf` links — matching current behavior with no visible change.
- **FR-006**: For study type `scans`, the "Review packets should contain:" box MUST display the items "DEXA scan reports" and "Please redact their PHI (names, birthdate, etc)", and MUST display no file links.
- **FR-007**: For study type `scans`, the "Review Instructions:" box MUST display the text "No additional instructions", and MUST display no file links.
- **FR-008**: When the configured study type has no defined content for these boxes, the system MUST fall back to the default (`mci`) content rather than render empty boxes.
- **FR-009**: Adding content for an additional study type MUST NOT require changing the box headers, the page layout, or the content of unrelated study types.
- **FR-010**: The page MUST settle on the correct study's content without transiently displaying a different study's guidance.

### Key Entities *(include if feature involves data)*

- **Study review-guidance content**: The per-study definition of what the two home-page boxes show. For each study type it carries, per box, an ordered set of display items (text) and an optional set of file links (label + target file). `mci` and `scans` are the initially defined studies; `mci` also serves as the fallback default.
- **Study type**: The deployment's existing configuration value (e.g. `mci`, `scans`) that selects which guidance content applies. Reused from the existing deployment configuration; this feature does not introduce a new selector.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a `scans` deployment, 100% of home-page loads show the DEXA packet items and "No additional instructions", and zero MI-specific items or MI file links.
- **SC-002**: On an `mci` deployment, the two boxes are visually identical to the pre-feature home page (same items, same links), confirmed by side-by-side comparison.
- **SC-003**: The two box headers read "Review packets should contain:" and "Review Instructions:" on every study type, with no variation.
- **SC-004**: Defining the guidance for one additional study type requires changes only to that study's content definition — no edits to headers, layout, or other studies' content.
- **SC-005**: A reviewer can identify the correct guidance for their study without encountering another study's content at any point during page load.

## Assumptions

- **Study-type source is reuse**: The configured study type is the existing deployment value already surfaced to the home page; this feature does not add a new configuration mechanism, only new per-study content keyed off it.
- **Fallback is `mci`**: An unrecognized or unset study type falls back to the `mci` content. This mirrors the system-wide default study type and guarantees reviewers always see usable guidance. (If a blank/empty box is preferred for unknown studies, this assumption should be revisited.)
- **`scans` has no linked files**: The `scans` study intentionally defines no `.doc`/`.pdf` links for either box; the user-provided content is the complete content for scans.
- **Item formatting follows the existing box style**: New study items render in the same list style currently used in the "Review packets should contain:" box; exact ordered-vs-bulleted presentation is a presentation detail, not a requirement.
- **File hosting is unchanged**: Study-specific `.doc`/`.pdf` files are served from the existing files location used by the current MI links; this feature adds per-study link targets, not a new file-serving path.
- **Operator responsibility for files**: Ensuring that any `.doc`/`.pdf` referenced by a study's content actually exists in the repo/deployment is an operator responsibility; the feature does not validate file presence at runtime.
- **No new roles or permissions**: Visibility of these boxes follows the current home page (shown to home-page viewers as today); this feature changes content, not who can see it.
