import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import DataTable from '../DataTable'

// Base URL for the backend API. When running under Docker Compose the
// environment variable is provided by the compose file. Fallback to a
// relative path so the frontend can be served without configuration.
const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
const PAGE_SIZE = 20

// Paged, searchable event table used by the home page (moved here from
// Home.jsx so the review queue below can share it; spec 012 research D13).
// A row click opens the packet-upload page unless `onRowClick` is given.
// With `emptyMessage`, an unfiltered table that loads no rows shows that
// message instead of the table.
export function TableWrapper({ endpoint, columns, renderActions, pageSize = PAGE_SIZE, onRowClick, emptyMessage }) {
  const navigate = useNavigate()
  const [rows, setRows] = useState([])
  const [totalCount, setTotalCount] = useState(null)
  const [search, setSearch] = useState('')
  const [siteFilter, setSiteFilter] = useState('')
  const [loaded, setLoaded] = useState(false)

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
        setLoaded(true)
      })
      .catch(() => {})
  }

  useEffect(() => {
    fetchPage(1)
  }, [endpoint])

  useEffect(() => {
    fetchPage(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, siteFilter])

  const handleClick = (row) => {
    if (onRowClick) {
      onRowClick(row)
      return
    }
    navigate(
      `/events/upload?event_id=${row['ID']}&patient_id=${row['Patient ID']}&date=${row['Date']}&criteria=${encodeURIComponent(row['Criteria'])}`
    )
  }
  const sites = Array.from(
    new Set(rows.map((r) => r['Site'] || r['site']).filter(Boolean))
  ).sort()

  if (emptyMessage && loaded && rows.length === 0 && !search && !siteFilter) {
    return <p>{emptyMessage}</p>
  }
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

// "Events to Review": the events awaiting the signed-in reviewer's review
// (admins see every review-eligible event; the backend scopes the rows).
// It is shown on the home page and at /events/review without an event_id,
// the "Review Events" menu link (spec 012 FR-025, research D13). A row
// click and its "review" button both open that event's review page.
function ReviewQueue() {
  const navigate = useNavigate()
  const openReview = (row) => navigate(`/events/review?event_id=${row['ID']}`)
  return (
    <>
      <h3>Events to Review</h3>
      <p>Events with packets ready for review.</p>
      <TableWrapper
        endpoint="/api/events/for_review"
        columns={['ID', 'Patient ID', 'Date', 'Criteria', 'Site']}
        onRowClick={openReview}
        emptyMessage="No events are awaiting review."
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); openReview(row) }}>review</button>
        )}
      />
    </>
  )
}

export default ReviewQueue
