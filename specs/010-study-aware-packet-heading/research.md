# Phase 0 Research: Study-aware naming on the shared event pages and reviewer emails

**Feature**: `010-study-aware-packet-heading` | **Date**: 2026-09-08

All unknowns from Technical Context are resolved below. Each decision records
what was observed in the current code before it was changed, per Constitution
Principle VI ("record the current observed behavior before modifying it").

---

## R1. Where the study label is computed, and how "unset" survives the trip to the browser

**Observed today**: `flask_backend/study_config.py:75-76` — `get_study_type()`
returns `os.getenv("STUDY_TYPE", "mci")`. An unset `STUDY_TYPE` is therefore
indistinguishable from `STUDY_TYPE=mci` by the time any caller sees it.
`/api/config` (`flask_backend/app.py:744`, body at 780-790) sends that already
defaulted value as `data.study_type`, and `frontend/src/App.jsx:106` stores it.

**The problem**: FR-005 requires the headings to omit the study word when the
identity is unset, and FR-024 requires the same of the email. Neither is
achievable while the default has already been applied — the frontend cannot
tell the two states apart, and the email would name MCI on a deployment that
was never configured.

**Decision**: Compute the label once, in the shared configuration layer, and
publish it as a new `study_label` field on `WorkflowConfig` and on
`/api/config`. The frontend renders that value; the emailer reads the same
helper. One rule, one implementation, both consumers.

```python
def get_study_label() -> str:
    """Upper-cased display label for this deployment's study; "" when unset."""
    return os.getenv("STUDY_TYPE", "").strip().upper()
```

`get_study_type()` is left exactly as it is. Nothing that resolves workflow
profiles, schema selection, or guidance content changes behaviour.

