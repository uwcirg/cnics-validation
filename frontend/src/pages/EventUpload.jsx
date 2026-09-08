import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import DataTable from '../components/DataTable'
import { resolveReviewGuidance } from '../components/reviewGuidance'
import { headingSuffix } from '../components/studyHeading'
import './EventUpload.css'

const PAGE_SIZE = 20
const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
// Placeholder for a value the record legitimately does not carry. Matches the
// convention already used on the scrub and screen pages.
const EMPTY_VALUE = '—'

// Render one guidance box's optional file links. Kept identical to Home's
// GuidanceLinks so the "Review packets should contain" box renders the same in
// both places. Returns null when the box defines no links.
function GuidanceLinks({ box }) {
  if (!box.links || box.links.length === 0) return null
  return (
    <div>
      {box.linkLabel ? `${box.linkLabel} ` : ''}
      {box.links.map((link, i) => (
        <span key={link.href}>
          {i > 0 ? ' | ' : ''}
          {link.download ? (
            <a href={`${API_BASE}${link.href}`} download>{link.label}</a>
          ) : (
            <a href={`${API_BASE}${link.href}`} target="_blank">{link.label}</a>
          )}
        </span>
      ))}
    </div>
  )
}

// Shared table wrapper copied from Home to ensure identical behavior/columns
function TableWrapper({ endpoint, columns, renderActions, pageSize = PAGE_SIZE }) {
  const navigate = useNavigate()
  const [rows, setRows] = useState([])
  const [totalCount, setTotalCount] = useState(null)
  const [search, setSearch] = useState('')
  const [siteFilter, setSiteFilter] = useState('')

  const fetchPage = (p) => {
    const params = new URLSearchParams({
      limit: String(pageSize),
      offset: String((p - 1) * pageSize),
    })
    if (search) params.set('q', search)
    if (siteFilter) params.set('site', siteFilter)
    fetch(`${API_BASE}${endpoint}?${params.toString()}`, {
      credentials: 'include',
    })
      .then((res) => {
        if (!res.ok) {
          if (res.status === 401) alert('Login required');
          else if (res.status === 403) alert('Not authorized');
          throw new Error('auth');
        }
        return res.json()
      })
      .then((json) => {
        const payload = json || {}
        setRows(payload.data || [])
        if (typeof payload.total === 'number') setTotalCount(payload.total)
      })
      .catch(() => {})
  }

  useEffect(() => {
    fetchPage(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [endpoint])

  useEffect(() => {
    fetchPage(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, siteFilter])

  const handleClick = (row) => {
    // Only the event id travels in the link. The upload page reads the
    // identifying values from the stored record, so passing them here would be
    // both redundant and a source of staleness.
    navigate(`/events/upload?event_id=${row['ID']}`)
  }
  const sites = Array.from(
    new Set(rows.map((r) => r['Site'] || r['site']).filter(Boolean))
  ).sort()
  return (
    <>
      <div style={{ display: 'flex', gap: '8px', margin: '8px 0', alignItems: 'center', justifyContent: 'space-between' }}>
        <div style={{ display: 'flex', gap: '8px' }}>
          <input
            type="text"
            placeholder="Search this table"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          {sites.length > 0 && (
            <select value={siteFilter} onChange={(e) => setSiteFilter(e.target.value)}>
              <option value="">All Sites</option>
              {sites.map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </select>
          )}
        </div>
        <div style={{ whiteSpace: 'nowrap', fontSize: '.9em', color: '#444' }}>
          {`Showing ${rows.length}${typeof totalCount === 'number' ? ` of ${totalCount}` : ''}`}
        </div>
      </div>
      <DataTable rows={rows} onRowClick={handleClick} onPageChange={fetchPage} totalCount={totalCount} columns={columns} renderActions={renderActions} />
    </>
  )
}

function EventUpload({ studyLabel, studyType, configResolved = true, workflow }) {
  const [searchParams] = useSearchParams()

  // What happens to a packet after upload depends on which optional stages the
  // deployment runs, so the confirmation names the stage the event actually
  // enters next rather than assuming the full workflow (spec 003, FR-018 and
  // FR-021). The `!== false` form matches EventViewAll: a missing, unresolved,
  // or malformed control keeps the conservative full-workflow wording.
  const wf = workflow || {}
  const uploadSuccessMessage = wf.scrubbing !== false
    ? 'Upload successful. Packet is now queued for scrubbing.'
    : wf.screening !== false
      ? 'Upload successful. Packet is now queued for screening.'
      : 'Upload successful. Packet is now ready for assignment.'

  // Body content for the "Review packets should contain" box, chosen by the
  // deployment's study type. Resolved the same way as Home so both pages show
  // identical, study-aware guidance.
  const guidance = resolveReviewGuidance(studyType)
  const navigate = useNavigate()
  // `event_id` is the only query parameter this page reads. The identifying
  // values are fetched from the stored record below, so a link, a bookmark, and
  // the row action button all render the same thing. Older links may still
  // carry patient_id/date/criteria; those are ignored, not rejected.
  const eventId = searchParams.get('event_id')

  // The study word shown in this page's heading. Withheld until GET /api/config
  // has resolved, so the heading never paints one study's name and then swaps
  // it for another (spec 010, FR-009) — the same gate Home.jsx applies to its
  // study-aware boxes. `headingSuffix` collapses an empty label to the bare
  // event identifier, so the pre-resolution form is the bare id, with no
  // stray space (e.g. "Packet for 4821").
  const headingText = headingSuffix(configResolved ? studyLabel : '', eventId)
  const [details, setDetails] = useState(null)
  // State for the upload UI only (search/table browsing removed to match Home)
  const [noPacketReason, setNoPacketReason] = useState('')
  const [priorEventDateKnown, setPriorEventDateKnown] = useState('')
  // The follow-up answers. These were rendered as uncontrolled inputs, so the
  // coordinator's answers were unreadable even once a handler existed.
  const [twoAttempts, setTwoAttempts] = useState('')
  const [priorEventMonth, setPriorEventMonth] = useState('')
  const [priorEventYear, setPriorEventYear] = useState('')
  const [priorEventOnsite, setPriorEventOnsite] = useState('')
  const [otherCause, setOtherCause] = useState('')
  const [noPacketStatus, setNoPacketStatus] = useState('idle') // idle | submitting | success | error
  const [noPacketError, setNoPacketError] = useState('')
  const [packetFile, setPacketFile] = useState(null)
  const [uploadStatus, setUploadStatus] = useState('idle') // idle | uploading | success | error
  const [uploadError, setUploadError] = useState('')

  // Named once: it is long enough that repeating it invites a typo, and a
  // mistyped reason would fail the backend's enum check rather than any
  // check here.
  const PRIOR_EVENT_REASON = 'Ascertainment diagnosis referred to a prior event'

  const noPacketReasons = [
    'Outside hospital',
    'Ascertainment diagnosis error',
    PRIOR_EVENT_REASON,
    'Other',
  ]

  const showTwoAttempts = noPacketReason === 'Outside hospital'
  const showPriorEvent = noPacketReason === PRIOR_EVENT_REASON
  const showOtherCause = noPacketReason === 'Other'

  // No homepage-style preloading or local search; use the shared TableWrapper instead

  // Removed client-side table browsing/filtering

  // Load the event's identifying details so the uploader can cross-reference
  // them against the packet in hand before attaching a file. Mirrors the fetch
  // in EventScrub. No request is made when browsing the list (no event_id).
  useEffect(() => {
    if (!eventId) return
    fetch(`${API_BASE}/api/events/${encodeURIComponent(eventId)}`, {
      credentials: 'include',
    })
      .then((res) => {
        if (!res.ok) throw new Error('load')
        return res.json()
      })
      .then((json) => setDetails(json.data || null))
      .catch(() => setDetails(null))
  }, [eventId])

  const handleUploadSubmit = async (e) => {
    e.preventDefault()
    setUploadError('')
    if (!eventId) {
      setUploadStatus('error')
      setUploadError('No event selected.')
      return
    }
    if (!packetFile) {
      setUploadStatus('error')
      setUploadError('Please choose a file first.')
      return
    }
    try {
      setUploadStatus('uploading')
      const form = new FormData()
      form.append('chart_file', packetFile)
      const res = await fetch(`${API_BASE}/api/events/${encodeURIComponent(eventId)}/upload_raw`, {
        method: 'POST',
        credentials: 'include',
        body: form,
      })
      if (!res.ok) {
        let msg = 'Upload failed.'
        try {
          const j = await res.json()
          if (j && j.error) msg = j.error
        } catch {}
        setUploadStatus('error')
        setUploadError(msg)
        return
      }
      setUploadStatus('success')
      setPacketFile(null)
      // Clear the file input element
      try { e.target.reset() } catch {}
    } catch (err) {
      setUploadStatus('error')
      setUploadError('Network or server error while uploading.')
    }
  }

  // Once recorded, the event is resolved: the form locks so the same
  // declaration cannot be submitted twice (FR-020).
  const noPacketDone = noPacketStatus === 'success'

  // Client-side mirrors of the backend's V2-V8 rules. Convenience only — the
  // backend re-validates everything — but it is what turns "nothing happened"
  // into a message naming the answer that is missing (FR-011 to FR-015).
  const noPacketValidationError = () => {
    if (noPacketReason === 'Outside hospital' && twoAttempts !== '1' && twoAttempts !== '0') {
      return 'Please answer whether 2 attempts were made to obtain the medical records.'
    }
    if (noPacketReason === 'Other') {
      const cause = otherCause.trim()
      if (!cause) return 'Please enter the other cause.'
      if (cause.length > 100) return 'Other cause must be 100 characters or fewer.'
    }
    if (noPacketReason === PRIOR_EVENT_REASON) {
      if (priorEventOnsite !== '1' && priorEventOnsite !== '0') {
        return 'Please answer whether the event occurred while in care at your site.'
      }
      if (priorEventDateKnown === '1') {
        const month = priorEventMonth.trim()
        const year = priorEventYear.trim()
        if (!month && !year) {
          return 'Enter a month or a year for the prior event, or answer that the date is not known.'
        }
        if (month && !(/^\d+$/.test(month) && Number(month) >= 1 && Number(month) <= 12)) {
          return 'Month must be between 1 and 12.'
        }
        if (year && !/^\d{4}$/.test(year)) {
          return 'Year must be a four-digit year.'
        }
      }
    }
    return ''
  }

  // Answers belong to the reason that raised them. Clearing on change makes
  // the "answered, then switched" case impossible to submit at all, rather
  // than merely filtered out server-side (FR-006).
  const handleNoPacketReasonChange = (e) => {
    setNoPacketReason(e.target.value)
    setTwoAttempts('')
    setPriorEventDateKnown('')
    setPriorEventMonth('')
    setPriorEventYear('')
    setPriorEventOnsite('')
    setOtherCause('')
    setNoPacketStatus('idle')
    setNoPacketError('')
  }

  // Records that no packet can be obtained. The form previously had no
  // onSubmit at all, so pressing Submit performed a default browser
  // submission that React never intercepted and no request was ever made —
  // the silent data loss this fixes.
  const handleNoPacketSubmit = async (e) => {
    e.preventDefault()
    setNoPacketError('')
    if (!eventId) {
      setNoPacketStatus('error')
      setNoPacketError('No event selected.')
      return
    }
    if (noPacketStatus === 'submitting' || noPacketStatus === 'success') return

    const invalid = noPacketValidationError()
    if (invalid) {
      setNoPacketStatus('error')
      setNoPacketError(invalid)
      return
    }

    // Only the fields the selected reason calls for are sent. The others may
    // hold answers given before the reason was changed, and must not be
    // persisted against a reason they do not belong to (FR-006).
    const body = { reason: noPacketReason }
    if (noPacketReason === 'Outside hospital') {
      body.two_attempts = twoAttempts === '1'
    }
    if (noPacketReason === 'Other') {
      body.other_cause = otherCause
    }
    if (noPacketReason === PRIOR_EVENT_REASON) {
      body.prior_event_date_known = priorEventDateKnown === '1'
      body.prior_event_onsite = priorEventOnsite === '1'
      // Sent as typed, blanks included: a blank half is a real answer that
      // the backend encodes as a zero sentinel, not an omission.
      if (priorEventDateKnown === '1') {
        body.prior_event_month = priorEventMonth
        body.prior_event_year = priorEventYear
      }
    }

    try {
      setNoPacketStatus('submitting')
      const res = await fetch(
        `${API_BASE}/api/events/${encodeURIComponent(eventId)}/mark_no_packet`,
        {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body),
        },
      )
      if (!res.ok) {
        let msg = 'Could not record that no packet is available.'
        try {
          const j = await res.json()
          if (j && j.error) msg = j.error
        } catch {
          // A non-JSON body (a proxy error page, say) leaves the generic
          // message in place; the point is that something is always shown.
        }
        setNoPacketStatus('error')
        setNoPacketError(msg)
        return
      }
      setNoPacketStatus('success')
    } catch {
      setNoPacketStatus('error')
      setNoPacketError('Network or server error while recording the reason.')
    }
  }

  return (
    <div>
      <h1>Upload Event Packet</h1>

      {!eventId && (
        <section>
          <h3>Events That Need Packets</h3>
          <TableWrapper
            endpoint="/api/events/need_packets"
            columns={['ID', 'Date', 'Created', 'Site']}
            renderActions={(row) => (
              <>
                <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/upload?event_id=${row['ID']}` }}>upload</button>
                {' '}
                |{' '}
                <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
              </>
            )}
          />
        </section>
      )}

      {eventId && details && (
        <div className="infobox">
          <div>Packet for {headingText}</div>
          {/* Patient ID and Site Patient ID are labelled separately on purpose:
              the uploader cross-references the site's own identifier against
              the packet, so collapsing the two into one line would defeat the
              check this box exists for. */}
          <div>Patient ID: {details.patient_id}</div>
          <div>Site Patient ID: {details.site_patient_id}</div>
          <div>Date: {details.event_date}</div>
          <div>
            Criteria:{' '}
            {details.criteria && details.criteria.length > 0
              ? details.criteria
                  .map((c) => `${c.name}: ${c.value || EMPTY_VALUE}`)
                  .join(', ')
              : EMPTY_VALUE}
          </div>
        </div>
      )}

      {configResolved && (
        <div className="infobox">
          <h3>Review packets should contain:</h3>
          {guidance.packets.items.length > 0 && (
            <ol>
              {guidance.packets.items.map((item, i) => (
                <li key={i}>{item}</li>
              ))}
            </ol>
          )}
          <GuidanceLinks box={guidance.packets} />
        </div>
      )}

      {eventId && (
        <>
          <h2 className="indent1" style={{ paddingTop: '6px' }}>
            If packet is available:
          </h2>
          <div className="indent2">
            <form onSubmit={handleUploadSubmit}>
              <div>
                <label>
                  Choose a file to upload:{' '}
                  <input
                    type="file"
                    name="chart_file"
                    onChange={(e) => setPacketFile(e.target.files && e.target.files[0] ? e.target.files[0] : null)}
                  />
                </label>
              </div>
              <div style={{ paddingTop: '6px' }}>
                <button type="submit" disabled={uploadStatus === 'uploading'}>
                  {uploadStatus === 'uploading' ? 'Uploading…' : 'Upload'}
                </button>
              </div>
              {uploadStatus === 'error' && uploadError && (
                <div style={{ color: 'red', paddingTop: '6px' }}>{uploadError}</div>
              )}
              {uploadStatus === 'success' && (
                <div style={{ color: 'green', paddingTop: '6px' }}>
                  {uploadSuccessMessage}
                </div>
              )}
            </form>
          </div>

          <h2 className="indent1" style={{ paddingTop: '6px' }}>
            If no packet is available:
          </h2>
          <div className="indent2">
            <form onSubmit={handleNoPacketSubmit}>
          <div id="noPacketReason" style={{ marginBottom: '12px' }}>
            Please document why there is no event packet:{' '}
            <select
              id="noPacketReasonSelect"
              value={noPacketReason}
              onChange={handleNoPacketReasonChange}
              disabled={noPacketDone}
            >
              <option value="">Select a reason</option>
              {noPacketReasons.map((reason) => (
                <option value={reason} key={reason}>
                  {reason}
                </option>
              ))}
            </select>
          </div>

          {showTwoAttempts && (
            <div id="twoAttempts">
              <div>
                The protocol requests that 2 attempts are made to obtain medical
                records for all events that occurred at outside hospitals if the
                location is known. Have you made 2 attempts to request the
                medical records from the outside hospital?
              </div>
              <div style={{ marginTop: '8px' }} className="indent3">
                <label>
                  <input
                    type="radio"
                    name="twoAttemptsFlag"
                    value="1"
                    checked={twoAttempts === '1'}
                    onChange={(e) => setTwoAttempts(e.target.value)}
                    disabled={noPacketDone}
                  />
                  {' '}Yes, 2 attempts were made
                </label>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <label>
                  <input
                    type="radio"
                    name="twoAttemptsFlag"
                    value="0"
                    checked={twoAttempts === '0'}
                    onChange={(e) => setTwoAttempts(e.target.value)}
                    disabled={noPacketDone}
                  />
                  {' '}No
                </label>
              </div>
            </div>
          )}

          {showPriorEvent && (
            <div id="priorEventDateKnown">
              <div>
                Is approximate month/year of the prior event known?
                <span className="indent3">
                  <label>
                    <input
                      type="radio"
                      name="priorEventDateKnown"
                      value="1"
                      checked={priorEventDateKnown === '1'}
                      onChange={(e) => setPriorEventDateKnown(e.target.value)}
                      disabled={noPacketDone}
                    />
                    {' '}Yes
                  </label>
                  &nbsp;&nbsp;&nbsp;&nbsp;
                  <label>
                    <input
                      type="radio"
                      name="priorEventDateKnown"
                      value="0"
                      checked={priorEventDateKnown === '0'}
                      onChange={(e) => setPriorEventDateKnown(e.target.value)}
                      disabled={noPacketDone}
                    />
                    {' '}No
                  </label>
                </span>
              </div>
            </div>
          )}

          {showPriorEvent && priorEventDateKnown === '1' && (
            <div id="priorEventDate" style={{ paddingTop: '12px' }}>
              <div>
                Please enter the month/year of the prior event. Leave a field
                blank if it is unknown:
              </div>
              <div style={{ paddingTop: '6px' }} className="indent3">
                Month:{' '}
                <input
                  type="number"
                  min="1"
                  max="12"
                  value={priorEventMonth}
                  onChange={(e) => setPriorEventMonth(e.target.value)}
                  disabled={noPacketDone}
                />{' '}
                Year:{' '}
                <input
                  type="text"
                  size="4"
                  value={priorEventYear}
                  onChange={(e) => setPriorEventYear(e.target.value)}
                  disabled={noPacketDone}
                />
              </div>
            </div>
          )}

          {showPriorEvent && (
            <div id="priorEventOnsite" style={{ paddingTop: '12px' }}>
              <div>
                Did event occur while in care at your site?
                <span className="indent3">
                  <label>
                    <input
                      type="radio"
                      name="priorEventOnsite"
                      value="1"
                      checked={priorEventOnsite === '1'}
                      onChange={(e) => setPriorEventOnsite(e.target.value)}
                      disabled={noPacketDone}
                    />
                    {' '}Yes
                  </label>
                  &nbsp;&nbsp;&nbsp;&nbsp;
                  <label>
                    <input
                      type="radio"
                      name="priorEventOnsite"
                      value="0"
                      checked={priorEventOnsite === '0'}
                      onChange={(e) => setPriorEventOnsite(e.target.value)}
                      disabled={noPacketDone}
                    />
                    {' '}No
                  </label>
                </span>
              </div>
            </div>
          )}

          {showOtherCause && (
            <div id="otherCause">
              <label>
                Other cause:{' '}
                {/* Deliberately no maxLength: the browser would silently
                    truncate a pasted over-length cause, which is the class of
                    failure this feature exists to end. Over-length is refused
                    with the limit stated instead (FR-015). */}
                <input
                  type="text"
                  name="otherCause"
                  value={otherCause}
                  onChange={(e) => setOtherCause(e.target.value)}
                  disabled={noPacketDone}
                />
              </label>
            </div>
          )}

          {(noPacketReason && (
            <div id="submit" style={{ paddingTop: '12px' }}>
              <button
                type="submit"
                disabled={noPacketStatus === 'submitting' || noPacketDone}
              >
                {noPacketStatus === 'submitting' ? 'Submitting…' : 'Submit'}
              </button>
            </div>
          )) || null}

          {noPacketStatus === 'error' && noPacketError && (
            <div style={{ color: 'red', paddingTop: '6px' }}>{noPacketError}</div>
          )}
          {noPacketStatus === 'success' && (
            <div style={{ color: 'green', paddingTop: '6px' }}>
              Recorded: no packet is available for {headingText}.
            </div>
          )}
        </form>
      </div>
        </>
      )}
    </div>
  )
}

export default EventUpload
