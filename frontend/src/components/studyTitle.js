/**
 * Derive the banner's display title from the deployment's study type.
 *
 * The study type originates from the `STUDY_TYPE` environment variable and
 * reaches the frontend on `GET /api/config` as `data.study_type`. Turning it
 * into a display label is a pure presentation concern
 * (spec 005-banner-restyle, FR-004 / FR-005 / FR-006 / FR-010 / FR-011):
 *
 *   - empty / null / undefined  -> `null`  (caller renders no title element,
 *                                  never a broken "undefined Project")
 *   - "scans"                   -> "Scans Project"  (title case, not all-caps)
 *   - anything else             -> "<UPPERCASE> Project"  (acronym studies
 *                                  such as CVA and MCI, plus the uppercase
 *                                  fallback for any unrecognized study type)
 *
 * Input is matched case-insensitively and trimmed first.
 *
 * @param {string|null|undefined} studyType raw study type
 * @returns {string|null} the display title, or null when no title should show
 */
export function formatStudyTitle(studyType) {
  const trimmed = (studyType ?? '').toString().trim()
  if (trimmed === '') return null
  const base = trimmed.toLowerCase() === 'scans' ? 'Scans' : trimmed.toUpperCase()
  return `${base} Project`
}
