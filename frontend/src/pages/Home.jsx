import { Link } from 'react-router-dom'
import { useEffect, useState } from 'react'
import './Home.css'

function Table({ rows }) {
  if (!rows.length) return <p>No data found.</p>
  const headers = Object.keys(rows[0])
  return (
    <table className="data-table">
      <thead>
        <tr>
          {headers.map(h => (
            <th key={h}>{h}</th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((row, idx) => (
          <tr key={idx}>
            {headers.map(h => (
              <td key={h}>{row[h]}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function Home() {
  const [rows, setRows] = useState([])
  const [needPacketRows, setNeedPacketRows] = useState([])
  const [search, setSearch] = useState('')

  useEffect(() => {
    fetch('/api/tables/events')
      .then((res) => res.json())
      .then((json) => setRows(json.data || []))
      .catch(() => {})

    fetch('/api/events/need_packets')
      .then((res) => res.json())
      .then((json) => setNeedPacketRows(json.data || []))
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

  return (
    <div className="home-container">
      <h1>CNICS Validation</h1>
      <p>Welcome to the CNICS Validation application.</p>

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
          <a href="/files/CNICS MI Review packet assembly instructions.doc" download>.doc</a>{' '}
          |{' '}
          <a
            href="/files/CNICS MI Review packet assembly instructions.pdf"
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
          <a href="/files/CNICS MI reviewer instructions.doc" download>.doc</a> |{' '}
          <a href="/files/CNICS MI reviewer instructions.pdf" target="_blank">
            .pdf
          </a>
        </div>
      </div>

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

      {/* Dropdown menus are now available in the top navigation */}
    </div>
  )
}

export default Home
