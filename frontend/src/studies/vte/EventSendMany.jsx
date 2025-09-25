import { useEffect, useState } from 'react'
import { showToast } from '../../components/Toast'

function VTEEventSendMany() {
  const apiUrl = import.meta.env.VITE_API_URL || ''
  const [rows, setRows] = useState([])
  const [selected, setSelected] = useState({})
  const [status, setStatus] = useState(null)

  useEffect(() => {
    async function load() {
      try {
        // Fetch events that are assigned but not yet sent
        const listRes = await fetch(`${apiUrl}/api/events/by_status/assigned?study=vte&limit=100&offset=0`, { credentials: 'include' })
        if (!listRes.ok) throw new Error('list')
        const listJson = await listRes.json()
        const base = listJson.data || []
        // Enrich with details to get assign_date and reviewers
        const details = await Promise.all(base.map(async (r) => {
          const id = r['ID'] || r.id
          try {
            const res = await fetch(`${apiUrl}/api/events/${id}?study=vte`, { credentials: 'include' })
            if (!res.ok) return { id }
            const j = await res.json()
            return { id, ...j.data }
          } catch { return { id } }
        }))
        const lookup = new Map(details.map((d) => [d.id, d]))
        const enriched = base.map((r) => {
          const id = r['ID'] || r.id
          const d = lookup.get(id) || {}
          return {
            ID: id,
            Date: r['Date'] || d.event_date || '',
            assign_date: d.assign_date || '',
            reviewer1: d.reviewer1_username || '',
            reviewer2: d.reviewer2_username || '',
          }
        })
        setRows(enriched)
      } catch {
        setRows([])
      }
    }
    load()
  }, [apiUrl])

  const toggle = (id) => setSelected((prev) => ({ ...prev, [id]: !prev[id] }))

  const handleSend = async (e) => {
    e.preventDefault()
    const ids = Object.keys(selected).filter((k) => selected[k]).map((k) => Number(k))
    if (!ids.length) return
    setStatus('saving')
    try {
      const res = await fetch(`${apiUrl}/api/events/send_many?study=vte`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ event_ids: ids }),
      })
      if (res.ok) {
        setStatus('saved')
        showToast('VTE charts sent to reviewers for selected events.', 'success')
        // Clear selection
        setSelected({})
      } else if (res.status === 401) {
        setStatus('unauthorized')
        showToast('Login required.', 'warning')
      } else if (res.status === 403) {
        setStatus('forbidden')
        showToast('Not authorized to send.', 'error')
      } else {
        setStatus('error')
        showToast('Failed to send charts.', 'error')
      }
    } catch {
      setStatus('error')
      showToast('Network or server error while sending charts.', 'error')
    }
  }

  return (
    <div>
      <h1>Send VTE Charts to Reviewers</h1>
      <table className="data-table">
        <thead>
          <tr>
            <th>Event Number</th>
            <th>Event Date</th>
            <th>Patient ID</th>
            <th>Site</th>
            <th>Assigned</th>
            <th>Reviewer 1</th>
            <th>Reviewer 2</th>
            <th>Send</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.ID}>
              <td>{r.ID}</td>
              <td>{r.Date}</td>
              <td>{r['Patient ID'] || r['patient_id']}</td>
              <td>{r['Site'] || r['site']}</td>
              <td>{r.assign_date}</td>
              <td>{r.reviewer1}</td>
              <td>{r.reviewer2}</td>
              <td>
                <input type="checkbox" checked={!!selected[r.ID]} onChange={() => toggle(r.ID)} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <div style={{ marginTop: '12px' }}>
        <button onClick={handleSend}>Send Charts</button>
      </div>

      {status === 'saving' && <p>Sending...</p>}
      {status === 'saved' && <p>Sent.</p>}
    </div>
  )
}

export default VTEEventSendMany
