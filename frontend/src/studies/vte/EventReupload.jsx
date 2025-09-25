import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import '../../pages/Home.css'

const API_BASE = import.meta.env.VITE_API_URL || ''

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
                navigate(
                  `/vte/upload?event_id=${row['ID']}&patient_id=${row['Patient ID']}&date=${row['Date']}&criteria=${encodeURIComponent(row['Criteria'])}`
                )
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

function VTEEventReupload() {
  const [rows, setRows] = useState([])

  useEffect(() => {
    fetch(`${API_BASE}/api/vte/events/need_reupload`)
      .then((res) => res.json())
      .then((json) => setRows(json.data || []))
      .catch(() => {})
  }, [])

  return (
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src="/cnics_logo.png" alt="CNICS" />
      <h1>Re-upload Existing VTE Packets</h1>
      {rows.length ? (
        <p>{rows.length} event(s) require packet re-upload.</p>
      ) : (
        <p>No events currently require re-upload.</p>
      )}

      <section>
        <Table rows={rows} />
      </section>

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
          <a href={`${API_BASE}/files/CNICS VTE reviewer instructions.doc`} download>.doc</a>{' '}
          |{' '}
          <a href={`${API_BASE}/files/CNICS VTE reviewer instructions.pdf`} target="_blank">
            .pdf
          </a>
        </div>
      </div>
    </div>
  )
}

export default VTEEventReupload
