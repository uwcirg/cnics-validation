import { useEffect, useState } from 'react'
import DataTable from '../../components/DataTable'
import '../../pages/Home.css'

const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
const PAGE_SIZE = 20

function TableSection({ title, endpoint, columns, renderActions, augmentRows, mergeEndpoints }) {
  const [rows, setRows] = useState([])
  const [totalCount, setTotalCount] = useState(null)
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [siteFilter, setSiteFilter] = useState('')
  const [colFilters, setColFilters] = useState({})
  const [sortKey, setSortKey] = useState(null)
  const [sortDir, setSortDir] = useState('none') // 'none' | 'asc' | 'desc'

  const fetchPage = (p) => {
    const params = new URLSearchParams({
      limit: String(PAGE_SIZE),
      offset: String((p - 1) * PAGE_SIZE),
    })
    if (search) params.set('q', search)
    if (siteFilter) params.set('site', siteFilter)
    if (sortKey && sortDir && sortDir !== 'none') {
      params.set('sort_by', sortKey)
      params.set('sort_dir', sortDir)
    }
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
          data = augmentRows(data)
        }
        setRows(data)
        const total = payloads.reduce((sum, pl) => sum + (pl && typeof pl.total === 'number' ? pl.total : 0), 0)
        setTotalCount(total)
      })
      .catch(() => {})
  }

  useEffect(() => {
    if (open) fetchPage(1)
  }, [open, endpoint, search, siteFilter, sortKey, sortDir])

  const sites = Array.from(
    new Set(rows.map((r) => r['Site'] || r['site']).filter(Boolean))
  ).sort()

  const handleSort = (key) => {
    if (sortKey === key) {
      setSortDir(sortDir === 'asc' ? 'desc' : sortDir === 'desc' ? 'none' : 'asc')
    } else {
      setSortKey(key)
      setSortDir('asc')
    }
  }

  const toggle = () => {
    const next = !open
    setOpen(next)
    if (next) fetchPage(1)
  }

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
          <DataTable
            rows={rows}
            onPageChange={fetchPage}
            totalCount={totalCount}
            columns={columns}
            renderActions={renderActions}
            sortKey={sortKey}
            sortDir={sortDir}
            onSortChange={(key, dir) => { setSortKey(key); setSortDir(dir) }}
          />
        </div>
      )}
    </section>
  )
}

function VTEEventViewAll() {
  const [statusSummary, setStatusSummary] = useState(null)

  useEffect(() => {
    fetch(`${API_BASE}/api/events/status_summary?study=vte`, { credentials: 'include' })
      .then((res) => {
        if (!res.ok) {
          if (res.status === 401) alert('Login required');
          else if (res.status === 403) alert('Not authorized');
          throw new Error('auth');
        }
        return res.json()
      })
      .then((json) => setStatusSummary(json.data || null))
      .catch(() => {})
  }, [])

  return (
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src="/cnics_logo.png" alt="CNICS" />
      <h1>VTE Events - View All</h1>
      
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
        title="All VTE Events"
        endpoint="/api/vte/events"
        columns={['ID', 'Patient ID', 'Date', 'Status', 'Site', 'Created', 'Uploaded', 'Scrubbed']}
        renderActions={(row) => (
          <>
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/vte/upload?event_id=${row['ID']}` }}>upload</button>
            {' '}|{' '}
            <button onClick={(e) => { e.stopPropagation(); window.location.href = `/vte/edit?event_id=${row['ID']}` }}>edit</button>
          </>
        )}
      />

      <TableSection
        title="Events Needing Packets"
        endpoint="/api/vte/events/need_packets"
        columns={['ID', 'Patient ID', 'Date', 'Criteria', 'Site']}
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/vte/upload?event_id=${row['ID']}` }}>upload</button>
        )}
      />

      <TableSection
        title="Events for Review"
        endpoint="/api/vte/events/for_review"
        columns={['ID', 'Patient ID', 'Date', 'Criteria', 'Site']}
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/vte/review?event_id=${row['ID']}` }}>review</button>
        )}
      />

      <TableSection
        title="Events Needing Re-upload"
        endpoint="/api/vte/events/need_reupload"
        columns={['ID', 'Patient ID', 'Date', 'Criteria', 'Site']}
        renderActions={(row) => (
          <button onClick={(e) => { e.stopPropagation(); window.location.href = `/vte/upload?event_id=${row['ID']}` }}>re-upload</button>
        )}
      />
    </div>
  )
}

export default VTEEventViewAll
