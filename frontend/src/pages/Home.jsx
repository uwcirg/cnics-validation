import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import DataTable from '../components/DataTable'
import { resolveReviewGuidance } from '../components/reviewGuidance'
import './Home.css'

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

// Render one guidance box's optional file links. Returns null when the box
// defines no links, so no orphaned "Full instructions:" / "View as:" label is
// shown (spec 007, FR-004). A `.doc`-style link downloads; a `.pdf`-style link
// opens in a new tab — matching the original MI behavior.
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

function Home({ auth, studyType, configResolved = true }) {
  const [rows, setRows] = useState([])
  const [statusSummary, setStatusSummary] = useState(null)
  const [search, setSearch] = useState('')

  // Body content for the two bottom guidance boxes, chosen by the deployment's
  // study type (the headers stay constant — FR-001). Resolved here so the JSX
  // below stays a thin renderer over the per-study data (FR-002).
  const guidance = resolveReviewGuidance(studyType)

  useEffect(() => {
    fetch(`${API_BASE}/api/mci/tables/events`, { credentials: 'include' })
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

    fetch(`${API_BASE}/api/events/status_summary?study=mci`, { credentials: 'include' })
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
          </div>
        </section>
      )}

      {/* Events to Review section - for reviewers and admins */}
      {(auth && (auth.reviewer || auth.admin)) && (
        <section>
          <h3>Events to Review</h3>
          <p>Events with packets ready for review.</p>
          <TableWrapper
            endpoint="/api/events/for_review"
            columns={['ID', 'Patient ID', 'Date', 'Criteria', 'Site']}
            renderActions={(row) => (
              <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/review?event_id=${row['ID']}` }}>review</button>
            )}
          />
        </section>
      )}

      <section>
        <h3>Search Events</h3>
        <p>
          Search for events across all columns (ID, Event Date, Created/Uploaded/Scrubbed, Criteria, Site). 
          Example: "UW" or "2024-01-15".
        </p>
        <input
          className="quick-search"
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search events..."
        />
        
        {search && (
          <div style={{ marginTop: '20px' }}>
            <h4>Search Results</h4>
            <TableWrapper
              endpoint="/api/mci/events"
              columns={['ID', 'Date', 'Created', 'Site']}
              renderActions={(row) => (
                <>
                  {(auth && (auth.uploader || auth.admin)) && (
                    <>
                      <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/upload?event_id=${row['ID']}` }}>upload</button>
                      {' '}
                      |{' '}
                    </>
                  )}
                  <button onClick={(e) => { e.stopPropagation(); window.location.href = `/events/edit?event_id=${row['ID']}` }}>edit</button>
                </>
              )}
            />
          </div>
        )}
      </section>






      {/* Study-aware guidance boxes. Headers are constant across studies
          (FR-001); body items and links come from the per-study content
          (FR-002). Gated on configResolved so a non-mci deployment never
          flashes mci content before its own resolves (FR-010). */}
      {configResolved && (
        <>
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

          <div className="infobox">
            <h3>Review Instructions:</h3>
            {/* The instructions box is prose, not a checklist — render each
                item as its own line rather than a numbered list. */}
            {guidance.instructions.items.map((item, i) => (
              <div key={i}>{item}</div>
            ))}
            <GuidanceLinks box={guidance.instructions} />
          </div>
        </>
      )}

    </div>
  )
}

export default Home
