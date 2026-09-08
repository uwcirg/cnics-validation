# Study-aware naming on the shared event pages and reviewer emails

Replaces the study name hard-coded as the literal `MI` across four page
headings, two document-link areas, and the reviewer-assignment email with the
deployment's configured study.

## Studies affected

**Shared code — all studies.** Nothing here is scoped to one deployment: the
four workflow pages, `reviewGuidance.js`, and `emailer.py` are read by every
study. Verified under `STUDY_TYPE=mci`, `scans`, `cva`, and unset.

## Accepted user-visible changes

Three changes are deliberate and were accepted in the spec:

1. **`MI` → `MCI` in the four headings on the cardiology deployment.** The
   headings now render the configured study identity, which is `mci`; the
   literal they replaced was `MI`. Same study, different token (spec FR-011).
2. **`SCANS` on a scans deployment** — and the correct label on any study
   configured in future, with no code change needed (FR-003).
3. **The assignment email loses its `Myocardial Infarction (MI)` expansion.**
   The body now opens `You have been assigned a review in the MCI study.` The
   full clinical name exists for exactly one study and cannot be derived for
   any other, so it is dropped rather than made per-study content (FR-023).

## Behaviour before this change, as observed

Recorded per Constitution Principle VI before being modified.

**Eight literal strings**, all naming `MI` regardless of the deployment:

| File | Line | String |
|---|---|---|
| `frontend/src/pages/EventUpload.jsx` | 247 | `Packet for MI {eventId}` |
| `frontend/src/pages/EventScreen.jsx` | 53 | `Screen charts for MI {eventId}` |
| `frontend/src/pages/EventScrub.jsx` | 73 | `Upload scrubbed charts for MI {eventId}` |
| `frontend/src/pages/EventReview.jsx` | 187 | `Review event: MI {eventId}` |
| `frontend/src/pages/EventScrub.jsx` | 67 | `/files/CNICS MI event scrubbing protocol.doc` |
| `frontend/src/pages/EventScrub.jsx` | 69 | `/files/CNICS MI event scrubbing protocol.pdf` |
| `frontend/src/pages/EventReview.jsx` | 181 | `/files/CNICS MI reviewer instructions.doc` |
| `frontend/src/pages/EventReview.jsx` | 183 | `/files/CNICS MI reviewer instructions.pdf` |

**The assignment email** (`flask_backend/emailer.py`), shared by all three
reviewer slots:

- Subject, line 59: `f"{prefix} MI Review Assignment – Event {event_id}"`
- Body opening, lines 77-80:
  `"You have been assigned a Myocardial Infarction (MI) review."`

Both reached real reviewers' inboxes on non-cardiology deployments.

## How it works

Two mechanisms, because the material differs:

- **Headings and the email** take a mechanical upper-cased study identity.
  New `get_study_label()` in `flask_backend/study_config.py` reads the raw
  `STUDY_TYPE` and returns `""` when unset, published to the browser as an
  additive `study_label` field on `GET /api/config`.
- **Document links** take per-study recorded content from
  `reviewGuidance.js`, because the files on disk are named `MI` while the
  identity is `mci`, and most studies have no documents at all.

`get_study_type()` is unchanged and keeps its `mci` default. The two accessors
differ **only** on an unset variable, and that difference is the point: a
heading or an email built from `get_study_type()` would silently name MCI on a
deployment that was never configured. The unset assertions in
`test_study_config.py`, `test_config_endpoint.py` and
`test_emailer_study_naming.py` are load-bearing — do not relax them.

## Gates

- `openapi.json` regenerated in this PR (`python -m flask_backend.generate_openapi`);
  the diff is the two added `study_label` lines.
- No schema change, no migration. `init/` untouched.
- Backend: 155 passed (baseline 134; +21 new).
- Frontend: `npm run build` clean; `npm run lint` unchanged at 31 problems,
  all pre-existing in `EventUpload.jsx`, `Home.jsx` and `studies/vte/` — this
  change introduces none.

## Known bounded inconsistency

`resolveReviewGuidance()` still falls back to `mci` content for an
unrecognized study (spec 007 FR-008); the new `resolveStudyDocuments()`
deliberately does not (FR-017). Both behaviours are kept, scoped to different
surfaces, and the divergence only appears on a misconfigured deployment. Both
functions carry comments saying so. Tracked as follow-up.
