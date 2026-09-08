# Feature Specification: Study-aware naming on the shared event pages and reviewer emails

**Feature Branch**: `010-study-aware-packet-heading`
**Created**: 2026-09-08
**Status**: Draft
**Input**: User description: "Replace hard-coded "MI" in EventUpload packet heading with the configured study"

## Clarifications

### Session 2026-09-08

- Q: Which label form should the packet heading use? → A: The configured study identity, upper-cased (e.g. "MCI", "CVA", "SCANS"); not a per-study display label and not the banner title.
- Q: Does this feature cover all four workflow-page headings, or just the upload one? → A: All four headings (upload, screening, scrubbing, review) plus the study-named document links hard-coded in the shared scrubbing and review pages.
- Q: How should the study-named document links resolve? → A: From the existing per-study guidance content, extended to carry the scrubbing protocol. Some studies — `scans` among them — have no documents at all, so an empty document set MUST render no link area.
- Q: Should the reviewer-assignment email be pulled into scope? → A: Yes. Its subject line and body name a myocardial-infarction review on every deployment.
- Q: How should the assignment email name the study? → A: The same mechanical upper-cased label the headings use, with the body reworded so no indefinite article precedes it. The full clinical name is not carried, since no per-study prose is maintained.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Every workflow page names the deployment's own study (Priority: P1)

A user working through the event lifecycle on a deployment for a study other than the cardiology one — DEXA scans, CVA, or any study added later — passes through four pages that each head their content with the study and the event identifier: uploading a packet, screening charts, uploading scrubbed charts, and reviewing the event. Every one of those headings names the study this deployment is actually running. None of them tells the user they are handling a myocardial-infarction event when they are not.

**Why this priority**: This is the request, widened to where the same defect actually lives. The headings exist so a user can confirm the event on screen is the one their work in hand belongs to, and a heading naming the wrong condition undermines exactly that check. Fixing one page of four would leave the same user misinformed on the next screen of the same workflow, which is why the four are one story rather than four.

**Independent Test**: Configure a deployment for a study other than the cardiology one and visit all four pages for a known event, confirming each heading names that deployment's study.

**Acceptance Scenarios**:

1. **Given** a deployment configured for a study other than the cardiology one, **When** a user opens the packet-upload page for an existing event, **Then** the heading names the configured study alongside the event identifier.
2. **Given** the same deployment, **When** the user opens the screening page, the scrubbing page, and the review page for that event, **Then** each heading names the configured study, in the same form as the upload page.
3. **Given** the same deployment, **When** the user reads any of the four headings, **Then** no study other than the configured one is named, and the cardiology study in particular is not named.
4. **Given** any deployment, **When** each heading renders, **Then** the event identifier is still shown in it, and the rest of each heading's wording — what the page is for — is unchanged.
5. **Given** two deployments configured for two different studies, **When** a user opens the same four pages on each, **Then** each set of headings names its own deployment's study, with no change to the application other than its configuration.

---

### User Story 2 - Linked documents match the study, and absent documents show nothing (Priority: P1)

The scrubbing page offers the scrubbing protocol, and the review page offers the reviewer instructions. On a deployment whose study has those documents, the links open that study's documents. On a deployment whose study has none — DEXA scans has no documents at all — the page shows no link area whatever: no link to another study's document, no dead link, and no label left hanging with nothing beneath it.

**Why this priority**: A wrong heading misinforms; a wrong document link hands someone the wrong protocol and invites them to follow it. A scrubber on a scans deployment currently reaches a cardiology scrubbing protocol from a page that presents it as theirs. The absent-document case carries equal weight because it is the common one — most studies have no documents prepared, so a design that only works when documents exist does not work.

**Independent Test**: On a deployment whose study has documents, follow each link and confirm it opens that study's document; on a deployment whose study has none, confirm no link area appears on either page.

**Acceptance Scenarios**:

