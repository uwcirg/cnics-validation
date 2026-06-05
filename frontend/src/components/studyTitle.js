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

/**
 * Resolve the banner study title for a deployment.
 *
 * A configured `STUDY_TITLE` override (delivered on `GET /api/config` as
 * `data.study_title`, e.g. "DEXA Scans Validation") is shown verbatim and
 * always wins. When it is blank/missing the title is derived from the study
 * type via {@link formatStudyTitle}. Returns `null` when neither yields a
 * title, so the banner renders the logo alone.
 *
 * @param {string|null|undefined} configuredTitle the STUDY_TITLE override
 * @param {string|null|undefined} studyType raw study type
 * @returns {string|null} the banner title, or null when none should show
 */
export function resolveStudyTitle(configuredTitle, studyType) {
  const explicit = (configuredTitle ?? '').toString().trim()
  if (explicit !== '') return explicit
  return formatStudyTitle(studyType)
}

/**
 * Build the browser tab title: the constant "CNICS" prefix joined with the
 * resolved banner study title (e.g. "CNICS DEXA Scans Validation"). Falls
 * back to "CNICS" alone when no study title is resolved.
 *
 * @param {string|null|undefined} resolvedTitle the resolved banner title
 * @returns {string} the document title
 */
export function documentTitle(resolvedTitle) {
  const t = (resolvedTitle ?? '').toString().trim()
  return t === '' ? 'CNICS' : `CNICS ${t}`
}
