# UI Contract: 014-packet-replace-review-edit

No new routes and no new dependencies. Pages are listed by path. Study-specific form changes stay in `frontend/src/studies/<study>/`, and there is no `isVtePage` branch.

## `/events/reupload` — "Re-upload Existing Packets" (`pages/EventReupload.jsx`)

- **Rows.** The list is now every event in the caller's replacement window except `created` (API `need_reupload`). Clicking a row opens `/events/upload?event_id=…`, as today.
- **Copy.**
  - The count line reads: "N event(s) can have their packet replaced. A packet can be replaced until it is sent to reviewers."
  - The empty state reads: "No packets can be replaced right now."
  - The heading is unchanged.

## `/events/upload?event_id=…` (`pages/EventUpload.jsx`)

- **When `details.packet_replaceable` is false and the event is not `created`:** hide the upload form and show "This packet has already been released to reviewers and can no longer be replaced here. Contact the systems operator."
- **When true and the event already has a packet (`upload_date` set):** label the submit button "Replace packet". Show a one-line note: "Replacing the packet sends it back through scrubbing and screening; any reviewer assignment is kept." Show the note only when scrubbing or screening is on for this deployment, using the `/api/config` flags already loaded by the app.
- **Errors.** Server errors are shown verbatim, as today (`EventUpload.jsx:225`).

## `/events/scrub?event_id=…` (`pages/EventScrub.jsx`)

- The upload form sends `packet_version` = `details.file_number`, as loaded with the page.
- On 400 or 409 the page shows the server's `error` text, not the generic "Upload failed". For 409, the file input is also cleared.

## `/events/screen?event_id=…` (`pages/EventScreen.jsx`)

- On 409 the page shows the server's `error` text.

## `/events/review?event_id=…` (`pages/EventReview.jsx`)

When `details.packet_replaced_at` is non-null, a notice appears above Step 1 in every state: "This event's packet was replaced on {packet_replaced_at}. If you downloaded it before then, download it again before reviewing." It is shown with warning styling.

| `mine` | Renders |
|---|---|
| null | The study's review form, empty (unchanged) |
| `editable: true` | Notice: "You submitted this review on {review_date}{, last edited {modified}}. You can change your answers until {reason}." The reason is "the other reviewer submits theirs" when there are two reviewers, and is omitted when there is one. Below the notice, the study's review form is pre-filled from `mine.answers`, with its submit button labelled "Save changes" (PUT). |
| `editable: false` | Notice: "You have already reviewed this event (submitted {review_date}{, last edited {modified}})." followed by `locked_reason`. Below the notice, the study's read-only review summary shows `mine.answers`. |

After a successful save the page re-fetches `GET /review` and shows "Your changes were saved." A 409 on save shows the server message and switches the page to the read-only state.

## Review form components

These props are added to every registered form (`components/review/reviewForms.js`):

| Prop | Type | Default | Meaning |
|---|---|---|---|
| `initialReview` | object \| null | null | One `get_reviews` item to pre-fill from |
| `mode` | `'create'` \| `'edit'` | `'create'` | `'edit'` sends PUT and uses "Save changes" |
| `onSaved` | function | none | Called after a 2xx response |
| `onConflict` | function(message) | none | Called with the server's `error` after a 409, so the page can re-fetch, switch to read-only and keep showing why (the form unmounts). *Added during implementation.* |

- **`studies/mci/MciReviewForm.jsx`**: maps review columns to state, for example `mci`, `abnormal_ce_values_flag` → `abnormalCeValues`, and `ci` → `'1'`/`'0'`.
- **`studies/scans/ScansReviewForm.jsx`**: maps `regions[]` to entries, keeping their order.
- **`studies/dischargesummaries/DischargesummariesReviewForm.jsx`**: accepts and ignores the props (Phase A has no form).

## `/events/edit?event_id=…` (`pages/EventEdit.jsx`, admin)

Each "Review N" row shows "Last edited: {modified}" under the summary when `modified` is non-null. The page renders this itself, so the per-study summary components are unchanged.
