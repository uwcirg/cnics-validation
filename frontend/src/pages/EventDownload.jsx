import { useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'

function EventDownload() {
  const [searchParams] = useSearchParams()
  const apiUrl = import.meta.env.VITE_API_URL || ''
  const eventId = searchParams.get('event_id')

  useEffect(() => {
    if (!eventId) return
    const url = `${apiUrl}/api/events/download/${eventId}`
    try {
      // Attempt immediate download in same tab
      window.location.href = url
    } catch {
      // noop – fallback link is rendered below
    }
  }, [apiUrl, eventId])

  if (!eventId) return <p>Missing event_id.</p>

  return (
    <div>
      <h1>Downloading charts for event {eventId}</h1>
      <p>If your download does not start automatically, click the link below:</p>
      <p>
        <a href={`${apiUrl}/api/events/download/${eventId}`}>Download charts</a>
      </p>
    </div>
  )
}

export default EventDownload


