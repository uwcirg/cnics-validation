import { useState } from 'react'

function SolicitationAdd() {
  const [formData, setFormData] = useState({ event_id: '', date: '', contact: '' })
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''

  const handleChange = (e) => {
    const { name, value } = e.target
    setFormData((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    try {
      const res = await fetch(`${apiUrl}/api/solicitations`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(formData),
      })
      if (res.ok) {
        setStatus('saved')
        setFormData({ event_id: '', date: '', contact: '' })
      } else {
        setStatus('error')
      }
    } catch {
      setStatus('error')
    }
  }

  return (
    <div>
      <h1>Add Solicitation</h1>
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
            Date:
            <input type="date" name="date" value={formData.date} onChange={handleChange} />
          </label>
        </div>
        <div>
          <label>
            Contact:
            <input type="text" name="contact" value={formData.contact} onChange={handleChange} />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
      {status === 'saved' && <p>Solicitation saved.</p>}
      {status === 'error' && <p>Failed to save solicitation.</p>}
    </div>
  )
}

export default SolicitationAdd
