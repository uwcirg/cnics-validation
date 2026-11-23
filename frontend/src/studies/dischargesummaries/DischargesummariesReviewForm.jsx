// The dischargesummaries review form: Step 2 onwards of the review page
// (spec 013). Selected by components/review/reviewForms.js from
// `review_form: "dischargesummaries"` on GET /api/config.
//
// Phase A (research D5, contracts/ui.md): the review questions are not known
// yet (FR-011), so this shows a notice with no inputs and no submit button.
// The backend refuses submissions with the same reason. Phase B replaces it
// with the real form.

// eslint-disable-next-line no-unused-vars -- Phase B uses eventId and apiUrl
function DischargesummariesReviewForm({ eventId, apiUrl }) {
  return <p role="status">The review form for this study is not available yet.</p>
}

export default DischargesummariesReviewForm
