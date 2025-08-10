import { useState } from 'react'

function EventAdd() {
  const [formData, setFormData] = useState({
    site_patient_id: '',
    site: '',
    event_date: '',
    criterion_name: '',
    criterion_value: '',
  })
  const [status, setStatus] = useState(null)
  const [error, setError] = useState('')
  const apiUrl = import.meta.env.VITE_API_URL || ''

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
        headers: { 'Content-Type': 'application/json' },
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
    } catch {
      setStatus('error')
      setError('Network error while saving event.')
    }
  }

  return (
    <div>
      <h1>Add Event</h1>
      <form onSubmit={handleSubmit}>
        <div>
          <label>
            Site Patient Id:
            <input
              type="text"
              name="site_patient_id"
              value={formData.site_patient_id}
              onChange={handleChange}
              required
            />
          </label>
        </div>
        <div>
          <label>
            Site:
            <input type="text" name="site" value={formData.site} onChange={handleChange} required />
          </label>
        </div>
        <div>
          <label>
            Event Date:
            <input
              type="date"
              name="event_date"
              value={formData.event_date}
              onChange={handleChange}
              required
            />
          </label>
        </div>
        <div>
          <label>
            Criterion Name:
            <input
              type="text"
              name="criterion_name"
              value={formData.criterion_name}
              onChange={handleChange}
            />
          </label>
        </div>
        <div>
          <label>
            Criterion Value:
            <input
              type="text"
              name="criterion_value"
              value={formData.criterion_value}
              onChange={handleChange}
            />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
      {status === 'saved' && <p>Event saved.</p>}
      {status === 'error' && <p>{error || 'Failed to save event.'}</p>}
    </div>
  )
}

export default EventAdd