1. **Given** a deployment whose study has a scrubbing protocol, **When** a scrubber opens the scrubbing page, **Then** the offered protocol is that study's, in the formats recorded for it.
2. **Given** a deployment whose study has reviewer instructions, **When** a reviewer opens the review page, **Then** the offered instructions are that study's, and are the same instructions offered for that study on the home page.
3. **Given** a deployment whose study has no documents recorded, **When** a user opens the scrubbing page or the review page, **Then** no document link area is rendered on either — no link, no label, and no empty space where one would be.
4. **Given** a deployment whose study has a document in one format but not another, **When** the page renders, **Then** only the formats that exist are offered, and no link is presented for a format that does not.
5. **Given** a deployment whose study has documents, **When** a user follows any offered link, **Then** a document is returned rather than a not-found response.
6. **Given** a study for which documents are added later, **When** they are recorded against that study, **Then** the links appear on the relevant pages with no other change to the application.

---

### User Story 3 - The assignment email names the study the reviewer is actually reviewing (Priority: P1)

A reviewer on any deployment is assigned an event and receives the assignment email. Its subject line and its opening sentence name the study that deployment is running. A reviewer on a DEXA scans or CVA deployment is not told they have been assigned a myocardial-infarction review.

**Why this priority**: This is the only part of the feature that has already left the building. A wrong heading misinforms someone who is looking at the application and can see the rest of its context; a wrong email arrives in an inbox with no context at all, is the first thing a new reviewer reads about their assignment, and is archived. Every non-cardiology reviewer on every deployment receives this today, on all three reviewer slots.

**Independent Test**: Trigger an assignment on a deployment configured for a study other than the cardiology one and inspect the resulting email's subject and body for the study named.

**Acceptance Scenarios**:

1. **Given** a deployment configured for a study other than the cardiology one, **When** an assignment email is generated, **Then** its subject names the configured study and not a myocardial-infarction review.
2. **Given** the same deployment, **When** the reviewer reads the email body, **Then** the opening sentence names the configured study, and no other study is named anywhere in the message.
3. **Given** an event assigned to a first and a second reviewer, **When** both emails are generated, **Then** both name the configured study.
4. **Given** an event escalated to a third reviewer, **When** that email is generated, **Then** it names the configured study in the same form as the first two.
5. **Given** any deployment, **When** an assignment email is generated, **Then** the event identifier, the links, the signature, and every other part of the message are unchanged.
6. **Given** a deployment whose study identity is not configured, **When** an assignment email is generated, **Then** the message omits the study word and remains grammatical, rather than naming a study the deployment was not configured for.

---

### User Story 4 - Headings degrade gracefully when no study is configured (Priority: P2)

A deployment is brought up before its study identity is configured, or has not finished loading its configuration when a user opens a page. Each heading still reads as a sensible sentence naming the event, rather than showing a blank gap, a raw placeholder such as "undefined", or a study label the deployment cannot vouch for.

**Why this priority**: Lower frequency than the primary flows, but it is the failure mode that replacing a literal string introduces. A hard-coded word can never be missing; a resolved one can. This story keeps the fix from trading a wrong label for a broken one, and it protects the identifying box's established guarantee that it never displays an empty or placeholder field.

**Independent Test**: Start the application with the study identity unset, then blank, and confirm on all four pages that the heading renders as readable text naming the event, with no blank gap, no "undefined", and no study name invented in place of the missing one.

**Acceptance Scenarios**:

1. **Given** a deployment whose study identity is not configured, **When** any of the four headings renders, **Then** it names the event without a study word, and shows no blank gap or placeholder text where the study word would be.
2. **Given** a deployment configured with a study the application has never served before, **When** the headings render, **Then** that study's configured identity is displayed, because the label is mechanical and needs nothing known about the study in advance.
3. **Given** a deployment whose study identity is not configured, **When** the scrubbing and review pages render, **Then** no document links are offered, since no study's documents can be known to be the right ones.
4. **Given** the page is still loading its configuration, **When** a heading first becomes visible, **Then** it does not briefly display a study name that is later replaced by a different one.
5. **Given** a study identity recorded with surrounding whitespace or in unexpected letter case, **When** the headings render, **Then** the same upper-case label is displayed as for the tidily recorded value, and the identity is not mistaken for a missing one.

