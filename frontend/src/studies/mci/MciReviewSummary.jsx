// Read-only summary of one submitted MI review, shown to admins in the
// "Review N" row of /events/edit (spec 012 FR-027, research D17). The lines
// follow the legacy MCI event page (mci.event.edit.Screenshot.1.2026-10-14),
// e.g. "MI: Probable", "Criteria: Chest pain; ECG changes;", "Cardiac Cath: No".
// The labels are that page's short forms, not MciReviewForm's full questions.
// Unanswered columns are left out.

const yesNo = (v) => (Number(v) === 1 ? 'Yes' : 'No')

// The MI criteria checkboxes, in MciReviewForm's order.
function criteriaText(review) {
  const items = []
  if (Number(review.abnormal_ce_values_flag) === 1) {
    items.push(`Abnormal cardiac enzyme values${review.ce_criteria ? ` (${review.ce_criteria})` : ''}`)
  }
  if (Number(review.chest_pain_flag) === 1) items.push('Chest pain')
  if (Number(review.ecg_changes_flag) === 1) items.push('ECG changes')
  if (Number(review.lvm_by_imaging_flag) === 1) {
    items.push('Loss of viable myocardium or regional wall abnormalities by imaging')
  }
  return items.length ? items.map((i) => `${i};`).join(' ') : null
}

// [label, column, format]; format defaults to the raw value. Order follows
// MciReviewForm's questions.
const LINES = [
  ['MI', 'mci'],
  ['Criteria', null, criteriaText],
  ['Cardiac intervention', 'ci', yesNo],
  ['Type of CI', 'ci_type'],
  ['Primary/Secondary', 'type'],
  ['Secondary cause', 'secondary_cause'],
  ['Other cause', 'other_cause'],
  ['ECG Type', 'ecg_type'],
  ['Possible false positive?', 'false_positive_flag', yesNo],
  ['False positive reason', 'false_positive_reason'],
  ['False positive other cause', 'false_positive_other_cause'],
  ['Current tobacco use?', 'current_tobacco_use_flag', yesNo],
  ['Past tobacco use?', 'past_tobacco_use_flag', yesNo],
  ['Past or current cocaine or crack use?', 'cocaine_use_flag', yesNo],
  ['Family history of coronary artery disease?', 'family_history_flag', yesNo],
  ['Cardiac Cath', 'cardiac_cath', yesNo],
]

function MciReviewSummary({ review }) {
  if (!review) return null
  const lines = []
  for (const [label, column, format] of LINES) {
    if (column === null) {
      const text = format(review)
      if (text) lines.push([label, text])
      continue
    }
    const value = review[column]
    if (value === null || value === undefined || value === '') continue
    lines.push([label, format ? format(value) : value])
  }
  return (
    <>
      {lines.map(([label, text]) => (
        <div key={label}>{label}: {text}</div>
      ))}
    </>
  )
}

export default MciReviewSummary
