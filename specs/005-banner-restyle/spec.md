# Feature Specification: Legacy-style Top Banner

**Feature Branch**: `005-banner-restyle`  
**Created**: 2026-05-22  
**Status**: Draft  
**Input**: User description: "I'd like the top banner to be more like the legacy code version. Compare the legacy version (in CVA mode): 'CVA.Screenshot 2026-05-21 103208.jpg' with our version 'Screenshot 2026-05-21 105013.events.viewAll.ScansMode.jpg'. Our version is 1) missing styling, 2) the logo isn't prominent, and 3) the study mode/type/title is missing; the study title should be upper case for the acronym studies (CVA, MCI, etc), and 'Scans' for scans. The name should include ' Project', e.g. Scans Project, MCI Project."

## Overview

The application's top banner currently presents a thin, unstyled strip with a
small "CNICS Validation" text link and a logged-in line. The legacy application
(see reference screenshot `CVA.Screenshot 2026-05-21 103208.jpg`) presents a
recognizable, branded banner: a prominent CNICS logo paired with a large study
title such as "CVA Project". This feature brings the current banner up to that
standard so every page clearly communicates the product's identity and which
study the deployment is serving.

The legacy screenshots are the visual source of truth for this feature:

- Reference (target): `CVA.Screenshot 2026-05-21 103208.jpg`
- Current (to be improved): `Screenshot 2026-05-21 105013.events.viewAll.ScansMode.jpg`

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See which study the deployment is serving (Priority: P1)

A reviewer, uploader, or administrator opens any page of the application. The
banner at the top of the page shows the CNICS logo prominently, paired with the
name of the study this deployment is running — for example "CVA Project",
"MCI Project", or "Scans Project". The user immediately knows which study they
are working in, without having to inspect a URL or ask a colleague.

**Why this priority**: This is the core functional gap. A deployment can be
configured for any of several studies, and today the banner gives the user no
indication of which one is active. Showing the study identity is the single
most valuable change and is independently useful even before any restyling.

**Independent Test**: Configure a deployment for a given study type, open any
page, and confirm the banner displays the correctly formatted study title and a
prominent logo. Repeat for an acronym study (e.g. CVA) and for the scans study.

**Acceptance Scenarios**:

1. **Given** a deployment configured for an acronym-based study such as CVA,
   **When** the user opens any page, **Then** the banner shows the study title
   with the acronym in uppercase followed by " Project" (e.g. "CVA Project").
2. **Given** a deployment configured for the scans study, **When** the user
   opens any page, **Then** the banner shows "Scans Project" (the word "Scans"
   in title case, not all-uppercase).
3. **Given** a deployment configured for any study (e.g. MCI), **When** the
   user opens any page, **Then** the CNICS logo is displayed as a prominent
   element of the banner, comparable in prominence to the legacy reference.
4. **Given** the user navigates between pages, **When** each page loads,
   **Then** the same logo and study title appear consistently on every page.

---

### User Story 2 - A banner that looks like the legacy application (Priority: P2)

A returning user who knows the legacy CNICS application opens the new
application. The top banner is styled to look like the one they remember — the
logo and study title are laid out together as a header block, and the
logged-in line is presented as a subtle strip rather than an unstyled box. The
banner feels finished and branded rather than placeholder-like.

**Why this priority**: The study identity (User Story 1) delivers the
information; this story makes the banner visually faithful to the legacy
reference so the application looks polished and familiar. It builds on User
Story 1 but can be verified independently once the logo and title exist.

**Independent Test**: Place the new banner side by side with the legacy
reference screenshot and confirm the layout, spacing, and styling of the logo,
title, and logged-in line are a faithful match.

**Acceptance Scenarios**:

1. **Given** the new banner, **When** it is compared side by side with the
   legacy reference screenshot, **Then** the logo and study title form a single
   header block in the same arrangement as the legacy banner.
2. **Given** a logged-in user, **When** they view any page, **Then** the
   logged-in identity and log-out control appear within the banner, styled as a
   subtle strip consistent with the legacy reference.
3. **Given** any page of the application, **When** it renders, **Then** the
   banner styling (background, spacing, typography of the title) is consistent
   with the legacy reference and applied uniformly across pages.