---

### User Story 5 - One deployment reads consistently throughout (Priority: P3)

A user moves between the banner at the top of every page, the review guidance on the home and upload pages, the four workflow headings, and the linked documents. All of them refer to the same study, so nothing in the application suggests the deployment is serving more than one.

**Why this priority**: The value is coherence rather than new capability, and the application is already correct once the earlier stories land. It matters because the same study identity now drives five separate displays; leaving any one of them on a different footing is how the next inconsistency gets introduced.

**Independent Test**: On a single configured deployment, walk the banner, the guidance boxes, all four headings, every offered document link, and an assignment email, confirming they all refer to that one study.

**Acceptance Scenarios**:

1. **Given** a configured deployment, **When** a user views any of the four pages, **Then** the study named in the heading is the same study named in the page banner.
2. **Given** a configured deployment, **When** a reviewer compares the instructions offered on the review page with those offered on the home page, **Then** both offer the same study's documents, from one recorded source rather than two that could drift apart.
3. **Given** a deployment whose study identity is changed and the application restarted, **When** the pages are reopened and a new assignment email is generated, **Then** the banner, the headings, the guidance, the document links, and the email all reflect the new study, with none lagging behind.

---

### Edge Cases

- The study identity is unset or empty — each heading names the event with no study word, never an empty gap or a placeholder such as "undefined", and no document links are offered.
- The study identity is a value the application has never served before — the headings display it, upper-cased, rather than substituting a known study's label. That study has no documents recorded, so no link area appears until documents are added for it.
- The configuration has not yet resolved when a heading first renders — the heading must not flash one study name and then settle on another, since a momentary wrong label is still a wrong label a user may act on.
- A study has documents in some formats but not others — only the formats that exist are offered, with no dead link for a missing one.
- A study has no documents at all, which is the case for DEXA scans and expected to be the common case — both pages render no link area rather than an empty label, a bare separator, or another study's documents.
- A document is recorded for a study but is missing from the served location — this is a deployment fault rather than a configuration one; the page behaves as it does today for any unavailable file and the heading is unaffected.
- The study identity carries stray whitespace or unexpected letter case — it is normalised for display rather than treated as unknown.
- A future study identity is longer than the current handful of short acronyms — each heading must remain a readable single line that still shows the event identifier.
- A deployment sets a free-text study title for the banner that is long or descriptive — the headings are unaffected, because they are built from the study identity rather than that title.
- A page is opened with no event named in the address, or for an event that cannot be retrieved — that page's existing behaviour is unchanged, and where it does not render its heading the study question does not arise.
- The same event is opened on two deployments configured for different studies — each set of headings names its own study, and neither is derived from anything stored against the event.
- An assignment email is generated on a deployment whose study identity is not configured — the message omits the study word and stays grammatical, and does not fall back to a default study name.
- An assignment email is generated for a study whose label begins with a vowel sound, such as "MCI", and for one that does not, such as "CVA" — the wording reads correctly for both, because no indefinite article precedes the label.
- Assignment emails are generated in the mode that reports what would be sent without sending it — the previewed subject and body name the study exactly as a delivered message would.

## Requirements *(mandatory)*

### Functional Requirements

#### Study naming in page headings

