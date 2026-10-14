# Feature Specification: DEXA scan review form and on-site reviewer instructions

**Feature Branch**: `012-dexa-review-form`  
**Created**: 2026-10-14  
**Status**: Draft  
**Input**: User description: "Add the review web form for DEXA scans, modeled on the (older-codebase) MCI review form but simpler. Step 1: Review Charts (download link for the event's packet). Step 2: Enter Body Region from a fixed dropdown (Spine, Lumbar spine, Thoracic spine, Cervical spine, Forearm/radius, Hip, Proximal femur, Femoral neck, Wrist, Other: please specify). Once a region is chosen, show Laterality (Left, Right, Both, Unspecified — optional), T score and Z score (typed decimal numbers, may be negative, may be blank). Step 3: Is there another body region? If not, click Submit. Regions may repeat; plan for up to twice the 4 regions seen so far. Also: instead of the MCI system's .doc/.pdf instruction downloads, display the reviewer instructions in the site itself, print-friendly, perhaps in a new tab — across all instance types. Instructions source: DEXA.scans.review.instructions.md; UI reference: mci.review.Screenshot.1/2.2026-10-14."

## Clarifications

### Session 2026-10-14

- Q: For studies that today link `.doc`/`.pdf` reviewer-instruction files (`mci`), does Part B convert them? → A: `mci` uses the new on-site instructions page mechanism too, but its content is left unpopulated for now (the MCI form, and so its instructions, will likely change if MCI is implemented in this software). The links to the MCI reviewer-instruction `.doc`/`.pdf` files are removed.
- Q: How is DEXA review data stored, given the existing `reviews` table is MI-shaped? → A: As in the legacy system, every study has a table named `reviews` whose schema is specific to that study (each deployment has its own database). `scans` defines its own `reviews` schema; the MI schema stays as `mci`'s.
- Q: What format are the on-site instructions kept in? → A: HTML (converted once from the source document); Markdown is not kept.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reviewer records the body regions and scores from a DEXA packet (Priority: P1)

A reviewer on a `scans` (DEXA) deployment opens an event assigned to them. The
page heading identifies the event, and Step 1 offers the event's packet for
download. The reviewer reads the DEXA report, then in Step 2 picks the first
body region reported (e.g. "Lumbar spine"). As soon as a region is picked, three
inputs for that region appear: Laterality (optional), T score, and Z score. The
reviewer fills in what the report gives. Step 3 asks whether there is another
body region; the reviewer picks the next region (e.g. "Hip"), and a fresh set of
Laterality / T score / Z score inputs appears for it. They repeat until every
region in the report is recorded, then click Submit. The review is saved with
one entry per region, and the event completes (single-reviewer study).

**Why this priority**: This is the feature. Today a `scans` deployment shows the
MI decision form, which cannot capture DEXA data at all, so no DEXA review can
be recorded. Delivering this alone makes the `scans` study usable end to end.

**Independent Test**: On a `scans` deployment, open an assigned event's review
page, enter three regions (one with laterality, one without, one with a T score
but no Z score), submit, and confirm that exactly three region entries are
stored for that event with the values entered, and that the event has advanced
to done.

**Acceptance Scenarios**:

1. **Given** a `scans` deployment and an event assigned to the reviewer, **When**
   the reviewer opens its review page, **Then** they see "Step 1: Review Charts"
   with a link to download the packet for that event, and "Step 2: Enter Body
   Region" with a region dropdown — and none of the MI decision questions.
2. **Given** the review page with no region chosen yet, **When** the reviewer
   picks a region from the dropdown, **Then** Laterality, T score, and Z score
   inputs appear, visibly associated with that region.
3. **Given** the reviewer picks "Other: please specify", **When** the region's
   inputs appear, **Then** a free-text box for the region name also appears, and
   it must be filled before the review can be submitted.
4. **Given** at least one region has been entered, **When** the reviewer looks
   below it, **Then** they see "Step 3: Is there another body region? If not,
   click 'Submit'" with another region dropdown and a Submit button.
5. **Given** the reviewer picks a region in Step 3, **When** it is chosen,
   **Then** a new set of Laterality / T score / Z score inputs appears for it,
   and a further "another body region?" prompt appears beneath.
6. **Given** the reviewer has already entered "Wrist", **When** they choose
   "Wrist" again for the next region, **Then** it is accepted as a separate
   entry (e.g. one Left, one Right).
7. **Given** a region with Laterality left blank and Z score left blank,
   **When** the reviewer submits, **Then** the submission succeeds and those
   values are stored as "not provided" (not as zero).
