/**
 * The one rule for putting a deployment's study label into a page heading.
 *
 * Four workflow pages — upload, screening, scrubbing and review — each name
 * the study in their `<h1>`. Every one of them used to hard-code the literal
 * `MI`. They now compose their own fixed wording ("Packet for ", "Screen
 * charts for ", …) around this single helper, so the four cannot drift apart
 * (spec 010, FR-010) and so the empty-label case is handled once rather than
 * in four hand-written ternaries.
 *
 * The label comes from `GET /api/config` as `data.study_label` — already
 * trimmed and upper-cased by `get_study_label()` on the server — and is the
 * empty string when the deployment's `STUDY_TYPE` is unset. It is NOT derived
 * from `study_type`, which has already been defaulted to `mci` server-side;
 * see `flask_backend/study_config.py`.
 *
 * This is the sibling of {@link ./studyTitle.js} — pure, side-effect-free
 * presentation logic with no React dependency.
 */

/**
 * Join a study label and an event identifier with exactly one space.
 *
 * Behaviour, which the four headings depend on (FR-006 — never a blank, a
 * double space, a dangling separator, or a literal `undefined`):
 *
 *   headingSuffix('MCI', 4821)   === 'MCI 4821'
 *   headingSuffix('SCANS', 4821) === 'SCANS 4821'
 *   headingSuffix('', 4821)      === '4821'      // unset study — no study word
 *   headingSuffix('   ', 4821)   === '4821'      // blank is treated as unset
 *   headingSuffix(null, 4821)    === '4821'      // config not yet resolved
 *   headingSuffix(undefined, 4821) === '4821'    // prop not passed
 *
 * In every empty-label case the result is the bare identifier with no leading
 * space, so `Packet for {headingSuffix(...)}` reads `Packet for 4821` rather
 * than `Packet for  4821`. The page's own wording is never part of this
 * function: the four headings share nothing but the suffix, and centralizing
 * their sentences would make the pages harder to read (research R3).
 *
 * @param {string|null|undefined} studyLabel upper-cased study label, or empty
 * @param {string|number|null|undefined} eventId the event identifier
 * @returns {string} `"<LABEL> <id>"`, or just `"<id>"` when there is no label
 */
export function headingSuffix(studyLabel, eventId) {
  const label = (studyLabel ?? '').toString().trim()
  return label === '' ? `${eventId}` : `${label} ${eventId}`
}