- **FR-001**: The headings of the packet-upload, screening, scrubbing, and review pages MUST each name the study the deployment is configured for, and MUST NOT contain a study name written into the application itself.
- **FR-002**: The study named in each heading MUST be derived solely from the deployment's configured study identity. It MUST NOT be derived from the event, the patient, the packet, or anything carried in the page address, so that the heading describes the deployment rather than the record on screen.
- **FR-003**: Each heading MUST display the deployment's study identity in upper case, exactly as configured apart from that case change — for example "MCI", "CVA", "VTE", "SCANS". No per-study wording is maintained for the headings; the displayed label is a mechanical transformation of the configured value, so a study the application has never served before displays correctly with no content added for it.
- **FR-003a**: The headings MUST NOT use the deployment's free-text banner title, and MUST NOT apply the banner's study-specific title-casing exception. The heading label is uniform across every study, including the one the banner presents differently.
- **FR-004**: Each heading MUST continue to show the event identifier alongside the study, and MUST retain its existing wording describing what the page is for. Only the study word changes.
- **FR-005**: When the study identity is unset or empty, each heading MUST omit the study word and remain a grammatical, readable line naming the event. A non-empty identity is always displayable under FR-003, so this is the only case in which the word is omitted.
- **FR-006**: No heading MUST render a blank space, a placeholder derived from missing data such as "undefined", or an orphaned separator where the study word would otherwise appear.
- **FR-007**: When the study word is omitted, the system MUST NOT substitute a default study's name, because naming a study the deployment has not been configured for is more misleading than naming none.
- **FR-008**: The system MUST tolerate surrounding whitespace and any letter case in the configured study identity, displaying the same upper-case label however the value was recorded.
- **FR-009**: No heading MUST display one study name and then replace it with a different one as configuration resolves; until the study identity is known it MUST show the no-study-word form defined in FR-005.
- **FR-010**: All four headings MUST resolve the study label the same way, so the four pages cannot drift apart as studies are added.
- **FR-011**: On the existing cardiology deployment, whose study identity is "mci", each heading MUST read "MCI" where it reads "MI" today. This wording change is an accepted consequence of deriving the label from configuration, and is the only visible heading change that deployment may show.

#### Study documents on the scrubbing and review pages

- **FR-012**: The scrubbing protocol offered on the scrubbing page and the reviewer instructions offered on the review page MUST be those recorded for the deployment's configured study, and MUST NOT be document references written into the pages themselves.
- **FR-013**: Document references MUST be held as per-study content, keyed on the study identity, rather than derived from it by transforming the identity into a filename. Document names do not follow the identity — the cardiology study's documents are named for "MI" while its identity is "mci", and studies exist with no documents at all — so a mechanical rule cannot address them.
- **FR-014**: The reviewer instructions offered on the review page MUST come from the same recorded source as the reviewer instructions offered on the home page for that study, so the two cannot drift apart.
- **FR-015**: When the configured study has no documents recorded for a page, that page MUST render no document link area at all — no link, no introducing label, and no separator or empty region where the area would be.
- **FR-016**: When a study has a document in some formats but not others, only the formats recorded MUST be offered, and no link MUST be presented for a format that does not exist.
- **FR-017**: The system MUST NOT fall back to another study's documents when the configured study has none, since a document is acted upon and offering the wrong protocol is materially worse than offering none.
- **FR-018**: When the study identity is unset, empty, or has no documents recorded, no document links MUST be offered on either page.
- **FR-019**: Adding documents for a study MUST require recording them against that study and placing the files where documents are served from, with no other change to the application.

#### Study naming in reviewer assignment emails

- **FR-020**: The assignment email's subject line and body MUST name the study the deployment is configured for, and MUST NOT contain a study name written into the application itself.
- **FR-021**: The email MUST name the study using the same upper-cased study identity the headings use (FR-003), so a reviewer's email and the page it links to name the study identically.
- **FR-022**: The email body MUST be worded so that no indefinite article precedes the study label, because the correct article depends on how the label is pronounced and cannot be derived from the configured value.
- **FR-023**: The email MUST NOT carry a study's full clinical name, since no per-study prose is maintained. Losing the "Myocardial Infarction" expansion the cardiology deployment sends today is an accepted consequence.
- **FR-024**: When the study identity is unset or empty, the email MUST omit the study word and remain grammatical, and MUST NOT name a default study.
- **FR-025**: All assignment emails MUST resolve the study the same way, across the first, second, and third reviewer slots, so no slot can drift from the others.
- **FR-026**: Every other part of the message — the configured subject prefix, the event identifier, the download, review, and index links, the signature, and any attachment — MUST be unchanged.

