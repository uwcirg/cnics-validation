import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

function EventScreen() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
  const eventId = searchParams.get('event_id')

  const [details, setDetails] = useState(null)
  const [decision, setDecision] = useState('accept')
  const [message, setMessage] = useState('')
  const [status, setStatus] = useState(null)

  useEffect(() => {
    if (!eventId) return
    fetch(`${apiUrl}/api/events/${eventId}`, { credentials: 'include' })
      .then((res) => {
        if (!res.ok) throw new Error('load')
        return res.json()
      })
      .then((json) => setDetails(json.data || null))
      .catch(() => setDetails(null))
  }, [apiUrl, eventId])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!eventId) return
    setStatus('saving')
    try {
      const res = await fetch(`${apiUrl}/api/events/${eventId}/screen`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decision, message }),
      })
      if (res.ok) {
        setStatus('saved')
      } else if (res.status === 401) {
        setStatus('unauthorized')
      } else if (res.status === 403) {
        setStatus('forbidden')
      } else {
        setStatus('error')
      }
    } catch {
      setStatus('error')
    }
  }

  return (
    <div>
      <h1>Screen charts for MI {eventId}</h1>

      {details && (
        <p>
          Site: {details.site || '—'}<br />
          Patient ID: {details.site_patient_id || details.patient_id || '—'}<br />
          Date: {details.event_date || '—'}
        </p>
      )}

      <p>
        <a 
          href={`${apiUrl}/api/events/download/${eventId}`} 
          download=""
          style={{ 
            display: 'inline-block',
            padding: '8px 16px',
            backgroundColor: '#007bff',
            color: 'white',
            textDecoration: 'none',
            borderRadius: '4px',
            fontSize: '14px'
          }}
        >
          📥 Download Charts
        </a>
      </p>

      <form onSubmit={handleSubmit}>
        <div>
          <label>
            <input
              type="radio"
              name="screenAccept"
              value="accept"
              checked={decision === 'accept'}
              onChange={(e) => setDecision(e.target.value)}
            />{' '}
            Accept
          </label>
          &nbsp;&nbsp;
          <label>
            <input
              type="radio"
              name="screenAccept"
              value="rescrub"
              checked={decision === 'rescrub'}
              onChange={(e) => setDecision(e.target.value)}
            />{' '}
            Rescrub
          </label>
          &nbsp;&nbsp;
          <label>
            <input
              type="radio"
              name="screenAccept"
              value="reject"
              checked={decision === 'reject'}
              onChange={(e) => setDecision(e.target.value)}
            />{' '}
            Reject
          </label>
        </div>

        <div style={{ marginTop: '8px' }}>
          <label>
            Message:&nbsp;
            <input
              type="text"
              name="message"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              style={{ width: '360px' }}
            />
          </label>
        </div>

        <div style={{ marginTop: '8px' }}>
          <button type="submit">Screen</button>
        </div>
      </form>

      {status === 'saving' && <p>Saving...</p>}
      {status === 'saved' && <p>Decision saved.</p>}
      {status === 'unauthorized' && <p>Login required.</p>}
      {status === 'forbidden' && <p>Not authorized.</p>}
      {status === 'error' && <p>Failed to save.</p>}

      <p style={{ marginTop: '16px' }}>
        <a href="#" onClick={(e) => { e.preventDefault(); navigate('/events/viewAll') }}>
          &lt; Return to View All Events
        </a>
      </p>
    </div>
  )
}

export default EventScreen