8. **Given** a filled-in form, **When** the reviewer clicks Submit, **Then** the
   review is saved, the reviewer sees a confirmation, and the event advances to
   done.

---

### User Story 2 - Reviewer corrects entries before submitting (Priority: P2)

While entering regions, the reviewer realises they picked the wrong region or
recorded a region the report does not actually contain. They change the region
on that entry, or remove the entry entirely, without losing the other entries
they have already typed.

**Why this priority**: Without correction, a single mis-click forces the reviewer
to reload and re-enter everything, or to submit wrong data. It is not needed to
record a correct review on the first try, so it ranks below Story 1.

**Independent Test**: Enter three regions, change the region of the second,
remove the first, submit, and confirm exactly two entries are stored with the
corrected values and in the remaining order.

**Acceptance Scenarios**:

1. **Given** several entered regions, **When** the reviewer changes the region
   on one entry, **Then** that entry's laterality and scores are kept and only
   its region changes.
2. **Given** several entered regions, **When** the reviewer removes one entry,
   **Then** that entry disappears and every other entry keeps its values.
3. **Given** a typed score that is not a number (e.g. "1..2", "abc"), **When**
   the reviewer tries to submit, **Then** submission is blocked and that
   specific field is flagged with a message saying what is expected.

---

### User Story 3 - Reviewer reads the review instructions on the site, and can print them (Priority: P2)

