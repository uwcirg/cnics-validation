import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { resolveReviewGuidance } from '../components/reviewGuidance'
import './Home.css'

const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')

// Render one guidance box's optional file links. Kept identical to Home's
// GuidanceLinks so the guidance boxes render the same across pages. Returns
// null when the box defines no links.
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

function Table({ rows }) {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const pageSize = 20

  // Reset page when new rows come in so pagination stays in range
  useEffect(() => {
    setPage(1)
  }, [rows])

  if (!rows.length) return <p>No data found.</p>

  const totalPages = Math.ceil(rows.length / pageSize)
  const headers = ['ID', 'Patient ID', 'Date', 'Criteria'].filter(
    (h) => h in rows[0]
  )
  const pageRows = rows.slice((page - 1) * pageSize, page * pageSize)

  const goPrev = () => setPage((p) => Math.max(1, p - 1))
  const goNext = () => setPage((p) => Math.min(totalPages, p + 1))

  return (
    <>
      <table className="data-table">
        <thead>
          <tr>
            {headers.map((h) => (
              <th key={h}>{h}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {pageRows.map((row, idx) => (
            <tr
              key={idx}
              className="clickable"
              onClick={() =>
                // Event id only; the upload page loads the identifying values
                // from the stored record.
                navigate(`/events/upload?event_id=${row['ID']}`)
              }
            >
              {headers.map((h) => (
                <td key={h}>{row[h]}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      {rows.length > 0 && (
        <div className="pagination">
          <button onClick={goPrev} disabled={page === 1}>
            Previous
          </button>
          <span>
            Page {page} of {totalPages}
          </span>
          <button onClick={goNext} disabled={page === totalPages}>
            Next
          </button>
        </div>
      )}
    </>
  )
}

function EventReupload({ studyType, configResolved = true }) {
  const [rows, setRows] = useState([])

  // Body content for the two bottom guidance boxes, chosen by the deployment's
  // study type. Resolved the same way as Home so all pages show identical,
  // study-aware guidance.
  const guidance = resolveReviewGuidance(studyType)

  useEffect(() => {
    fetch(`${API_BASE}/api/events/need_reupload`)
      .then((res) => res.json())
      .then((json) => setRows(json.data || []))
      .catch(() => {})
  }, [])

  return (
    <div className="home-container">
      <h1>Re-upload Existing Packets</h1>
      {rows.length ? (
        <p>{rows.length} event(s) require packet re-upload.</p>
      ) : (
        <p>No events currently require re-upload.</p>
      )}

      <section>
        <Table rows={rows} />
      </section>

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

export default EventReupload
