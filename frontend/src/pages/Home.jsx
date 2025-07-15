import { useEffect, useState } from 'react'
import './Home.css'

// Base URL for the backend API. When running under Docker Compose the
// environment variable is provided by the compose file. Fallback to a
// relative path so the frontend can be served without configuration.
const API_BASE = import.meta.env.VITE_API_URL || ''

function Table({ rows }) {
  const [page, setPage] = useState(1)
  const pageSize = 20

  if (!rows.length) return <p>No data found.</p>

  const totalPages = Math.ceil(rows.length / pageSize)
  const headers = Object.keys(rows[0])
  const pageRows = rows.slice((page - 1) * pageSize, page * pageSize)

  const goPrev = () => setPage(p => Math.max(1, p - 1))
  const goNext = () => setPage(p => Math.min(totalPages, p + 1))

  return (
    <>
      <table className="data-table">
        <thead>
          <tr>
            {headers.map(h => (
              <th key={h}>{h}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {pageRows.map((row, idx) => (
            <tr key={idx}>
              {headers.map(h => (
                <td key={h}>{row[h]}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      {totalPages > 1 && (
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

function Home() {
  const [rows, setRows] = useState([])
  const [needPacketRows, setNeedPacketRows] = useState([])
  const [reviewRows, setReviewRows] = useState([])
  const [search, setSearch] = useState('')

  useEffect(() => {
    // Use the events_view provided by the backend instead of the raw table
    fetch(`${API_BASE}/api/tables/events_view`)
      .then((res) => res.json())
      .then((json) => setRows(json.data || []))
      .catch(() => {})

    fetch(`${API_BASE}/api/events/need_packets`)
      .then((res) => res.json())
      .then((json) => setNeedPacketRows(json.data || []))
      .catch(() => {})

    fetch(`${API_BASE}/api/events/for_review`)
      .then((res) => res.json())
      .then((json) => setReviewRows(json.data || []))
      .catch(() => {})
  }, [])

  const filteredRows = rows.filter((row) =>
    Object.values(row).some((v) =>
      String(v).toLowerCase().includes(search.toLowerCase())
    )
  )

  const filteredNeedPacketRows = needPacketRows.filter((row) =>
    Object.values(row).some((v) =>
      String(v).toLowerCase().includes(search.toLowerCase())
    )
  )

  const filteredReviewRows = reviewRows.filter((row) =>
    Object.values(row).some((v) =>
      String(v).toLowerCase().includes(search.toLowerCase())
    )
  )

  return (
    <div className="home-container">
      <h1>CNICS Validation</h1>
      <p>Welcome to the CNICS Validation application.</p>
      <p>
        Use this page to find an event and upload its packet. Please note the
        instructions on the right about how to properly assemble a review
        packet.
      </p>


      <section>
        <h3>Quick Search</h3>
        <input
          className="quick-search"
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search"
        />
      </section>

      <section>
        <h3>Table Preview</h3>
        <Table rows={filteredRows} />
      </section>

      <section>
        <h3>Events That Need Packets</h3>
        <Table rows={filteredNeedPacketRows} />
      </section>

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

      <div className="infobox">
        <h3>Additional Documents:</h3>
        <ul>
          <li>
            <a href={`${API_BASE}/files/CNICS MI event scrubbing protocol.doc`} download>
              Event Scrubbing Protocol
            </a>
            {' | '}
            <a href={`${API_BASE}/files/CNICS MI event scrubbing protocol.pdf`} target="_blank">PDF</a>
          </li>
          <li>
            <a href={`${API_BASE}/files/CNICS VTE Review packet assembly instructions.doc`} download>
              VTE Packet Assembly Instructions
            </a>
            {' | '}
            <a href={`${API_BASE}/files/CNICS VTE Review packet assembly instructions.pdf`} target="_blank">PDF</a>
          </li>
          <li>
            <a href={`${API_BASE}/files/NA-ACCORD MI Review packet assembly instructions.doc`} download>
              NA-ACCORD Packet Assembly Instructions
            </a>
            {' | '}
            <a href={`${API_BASE}/files/NA-ACCORD MI Review packet assembly instructions.pdf`} target="_blank">PDF</a>
          </li>
          <li>
            <a href={`${API_BASE}/files/NA-ACCORD MI reviewer instructions.doc`} download>
              NA-ACCORD Reviewer Instructions
            </a>
            {' | '}
            <a href={`${API_BASE}/files/NA-ACCORD MI reviewer instructions.pdf`} target="_blank">PDF</a>
          </li>
        </ul>
      </div>

      {/* Dropdown menus are now available in the top navigation */}
    </div>
  )
}

export default Home
