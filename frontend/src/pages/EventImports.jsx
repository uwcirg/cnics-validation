import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import DataTable from '../components/DataTable'
import { showToast } from '../components/Toast'

const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
// The API's own maximum for one request; see GET /api/events/imports.
const PAGE_LIMIT = 500

// How each outcome reads on screen. `refused` and `unknown` need saying out
// loud: a refused submission has no contents to download, and an incomplete
// record must not be mistaken for a genuine zero-event import.
const OUTCOME_LABELS = {
  imported: 'Imported',
  partial: 'Imported with skipped rows',
  rejected: 'Nothing imported',
  refused: 'Too large — contents not kept',
  unknown: 'Outcome not recorded',
}

const formatSubmittedAt = (value) => {
  if (!value) return ''
  const parsed = new Date(value)
  return isNaN(parsed.getTime()) ? value : parsed.toLocaleString()
}

const formatSize = (bytes) => {
  if (typeof bytes !== 'number') return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const formatCount = (value) => (typeof value === 'number' ? String(value) : '—')

function EventImports() {
  const [records, setRecords] = useState([])
  const [totalCount, setTotalCount] = useState(null)
  const [selected, setSelected] = useState(null)
  const [loaded, setLoaded] = useState(false)
  // The selection lives in the URL so the bulk-import result panel can link
  // straight to one record (FR-018) rather than to an undifferentiated list in
  // which the administrator has to find their own import by timestamp.
  const [searchParams, setSearchParams] = useSearchParams()
  const requestedImportId = searchParams.get('import_id')

  // The whole page is fetched at once and DataTable paginates and sorts it —
  // 20 rows a page, any column — which is why PAGE_LIMIT matches the API's
  // own cap rather than a page size. Bulk imports happen a few times a month,
  // so this holds for years; if the archive ever outgrows it, the note below
  // says so rather than quietly showing a truncated history.
  useEffect(() => {
    fetch(`${API_BASE}/api/events/imports?limit=${PAGE_LIMIT}`, { credentials: 'include' })
      .then(async (res) => {
        if (!res.ok) {
          if (res.status === 401) showToast('Login required.', 'error')
          else if (res.status === 403) showToast('Not authorized.', 'error')
          else showToast('Could not load past imports.', 'error')
          throw new Error('load failed')
        }
        return res.json()
      })
      .then((body) => {
        setRecords((body && body.data) || [])
        setTotalCount(typeof body?.total === 'number' ? body.total : null)
        setLoaded(true)
      })
      .catch(() => setLoaded(true))
  }, [])

  // Open the record named by `?import_id=` once the list is in hand. Kept
  // separate from the fetch so that selecting a row — which writes the same
  // parameter back to the URL — does not re-request the list.
  useEffect(() => {
    if (!loaded || !requestedImportId) return
    const requested = records.find((r) => r.import_id === requestedImportId)
    if (requested) setSelected(requested)
    else if (records.length > 0) {
      showToast('That import is not among the imports listed here.', 'warning')
    }
  }, [loaded, records, requestedImportId])

  // DataTable renders whatever keys the column list names, so the display
  // shape is built here and the raw record is carried along for the detail
  // panel below.
  const rows = records.map((record) => ({
    Submitted: formatSubmittedAt(record.submitted_at),
    'Submitted by': record.submitted_by || '—',
    File: record.original_name || '—',
    'Events created': formatCount(record.imported_count),
    Skipped: formatCount(record.skipped_count),
    Outcome: OUTCOME_LABELS[record.outcome] || record.outcome,
    record,
  }))

  return (
    <div>
      <h1>Past CSV Imports</h1>
      <p>
        Every CSV submitted through <em>Add multiple events</em> is kept here,
        together with what the import did. Select an import to see the rows it
        skipped and to download the file exactly as it was submitted.
      </p>

      {loaded && records.length === 0 && <p>No CSV imports have been made yet.</p>}

      {typeof totalCount === 'number' && totalCount > records.length && (
        <p>
          Showing the {records.length} most recent of {totalCount} imports.
        </p>
      )}

      {records.length > 0 && (
        <DataTable
          rows={rows}
          columns={[
            'Submitted',
            'Submitted by',
            'File',
            'Events created',
            'Skipped',
            'Outcome',
          ]}
          onRowClick={(row) => {
            setSelected(row.record)
            // Keep the URL in step with the selection, so a selected record can
            // be linked to or reloaded. `replace` keeps the Back button
            // pointing at wherever the administrator came from.
            if (row.record?.import_id) {
              setSearchParams({ import_id: row.record.import_id }, { replace: true })
            }
          }}
        />
      )}

      {selected && (
        <section>
          <h2>Import of {formatSubmittedAt(selected.submitted_at)}</h2>
          <ul>
            <li>Submitted by: {selected.submitted_by || 'unknown'}</li>
            <li>File as submitted: {selected.original_name || 'unknown'}</li>
            <li>Size: {formatSize(selected.size_bytes) || 'unknown'}</li>
            <li>Events created: {formatCount(selected.imported_count)}</li>
            <li>Rows skipped: {formatCount(selected.skipped_count)}</li>
            <li>Outcome: {OUTCOME_LABELS[selected.outcome] || selected.outcome}</li>
          </ul>

          {selected.incomplete && (
            <p>
              <strong>This import&rsquo;s outcome was not recorded.</strong> The
              submitted file was archived, but the record of what the import did
              is missing, so the counts above are unknown rather than zero.
            </p>
          )}

          {selected.outcome === 'refused' && (
            <p>
              <strong>This submission was too large and its contents were not
              kept.</strong> The attempt is recorded so it is not invisible, but
              there is no file to download.
            </p>
          )}

          <h3>Skipped rows</h3>
          {selected.errors && selected.errors.length > 0 ? (
            <ul>
              {selected.errors.map((message, i) => (
                <li key={i}>{message}</li>
              ))}
            </ul>
          ) : (
            <p>No rows were skipped.</p>
          )}

          {selected.file_available && (
            <p>
              <a
                href={`${API_BASE}/api/events/imports/${selected.import_id}/file`}
              >
                Download the submitted file
              </a>
            </p>
          )}
        </section>
      )}
    </div>
  )
}

export default EventImports
