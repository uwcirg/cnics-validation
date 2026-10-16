// Read-only summary of one submitted scans (DEXA) review, shown to admins in
// the "Review N" row of /events/edit (spec 012 FR-027, research D17).
// One line per body-region entry, in entry order:
//   "Hip — Left, T: -0.90"   "Other: Calcaneus — Right"   "Spine"
// Values that were not provided are left out, never shown as 0 (FR-006).

function regionLine(r) {
  const name = r.region_other ? `${r.region}: ${r.region_other}` : r.region
  const parts = []
  if (r.laterality) parts.push(r.laterality)
  if (r.t_score != null) parts.push(`T: ${r.t_score}`)
  if (r.z_score != null) parts.push(`Z: ${r.z_score}`)
  return parts.length ? `${name} — ${parts.join(', ')}` : name
}

function ScansReviewSummary({ review }) {
  const regions = [...(review?.regions || [])].sort((a, b) => a.position - b.position)
  if (regions.length === 0) return <div>No regions recorded</div>
  return (
    <>
      {regions.map((r) => (
        <div key={r.position}>{regionLine(r)}</div>
      ))}
    </>
  )
}

export default ScansReviewSummary
