import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { headingSuffix } from '../components/studyHeading'
import GuidanceLinks from '../components/GuidanceLinks'
import { resolveStudyDocuments } from '../components/reviewGuidance'
import { resolveReviewForm } from '../components/review/reviewForms'

function EventReview({ studyLabel, studyType, reviewForm, configResolved = true }) {
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
  const [searchParams] = useSearchParams()
  const eventId = searchParams.get('event_id')

  // The study word shown in this page's heading. Withheld until GET /api/config
  // has resolved, so the heading never paints one study's name and then swaps
  // it for another (spec 010, FR-009) — the same gate Home.jsx applies to its
  // study-aware boxes. `headingSuffix` collapses an empty label to the bare
  // event identifier, so the pre-resolution form is the bare id, with no
  // stray space (e.g. "Review event: 4821").
  const headingText = headingSuffix(configResolved ? studyLabel : '', eventId)

  // Reviewer instructions for this deployment's study, read from the same
  // recorded source as the home page's "Review Instructions:" box, so the two
  // cannot drift apart (spec 010, FR-014). No fallback: a study with no
  // instructions document shows no link area (FR-017).
  const documents = resolveStudyDocuments(studyType)
  const showInstructionDocs = configResolved && documents.instructions.links.length > 0

  const [eventDetails, setEventDetails] = useState(null)

  useEffect(() => {
    if (!eventId) return
    fetch(`${apiUrl}/api/events/${eventId}`, { credentials: 'include' })
      .then((r) => r.ok ? r.json() : Promise.reject(r))
      .then((j) => setEventDetails(j.data || null))
      .catch(() => setEventDetails(null))
  }, [apiUrl, eventId])

  const ReviewForm = resolveReviewForm(reviewForm)

  return (
    <div>
      {/* The whole box — header included — is withheld when this study has no
          reviewer instructions, so no orphaned "Review Instructions:" header
          is left behind (spec 010, FR-015). */}
      {showInstructionDocs && (
        <div className="boxright" style={{ width: '300px', fontSize: '.95em' }}>
          <h3>Review Instructions:</h3>
          <div style={{ marginTop: '8px' }}>
            <GuidanceLinks box={documents.instructions} />
          </div>
        </div>
      )}

      <h1>Review event: {headingText}</h1>
      {eventDetails && (
        <p>Date: {eventDetails.event_date || '—'}</p>
      )}

      <div className="indent1">
        <h2>Step 1: Review Charts</h2>
        <p>Review the packet for this event:</p>
        <ul>
          <li>
            <a 
              href={`${apiUrl}/api/events/download/${eventId}`} 
              download=""
              style={{ 
                display: 'inline-block',
                padding: '6px 12px',
                backgroundColor: '#007bff',
                color: 'white',
                textDecoration: 'none',
                borderRadius: '4px',
                fontSize: '14px'
              }}
            >
              Download charts for {headingText}
            </a>
          </li>
        </ul>

        {/* Step 2 onwards is the study's own review form, chosen by the
            backend-reported `review_form` (spec 012, research D6). Withheld
            until GET /api/config resolves, so a scans reviewer never sees
            the MI form flash first. */}
        {configResolved && <ReviewForm eventId={eventId} apiUrl={apiUrl} />}
      </div>
    </div>
  )
}

export default EventReview
