import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { headingSuffix } from '../components/studyHeading'
import GuidanceLinks from '../components/GuidanceLinks'
import { resolveStudyDocuments } from '../components/reviewGuidance'
import { resolveReviewForm, resolveReviewSummary } from '../components/review/reviewForms'
import ReviewQueue from '../components/review/ReviewQueue'
import PacketDownloadLink from '../components/PacketDownloadLink'

// An ISO datetime from the API as "YYYY-MM-DD HH:MM:SS" for display.
const showTime = (iso) => (iso ? String(iso).replace('T', ' ') : '')

function EventReview({ studyLabel, studyType, reviewForm, configResolved = true, workflow }) {
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

  // Whether the signed-in reviewer has already reviewed this event. `mine`
  // from GET …/review uses the same rule as the server's duplicate refusal
  // (FR-024), so a reviewed event never shows the empty form again (spec 012
  // FR-029, research D15). Since spec 014 a submitted review is either
  // editable (the form, pre-filled, saving with PUT) or locked (read-only),
  // as the server decides in `mine.editable` (research D6–D8):
  //   'open' | 'editable' | 'locked' | 'loading' | 'error'
  const [reviewStatus, setReviewStatus] = useState('loading')
  const [mine, setMine] = useState(null)
  // Bumped to re-fetch GET …/review after a save or a refused save.
  const [reloadKey, setReloadKey] = useState(0)
  // "Your changes were saved.", or the server's reason a save was refused.
  const [saveNotice, setSaveNotice] = useState(null)

  useEffect(() => {
    if (!eventId) return
    setReviewStatus('loading')
    fetch(`${apiUrl}/api/events/${eventId}/review`, { credentials: 'include' })
      .then((r) => r.ok ? r.json() : Promise.reject(r))
      .then((j) => {
        const m = (j && j.data && j.data.mine) || null
        setMine(m)
        setReviewStatus(!m ? 'open' : (m.editable ? 'editable' : 'locked'))
      })
      .catch(() => setReviewStatus('error'))
  }, [apiUrl, eventId, reloadKey])

  const ReviewForm = resolveReviewForm(reviewForm)
  const ReviewSummary = resolveReviewSummary(reviewForm)
  const singleReviewer = workflow?.reviewer_count === 1

  const handleSaved = () => {
    setSaveNotice({ type: 'success', text: 'Your changes were saved.' })
    setReloadKey((k) => k + 1)
  }
  // A refused save (409) means the review can no longer be edited, e.g. the
  // other reviewer has just submitted. Re-fetching switches to read-only.
  const handleConflict = (text) => {
    setSaveNotice({ type: 'error', text })
    setReloadKey((k) => k + 1)
  }

  const submittedText = mine
    ? `${mine.review_date}${mine.modified ? `, last edited ${showTime(mine.modified)}` : ''}`
    : ''

  // Without an event, this page is the reviewer's queue: the "Review
  // Events" menu link lands here (spec 012 FR-025, research D13).
  if (!eventId) {
    return (
      <div>
        <ReviewQueue />
      </div>
    )
  }

  return (
    <div>
      {/* The whole box — header included — is withheld when this study has no
          reviewer instructions, so no orphaned "Review Instructions:" header
          is left behind (spec 010, FR-015). */}
      {showInstructionDocs && (
        <div className="boxright">
          <h3>Review Instructions:</h3>
          <GuidanceLinks box={documents.instructions} />
        </div>
      )}

      <h1>Review event: {headingText}</h1>
      {eventDetails && (
        <p>Date: {eventDetails.event_date || '—'}</p>
      )}

      {/* Spec 014 D14: where reviewers can download before any send step,
          tell them when the packet was replaced so they don't review an old
          copy. Shown in every review state. */}
      {eventDetails?.packet_replaced_at && (
        <div role="alert" className="warning-notice">
          This event&apos;s packet was replaced on {showTime(eventDetails.packet_replaced_at)}.
          If you downloaded it before then, download it again before reviewing.
        </div>
      )}

      <div className="indent1">
        <h2>Step 1: Review Charts</h2>
        <p>Review the packet for this event:</p>
        <ul>
          <li>
            <PacketDownloadLink
              href={`${apiUrl}/api/events/download/${eventId}`}
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
            </PacketDownloadLink>
          </li>
        </ul>

        {/* Step 2 onwards is the study's own review form, chosen by the
            backend-reported `review_form` (spec 012, research D6). Withheld
            until GET /api/config resolves, so a scans reviewer never sees
            the MI form flash first. */}
        {saveNotice && (
          <p role="status" style={{ color: saveNotice.type === 'success' ? '#1a7f37' : '#b3261e' }}>
            {saveNotice.text}
          </p>
        )}
        {configResolved && reviewStatus === 'open' && (
          <ReviewForm eventId={eventId} apiUrl={apiUrl} />
        )}
        {reviewStatus === 'editable' && (
          <>
            <p>
              You submitted this review on {submittedText}.
              {singleReviewer
                ? ' You can change your answers.'
                : ' You can change your answers until the other reviewer submits theirs.'}
            </p>
            {configResolved && (
              <ReviewForm
                // Remount on each save so the form shows what was stored.
                key={mine.modified || 'submitted'}
                eventId={eventId}
                apiUrl={apiUrl}
                initialReview={mine.answers}
                mode="edit"
                onSaved={handleSaved}
                onConflict={handleConflict}
              />
            )}
          </>
        )}
        {reviewStatus === 'locked' && (
          <>
            <p>
              You have already reviewed this event (submitted {submittedText}).
              {mine.locked_reason ? ` ${mine.locked_reason}` : ''}
            </p>
            {configResolved && mine.answers && <ReviewSummary review={mine.answers} />}
          </>
        )}
        {reviewStatus === 'error' && (
          <p>This review could not be loaded.</p>
        )}
      </div>
    </div>
  )
}

export default EventReview
