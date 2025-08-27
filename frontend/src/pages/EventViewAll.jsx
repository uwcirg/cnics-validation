import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'

const API_BASE = import.meta.env.VITE_API_URL || ''
const PAGE_SIZE = 20

function TableSection({ title, endpoint, columns, renderActions, augmentRows, mergeEndpoints }) {
  const [rows, setRows] = useState([])
  const [totalCount, setTotalCount] = useState(null)
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [siteFilter, setSiteFilter] = useState('')
  const [colFilters, setColFilters] = useState({})

  const fetchPage = (p) => {
    const params = new URLSearchParams({
      limit: String(PAGE_SIZE),
      offset: String((p - 1) * PAGE_SIZE),
    })
    if (search) params.set('q', search)
    if (siteFilter) params.set('site', siteFilter)
    const urlFor = (ep) => `${API_BASE}${ep}?${params.toString()}`
    const endpoints = [endpoint, ...(mergeEndpoints || [])]
    Promise.all(endpoints.map((ep) => fetch(urlFor(ep), { credentials: 'include' })))
      .then(async (responses) => {
        for (const res of responses) {
          if (!res.ok) {
            if (res.status === 401) alert('Login required');
            else if (res.status === 403) alert('Not authorized');
            throw new Error('auth')
          }
        }
        const payloads = await Promise.all(responses.map((r) => r.json()))
        const allRows = payloads.flatMap((pl) => (pl && pl.data) ? pl.data : [])
        const dedup = []
        const seen = new Set()
        for (const r of allRows) {
          const id = r['ID'] || r.id
          if (id == null || seen.has(id)) continue
          seen.add(id)
          dedup.push(r)
        }
        let data = dedup
        if (augmentRows) {
          try {
            const augmented = await augmentRows(data)
            data = augmented || data
          } catch {}
        }
        setRows(data)
        // Derive totalCount from API payloads when available. For merged endpoints,
        // sum totals (assuming disjoint result sets per endpoint).
        const totals = payloads.map((pl) => (pl && typeof pl.total === 'number') ? pl.total : null)
        if (totals.every((t) => typeof t === 'number')) {
          const sum = totals.reduce((a, b) => a + (b || 0), 0)
          setTotalCount(sum)
        } else if (typeof (payloads[0] && payloads[0].total) === 'number') {
          setTotalCount(payloads[0].total)
        } else {
          setTotalCount(null)
        }
      })
      .catch(() => {})
  }

  const toggle = () => {
    const next = !open
    setOpen(next)
    if (next) fetchPage(1)
  }

  useEffect(() => {
    if (open) fetchPage(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [endpoint, search, siteFilter, open])

  const headers = (columns && columns.length) ? columns : (rows[0] ? Object.keys(rows[0]) : [])
  const filteredByColumns = rows.filter((r) => {
    return Object.entries(colFilters).every(([key, val]) => {
      if (!val) return true
      const v = r[key]
      return String(v || '').toLowerCase().includes(String(val).toLowerCase())
    })
  })

  return (
    <section>
      <h3>{title}</h3>
      <div>
        {open ? (
          <button onClick={toggle} className="hide">Hide</button>
        ) : (
          <button onClick={toggle} className="show">Show</button>
        )}
      </div>
      {open && (
        <div className="eventTable">
          <div style={{ display: 'flex', gap: '8px', margin: '8px 0', alignItems: 'center', justifyContent: 'space-between' }}>
            <div style={{ display: 'flex', gap: '8px' }}>
              <input
                type="text"
                placeholder="Search all columns (ID, dates, criteria, site, etc.) — e.g., 'UW' or '2024-01-15'"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
              {Array.from(new Set(rows.map((r) => r['Site'] || r['site']).filter(Boolean))).length > 0 && (
                <select value={siteFilter} onChange={(e) => setSiteFilter(e.target.value)}>
                  <option value="">All Sites</option>
                  {Array.from(new Set(rows.map((r) => r['Site'] || r['site']).filter(Boolean)))
                    .sort()
                    .map((s) => (
                      <option key={s} value={s}>{s}</option>
                    ))}
                </select>
              )}
            </div>
            <div style={{ whiteSpace: 'nowrap', fontSize: '.9em', color: '#444' }}>
              {`Showing ${rows.length}${typeof totalCount === 'number' ? ` of ${totalCount}` : ''}`}
            </div>
          </div>
          {/* Column filters removed per request; sorting now via clickable headers in DataTable */}
          <DataTable
            rows={filteredByColumns}
            onPageChange={fetchPage}
            totalCount={totalCount}
            columns={columns}
            renderActions={renderActions}
          />
        </div>
      )}
    </section>
  )
}

function EventViewAll() {
  const [statusSummary, setStatusSummary] = useState(null)

  useEffect(() => {
    fetch(`${API_BASE}/api/events/status_summary`, { credentials: 'include' })
      .then((res) => {
        if (!res.ok) throw new Error('status')
        return res.json()
      })
      .then((json) => setStatusSummary(json.data || null))
      .catch(() => setStatusSummary(null))
  }, [])

  return (
    <div>
      <h1>Events Summary</h1>
      {statusSummary && (
        <section>
          <h3>Event Status Summary</h3>
          <table className="data-table">
            <thead>
              <tr>
                <th>Status</th>
                <th>Count</th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(statusSummary).map(([status, count]) => (
                <tr key={status}>
                  <td>{status}</td>
                  <td>{count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      )}
      <TableSection
        title="All Events"
        endpoint="/api/events"
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
        )}
      />
      <TableSection
        title="To Be Uploaded"
        endpoint="/api/events/need_packets"
        columns={['ID', 'Date', 'Created', 'site']}
        renderActions={(row) => (
          <>
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/upload?event_id=${row['ID']}` }}>upload</button>
            {' '}
            |{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
          </>
        )}
      />
      <TableSection
        title="Not Yet Reviewed"
        endpoint="/api/events/by_status/sent"
        mergeEndpoints={["/api/events/by_status/reviewer1_done", "/api/events/by_status/reviewer2_done"]}
        columns={['Event Number', 'Event Date', 'Sent/Last Review', 'Yet to review']}
        augmentRows={async (rows) => {
          const fetchDetails = async (id) => {
            try {
              const res = await fetch(`${API_BASE}/api/events/${id}`, { credentials: 'include' })
              if (!res.ok) return null
              const json = await res.json()
              return json.data || null
            } catch { return null }
          }
          const now = new Date()
          const msPerDay = 24 * 60 * 60 * 1000
          const toISO = (d) => d ? String(d) : ''
          const maxDate = (values) => {
            const ds = values.filter(Boolean).map((v) => new Date(v))
            if (!ds.length) return ''
            const latest = new Date(Math.max(...ds.map((x) => x.getTime())))
            return latest.toISOString().slice(0, 10)
          }
          const augmented = await Promise.all(rows.map(async (r) => {
            const id = r['ID'] || r.id
            const d = await fetchDetails(id)
            const eventDate = r['Date'] || (d && d.event_date) || ''
            const sent = d && d.send_date
            const lastReview = maxDate([sent, d && d.review1_date, d && d.review2_date])
            const pending = []
            if (d) {
              if (!d.review1_date && d.reviewer1_username) {
                const days = sent ? Math.floor((now - new Date(sent)) / msPerDay) : null
                pending.push(`${d.reviewer1_username}${days !== null ? ` (${days})` : ''}`)
              }
              if (!d.review2_date && d.reviewer2_username) {
                const days = sent ? Math.floor((now - new Date(sent)) / msPerDay) : null
                pending.push(`${d.reviewer2_username}${days !== null ? ` (${days})` : ''}`)
              }
            }
            return {
              'Event Number': id,
              'Event Date': toISO(eventDate),
              'Sent/Last Review': lastReview,
              'Yet to review': pending.join('   '),
              ID: id, // keep original key for actions
            }
          }))
          return augmented
        }}
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
        )}
      />
      {/* To Be Scrubbed */}
      <TableSection
        title="To Be Scrubbed"
        endpoint="/api/events/by_status/uploaded"
        columns={['Event Number', 'Event Date', 'Uploaded', 'Site']}
        augmentRows={(rows) => rows.map((r) => ({
          ...r,
          'Event Number': (r['ID'] != null ? 1000 + Number(r['ID']) : ''),
          'Event Date': r['Date'] || '',
        }))}
        renderActions={(row) => (
          <>
            <button onClick={(e) => { e.stopPropagation(); window.open(`${API_BASE}/api/events/download/${row['ID']}`, '_blank') }}>download</button>
            {' '}
            |{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/scrub?event_id=${row['ID']}` }}>upload scrubbed</button>
            {' '}
            |{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
          </>
        )}
      />
      {/* To Be Screened */}
      <TableSection
        title="To Be Screened"
        endpoint="/api/events/by_status/scrubbed"
        columns={['Event Number', 'Event Date', 'Scrubbed', 'Site']}
        augmentRows={(rows) => rows.map((r) => ({
          ...r,
          'Event Number': (r['ID'] != null ? 1000 + Number(r['ID']) : ''),
          'Event Date': r['Date'] || '',
        }))}
        renderActions={(row) => (
          <>
            <button onClick={(e) => { e.stopPropagation(); window.open(`${API_BASE}/api/events/download/${row['ID']}`, '_blank') }}>download</button>
            {' '}
            |{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/scrub?event_id=${row['ID']}` }}>re-upload scrubbed</button>
            {' '}
            |{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/screen?event_id=${row['ID']}` }}>screen</button>
            {' '}
            |{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
          </>
        )}
      />
      {/* To Be Assigned = awaiting assignment -> status 'screened' */}
      <TableSection
        title="To Be Assigned"
        endpoint="/api/events/by_status/screened"
        renderActions={(row) => (
          <>
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
            {' '}
            |{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/assignThird` }}>assign 3rd</button>
          </>
        )}
      />
      {/* To Be Sent = awaiting send -> status 'assigned' */}
      <TableSection
        title="To Be Sent"
        endpoint="/api/events/by_status/assigned"
        renderActions={(row) => (
          <>
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
            {' '}
            |{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/sendMany` }}>send</button>
          </>
        )}
      />
      <TableSection
        title="Third Review Needed"
        endpoint="/api/events/by_status/third_review_needed"
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
        )}
      />
      <TableSection
        title="Third Reviewer Assigned"
        endpoint="/api/events/by_status/third_review_assigned"
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
        )}
      />
      <TableSection
        title="All Done"
        endpoint="/api/events/by_status/done"
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
        )}
      />
      <TableSection
        title="No Packet Available"
        endpoint="/api/events/by_status/no_packet_available"
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
        )}
      />
      <TableSection
        title="Rejected"
        endpoint="/api/events/by_status/rejected"
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
        )}
      />
    </div>
  )
}

export default EventViewAll