---

### Edge Cases

- **Study type not yet resolved**: When the deployment's study type has not yet
  been determined (e.g. the configuration is still loading or could not be
  retrieved), the banner MUST still render the logo and remain usable; it MUST
  NOT display a broken or placeholder title such as "undefined Project".
- **Unknown or unrecognized study type**: When the configured study type is not
  one of the explicitly known studies, the banner MUST still produce a sensible
  title (the study type rendered in uppercase, suffixed with " Project") rather
  than failing to render.
- **Logo image unavailable**: If the logo image fails to load, the banner MUST
  still display the study title and the log-out control, and remain usable.
- **Unauthenticated visitor**: When no user is logged in, the banner MUST still
  show the logo and study title; the logged-in line simply shows no identity or
  log-out control.
- **Narrow viewport**: On a narrow screen, the banner MUST remain readable —
  the study title and log-out control must not be clipped or overlap.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The top banner MUST display the CNICS logo prominently, at a size
  and placement comparable to the legacy reference banner.
- **FR-002**: The top banner MUST display a study title alongside the logo,
  presented as the most prominent text in the banner.
- **FR-003**: The study title MUST be derived from the deployment's configured
  study type, not hard-coded.
- **FR-004**: For an acronym-based study (e.g. CVA, MCI), the study title MUST
  render the study acronym in uppercase.
- **FR-005**: For the scans study, the study title MUST render the word "Scans"
  in title case (first letter capitalized, not all-uppercase).
- **FR-006**: Every study title MUST be suffixed with " Project" — for example
  "CVA Project", "MCI Project", "Scans Project".
- **FR-007**: The banner MUST be styled to be visually faithful to the legacy
  reference: the logo and title form a header block, and the logged-in line is
  presented as a subtle strip rather than an unstyled box.
- **FR-008**: The banner (logo, study title, and logged-in line) MUST appear
  consistently on every page of the application.
- **FR-009**: The logged-in user identity and log-out control MUST remain part
  of the banner and reachable from every page.
- **FR-010**: When the study type cannot yet be determined, the banner MUST
  still render without showing a broken or placeholder title.
- **FR-011**: When the configured study type is not an explicitly recognized
  study, the banner MUST still produce a title by rendering the study type in
  uppercase followed by " Project".
- **FR-012**: If the logo image fails to load, the banner MUST remain usable
  with the study title and log-out control still visible.

### Key Entities *(include if feature involves data)*

- **Study Type**: An identifier for the study a deployment is serving (e.g.
  `cva`, `mci`, `scans`). Provided by the deployment's existing configuration.
  Drives the displayed study title.
- **Study Title**: The user-facing label shown in the banner, derived from the
  study type by applying the casing rules above and appending " Project".

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On 100% of application pages, a user can identify the active
  study from the banner alone, without navigating away or inspecting the URL.
- **SC-002**: For every supported study type, the displayed study title follows
  the casing rules — acronym studies uppercased, the scans study as "Scans" —
  and is suffixed with " Project".
- **SC-003**: Placed side by side with the legacy reference screenshot, a
  reviewer judges the new banner a faithful match in logo prominence, title
  placement, and header styling.
- **SC-004**: The banner renders consistently — same logo, title, and logged-in
  strip — on 100% of application pages.
- **SC-005**: A user unfamiliar with the deployment, shown only the banner, can
  correctly name the active study.
- **SC-006**: The banner remains usable (study title readable, log-out control
  reachable) when the logo image fails to load or the study type has not yet
  resolved.

## Assumptions

- The deployment's study type is already available from existing application
  configuration; this feature consumes it and does not introduce a new way to
  configure or change the study.
- A CNICS logo asset is already available to the application for display.
- "Scans" is the only non-acronym study type; every other study type is treated
  as an acronym and rendered in uppercase. New study types added later follow
  the same rule unless explicitly specified otherwise.
- The wording of the logged-in line ("logged in as …", log-out control) is
  preserved as-is; only its placement and styling within the banner change to
  match the legacy reference.
- The banner is part of the shared page layout, so applying it once makes it
  appear on every page.
- Restyling of the navigation menu and page content area beyond the banner
  region is out of scope for this feature.
</content>
