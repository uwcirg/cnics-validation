import { useState } from 'react'

function VTECriteriaAdd() {
  const [formData, setFormData] = useState({ event_id: '', name: '', value: '' })
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    try {
      const res = await fetch(`${apiUrl}/api/criteria?study=vte`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(formData),
      })
      if (res.ok) {
        setStatus('saved')
        setFormData({ event_id: '', name: '', value: '' })
      } else {
        setStatus('error')
      }
    } catch {
      setStatus('error')
    }
  }

  return (
    <div>
      <h1>Add VTE Criterion</h1>
      <form onSubmit={handleSubmit}>
        <div>
          <label>
            Event ID:
            <input
              type="text"
              name="event_id"
              value={formData.event_id}
              onChange={handleChange}
            />
          </label>
        </div>
        <div>
          <label>
            Name:
            <input type="text" name="name" value={formData.name} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Value:
            <input type="text" name="value" value={formData.value} onChange={handleChange} />
          </label>
        </div>
        <button type="submit">Add VTE Criterion</button>
      </form>
      {status === 'saved' && <p>VTE criterion saved.</p>}
      {status === 'error' && <p>Failed to save VTE criterion.</p>}
    </div>
  )
}

export default VTECriteriaAdd
