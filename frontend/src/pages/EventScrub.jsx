import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { showToast } from '../components/Toast'
import GuidanceLinks from '../components/GuidanceLinks'
import { resolveStudyDocuments } from '../components/reviewGuidance'
import { headingSuffix } from '../components/studyHeading'

function EventScrub({ studyLabel, studyType, configResolved = true }) {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
  const eventId = searchParams.get('event_id')

  // The study word shown in this page's heading. Withheld until GET /api/config
  // has resolved, so the heading never paints one study's name and then swaps
  // it for another (spec 010, FR-009) — the same gate Home.jsx applies to its
  // study-aware boxes. `headingSuffix` collapses an empty label to the bare
  // event identifier, so the pre-resolution form is the bare id, with no
  // stray space (e.g. "Upload scrubbed charts for 4821").
  const headingText = headingSuffix(configResolved ? studyLabel : '', eventId)

  // Document links for this deployment's study, from the same recorded source
  // the home page reads. A study with no scrubbing protocol resolves to an
  // empty box and renders no link area at all — deliberately with no fallback
  // to another study's protocol (spec 010, FR-016, FR-017).
  const documents = resolveStudyDocuments(studyType)
  const showScrubbingDocs = configResolved && documents.scrubbing.links.length > 0

  const [details, setDetails] = useState(null)
  const [file, setFile] = useState(null)
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
    if (!file || !eventId) return
    setStatus('uploading')
    // Placeholder endpoint – backend route to accept scrubbed uploads not yet implemented
    // Once available, update the URL below accordingly.
    try {
      const form = new FormData()
      form.append('scrubbed_file', file)
      const res = await fetch(`${apiUrl}/api/events/${eventId}/upload_scrubbed`, {
        method: 'POST',
        credentials: 'include',
        body: form,
      })
      if (res.ok) {
        setStatus('saved')
        setFile(null)
        e.target.reset()
        showToast('Scrubbed file uploaded successfully.', 'success')
      } else if (res.status === 401) {
        setStatus('unauthorized')
        showToast('Login required to upload.', 'warning')
      } else if (res.status === 403) {
        setStatus('forbidden')
        showToast('You are not authorized to upload for this event.', 'error')
      } else {
        setStatus('error')
        showToast('Upload failed. Please try again.', 'error')
      }
    } catch {
      setStatus('error')
      showToast('Upload failed due to a network or server error.', 'error')
    }
  }

  return (
    <div>
      {/* The whole box — header included — is withheld when this study has no
          scrubbing protocol, so no orphaned "Scrubbing Instructions:" header
          is left behind (spec 010, FR-015). */}
      {showScrubbingDocs && (
        <div className="infobox" style={{ width: '300px', fontSize: '.95em' }}>
          <h3>Scrubbing Instructions:</h3>
          <div style={{ marginTop: '8px' }}>
            <GuidanceLinks box={documents.scrubbing} />
          </div>
        </div>
      )}

      <h1>Upload scrubbed charts for {headingText}</h1>

      {details && (
        <p>
          Site: {details.site || '—'}<br />
          Patient ID: {details.site_patient_id || details.patient_id || '—'}<br />
          Date: {details.event_date || '—'}
        </p>
      )}

      {details && details.rescrub_message && (
        <h2 id="rescrub">
          Needs rescrubbing. Message: {details.rescrub_message}
        </h2>
      )}

      <form onSubmit={handleSubmit} encType="multipart/form-data">
        <div>
          <label>
            Choose a scrubbed file to upload:{' '}
            <input
              type="file"
              name="scrubbed_file"
              onChange={(e) => setFile(e.target.files[0])}
            />
          </label>
        </div>
        <button type="submit" disabled={!file || !eventId}>Upload scrubbed</button>
      </form>

      {status === 'uploading' && <p>Uploading...</p>}
      {status === 'saved' && <p>Scrubbed file uploaded.</p>}
      {status === 'unauthorized' && <p>Login required.</p>}
      {status === 'forbidden' && <p>Not authorized.</p>}
      {status === 'error' && <p>Upload failed.</p>}

      <p style={{ marginTop: '16px' }}>
        <a href="#" onClick={(e) => { e.preventDefault(); navigate('/events/viewAll') }}>
          &lt; Return to View All Events
        </a>
      </p>
    </div>
  )
}

export default EventScrub