#### Scope containment

- **FR-027**: The system MUST require no change to the application beyond its configuration in order to show a different study's headings and send a different study's emails, and no change beyond recorded per-study content to offer a different study's documents.
- **FR-028**: The change MUST be confined to the four headings, the two sets of document links, and the two study-naming strings in the assignment email. Every other behaviour of the four pages — the values they display, the conditions under which they render, what they accept, and what they submit — and every other behaviour of email delivery MUST be unchanged.

### Key Entities

- **Study identity**: The single configured value that tells a deployment which clinical validation study it is serving. It is a property of the deployment, not of any record, and it already drives the page banner and the study-specific review guidance. This feature adds four headings and two document areas that read it.
- **Study label**: The form of the study identity as it appears in a heading — the configured value in upper case. Derived mechanically, with no per-study content behind it; absent only when the identity is unset or empty.
- **Study document set**: The documents recorded for a study — a scrubbing protocol and reviewer instructions, each in the formats that exist for it. Held as per-study content keyed on the study identity, alongside the packet-assembly guidance already recorded that way. May be empty, which is the expected case for most studies and the actual case for DEXA scans.
- **Assignment email**: The message a reviewer receives when an event is assigned to them, for any of the three reviewer slots. Built from one subject template and one body template shared by every slot. Names the study via the study label, and carries the event identifier and links unchanged.
- **Event**: The clinical event being uploaded, screened, scrubbed, or reviewed, identified by the event identifier shown in each heading alongside the study. Carries no study identity of its own — every event in a deployment belongs to that deployment's study.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On every configured study deployment, all four headings name that deployment's study in 100% of page loads where the heading is shown.
- **SC-002**: Zero occurrences of a study name in any of the four headings that does not match the deployment's configured study identity, character for character, ignoring case.
- **SC-003**: Zero occurrences of a blank gap, a stray separator, or an "undefined" study word in any heading, across configured, unconfigured, and not-previously-served study identities.
- **SC-004**: Standing up a deployment for a study the application has never served before yields correct headings on all four pages with configuration changes alone — no code change, and no per-study label added anywhere.
- **SC-005**: Every document link offered on the scrubbing and review pages returns a document rather than a not-found response, on every configured deployment.
- **SC-006**: Zero occurrences of a document from one study being offered on a deployment configured for another.
- **SC-007**: On a deployment whose study has no documents, zero link areas, orphaned labels, or empty regions appear where documents would otherwise be offered on either page.
- **SC-008**: On any single page load, the banner, the guidance, the heading, and any offered documents refer to the same study in 100% of cases. They need not use identical wording — the banner may show "MCI Project" where the heading shows "MCI" — but they must never name different studies.
- **SC-009**: All four pages continue to display the values they display today and to accept work under exactly the conditions they accept it today, verified against the existing acceptance scenarios for each.
- **SC-010**: A user can still read the study and the event identifier from each heading at a glance, without scrolling or expanding anything, at the window sizes the application already supports.
- **SC-011**: Zero assignment emails name a study other than the deployment's configured one, across all three reviewer slots.
- **SC-012**: The study named in an assignment email matches the study named in the heading of the page that email links to, in 100% of assignments.
- **SC-013**: Assignment emails continue to be delivered at the rate they are today, with the event identifier, links, signature, and attachment behaviour unchanged, verified against the existing email tests.

## Assumptions

