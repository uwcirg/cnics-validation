/**
 * Per-study content for the two home-page guidance boxes near the bottom of
 * the page: "Review packets should contain:" and "Review Instructions:".
 *
 * The two `<h3>` headers are NOT defined here — they are constant across every
 * study and live literally in `Home.jsx` (spec 007, FR-001). Only the *body*
 * of each box varies by study type: an ordered list of display items, plus an
 * optional set of downloadable/viewable file links (spec 007, FR-002).
 *
 * This is a pure, side-effect-free presentation map keyed on the deployment's
 * `STUDY_TYPE` (delivered to the frontend on `GET /api/config` as
 * `data.study_type`). It is the sibling of {@link ./studyTitle.js} and follows
 * the same "always resolve to something sensible" philosophy: an unrecognized
 * or unset study type falls back to the `mci` content (FR-008), which mirrors
 * the system-wide default study type.
 *
 * Shapes (see specs/007-study-aware-review-sections/data-model.md):
 *
 *   Link   = { label: string, href: string, download: boolean }
 *   Box    = { items: string[], linkLabel?: string, links: Link[] }
 *   Guide  = { packets: Box, instructions: Box }
 *
 * A `Box` with an empty `links` array renders no link area at all — no
 * orphaned "Full instructions:" / "View as:" label (FR-004).
 */

// Per-study guidance content. Entries are keyed by lowercased study type.
// `mci` doubles as the fallback for any unrecognized/unset study (FR-008).
const STUDY_GUIDANCE = {
  // MI / cardiology — the original home-page content, preserved verbatim
  // (spec 007, FR-005). Also the default fallback.
  mci: {
    packets: {
      items: [
        "Physician's notes closest to potential Event date",
        'Outpatient cardiology consultations',
        'In-patient cardiology notes or consults',
        'Baseline ECG',
        'First 2 ECGs after admission or in-hospital event',
        'Related procedure and diagnostic test results',
        'Related laboratory evidence',
        'Please redact the personal identifiers including name, birthday, and hospital number',
      ],
      linkLabel: 'Full instructions:',
      links: [
        { label: '.doc', href: '/files/CNICS MI Review packet assembly instructions.doc', download: true },
        { label: '.pdf', href: '/files/CNICS MI Review packet assembly instructions.pdf', download: false },
      ],
    },
    instructions: {
      items: [],
      linkLabel: 'View as:',
      links: [
        { label: '.doc', href: '/files/CNICS MI reviewer instructions.doc', download: true },
        { label: '.pdf', href: '/files/CNICS MI reviewer instructions.pdf', download: false },
      ],
    },
  },
  // DEXA scans — short, study-specific guidance with no linked files
  // (spec 007, FR-006 / FR-007). Empty `links` ⇒ no link area shown.
  scans: {
    packets: {
      items: [
        'DEXA scan reports',
        'Please redact their PHI (names, birthdate, etc)',
      ],
      links: [],
    },
    instructions: {
      items: ['No additional instructions'],
      links: [],
    },
  },
}

// The empty result used only when no entry matches and no `mci` fallback is
// defined. Keeps the renderer safe (two empty boxes) rather than throwing.
const EMPTY_GUIDANCE = {
  packets: { items: [], links: [] },
  instructions: { items: [], links: [] },
}

/**
 * Resolve the review-guidance content for a deployment's study type.
 *
 * The study type is trimmed and lowercased before matching. A defined entry
 * wins; otherwise the `mci` entry is used as the fallback (FR-008); if even
 * `mci` is undefined, a safe empty result is returned so the renderer never
 * crashes.
 *
 * @param {string|null|undefined} studyType raw study type
 * @returns {{packets: object, instructions: object}} guidance content, never null
 */
export function resolveReviewGuidance(studyType) {
  const key = (studyType ?? '').toString().trim().toLowerCase()
  return STUDY_GUIDANCE[key] || STUDY_GUIDANCE.mci || EMPTY_GUIDANCE
}
