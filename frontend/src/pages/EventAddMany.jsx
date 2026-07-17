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
      let body = {}
      try {
        body = await res.json()
      } catch {
        body = {}
      }
      const imported = body?.data?.imported ?? 0
      const rowErrors = body?.data?.errors || []
      if (res.ok && imported > 0) {
        setStatus('saved')
        setFile(null)
        e.target.reset()
        const suffix = rowErrors.length
          ? ` ${rowErrors.length} row(s) were skipped: ${rowErrors.join('; ')}`
          : ''
        showToast(
          `Imported ${imported} event${imported === 1 ? '' : 's'}.${suffix}`,
          rowErrors.length ? 'warning' : 'success',
          rowErrors.length ? 10000 : 3000,
        )
      } else {
        setStatus('error')
        const detail = rowErrors.length
          ? rowErrors.join('; ')
          : body?.error || 'Please check the file and try again.'
        showToast(`CSV upload failed. ${detail}`, 'error', 10000)
      }
    } catch {
      setStatus('error')
      showToast('CSV upload failed due to a network or server error.', 'error')
    }
  }

  return (
    <div>
      <h1>Add Multiple New Events</h1>
      <p>
      Select a CSV file where each line has this format:
      <blockquote>
      <code>site_patient_id, site_name, event_date, criteria</code>
      </blockquote>
      </p>

      <p>
      <code>event_date</code> is of the form <code>2000-12-25</code>, and corresponds to the date of the diagnosis of labs or procedure that trigger the review.
      </p>

      <p>
      <code>criteria</code> is a (possibly empty) list of criteria used to identify potential events.  Each criterion consists of two comma-separated fields. The first field is the name of a criterion, the second is the value of the criterion.  Some examples:
      </p>

      <ul>
      <li>
      CK,5
      </li>
      <li>
      troponins,2,CK,5
      </li>
      <li>
      troponins,2,CK,5,procedures,"CPR,defibrillation" 
      </li>
      </ul>

      <p>
      Note: criteria fields that contain commas should be enclosed in double quotes
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