- The deployment's study identity is already available to all four pages — two of them read it today to choose which review guidance to show — so this feature displays configuration that is already present and introduces no new configuration and no new data.
- Each heading's sentence shape is retained: the study word sits where it sits today, between the page's own wording and the event identifier. Only the study word becomes configurable.
- The heading label form was chosen deliberately: the configured study identity, upper-cased, and nothing else. It was preferred over a per-study display label because it needs no content maintained for each study — a new deployment gets correct headings from its configuration alone — and over the banner's full title because that title reads as a mouthful mid-sentence and can be overridden with arbitrary free text.
- Two consequences of that choice are understood and accepted. The existing cardiology deployment's headings change from "MI" to "MCI", because "mci" is what that deployment is configured with. And a scans deployment reads "Packet for SCANS 4821"; uniformity across studies was preferred to a per-study exception.
- The banner's title-casing exception for the scans study is therefore not reused for headings. This is intentional, and the two displays are expected to differ in form while always naming the same study.
- Documents are treated differently from headings on purpose. A mechanical rule works for a label and fails for a filename: the documents on disk are named for "MI" and "VTE" while the identities are "mci" and "vte", and most studies have no documents at all. Per-study recorded content handles both facts, and the mechanism already exists for the home page's guidance.
- Most studies are expected to have no documents. The empty case is therefore the normal path rather than an error path, and the established behaviour of rendering no link area for it is adopted rather than reinvented.
- The four pages render their headings only under conditions each page already enforces — an event named in the address, retrievable, and the user entitled to see it. This feature does not change those gates and has nothing to say about the pages' no-event or unretrievable-event states beyond leaving them alone.
- Existing access rules are unchanged. The headings expose only the deployment's own study identity, already visible in the banner on every page, and the documents offered are those already served to that deployment's users. Nothing new is disclosed.
- The assignment email takes the mechanical label rather than a per-study prose name, for the same reason the headings do: no per-study content to maintain, so a new study's reviewers get correct emails from configuration alone. Two consequences are accepted — the cardiology email loses its "Myocardial Infarction (MI)" expansion, and its opening sentence is reworded so no indefinite article sits in front of a label whose article depends on pronunciation.
- One subject template and one body template serve all three reviewer slots, so making them study-aware covers first, second, and third-reviewer assignment together, with no per-slot work.
- The email is composed on the server, where the configured study identity currently defaults to the cardiology study when unset. FR-024 requires an unset identity to omit the study word rather than inherit that default, so the unset case must be distinguishable from an explicit cardiology configuration. This is a constraint on how the label is resolved, not a change to the default itself, which stays as it is for everything else that reads it.
- No accessibility, localisation, or layout change is intended beyond the study word varying in length and a link area disappearing where a study has no documents.

### Out of scope

These were identified by a sweep of the shared code for hard-coded study names and are deliberately excluded, so each is a recorded decision rather than an oversight.

- **The cardiology review form and its stored fields.** The review page's questionnaire is a cardiology instrument in shared code — its questions, its answer values, the field it submits, the column that field is stored in, and the study-named columns of the CSV export. Making the review instrument study-aware is a substantial feature touching the schema, the review endpoint, and the export, and is not a naming change. This feature alters that page's heading and its instructions link only, and leaves the form untouched.
- **Document filenames on disk.** The served documents keep the names they have. This feature records which documents belong to which study; it does not rename them or impose a naming convention, since a convention is exactly what the per-study mapping exists to avoid needing.
- **The legacy VTE-specific pages.** Standing project guidance is that the VTE fork is not extended, and the constitution prohibits divergent copies of shared modules. New work targets the shared pages only.
- **Two stale request paths on the home page.** The sweep found the home page requesting a study-scoped events path that the application does not serve, and passing a study parameter to a summary endpoint that takes none. These fail on every deployment including the cardiology one, so they are a pre-existing defect rather than a study-configuration one, and fixing them here would mix an unrelated repair into this change. They are recorded for separate treatment.
