import { useState } from 'react'
import { showToast } from '../components/Toast'

function EventAddMany() {
  const [file, setFile] = useState(null)
  const [status, setStatus] = useState(null)
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

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
        showToast('Events CSV uploaded successfully.', 'success')
      } else {
        setStatus('error')
        showToast('CSV upload failed. Please check the file and try again.', 'error')
      }
    } catch {
      setStatus('error')
      showToast('CSV upload failed due to a network or server error.', 'error')
    }
  }

  return (
    <div>
      <h1>Add Multiple Events</h1>
      <p>
        Download the CSV template: <a href="/files/CSV_template_events.csv" target="_blank" rel="noreferrer">CSV_template_events.csv</a>
      </p>
      <p>
        Required columns: MI, Patient ID, Site Patient ID, Patient Site, Event Date (YYYY-MM-DD). Criteria may be a semicolon-separated list.
      </p>
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