A reviewer wants to check the DEXA instructions (e.g. "use Spine when the spine
location isn't specified", "record L1–L2 as Lumbar spine") while reviewing.
From the review page — and from the home page's "Review Instructions:" box —
they open the instructions, which display as a readable page in a new browser
tab, so the review form stays open alongside. If they print that page, they get
just the instructions — no navigation menus, banners, or buttons.

**Why this priority**: The rules on that page decide how ambiguous reports are
coded, so reviewers need them at hand; but a reviewer who already knows the
rules can complete reviews without it.

**Independent Test**: On a `scans` deployment, follow the instructions link from
the review page and from the home page; confirm a new tab opens showing the full
DEXA instructions text, and that the browser's print preview shows only the
instructions content.

**Acceptance Scenarios**:

1. **Given** a `scans` deployment, **When** a reviewer is on the review page,
   **Then** a "Review Instructions:" box offers a link to view the instructions
   (no `.doc` / `.pdf` download links).
6. **Given** an `mci` deployment, **When** a user views the home page or a
   review page, **Then** no links to the MCI reviewer-instruction `.doc` / `.pdf`
   files appear, and no instructions link appears (no `mci` content exists yet).
2. **Given** a `scans` deployment, **When** a user views the home page, **Then**
   the "Review Instructions:" box links to the same instructions page, instead of
   reading "No additional instructions".
3. **Given** the instructions link, **When** it is followed, **Then** the
   instructions open in a new tab, and the original page (including any
   unsubmitted form entries) is undisturbed.
4. **Given** the instructions page, **When** it is printed, **Then** the printed
   output contains the instruction content only, with no site navigation,
   header banner, or interactive controls, and with legible text that fits the
   page width.
5. **Given** the instructions page, **When** its content is compared with the
   study's source instructions document, **Then** every numbered step and note
   is present and in the same order.

---

### Edge Cases

- **Many regions**: the largest packet seen so far has 4 regions. The form must
  comfortably take at least 8; entering an 8th region must not degrade the page.
  No hard upper cap is required, but the form should remain usable past 8.
- **No regions entered**: Submit is not offered until at least one region has
  been chosen (a DEXA report always reports at least one region; see
  Assumptions).
- **Region chosen, all three inputs blank**: allowed — the reviewer recorded that
  the region is present but the report gave no laterality or scores. Stored as
  region-only.
- **Score formats**: "-1.1", "2.0", "0", "+0.5", "-0.4", ".5", and "2" are all
  accepted as numbers. Leading/trailing whitespace is ignored. Values outside a
  plausible range (below -10 or above 10) are rejected as a likely typo, as
  are values with more than 2 decimal places (e.g. "1.234").
- **"Other" with blank text**: not submittable; the free-text box is flagged.
- **Abandoning an empty trailing prompt**: after the last region, the empty
  "another body region?" dropdown left unselected is not a region and is not
  stored.
- **Double submission**: a second click on Submit while the first is in flight
  must not create a second copy of the review.
- **Event already reviewed by this reviewer**: refused with "This event has
  already been reviewed by you" (FR-024); nothing is stored. Previously the
  system accepted duplicates, so this changes behaviour for every study.
- **Event not assigned to this reviewer**: refused, as today; this feature does
  not change who may review.
- **Non-`scans` deployments**: the MI review form is unchanged.
- **Instructions page with no defined content for a study**: no instructions
  link is shown for that study (no dead link, no empty box).

## Requirements *(mandatory)*

### Functional Requirements

**DEXA review form (Part A)**

- **FR-001**: On a deployment whose study type is `scans`, the event review page
  MUST present the DEXA review form described here in place of the MI decision
  form. Deployments of other study types MUST continue to show their existing
  form unchanged.
- **FR-002**: The form MUST show "Step 1: Review Charts" with the text "Review
  the packet for this event:" and a link to download that event's packet,
  labelled with the event identifier (e.g. "Download charts for 4821").
- **FR-003**: The form MUST show "Step 2: Enter Body Region" with a region
  dropdown whose choices are exactly, in this order: Spine, Lumbar spine,
  Thoracic spine, Cervical spine, Forearm/radius, Hip, Proximal femur, Femoral
  neck, Wrist, Other: please specify.
- **FR-004**: When a region is chosen, the form MUST display, associated with
  that region, three inputs: Laterality (a dropdown of Left, Right, Both,
  Unspecified, with an empty "not provided" default), T score, and Z score.
- **FR-005**: When "Other: please specify" is chosen, the form MUST also display
  a free-text input for the region name, which MUST be non-blank to submit.
- **FR-006**: Laterality, T score, and Z score MUST each be optional; a blank
  value MUST be stored as "not provided", distinguishable from any entered value
  (in particular, a blank score MUST NOT be stored as 0).
- **FR-007**: T score and Z score MUST accept typed decimal numbers that may be
  negative (e.g. -1.1, 2.0, 0), with at most 2 decimal places. The value MUST
  be stored exactly as entered, never rounded. Input that is not a number, has
  more than 2 decimal places, or lies outside -10 to +10, MUST block submission
  and flag the offending field.
- **FR-008**: Once at least one region has been chosen, the form MUST show
  "Step 3: Is there another body region? If not, click 'Submit'", with a region
  dropdown (same choices as FR-003) and the Submit button.
- **FR-009**: Choosing a region in Step 3 MUST add a new region entry with its own
  Laterality / T score / Z score inputs, and MUST offer a further "another body
  region?" prompt beneath it. This MUST repeat for at least 8 regions.
- **FR-010**: The same region MAY be chosen more than once in one review; each
  choice is a separate entry.
- **FR-011**: The reviewer MUST be able to change the region of any entry and to
  remove any entry before submitting, without losing values in other entries.
- **FR-012**: Submit MUST be unavailable (or refused with a message) while no
  region has been entered.
- **FR-013**: On submit, the system MUST save the review as the set of region
  entries entered, preserving their entry order, each with its region (and
  "Other" text where applicable), laterality, T score, and Z score, linked to the
  event and the reviewer.
- **FR-014**: The system MUST validate submitted entries server-side to the same
  rules as FR-003 – FR-007 and FR-012, rejecting invalid submissions with a
  clear message rather than storing partial data.
- **FR-015**: A successful submission MUST advance the event through the existing
  single-reviewer lifecycle to done, and MUST show the reviewer a confirmation,
  as review submission does today.
- **FR-016**: Saved DEXA reviews MUST be retrievable per event with all region
  entries, so that study staff can export or inspect them.
- **FR-024**: A reviewer MUST NOT be able to submit a second review for an
  event they have already reviewed; the second submission is refused with a
  clear message and nothing is stored. This applies to every study type.

**On-site reviewer instructions (Part B)**

- **FR-017**: Reviewer instructions MUST be viewable as a page within the site,
  rather than as `.doc` / `.pdf` downloads. This one mechanism MUST serve every
  study type; each study supplies (or does not yet supply) its own content.
- **FR-017a**: For study type `mci`, the links to the MCI reviewer-instruction
  `.doc` / `.pdf` files MUST be removed from both the home page's and the review
  page's "Review Instructions:" boxes. `mci` MUST use the on-site instructions
  page, with no content supplied yet — so, per FR-022, no instructions link is
  shown for `mci` until content is added. This supersedes spec 007's FR-005 for
  the "Review Instructions:" box only.
- **FR-018**: For study type `scans`, the instructions page MUST present the full
  content of the DEXA chart review instructions — the introduction, the three
  numbered steps with sub-items a–d, and the six numbered notes — with their
  numbering and order preserved.
- **FR-019**: Links to the instructions page MUST open it in a new browser tab, so
  the page the reviewer came from (including unsubmitted review entries) is not
  disturbed.
- **FR-020**: The instructions page MUST be print-friendly: when printed, it
  shows only the instruction content (title and body), without the site
  navigation, banner, or interactive controls, and the text wraps to the printed
  page width.
- **FR-021**: The event review page's "Review Instructions:" box and the home
  page's "Review Instructions:" box MUST both link to the same instructions page
  for the deployment's study, read from the same single source, so they cannot
  disagree. For `scans`, this replaces the home page text "No additional
  instructions".
- **FR-022**: A study type that has no instructions content defined MUST show no
  instructions link (no dead link, no empty box) — consistent with current
  behaviour for studies without documents.
- **FR-023**: Instructions content MUST be maintainable as a document that a
  non-developer can edit (an HTML file), not text embedded in
  program code, so updates such as new dropdown regions in the notes do not
  require a code change.

### Key Entities

- **DEXA review**: one reviewer's completed review of one `scans` event. Belongs
  to an event and a reviewer; has a submission time; contains an ordered list of
  one or more region entries.
- **Region entry**: one body region recorded from the DEXA report. Attributes:
  region (one of the fixed list), other-region text (only when region is
  "Other"), laterality (Left / Right / Both / Unspecified / not provided),
  T score (signed decimal or not provided), Z score (signed decimal or not
  provided), and its position within the review.
- **Study instructions**: the reviewer-facing instructions content for a study
  type, displayed as an on-site page. At most one per study type; absence means
  no instructions link is shown.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A reviewer can record a typical 4-region DEXA report and submit it
  in under 3 minutes after reading the packet.
- **SC-002**: 100% of submitted DEXA reviews store exactly the region entries the
  reviewer entered — same count, order, regions, laterality, and scores — with
  blank values stored as "not provided" rather than as zero.
- **SC-003**: A review with 8 region entries can be entered and submitted without
  errors and without the reviewer having to scroll horizontally.
- **SC-004**: Zero MI-specific questions appear on the review page of a `scans`
  deployment, and the MI review form on `mci` deployments is unchanged.
- **SC-005**: From the review page, a reviewer reaches the full DEXA instructions
  in one click, without leaving or resetting the review form.
- **SC-006**: Printing the instructions page produces output containing every
  instruction step and note and no navigation or interactive elements, in no
  more than 2 printed pages for the current DEXA instructions.

## Assumptions

- **Single reviewer**: `scans` is a single-reviewer study (spec 003); one DEXA
  review completes the event. No reconciliation of two reviewers' region lists is
  needed.
- **At least one region**: every DEXA packet reports at least one body region, so
  a review with zero regions is not a valid submission. If a packet genuinely has
  no usable region, the existing mechanisms for problem events (outside this
  form) apply.
- **Plausible score range**: T and Z scores of real DEXA reports fall well within
  -10 to +10; values outside are treated as typos. Scores are stored to at least
  one decimal place of precision.
- **No editing after submit**: as with the existing review flow, a submitted
  review is final from the reviewer's side; corrections after submission are an
  admin matter and are out of scope.
- **Instructions source**: the DEXA instructions content is that of
  `DEXA.scans.review.instructions.md` (matching the "DEXA CHART REVIEW
  INSTRUCTIONS" part of the 2026-09-22 Word document). The "PROGRAMMING
  INSTRUCTIONS" part of that Word document is a requirements source for this
  spec, not reviewer content, and is not displayed.
- **Visual style**: the layout follows the existing review page and the MCI
  screenshots — step headings, label/input rows in the existing review-form table
  style, and the "Review Instructions:" box at the top right.
- **Per-study review schema**: each study's deployment has its own `reviews`
  table with a study-specific shape (as in the legacy system). `scans` gets a
  DEXA-specific schema able to hold a variable number of region entries per
  review; the `mci` schema and its data are not changed.
- **Packet download**: Step 1 reuses the existing per-event packet download.
- **Other MCI documents untouched**: only the MCI *reviewer-instruction* links
  are removed (FR-017a). The MCI packet-assembly instructions ("Review packets
  should contain:" box) and the scrubbing-protocol links are not reviewer
  instructions and stay as they are. The MCI `.doc` / `.pdf` files themselves
  stay in the repository; only the links to them go.
- **Empty `mci` instructions box**: with no content, the `mci` home page shows
  the "Review Instructions:" box without a link, and the review page omits the
  box, matching how each page already handles a study without documents.
