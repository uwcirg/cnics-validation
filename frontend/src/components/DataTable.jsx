import { useMemo, useState } from 'react'
import "./DataTable.css"

// ``rows`` is expected to contain only the rows for the current page.
// ``totalCount`` is optional and can be used to compute total pages when
// available from the API.
function DataTable({ rows, onRowClick, onPageChange, totalCount, columns, renderActions, sortKey: controlledSortKey, sortDir: controlledSortDir, onSortChange }) {
  const [page, setPage] = useState(1)
  const [uncontrolledSortKey, setUncontrolledSortKey] = useState(null)
  const [uncontrolledSortDir, setUncontrolledSortDir] = useState('none') // 'none' | 'asc' | 'desc'
  const pageSize = 20

  // When using server‑side pagination ``rows`` will change whenever a new
  // page is fetched. We no longer reset ``page`` back to ``1`` on each update
  // so that the current page indicator remains stable.

  const isServerMode = Boolean(onPageChange)
  const clientTotalPages = !isServerMode ? Math.ceil((rows?.length || 0) / pageSize) : null
  const totalPages = totalCount ? Math.ceil(totalCount / pageSize) : clientTotalPages

  const effectiveSortKey = isServerMode && (controlledSortKey || controlledSortDir)
    ? controlledSortKey
    : uncontrolledSortKey
  const effectiveSortDir = isServerMode && (controlledSortKey || controlledSortDir)
    ? (controlledSortDir || 'none')
    : uncontrolledSortDir

  const sortedRows = useMemo(() => {
    if (isServerMode) return rows
    if (!rows || rows.length === 0 || !effectiveSortKey || effectiveSortDir === 'none') return rows
    const copy = [...rows]
    const parseMaybeNumber = (v) => {
      const n = Number(v)
      return Number.isNaN(n) ? null : n
    }
    const parseMaybeDate = (v) => {
      const d = new Date(v)
      return isNaN(d.getTime()) ? null : d.getTime()
    }
    copy.sort((a, b) => {
      const av = a[effectiveSortKey]
      const bv = b[effectiveSortKey]
      // Try date, then number, then string
      const ad = parseMaybeDate(av)
      const bd = parseMaybeDate(bv)
      let cmp = 0
      if (ad !== null && bd !== null) cmp = ad - bd
      else {
        const an = parseMaybeNumber(av)
        const bn = parseMaybeNumber(bv)
        if (an !== null && bn !== null) cmp = an - bn
        else cmp = String(av || '').localeCompare(String(bv || ''), undefined, { numeric: true })
      }
      return effectiveSortDir === 'asc' ? cmp : -cmp
    })
    return copy
  }, [rows, isServerMode, effectiveSortKey, effectiveSortDir])

  if (!rows.length) return <p>No data found.</p>

  let headers = columns && columns.length ? columns : ['ID', 'Patient ID', 'Date', 'Criteria'].filter(
    (h) => rows[0] && (h in rows[0])
  )
  if ((!headers || headers.length === 0) && rows[0]) {
    headers = Object.keys(rows[0])
  }

  const pageRows = !isServerMode
    ? sortedRows.slice((page - 1) * pageSize, (page - 1) * pageSize + pageSize)
    : sortedRows

  const toggleSort = (key) => {
    // Controlled/server-side sorting path
    if (onSortChange) {
      const currentKey = controlledSortKey || null
      const currentDir = controlledSortDir || 'none'
      let newKey = key
      let newDir = 'asc'
      if (currentKey === key) {
        if (currentDir === 'none') newDir = 'asc'
        else if (currentDir === 'asc') newDir = 'desc'
        else newDir = 'none'
      }
      onSortChange(newKey, newDir)
      setPage(1)
      if (isServerMode && onPageChange) onPageChange(1)
      return
    }
    // Uncontrolled/client-side sorting path
    if (uncontrolledSortKey !== key) {
      setUncontrolledSortKey(key)
      setUncontrolledSortDir('asc')
      setPage(1)
      return
    }
    setUncontrolledSortDir((d) => {
      if (d === 'none') return 'asc'
      if (d === 'asc') return 'desc'
      return 'none'
    })
    setPage(1)
  }

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
              <th
                key={h}
                onClick={() => toggleSort(h)}
                style={{ cursor: 'pointer', userSelect: 'none' }}
                title="Click to sort"
              >
                {h}{' '}
                {(effectiveSortKey === h && effectiveSortDir !== 'none') ? (effectiveSortDir === 'asc' ? '▲' : '▼') : ''}
              </th>
            ))}
            {renderActions && <th />}
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
              {renderActions && (
                <td>{renderActions(row)}</td>
              )}
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
