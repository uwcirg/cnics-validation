import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import '../../pages/Home.css'

function VTEEventEdit() {
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
    fetch(`${apiUrl}/api/events/${eventId}`, { 
      credentials: 'include',
      headers: { 'X-Study-Mode': 'vte' }
    })
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
    // Load sites from patients_view (distinct)
    fetch(`${apiUrl}/api/tables/patients_view?limit=2000`, { credentials: 'include' })
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

    // Load solicitations for this event (client-side filter from table API)
    fetch(`${apiUrl}/api/tables/solicitations?limit=5000`, { credentials: 'include' })
      .then((res) => res.ok ? res.json() : Promise.reject(res))
      .then((json) => {
        const rows = (json.data || []).filter((r) => String(r.event_id) === String(eventId))
        setSolicitations(rows)
      })
      .catch(() => setSolicitations([]))
  }, [eventId, apiUrl])

  const handleFormChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleFormSubmit = async (e) => {
    e.preventDefault()
    setStatus('saving')
    try {
      const res = await fetch(`${apiUrl}/api/events/${eventId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-Study-Mode': 'vte' },
        credentials: 'include',
        body: JSON.stringify(form),
      })
      if (res.ok) {
        setStatus('saved')
        // Reload details
        const json = await res.json()
        setDetails(json.data || null)
      } else {
        setStatus('error')
      }
    } catch {
      setStatus('error')
    }
  }

  const handleCriteriaSubmit = async (e) => {
    e.preventDefault()
    if (!criteriaForm.name.trim() || !criteriaForm.value.trim()) return
    setCriteriaStatus('saving')
    try {
      const res = await fetch(`${apiUrl}/api/criterias`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          event_id: eventId,
          name: criteriaForm.name,
          value: criteriaForm.value,
        }),
      })
      if (res.ok) {
        setCriteriaStatus('saved')
        setCriteriaForm({ name: '', value: '' })
        // Reload criteria
        const json = await res.json()
        setCriteriaList((prev) => [...prev, json.data])
      } else {
        setCriteriaStatus('error')
      }
    } catch {
      setCriteriaStatus('error')
    }
  }

  const handleSolicitationSubmit = async (e) => {
    e.preventDefault()
    if (!solicitationForm.date.trim() || !solicitationForm.contact.trim()) return
    setSolicitationStatus('saving')
    try {
      const res = await fetch(`${apiUrl}/api/solicitations`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          event_id: eventId,
          date: solicitationForm.date,
          contact: solicitationForm.contact,
        }),
      })
      if (res.ok) {
        setSolicitationStatus('saved')
        setSolicitationForm({ date: '', contact: '' })
        // Reload solicitations
        const json = await res.json()
        setSolicitations((prev) => [...prev, json.data])
      } else {
        setSolicitationStatus('error')
      }
    } catch {
      setSolicitationStatus('error')
    }
  }

  if (!eventId) {
    return <div>No event ID provided</div>
  }

  if (!details) {
    return <div>Loading event details...</div>
  }

  return (
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src="/cnics_logo.png" alt="CNICS" />
      <h1>Edit VTE Event {eventId}</h1>
      
      <section>
        <h3>Event Details</h3>
        <form onSubmit={handleFormSubmit}>
          <div>
            <label>
              Site Patient ID:
              <input
                type="text"
                name="site_patient_id"
                value={form.site_patient_id}
                onChange={handleFormChange}
                required
              />
            </label>
          </div>
          <div>
            <label>
              Site:
              <select name="site" value={form.site} onChange={handleFormChange} required>
                <option value="">Select site...</option>
                {sites.map((site) => (
                  <option key={site} value={site}>{site}</option>
                ))}
              </select>
            </label>
          </div>
          <div>
            <label>
              Event Date:
              <input
                type="date"
                name="event_date"
                value={form.event_date}
                onChange={handleFormChange}
                required
              />
            </label>
          </div>
          <button type="submit">Update Event</button>
        </form>
        {status === 'saved' && <p style={{ color: 'green' }}>Event updated successfully!</p>}
        {status === 'error' && <p style={{ color: 'red' }}>Failed to update event.</p>}
      </section>

      <section>
        <h3>Criteria</h3>
        <form onSubmit={handleCriteriaSubmit}>
          <div>
            <label>
              Name:
              <input
                type="text"
                value={criteriaForm.name}
                onChange={(e) => setCriteriaForm((prev) => ({ ...prev, name: e.target.value }))}
                required
              />
            </label>
          </div>
          <div>
            <label>
              Value:
              <input
                type="text"
                value={criteriaForm.value}
                onChange={(e) => setCriteriaForm((prev) => ({ ...prev, value: e.target.value }))}
                required
              />
            </label>
          </div>
          <button type="submit">Add Criteria</button>
        </form>
        {criteriaStatus === 'saved' && <p style={{ color: 'green' }}>Criteria added successfully!</p>}
        {criteriaStatus === 'error' && <p style={{ color: 'red' }}>Failed to add criteria.</p>}
        
        <div style={{ marginTop: '20px' }}>
          <h4>Existing Criteria:</h4>
          {criteriaList.length === 0 ? (
            <p>No criteria found for this event.</p>
          ) : (
            <ul>
              {criteriaList.map((criteria, idx) => (
                <li key={idx}>
                  <strong>{criteria.name}:</strong> {criteria.value}
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>

      <section>
        <h3>Solicitations</h3>
        <form onSubmit={handleSolicitationSubmit}>
          <div>
            <label>
              Date:
              <input
                type="date"
                value={solicitationForm.date}
                onChange={(e) => setSolicitationForm((prev) => ({ ...prev, date: e.target.value }))}
                required
              />
            </label>
          </div>
          <div>
            <label>
              Contact:
              <input
                type="text"
                value={solicitationForm.contact}
                onChange={(e) => setSolicitationForm((prev) => ({ ...prev, contact: e.target.value }))}
                required
              />
            </label>
          </div>
          <button type="submit">Add Solicitation</button>
        </form>
        {solicitationStatus === 'saved' && <p style={{ color: 'green' }}>Solicitation added successfully!</p>}
        {solicitationStatus === 'error' && <p style={{ color: 'red' }}>Failed to add solicitation.</p>}
        
        <div style={{ marginTop: '20px' }}>
          <h4>Existing Solicitations:</h4>
          {solicitations.length === 0 ? (
            <p>No solicitations found for this event.</p>
          ) : (
            <ul>
              {solicitations.map((solicitation, idx) => (
                <li key={idx}>
                  <strong>{solicitation.date}:</strong> {solicitation.contact}
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>

      <div style={{ marginTop: '20px' }}>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>
    </div>
  )
}

export default VTEEventEdit
