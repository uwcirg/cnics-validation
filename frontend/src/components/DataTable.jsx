import { useState } from 'react'
import "./DataTable.css"

// ``rows`` is expected to contain only the rows for the current page.
// ``totalCount`` is optional and can be used to compute total pages when
// available from the API.
function DataTable({ rows, onRowClick, onPageChange, totalCount }) {
  const [page, setPage] = useState(1)
  const pageSize = 20

  // When using server‑side pagination ``rows`` will change whenever a new
  // page is fetched. We no longer reset ``page`` back to ``1`` on each update
  // so that the current page indicator remains stable.

  if (!rows.length) return <p>No data found.</p>

  const totalPages = totalCount ? Math.ceil(totalCount / pageSize) : null
  let headers = ['ID', 'Patient ID', 'Date', 'Criteria'].filter(
    (h) => h in rows[0]
  )
  if (headers.length === 0) {
    headers = Object.keys(rows[0])
  }
  const pageRows = rows

  const goPrev = () => {
    setPage((p) => {
      const newPage = Math.max(1, p - 1)
      if (newPage !== p && onPageChange) onPageChange(newPage)
      return newPage
    })
  }
  const goNext = () => {
    setPage((p) => {
      const newPage = totalPages ? Math.min(totalPages, p + 1) : p + 1
      if (
        (totalPages ? newPage <= totalPages : rows.length === pageSize) &&
        newPage !== p &&
        onPageChange
      ) {
        onPageChange(newPage)
      }
      return newPage
    })
  }

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
      {rows.length > 0 && (
        <div className="pagination">
          <button onClick={goPrev} disabled={page === 1}>
            Previous
          </button>
          <span>
            Page {page}
            {totalPages ? ` of ${totalPages}` : ''}
          </span>
          <button
            onClick={goNext}
            disabled={totalPages ? page === totalPages : rows.length < pageSize}
          >
            Next
          </button>
        </div>
      )}
    </>
  )
}

export default DataTable
