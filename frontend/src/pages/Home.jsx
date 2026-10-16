import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import ReviewQueue, { TableWrapper } from '../components/review/ReviewQueue'
import { resolveReviewGuidance } from '../components/reviewGuidance'
import GuidanceLinks from '../components/GuidanceLinks'
import './Home.css'

// Base URL for the backend API. When running under Docker Compose the
// environment variable is provided by the compose file. Fallback to a
// relative path so the frontend can be served without configuration.
const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

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
                <Link to="/events/imports">View past CSV imports</Link>
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
          <ReviewQueue />
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
