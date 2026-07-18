import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import DataTable from '../components/DataTable'
import { resolveReviewGuidance } from '../components/reviewGuidance'
import './EventUpload.css'

const PAGE_SIZE = 20
const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

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
    navigate(
      `/events/upload?event_id=${row['ID']}&patient_id=${row['Patient ID']}&date=${row['Date']}&criteria=${encodeURIComponent(row['Criteria'])}`
    )
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

function EventUpload({ studyType, configResolved = true }) {
  const [searchParams] = useSearchParams()

  // Body content for the "Review packets should contain" box, chosen by the
  // deployment's study type. Resolved the same way as Home so both pages show
  // identical, study-aware guidance.
  const guidance = resolveReviewGuidance(studyType)
  const navigate = useNavigate()
  const eventId = searchParams.get('event_id')
  const patientId = searchParams.get('patient_id')
  const date = searchParams.get('date')
  const criteria = searchParams.get('criteria')
  // State for the upload UI only (search/table browsing removed to match Home)
  const [noPacketReason, setNoPacketReason] = useState('')
  const [priorEventDateKnown, setPriorEventDateKnown] = useState('')
  const [packetFile, setPacketFile] = useState(null)
  const [uploadStatus, setUploadStatus] = useState('idle') // idle | uploading | success | error
  const [uploadError, setUploadError] = useState('')

  const noPacketReasons = [
    'Outside hospital',
    'Ascertainment diagnosis error',
    'Ascertainment diagnosis referred to a prior event',
    'Other',
  ]

  const showTwoAttempts = noPacketReason === 'Outside hospital'
  const showPriorEvent =
    noPacketReason === 'Ascertainment diagnosis referred to a prior event'
  const showOtherCause = noPacketReason === 'Other'

  // No homepage-style preloading or local search; use the shared TableWrapper instead

  // Removed client-side table browsing/filtering

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

      {eventId && (
        <div className="infobox">
          <div>Packet for MI {eventId}</div>
          <div>Patient ID: {patientId}</div>
          <div>Date: {date}</div>
          <div>Criteria: {criteria}</div>
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
                  Upload successful. Packet is now queued for scrubbing.
                </div>
              )}
            </form>
          </div>

          <h2 className="indent1" style={{ paddingTop: '6px' }}>
            If no packet is available:
          </h2>
          <div className="indent2">
            <form>
          <div id="noPacketReason" style={{ marginBottom: '12px' }}>
            Please document why there is no event packet:{' '}
            <select
              id="noPacketReasonSelect"
              value={noPacketReason}
              onChange={(e) => setNoPacketReason(e.target.value)}
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
                  <input type="radio" name="twoAttemptsFlag" value="1" /> Yes, 2
                  attempts were made
                </label>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <label>
                  <input type="radio" name="twoAttemptsFlag" value="0" /> No
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
                Month: <input type="number" min="1" max="12" />{' '}
                Year:{' '}
                <input type="text" size="4" />
              </div>
            </div>
          )}

          {showPriorEvent && (
            <div id="priorEventOnsite" style={{ paddingTop: '12px' }}>
              <div>
                Did event occur while in care at your site?
                <span className="indent3">
                  <label>
                    <input type="radio" name="priorEventOnsite" value="1" /> Yes
                  </label>
                  &nbsp;&nbsp;&nbsp;&nbsp;
                  <label>
                    <input type="radio" name="priorEventOnsite" value="0" /> No
                  </label>
                </span>
              </div>
            </div>
          )}

          {showOtherCause && (
            <div id="otherCause">
              <label>
                Other cause: <input type="text" name="otherCause" />
              </label>
            </div>
          )}

          {(noPacketReason && (
            <div id="submit" style={{ paddingTop: '12px' }}>
              <button type="submit">Submit</button>
            </div>
          )) || null}
        </form>
      </div>
        </>
      )}
    </div>
  )
}

export default EventUpload
