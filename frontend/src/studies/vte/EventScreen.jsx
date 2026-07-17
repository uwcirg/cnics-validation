import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import '../../pages/Home.css'

function VTEEventScreen() {
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
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src="/cnics_logo.png" alt="CNICS" />
      <h1>Screen charts for VTE {eventId}</h1>

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
        </div>

        <div style={{ marginTop: '16px' }}>
          <label htmlFor="message">Message (optional):</label>
          <br />
          <textarea
            id="message"
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            rows="4"
            cols="50"
            style={{ marginTop: '4px' }}
          />
        </div>

        <div style={{ marginTop: '16px' }}>
          <button type="submit" disabled={status === 'saving'}>
            {status === 'saving' ? 'Saving...' : 'Submit'}
          </button>
        </div>

        {status === 'saved' && (
          <div style={{ color: 'green', marginTop: '8px' }}>
            Decision saved successfully.
          </div>
        )}

        {status === 'error' && (
          <div style={{ color: 'red', marginTop: '8px' }}>
            Error saving decision. Please try again.
          </div>
        )}

        {status === 'unauthorized' && (
          <div style={{ color: 'red', marginTop: '8px' }}>
            You are not authorized to perform this action.
          </div>
        )}

        {status === 'forbidden' && (
          <div style={{ color: 'red', marginTop: '8px' }}>
            Access forbidden. Please check your permissions.
          </div>
        )}
      </form>

      <div style={{ marginTop: '32px' }}>
        <button onClick={() => navigate(-1)}>Back</button>
      </div>
    </div>
  )
}

export default VTEEventScreen