**Rationale**: The empty string is the only honest representation of "not
configured", and deriving it anywhere other than the config layer would mean
two implementations of one rule (against FR-010 and FR-025) or a scattered
`os.environ` read (against the constitution's configuration-layer rule). The
label is a pure function of one variable, so it costs one function and one
response field.

**Alternatives considered**:

- *Frontend derives the label from `study_type` itself*, mirroring
  `studyTitle.js`. Rejected: cannot satisfy FR-005, because the value it
  receives has already been defaulted to `mci`. It would also put a second
  copy of the rule in the codebase.
- *Change `get_study_type()` to return `""` when unset.* Rejected: it feeds
  `_profile_for()` and every other study-keyed lookup. `_PROFILES` only
  contains `scans` (`study_config.py:51`), so an empty study type happens to
  resolve to the same full-workflow profile today — but relying on that
  coincidence to change a widely-read accessor is exactly the silent
  regression Principle VI warns about.
- *Send a separate `study_configured` boolean.* Rejected: it pushes the rule
  back to two places, since each consumer would still have to combine the flag
  with the type to build a label.

**Cost**: `/api/config`'s response shape changes. Principle VI permits this
pre-release, and the Development Workflow gate requires the regenerated
`openapi.json` to land in the same PR. Recorded as a task.

---

## R2. How the four page headings get the label

**Observed today**: Only three components receive study context.
`App.jsx:139` passes `studyType`/`configResolved` to `Home`, `:271` to
`EventUpload` (plus `workflow`), and `:281` to `EventReupload`. The three
remaining pages in scope are mounted with **no props at all** —
`EventReview` at `:293`, `EventScreen` at `:305`, `EventScrub` at `:329`.

**Decision**: Add `studyLabel` state in `App.jsx` alongside the existing
`studyType`, populated from `data.study_label` in the same `/api/config`
handler, and pass `studyLabel` plus the existing `configResolved` to all four
pages. Each heading renders the label through one shared helper so the four
cannot drift (FR-010).

**Rationale**: The plumbing already exists and is proven for two pages; this
extends it rather than inventing a context or a store for four strings.
`configResolved` is already threaded for exactly this purpose and satisfies
FR-009 (no flash of one study name replaced by another) with no new mechanism.

**Alternatives considered**:

- *React context for study identity.* Rejected: four consumers, one value,
  and prop threading is the pattern already established in this file. Context
  would be a new abstraction for no reduction in code.
- *Each page fetching `/api/config` itself.* Rejected: four extra requests per
  navigation, and four chances to render before the answer arrives.

---

## R3. Rendering a heading whose study word may be absent

**Observed today**: All four headings interpolate a literal —
`EventUpload.jsx:247` `Packet for MI {eventId}`, `EventScreen.jsx:53`
`Screen charts for MI {eventId}`, `EventScrub.jsx:73`
`Upload scrubbed charts for MI {eventId}`, `EventReview.jsx:187`
`Review event: MI {eventId}`.

**Decision**: Add a pure helper, `frontend/src/components/studyHeading.js`,
sibling to `studyTitle.js`, exporting a function that joins the label and the
event identifier with exactly one space and collapses cleanly when the label
is empty:

```js
export function headingSuffix(studyLabel, eventId) {
  const label = (studyLabel ?? '').toString().trim()
  return label === '' ? `${eventId}` : `${label} ${eventId}`
}
```

Each page composes its own fixed wording around it — `Packet for `,
`Screen charts for `, and so on — so FR-004 (page wording unchanged) holds
while FR-006 (no blank, no stray separator) is guaranteed by construction
rather than by four separate conditionals.

**Rationale**: The double-space and dangling-separator failures FR-006 forbids
are exactly what four hand-written ternaries would eventually produce. One
tested function removes the class of bug. It also gives the label rule a
single frontend home, matching `studyTitle.js` and `reviewGuidance.js`.

**Alternatives considered**:

- *Inline ternary in each heading.* Rejected: four copies of a rule that
  FR-010 requires to be identical.
- *Render the whole heading from the helper*, page wording included.
  Rejected: it would centralise four sentences that have nothing in common
  and are not required to stay in step, making the pages harder to read.

---

## R4. Where the document links come from, and the fallback conflict

**Observed today**: `frontend/src/components/reviewGuidance.js` holds a
per-study map keyed on lowercased study type, with `mci` and `scans` entries
of shape `{packets: Box, instructions: Box}`. `EventScrub.jsx:67,69` and
`EventReview.jsx:181,183` ignore it and hard-code four `CNICS MI …` hrefs.
The review page's two links duplicate what the map already holds at
`reviewGuidance.js:54-55`.

`app/webroot/files/` contains documents for **MI and VTE only** (plus
`NA-ACCORD MI` variants). There is no `CNICS MCI …` file, and `scans` has no
documents, which is why its map entry carries `links: []`.

**Decision**: Extend each study's entry with a third box, `scrubbing`, holding
the scrubbing-protocol links. Both pages then read from the map. Extract the
existing `GuidanceLinks` component out of `Home.jsx` (it is defined at
`Home.jsx:96-112` and not exported) into
`frontend/src/components/GuidanceLinks.jsx`, and use it on all three pages.

**Rationale**: `GuidanceLinks` already returns `null` for an empty link list —
written for spec 007 FR-004, and precisely the behaviour FR-015 and FR-016
now require. Reusing it means the empty case is satisfied by code that already
works, and FR-014 (the review page's instructions come from the same recorded
source as the home page's) becomes true by construction rather than by
convention.

**Conflict found — and how it is bounded**: `resolveReviewGuidance()`
(`reviewGuidance.js:94-96`) deliberately falls back to the `mci` entry for an
unrecognized or unset study type. That is spec 007's FR-008, and it directly
contradicts this feature's FR-017 and FR-018, which forbid offering another
study's documents.

**Decision**: Leave `resolveReviewGuidance()` untouched, so the home page
keeps the behaviour spec 007 specified, and add a sibling resolver used only
by the workflow pages' document links:

```js
export function resolveStudyDocuments(studyType) {
  const key = (studyType ?? '').toString().trim().toLowerCase()
  const entry = STUDY_GUIDANCE[key]          // no `|| mci` fallback
  return {
    scrubbing: entry?.scrubbing ?? { links: [] },
    instructions: entry?.instructions ?? { links: [] },
  }
}
```

Both resolvers read the same map, so for any **configured, recognized** study
the home page and the workflow pages agree exactly, which is what FR-014 and
SC-008 are about. They diverge only on a deployment whose study type is
unrecognized or unset — a misconfiguration — where the home page shows MI
guidance and the workflow pages show no document links.

**This divergence is a known, bounded inconsistency and is flagged for
follow-up.** It is not resolved here because reversing spec 007's FR-008 is a
decision about the home page, outside this feature's scope, and because the
asymmetry is defensible on its own terms: guidance prose is read, whereas a
protocol document is acted on, so the cost of a wrong fallback is higher for
documents than for guidance text.

**Alternatives considered**:

- *Derive filenames from the study identity*, matching the heading rule.
  Rejected on evidence: `CNICS MCI event scrubbing protocol.doc` does not
  exist, so this would break the links on the one deployment where they
  currently work, and it cannot express "this study has no documents".
- *Make `resolveReviewGuidance` stop falling back.* Rejected: it silently
  changes the home page, which spec 007 specified deliberately, and does so
  outside this feature's declared scope.
- *A separate document map, independent of the guidance map.* Rejected: two
  sources for the reviewer-instructions links is exactly the drift FR-014
  exists to prevent.

---

## R5. Rewording the assignment email

**Observed today**: `flask_backend/emailer.py:58-60` builds the subject as
`f"{prefix} MI Review Assignment – Event {event_id}"`, where `prefix` comes
from `EMAIL_SUBJECT_PREFIX` (default `"CNICS / NA-ACCORD"`). The body's
opening sentence, at `:77-80`, reads `"You have been assigned a Myocardial
Infarction (MI) review."` Both helpers are shared by all three reviewer slots:
`_format_subject`/`_build_body` are called at `:222-223` for reviewers 1 and 2
and again at `:353-354` for the third reviewer, so two strings cover all of
FR-025.

**Decision**: Both strings take `get_study_label()`, and both collapse without
leaving stray whitespace when it is empty.

| | Configured (`mci`) | Unset |
|---|---|---|
| Subject | `CNICS / NA-ACCORD MCI Review Assignment – Event 4821` | `CNICS / NA-ACCORD Review Assignment – Event 4821` |
| Body | `You have been assigned a review in the MCI study.` | `You have been assigned a review.` |

**Rationale**: "a review in the MCI study" puts no indefinite article before
the label, satisfying FR-022 without needing to know how each acronym is
pronounced — "an MCI" but "a CVA" is not derivable from the configured value.
The full clinical name is dropped per FR-023, which the spec records as an
accepted loss.

**Alternatives considered**:

- *"You have been assigned an MCI review."* Rejected: correct for MCI, wrong
  for CVA, VTE and SCANS. This is the trap FR-022 was written to close.
- *"You have been assigned a MCI/CVA review" with an article lookup.*
  Rejected: per-study content, which Q1 and Q4 both ruled out.
- *Keeping the study out of the body entirely.* Considered and rejected during
  clarification (Q4 option C); the reviewer needs the study named in the
  message body, not only in a subject line that mail clients truncate.

---

## R6. How this gets verified

**Observed today**: `flask_backend/tests/` holds 11 pytest modules, including
`test_study_config.py` and `test_config_endpoint.py`, which are the natural
homes for the new config behaviour. `emailer` is exercised indirectly by
`test_scans_workflow.py` and `test_full_workflow_defaults.py`.
`frontend/package.json` defines `dev`, `build`, `lint`, and `preview` — **there
is no test script and no test runner**; the project has no frontend test
framework.

**Decision**: Cover everything testable on the backend, and be explicit that
the frontend changes are verified by inspection and by the constitution's
pre-merge configuration check rather than by automated test.

- `test_study_config.py` — `get_study_label()` for a configured study, for a
  lowercase/whitespace-padded value, and for unset (FR-003, FR-008, FR-005).
- `test_config_endpoint.py` — `/api/config` exposes `study_label`, correct for
  a configured study and empty when unset.
- New `test_emailer_study_naming.py` — subject and body for a configured
  study, for `scans`, and for unset; and that the third-reviewer path produces
  the same naming as the first two (FR-025). `EMAIL_TEST_MODE=1` already
  returns the composed subject and a body preview without sending, so this
  needs no SMTP.

**Rationale**: Standing up a frontend test framework is a larger change than
this feature and would be its own decision. Claiming automated coverage that
does not exist would be worse than naming the gap.

**Known limitation, stated plainly**: FR-001 through FR-019 land in React
components with no automated verification available in this repository. The
constitution's Development Workflow gate ("frontend study-specific components
SHOULD be verified under at least one `STUDY_TYPE` configuration before
merge") applies, and that check is a manual one performed against a running
deployment. The backend tests above do cover the label rule itself, which is
the part the four headings and the email share, so the untested surface is the
rendering rather than the logic.
