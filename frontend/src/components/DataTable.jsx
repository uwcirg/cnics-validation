import { useState } from 'react'
import "./DataTable.css"

function DataTable({ rows, onRowClick }) {
  const [page, setPage] = useState(1)
  const pageSize = 20

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
              className={onRowClick ? 'clickable' : undefined}
              onClick={onRowClick ? () => onRowClick(row) : undefined}
            >
              {headers.map((h) => (
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

export default DataTable
