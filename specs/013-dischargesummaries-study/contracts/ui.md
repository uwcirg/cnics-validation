# Contract: User Interface

**Feature**: 013-dischargesummaries-study

Every difference below follows from `GET /api/config` (`workflow` flags,
`review_form`, `study_type` for the guidance map, `study_label`,
`study_title`). No component checks the study name except through the keyed
maps that already exist (`reviewGuidance.js`, `reviewForms.js`).

## Visible by role

| Role | Sees | Does not see |
|------|------|--------------|
| Site uploader | Upload page ("Events That Need Packets", own site only); re-upload page for own `uploaded` events; "no document" action | Scrub, assign, view-all, review queue |
| Admin | Add / Add Many / Imports; view-all with **To Be Uploaded, To Be Scrubbed, To Be Assigned, Awaiting Review, Done**; scrub page; assign page (one reviewer field) | To Be Screened, To Be Sent, second/third reviewer columns or actions |
| Reviewer | Own assigned events; review page with the scrubbed document link and the review form (Phase A: "not available yet" notice) | Other reviewers' events; original uploads |

The view-all list names in the table are the intended result. The task is to
confirm that the existing flag-driven rendering already produces exactly
this list for scrub-on / screen-off / send-off / one reviewer, and to fix
only what does not.

## Wording

- Banner and tab: `STUDY_TITLE`, e.g. "Discharge Summaries Validation" / "CNICS Discharge Summaries Validation".
- Headings: "Packet for Discharge Summary 4821", "Scrub charts for Discharge Summary 4821" and so on, via `study_label`.
- After upload, the confirmation names scrubbing as the next step. This already comes from the workflow flags (PR #108).
- Home page "Review packets should contain:" uses the `dischargesummaries` entry in `reviewGuidance.js` (research D7).
- "Review Instructions:" shows no link in Phase A. Phase B links `/files/instructions/dischargesummaries.html`.

## Review form registry

`frontend/src/components/review/reviewForms.js` gains a
`dischargesummaries` entry for form and summary, both in
`frontend/src/studies/dischargesummaries/`:

- **Phase A**: `DischargesummariesReviewForm` renders a notice, "The review
  form for this study is not available yet," with no inputs and no submit.
  `DischargesummariesReviewSummary` renders "No review recorded."
- **Phase B**: the real form, its validation mirrored from the backend
  module, and a read-only summary for admins.

## Errors surfaced

- Assign refused (400): shown in the assign page's existing error area.
- Re-upload refused (409): shown on the upload/re-upload page as the error text.
- Download refused (403): the download link shows "You do not have access to this event's document."
