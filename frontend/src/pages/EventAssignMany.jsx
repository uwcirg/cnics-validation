function EventAssignMany({ workflow }) {
  // Reviewer-slot availability is driven by the resolved workflow control,
  // never by a hard-coded study name (FR-019, FR-021). In a single-reviewer
  // configuration only the first reviewer slot is offered.
  const singleReviewer = (workflow && workflow.reviewer_count) === 1

  return (
    <div>
      <h1>Assign Charts</h1>
      <p>This page will allow assigning many events to a reviewer.</p>
      {singleReviewer ? (
        <p>
          This deployment is configured for a single reviewer — events are
          assigned to one (first) reviewer. Second- and third-reviewer
          assignment is unavailable.
        </p>
      ) : (
        <p>Events may be assigned to first, second, or third reviewers.</p>
      )}
    </div>
  )
}

export default EventAssignMany
