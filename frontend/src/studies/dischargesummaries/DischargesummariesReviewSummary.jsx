// Read-only summary of one submitted dischargesummaries review, shown to
// admins in /events/edit (spec 013).
//
// Phase A (research D5, contracts/ui.md): no reviews are stored yet, so
// there is nothing to summarize. Phase B renders the recorded answers.

// eslint-disable-next-line no-unused-vars -- Phase B renders the review
function DischargesummariesReviewSummary({ review }) {
  return <div>No review recorded</div>
}

export default DischargesummariesReviewSummary
