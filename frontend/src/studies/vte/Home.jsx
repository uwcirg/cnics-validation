import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import DataTable from '../../components/DataTable'
import '../../pages/Home.css'

// Base URL for the backend API. When running under Docker Compose the
// environment variable is provided by the compose file. Fallback to a
// relative path so the frontend can be served without configuration.
const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
const PAGE_SIZE = 20

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
  }, [endpoint])

  useEffect(() => {
    fetchPage(1)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, siteFilter])

  const handleClick = (row) => {
    navigate(
      `/vte/upload?event_id=${row['ID']}&patient_id=${row['Patient ID']}&date=${row['Date']}&criteria=${encodeURIComponent(row['Criteria'])}`
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

function VTEHome({ auth }) {
  const [rows, setRows] = useState([])
  const [statusSummary, setStatusSummary] = useState(null)
  const [search, setSearch] = useState('')

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

  // eslint-disable-next-line no-unused-vars
  const filteredRows = rows.filter((row) =>
    Object.values(row).some((v) =>
      String(v).toLowerCase().includes(search.toLowerCase())
    )
  )

  return (
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src="/cnics_logo.png" alt="CNICS" />
      <h1>CNICS VTE Validation</h1>
      <p>Welcome to the CNICS VTE (Venous Thromboembolism) Validation application.</p>

      {/* Four main sections */}
      {auth && auth.admin && (
        <section>
          <h3>Admin Tools</h3>
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
      )}

      {(auth && (auth.uploader || auth.admin)) && (
        <section>
          <h3>Upload New Packets</h3>
          <p>
            Use this page to find an event and upload its packet. Please note the
            instructions on the right about how to properly assemble a review
            packet.
          </p>
          <h4>Quick Search</h4>
          <p style={{ marginTop: '4px', color: '#444', fontSize: '14px' }}>
            Search across all columns (ID, Event Date, Created/Uploaded/Scrubbed, Criteria, Site). Example: "UW" or "2024-01-15".
          </p>
          <input
            className="quick-search"
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search"
          />
          
          <h4>Events That Need Packets</h4>
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

      {(auth && (auth.reviewer || auth.admin)) && (
        <section>
          <h3>Review Events</h3>
          <TableWrapper endpoint="/api/reviewer/awaiting" pageSize={3} />
        </section>
      )}

      <div className="infobox">
        <h3>Review packets should contain:</h3>
        <ol>
          <li>Physician's notes closest to potential Event date</li>
          <li>Outpatient hematology/oncology consultations</li>
          <li>In-patient hematology/oncology notes or consults</li>
          <li>Baseline imaging studies (if available)</li>
          <li>Imaging studies confirming VTE diagnosis (CTPA, V/Q scan, Doppler ultrasound)</li>
          <li>Related procedure and diagnostic test results</li>
          <li>Related laboratory evidence (D-dimer, coagulation studies, CBC)</li>
          <li>Medication history (anticoagulants, contraceptives, etc.)</li>
          <li>
            Please redact the personal identifiers including name, birthday, and
            hospital number
          </li>
        </ol>
        <div>
          Full instructions:{' '}
          <a href={`${API_BASE}/files/CNICS VTE Review packet assembly instructions.doc`} download>.doc</a>{' '}
          |{' '}
          <a
            href={`${API_BASE}/files/CNICS VTE Review packet assembly instructions.pdf`}
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
          <a href={`${API_BASE}/files/CNICS VTE reviewer instructions.doc`} download>.doc</a> |{' '}
          <a href={`${API_BASE}/files/CNICS VTE reviewer instructions.pdf`} target="_blank">
            .pdf
          </a>
        </div>
      </div>

    </div>
  )
}

export default VTEHome
