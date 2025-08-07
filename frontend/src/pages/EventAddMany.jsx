import { useState } from 'react'

function EventAddMany() {
  const [file, setFile] = useState(null)
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.VITE_API_URL || ''

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!file) return
    const form = new FormData()
    form.append('events_csv', file)
    try {
      const res = await fetch(`${apiUrl}/api/events/bulk`, {
        method: 'POST',
        credentials: 'include',
        body: form,
      })
      if (res.ok) {
        setStatus('saved')
        setFile(null)
        e.target.reset()
      } else {
        setStatus('error')
      }
    } catch {
      setStatus('error')
    }
  }

  return (
    <div>
      <h1>Add Multiple Events</h1>
      <form onSubmit={handleSubmit}>
        <div>
          <label>
            CSV File:
            <input
              type="file"
              name="events_csv"
              onChange={(e) => setFile(e.target.files[0])}
            />
          </label>
        </div>
        <button type="submit">Add</button>
      </form>
      {status === 'saved' && <p>Events saved.</p>}
      {status === 'error' && <p>Failed to save events.</p>}
    </div>
  )
}

export default EventAddMany
