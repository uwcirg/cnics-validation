import { useEffect, useState } from 'react'
import { showToast } from '../../components/Toast'

function VTEEventAssignThird() {
  const apiUrl = import.meta.env.VITE_API_URL || ''
  const [rows, setRows] = useState([])
  const [reviewers, setReviewers] = useState([])
  const [selected, setSelected] = useState({})
  const [reviewerId, setReviewerId] = useState('')
  const [status, setStatus] = useState(null)

  useEffect(() => {
    // Load events needing third review
    fetch(`${apiUrl}/api/events/by_status/third_review_needed?study=vte&limit=50&offset=0`, { credentials: 'include' })
      .then((r) => r.ok ? r.json() : Promise.reject(r))
      .then((j) => setRows(j.data || []))
      .catch(() => setRows([]))
    // Load reviewers (simple: from users table)
    fetch(`${apiUrl}/api/tables/users?limit=2000`, { credentials: 'include' })
      .then((r) => r.ok ? r.json() : Promise.reject(r))
      .then((j) => {
        const all = (j.data || []).filter((u) => u.third_reviewer_flag || u.reviewer_flag)
        setReviewers(all)
      })
      .catch(() => setReviewers([]))
  }, [apiUrl])

  const toggle = (id) => setSelected((prev) => ({ ...prev, [id]: !prev[id] }))

  const handleAssign = async (e) => {
    e.preventDefault()
    const ids = Object.keys(selected).filter((k) => selected[k]).map((k) => Number(k))
    if (!ids.length || !reviewerId) return
    setStatus('saving')
    try {
      const res = await fetch(`${apiUrl}/api/events/assign_many?study=vte`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ event_ids: ids, reviewer_id: Number(reviewerId), slot: 'third' }),
      })
      if (res.ok) {
        setStatus('saved')
        showToast('Assigned third reviewer to selected events.', 'success')
        // Clear selection
        setSelected({})
      } else if (res.status === 401) {
        setStatus('unauthorized')
        showToast('Login required.', 'warning')
      } else if (res.status === 403) {
        setStatus('forbidden')
        showToast('Not authorized to assign.', 'error')
      } else {
        setStatus('error')
        showToast('Failed to assign third reviewer.', 'error')
      }
    } catch {
      setStatus('error')
      showToast('Network or server error while assigning.', 'error')
    }
  }

  return (
    <div>
      <h1>Assign VTE Third Reviewer</h1>

      <h2>Step 1: Select Event(s)</h2>
      <div className="indent1">
        <table className="data-table">
          <thead>
            <tr>
              <th>Event Number</th>
              <th>Event Date</th>
              <th>Patient ID</th>
              <th>Site</th>
              <th>Last Review</th>
              <th>Assign</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r['ID']}>
                <td>{r['ID']}</td>
                <td>{r['Date']}</td>
                <td>{r['Patient ID'] || r['patient_id']}</td>
                <td>{r['Site'] || r['site']}</td>
                <td>{r['review2_date'] || r['review1_date'] || ''}</td>
                <td>
                  <input type="checkbox" checked={!!selected[r['ID']]} onChange={() => toggle(r['ID'])} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <h2 style={{ marginTop: '16px' }}>Step 2: Choose Third Reviewer</h2>
      <div className="indent1">
        <select value={reviewerId} onChange={(e) => setReviewerId(e.target.value)}>
          <option value="">Select third reviewer</option>
          {reviewers.map((u) => (
            <option key={u.id} value={u.id}>{u.username} ({u.site})</option>
          ))}
        </select>
      </div>

      <div style={{ marginTop: '12px' }}>
        <button onClick={handleAssign} disabled={!reviewerId}>
          Assign Third Reviewer
        </button>
      </div>

      {status === 'saving' && <p>Assigning...</p>}
      {status === 'saved' && <p>Assignment saved.</p>}
    </div>
  )
}

export default VTEEventAssignThird
