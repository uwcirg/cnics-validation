import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import DataTable from '../components/DataTable'
import './Home.css'

// Base URL for the backend API. When running under Docker Compose the
// environment variable is provided by the compose file. Fallback to a
// relative path so the frontend can be served without configuration.
const API_BASE = import.meta.env.VITE_API_URL || ''
const PAGE_SIZE = 20

function TableWrapper({ endpoint, columns, renderActions }) {
  const navigate = useNavigate()
  const [rows, setRows] = useState([])
  const [totalCount, setTotalCount] = useState(null)
  const [search, setSearch] = useState('')
  const [siteFilter, setSiteFilter] = useState('')

  const fetchPage = (p) => {
    const params = new URLSearchParams({
      limit: String(PAGE_SIZE),
      offset: String((p - 1) * PAGE_SIZE),
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

function ReviewerList({ apiBase, q, rows, setRows }) {
  const [loading, setLoading] = useState(false)
  useEffect(() => {
    let cancelled = false
    async function load() {
      setLoading(true)
      try {
        const url = new URL(`${apiBase}/api/reviewer/awaiting`)
        if (q) url.searchParams.set('q', q)
        const res = await fetch(url, { credentials: 'include' })
        if (!cancelled && res.ok) {
          const json = await res.json()
          setRows(json.data || [])
        }
      } catch {}
      if (!cancelled) setLoading(false)
    }
    load()
    return () => { cancelled = true }
  }, [apiBase, q, setRows])

  const go = (r) => {
    const slot = r.slot
    if (!slot) return
    const id = r.id
    window.location.href = `/events/review?event_id=${id}&slot=${slot}`
  }

  if (loading && (!rows || rows.length === 0)) return <p>Loading…</p>
  if (!rows || rows.length === 0) return <p>No events awaiting your review.</p>

  return (
    <table className="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((r) => (
          <tr key={r.id} className="clickable" onClick={() => go(r)}>
            <td>
              <a href={`/events/review?event_id=${r.id}&slot=${r.slot}`} onClick={(e) => { e.preventDefault(); go(r) }}>Event {1000 + Number(r.id)}</a>
            </td>
            <td>{r.event_date || ''}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function Home() {
  const [rows, setRows] = useState([])
  const [statusSummary, setStatusSummary] = useState(null)
  const [reviewRows, setReviewRows] = useState([])
  const [reviewSearch, setReviewSearch] = useState('')

  useEffect(() => {
    fetch(`${API_BASE}/api/tables/events`, { credentials: 'include' })
      .then((res) => {
        if (!res.ok) {
          if (res.status === 401) alert('Login required');
          else if (res.status === 403) alert('Not authorized');
          throw new Error('auth');
        }
        return res.json()
      })
      .then((json) => setRows(json.data || []))
      .catch(() => {})

    fetch(`${API_BASE}/api/events/status_summary`, { credentials: 'include' })
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

  // Removed Quick Search; table-level search is provided in each section


  return (
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src={`${API_BASE}/files/cnics_logo.png`} alt="CNICS" />
      <h1>CNICS Validation</h1>
      <p>Welcome to the CNICS Validation application.</p>
      

      <section>
        <h3>Administrative Tools</h3>
        <div>
          <h4>Events</h4>
          <ul>
            <li>
              <Link to="/events/viewAll">View all events</Link>
            </li>
            <li>
              <Link to="/events/add">Add an event</Link>
            </li>
            <li>
              <Link to="/events/addMany">Add multiple events from a CSV file</Link>
            </li>
            <li>
              <a href={`${API_BASE}/api/events/export?format=csv`}>Export all events as CSV</a>
            </li>
          </ul>
          <h4>Users</h4>
          <ul>
            <li>
              <Link to="/users/add">Add a user</Link>
            </li>
            <li>
              <Link to="/users/viewAll">Edit/Delete users</Link>
            </li>
          </ul>
        </div>
      </section>


      <section>
        <h3>Upload New Packets</h3>
        <p>
          Use this page to find an event and upload its packet. Please note the
          instructions on the right about how to properly assemble a review
          packet.
        </p>
      </section>



      <section>
        <h3>Event Packets for Your Review</h3>
        <p style={{ marginTop: '4px', color: '#444' }}>
          These are events assigned to you and awaiting your review.
        </p>
        <div style={{ margin: '8px 0' }}>
          <input
            type="text"
            placeholder="Quick search (Event ID or Date, e.g., 2024-01-15)"
            value={reviewSearch}
            onChange={(e) => setReviewSearch(e.target.value)}
          />
        </div>
        <ReviewerList apiBase={API_BASE} q={reviewSearch} rows={reviewRows} setRows={setReviewRows} />
      </section>

      <section>
        <h3>Events That Need Packets</h3>
        <TableWrapper
          endpoint="/api/events/by_status/created"
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

      {/* Event Status Summary removed per request */}

      <div className="infobox">
        <h3>Review packets should contain:</h3>
        <ol>
          <li>Physician's notes closest to potential Event date</li>
          <li>Outpatient cardiology consultations</li>
          <li>In-patient cardiology notes or consults</li>
          <li>Baseline ECG</li>
          <li>First 2 ECGs after admission or in-hospital event</li>
          <li>Related procedure and diagnostic test results</li>
          <li>Related laboratory evidence</li>
          <li>
            Please redact the personal identifiers including name, birthday, and
            hospital number
          </li>
        </ol>
        <div>
          Full instructions:{' '}
          <a href={`${API_BASE}/files/CNICS MI Review packet assembly instructions.doc`} download>.doc</a>{' '}
          |{' '}
          <a
            href={`${API_BASE}/files/CNICS MI Review packet assembly instructions.pdf`}
            target="_blank"
          >
            .pdf
          </a>
        </div>
      </div>

      <div className="infobox">
        <h3>Review Instructions:</h3>
        <div>
          View as:{' '}
          <a href={`${API_BASE}/files/CNICS MI reviewer instructions.doc`} download>.doc</a> |{' '}
          <a href={`${API_BASE}/files/CNICS MI reviewer instructions.pdf`} target="_blank">
            .pdf
          </a>
        </div>
      </div>

      {/* Additional Documents removed for now */}

    </div>
  )
}

export default Home
