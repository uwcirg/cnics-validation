import { useState } from 'react'

function CriteriaAdd() {
  const [formData, setFormData] = useState({ event_id: '', name: '', value: '' })
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''

  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    try {
      const res = await fetch(`${apiUrl}/api/criteria`, {
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
      <h1>Add Criterion</h1>
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
        <button type="submit">Add</button>
      </form>
      {status === 'saved' && <p>Criterion saved.</p>}
      {status === 'error' && <p>Failed to save criterion.</p>}
    </div>
  )
}

export default CriteriaAdd
