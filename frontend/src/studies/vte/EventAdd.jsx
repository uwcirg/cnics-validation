import { useEffect, useState } from 'react'
import '../../pages/Home.css'

function VTEEventAdd() {
  const [formData, setFormData] = useState({
    site_patient_id: '',
    site: '',
    event_date: '',
    criterion_name: '',
    criterion_value: '',
  })
  const [status, setStatus] = useState(null)
  const [error, setError] = useState('')
  const [sites, setSites] = useState([])
  const [loading, setLoading] = useState(true)
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

  useEffect(() => {
    // Load sites for dropdown
    fetch(`${apiUrl}/api/sites`, { credentials: 'include' })
      .then((res) => {
        if (!res.ok) {
          throw new Error('Failed to load sites')
        }
        return res.json()
      })
      .then((json) => setSites(json.data || []))
      .catch((err) => {
        console.error('Error loading sites:', err)
        setError('Failed to load sites')
      })
      .finally(() => setLoading(false))
  }, [apiUrl])

  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    if (!formData.site_patient_id.trim() || !formData.site.trim() || !formData.event_date.trim()) {
      setStatus('error')
      setError('Please provide site_patient_id, site, and event_date (YYYY-MM-DD).')
      return
    }
    try {
      const res = await fetch(`${apiUrl}/api/events`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Study-Mode': 'vte' },
        credentials: 'include',
        body: JSON.stringify(formData),
      })
      if (res.ok) {
        setStatus('saved')
        setFormData({
          site_patient_id: '',
          site: '',
          event_date: '',
          criterion_name: '',
          criterion_value: '',
        })
        setError('')
      } else {
        setStatus('error')
        try {
          const body = await res.json()
          setError(body?.error || 'Failed to save event.')
        } catch {
          setError('Failed to save event.')
        }
      }
    } catch (err) {
      setStatus('error')
      setError('Network error: ' + err.message)
    }
  }

  if (loading) {
    return (
      <div className="home-container">
        <h1>Add VTE Event</h1>
        <p>Loading...</p>
      </div>
    )
  }

  return (
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src="/cnics_logo.png" alt="CNICS" />
      <h1>Add a new VTE event</h1>
      <form onSubmit={handleSubmit}>
        <table>
          <tbody>
            <tr>
              <th>
                <label htmlFor="site_patient_id">Site Patient Id</label>
              </th>
              <td>
                <input
                  type="text"
                  id="site_patient_id"
                  name="site_patient_id"
                  value={formData.site_patient_id}
                  onChange={handleChange}
                  required
                />
              </td>
            </tr>
            <tr>
              <th>
                <label htmlFor="site">Site</label>
              </th>
              <td>
                <select
                  id="site"
                  name="site"
                  value={formData.site}
                  onChange={handleChange}
                  required
                >
                  <option value="">Select a site</option>
                  {sites.map((site) => (
                    <option key={site} value={site}>
                      {site}
                    </option>
                  ))}
                </select>
              </td>
            </tr>
            <tr>
              <th>
                <label htmlFor="event_date">Event date</label>
              </th>
              <td>
                <input
                  type="date"
                  id="event_date"
                  name="event_date"
                  value={formData.event_date}
                  onChange={handleChange}
                  min="1985-01-01"
                  max={new Date().getFullYear() + '-12-31'}
                  required
                />
              </td>
            </tr>
            <tr>
              <th>Criterion used to flag this event</th>
              <td>
                <input
                  type="text"
                  name="criterion_name"
                  value={formData.criterion_name}
                  onChange={handleChange}
                  placeholder="Criterion name"
                />
                <input
                  type="text"
                  name="criterion_value"
                  value={formData.criterion_value}
                  onChange={handleChange}
                  placeholder="Criterion value"
                />
              </td>
            </tr>
            <tr>
              <td colSpan="2">
                <button type="submit">Add</button>
              </td>
            </tr>
          </tbody>
        </table>
      </form>
      {status === 'saved' && <p style={{ color: 'green' }}>Event added successfully!</p>}
      {status === 'error' && <p style={{ color: 'red' }}>Error: {error}</p>}
    </div>
  )
}

export default VTEEventAdd
