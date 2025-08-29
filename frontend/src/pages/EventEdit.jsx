import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

function EventEdit() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const apiUrl = import.meta.env.VITE_API_URL || ''
  const eventId = searchParams.get('event_id')

  const [details, setDetails] = useState(null)
  const [sites, setSites] = useState([])
  const [form, setForm] = useState({ site_patient_id: '', site: '', event_date: '' })
  const [status, setStatus] = useState(null)
  const [criteriaList, setCriteriaList] = useState([])
  const [criteriaForm, setCriteriaForm] = useState({ name: '', value: '' })
  const [criteriaStatus, setCriteriaStatus] = useState(null)
  const [solicitations, setSolicitations] = useState([])
  const [solicitationForm, setSolicitationForm] = useState({ date: '', contact: '' })
  const [solicitationStatus, setSolicitationStatus] = useState(null)

  useEffect(() => {
    if (!eventId) return
    // Load event details
    fetch(`${apiUrl}/api/events/${eventId}`, { credentials: 'include' })
      .then((res) => res.ok ? res.json() : Promise.reject(res))
      .then((json) => {
        const d = json.data || null
        setDetails(d)
        setForm({
          site_patient_id: d?.site_patient_id || '',
          site: d?.site || '',
          event_date: d?.event_date || '',
        })
      })
      .catch(() => setDetails(null))
    // Load sites from patients table (distinct)
    fetch(`${apiUrl}/api/tables/patients?limit=2000`, { credentials: 'include' })
      .then((res) => res.ok ? res.json() : Promise.reject(res))
      .then((json) => {
        const rows = json.data || []
        const uniqueSites = Array.from(new Set(rows.map((r) => r.site).filter(Boolean))).sort()
        setSites(uniqueSites)
      })
      .catch(() => setSites([]))

    // Load criteria for this event (client-side filter from table API)
    fetch(`${apiUrl}/api/tables/criterias?limit=5000`, { credentials: 'include' })
      .then((res) => res.ok ? res.json() : Promise.reject(res))
      .then((json) => {
        const rows = (json.data || []).filter((r) => String(r.event_id) === String(eventId))
        setCriteriaList(rows)
      })
      .catch(() => setCriteriaList([]))

    // Load solicitations for this event
    fetch(`${apiUrl}/api/tables/solicitations?limit=2000`, { credentials: 'include' })
      .then((res) => res.ok ? res.json() : Promise.reject(res))
      .then((json) => {
        const rows = (json.data || []).filter((r) => String(r.event_id) === String(eventId))
        setSolicitations(rows)
      })
      .catch(() => setSolicitations([]))
  }, [apiUrl, eventId])

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleMainSubmit = async (e) => {
    e.preventDefault()
    setStatus('saving')
    try {
      // Placeholder: backend update endpoint not yet implemented
      const res = await fetch(`${apiUrl}/api/events/${eventId}`, {
        method: 'PUT',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          site_patient_id: form.site_patient_id,
          site: form.site,
          event_date: form.event_date,
        }),
      })
      setStatus(res.ok ? 'saved' : 'error')
    } catch {
      setStatus('error')
    }
  }

  const handleCriteriaChange = (e) => {
    const { name, value } = e.target
    setCriteriaForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleCriteriaSubmit = async (e) => {
    e.preventDefault()
    if (!criteriaForm.name || !criteriaForm.value) return
    setCriteriaStatus('saving')
    try {
      const res = await fetch(`${apiUrl}/api/criteria`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ event_id: eventId, name: criteriaForm.name, value: criteriaForm.value }),
      })
      if (res.ok) {
        setCriteriaStatus('saved')
        setCriteriaForm({ name: '', value: '' })
        // Optimistic refresh
        setCriteriaList((prev) => ([...prev, { event_id: Number(eventId), name: criteriaForm.name, value: criteriaForm.value }]))
      } else {
        setCriteriaStatus('error')
      }
    } catch {
      setCriteriaStatus('error')
    }
  }

  const handleSolicitationChange = (e) => {
    const { name, value } = e.target
    setSolicitationForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleSolicitationSubmit = async (e) => {
    e.preventDefault()
    if (!solicitationForm.date || !solicitationForm.contact) return
    setSolicitationStatus('saving')
    try {
      const res = await fetch(`${apiUrl}/api/solicitations`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ event_id: eventId, date: solicitationForm.date, contact: solicitationForm.contact }),
      })
      if (res.ok) {
        setSolicitationStatus('saved')
        setSolicitationForm({ date: '', contact: '' })
        setSolicitations((prev) => ([...prev, { event_id: Number(eventId), date: solicitationForm.date, contact: solicitationForm.contact }]))
      } else {
        setSolicitationStatus('error')
      }
    } catch {
      setSolicitationStatus('error')
    }
  }

  return (
    <div>
      <h2>Edit Event MI {eventId}</h2>

      {details && (
        <p>
          <a href={`${apiUrl}/api/events/download/${eventId}`} target="_blank" rel="noreferrer">Download charts for this event</a>
        </p>
      )}

      <h3>Main Details</h3>
      <div className="indent1">
        <form onSubmit={handleMainSubmit}>
          <table>
            <tbody>
              <tr>
                <th>Site Patient Id</th>
                <td>
                  <input name="site_patient_id" value={form.site_patient_id} onChange={handleChange} />
                </td>
              </tr>
              <tr>
                <th>Site</th>
                <td>
                  <select name="site" value={form.site} onChange={handleChange}>
                    <option value="">Select site</option>
                    {sites.map((s) => (
                      <option key={s} value={s}>{s}</option>
                    ))}
                  </select>
                </td>
              </tr>
              <tr>
                <th>Event date</th>
                <td>
                  <input name="event_date" type="date" value={form.event_date} onChange={handleChange} />
                </td>
              </tr>
              {details && (
                <>
                  <tr><th>Status:</th><td>{details.status || ''}</td></tr>
                  <tr><th>Creation Date:</th><td>{details.add_date || ''}</td></tr>
                  <tr><th>Creator:</th><td>{details.creator_username || ''}</td></tr>
                  {details.upload_date && (<>
                    <tr><th>Upload Date:</th><td>{details.upload_date}</td></tr>
                    <tr><th>Uploader:</th><td>{details.uploader_username || ''}</td></tr>
                  </>)}
                  {details.markNoPacket_date && (<>
                    <tr><th>Date packet was marked as not available:</th><td>{details.markNoPacket_date}</td></tr>
                  </>)}
                  {details.scrub_date && (<>
                    <tr><th>Scrub Date:</th><td>{details.scrub_date}</td></tr>
                    <tr><th>Scrubber:</th><td>{details.scrubber_username || ''}</td></tr>
                    {details.rescrub_message && <tr><th>Rescrub Message:</th><td>{details.rescrub_message}</td></tr>}
                  </>)}
                  {details.screen_date && (<>
                    <tr><th>Screen Date:</th><td>{details.screen_date}</td></tr>
                    <tr><th>Screener:</th><td>{details.screener_username || ''}</td></tr>
                    {details.reject_message && <tr><th>Reject Message:</th><td>{details.reject_message}</td></tr>}
                  </>)}
                  {details.assign_date && (<>
                    <tr><th>Assign Date:</th><td>{details.assign_date}</td></tr>
                    <tr><th>Assigner:</th><td>{details.assigner_username || ''}</td></tr>
                    <tr><th>Reviewer 1:</th><td>{details.reviewer1_username || ''}</td></tr>
                    <tr><th>Reviewer 2:</th><td>{details.reviewer2_username || ''}</td></tr>
                  </>)}
                  {details.send_date && (<>
                    <tr><th>Send Date:</th><td>{details.send_date}</td></tr>
                    <tr><th>Sender:</th><td>{details.sender_username || ''}</td></tr>
                  </>)}
                  {details.review1_date && (<>
                    <tr><th>Review 1 Date:</th><td>{details.review1_date}</td></tr>
                  </>)}
                  {details.review2_date && (<>
                    <tr><th>Review 2 Date:</th><td>{details.review2_date}</td></tr>
                  </>)}
                  {details.assign3rd_date && (<>
                    <tr><th>Third Review Assign Date:</th><td>{details.assign3rd_date}</td></tr>
                    <tr><th>Third Review Assigner:</th><td>{details.assigner3rd_username || ''}</td></tr>
                    <tr><th>Reviewer 3:</th><td>{details.reviewer3_username || ''}</td></tr>
                  </>)}
                  {details.review3_date && (<>
                    <tr><th>Review 3 Date:</th><td>{details.review3_date}</td></tr>
                  </>)}
                </>
              )}
              <tr>
                <td colSpan="2">
                  <button type="submit">Edit</button>
                </td>
              </tr>
            </tbody>
          </table>
        </form>
      </div>

      <br />
      <hr />

      <h3>Criteria</h3>
      <div className="indent1">
        {criteriaList.length === 0 ? (
          <p>No criteria currently listed.</p>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Value</th>
              </tr>
            </thead>
            <tbody>
              {criteriaList.map((c, idx) => (
                <tr key={idx}>
                  <td>{c.name}</td>
                  <td>{c.value}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        <div style={{ marginTop: '12px' }}>
          <strong>Add Criterion</strong>
          <form onSubmit={handleCriteriaSubmit} style={{ marginTop: '6px' }}>
            <table>
              <tbody>
                <tr>
                  <th><label htmlFor="criteria_name">Name</label></th>
                  <td>
                    <input id="criteria_name" name="name" value={criteriaForm.name} onChange={handleCriteriaChange} />
                  </td>
                </tr>
                <tr>
                  <th><label htmlFor="criteria_value">Value</label></th>
                  <td>
                    <input id="criteria_value" name="value" value={criteriaForm.value} onChange={handleCriteriaChange} />
                  </td>
                </tr>
                <tr>
                  <td colSpan="2"><button type="submit">Add</button></td>
                </tr>
              </tbody>
            </table>
          </form>
          {criteriaStatus === 'saving' && <p>Saving...</p>}
          {criteriaStatus === 'saved' && <p>Criterion saved.</p>}
          {criteriaStatus === 'error' && <p>Failed to save criterion.</p>}
        </div>
      </div>

      <br />
      <hr />

      <h3>Chart Solicitations</h3>
      <div className="indent1">
        {solicitations.length === 0 ? (
          <p>No solicitations currently listed.</p>
        ) : (
          <table className="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Contact</th>
              </tr>
            </thead>
            <tbody>
              {solicitations.map((s, idx) => (
                <tr key={idx}>
                  <td>{s.date}</td>
                  <td>{s.contact}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        <div style={{ marginTop: '12px' }}>
          <strong>Add Solicitation</strong>
          <form onSubmit={handleSolicitationSubmit} style={{ marginTop: '6px' }}>
            <table>
              <tbody>
                <tr>
                  <th><label htmlFor="sol_date">Date</label></th>
                  <td>
                    <input id="sol_date" name="date" type="date" value={solicitationForm.date} onChange={handleSolicitationChange} />
                  </td>
                </tr>
                <tr>
                  <th><label htmlFor="sol_contact">Contact information</label></th>
                  <td>
                    <input id="sol_contact" name="contact" value={solicitationForm.contact} onChange={handleSolicitationChange} />
                  </td>
                </tr>
                <tr>
                  <td colSpan="2"><button type="submit">Add</button></td>
                </tr>
              </tbody>
            </table>
          </form>
          {solicitationStatus === 'saving' && <p>Saving...</p>}
          {solicitationStatus === 'saved' && <p>Solicitation saved.</p>}
          {solicitationStatus === 'error' && <p>Failed to save solicitation.</p>}
        </div>
      </div>

      <p style={{ marginTop: '16px' }}>
        <a href="#" onClick={(e) => { e.preventDefault(); navigate('/events/viewAll') }}>
          &lt; Return to View All Events
        </a>
      </p>
    </div>
  )
}

export default EventEdit
