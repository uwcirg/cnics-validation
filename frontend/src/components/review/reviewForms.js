/**
 * Registry of per-study review forms (spec 012, research D6).
 *
 * The review page (`pages/EventReview.jsx`) is shared by every study: the
 * instructions box, heading, date, and Step 1 download are the same. Step 2
 * onwards differs by study, so each study's form is its own component under
 * `frontend/src/studies/<study>/` (constitution Principle IV, mechanism 3)
 * and this map picks one.
 *
 * The key is `review_form` from GET /api/config, which the backend takes
 * from the study module it actually uses (`flask_backend/models/studies/`).
 * Keying on that value rather than on `study_type` means a dormant or
 * unknown study, which the backend serves with the `mci` module, also gets
 * the `mci` form here. The form shown and the body the backend accepts
 * cannot disagree.
 *
 * Note that `studies/<study>/` holds study *components* only. It is not the
 * retired `studies/vte/` pattern of copied pages, parallel `/vte/*` routes,
 * and `isVtePage` branching: there is one page and one route.
 */

import MciReviewForm from '../../studies/mci/MciReviewForm'
import ScansReviewForm from '../../studies/scans/ScansReviewForm'

const REVIEW_FORMS = {
  mci: MciReviewForm,
  scans: ScansReviewForm,
}

/**
 * Resolve the review form component for a `review_form` id.
 *
 * @param {string} reviewForm `review_form` from GET /api/config
 * @returns {Function} a React component taking `{ eventId, apiUrl }`;
 *   `MciReviewForm` when the id is empty or unrecognized
 */
export function resolveReviewForm(reviewForm) {
  const key = (reviewForm ?? '').toString().trim().toLowerCase()
  return REVIEW_FORMS[key] ?? MciReviewForm
}
