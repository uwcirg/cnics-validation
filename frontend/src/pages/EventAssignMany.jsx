import { useEffect, useState } from 'react'
import { showToast } from '../components/Toast'

// Match App.jsx: '' in production (same-origin), VITE_API_URL in dev.
const API_BASE = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
const PAGE_SIZE = 20

// Interactive reviewer-assignment page. The administrator picks awaiting
// events from the flag-aware "To Be Assigned" queue, chooses a reviewer
// (or, in a two-reviewer deployment, a first and a second reviewer), and
// confirms. A first-reviewer assignment advances each event to `assigned`
// and removes it from the queue.
//
// The number of reviewer slots is driven entirely by the resolved
// `workflow.reviewer_count` control, never by a study-name check
// (Constitution Principle IV; FR-008/009/010).
function EventAssignMany({ workflow }) {
  const reviewerCount = Number(workflow && workflow.reviewer_count)
  const twoReviewers = reviewerCount >= 2

  const [rows, setRows] = useState([])
  const [total, setTotal] = useState(0)
  const [loadError, setLoadError] = useState(false)
  const [reviewers, setReviewers] = useState([])
  const [reviewersLoaded, setReviewersLoaded] = useState(false)
  const [selected, setSelected] = useState({})
  const [reviewerId, setReviewerId] = useState('')
  const [reviewer2Id, setReviewer2Id] = useState('')
  const [siteFilter, setSiteFilter] = useState('')
  const [page, setPage] = useState(0)
  const [status, setStatus] = useState('idle') // 'idle' | 'saving' | 'saved' | 'error'
  const [message, setMessage] = useState('')

  // Fetch the To-Be-Assigned queue. Explicit page/site arguments avoid
  // stale-closure reads when called from event handlers.
  const fetchQueue = (pageArg, siteArg) => {
    const params = new URLSearchParams({
      limit: String(PAGE_SIZE),
      offset: String(pageArg * PAGE_SIZE),
    })
    if (siteArg) params.set('site', siteArg)
    setLoadError(false)
    fetch(`${API_BASE}/api/events/by_status/screened?${params.toString()}`, { credentials: 'include' })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((j) => {
        setRows(j.data || [])
        setTotal(typeof j.total === 'number' ? j.total : (j.data || []).length)
      })
      .catch(() => {
        setRows([])
        setTotal(0)
        setLoadError(true)
      })
  }

  useEffect(() => {
    fetchQueue(0, '')
    // Reviewers are any users with the reviewer role (admins included) —
    // the established pattern from EventAssignThird.jsx.
    fetch(`${API_BASE}/api/tables/users?limit=2000`, { credentials: 'include' })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((j) => {
        setReviewers((j.data || []).filter((u) => u.reviewer_flag))
        setReviewersLoaded(true)
      })
      .catch(() => {
        setReviewers([])
        setReviewersLoaded(true)
      })
  }, [])

  const selectedIds = () =>
    Object.keys(selected).filter((k) => selected[k]).map((k) => Number(k))

  const toggle = (id) => setSelected((prev) => ({ ...prev, [id]: !prev[id] }))

  const allSelected = rows.length > 0 && rows.every((r) => selected[r['ID']])
  const toggleAll = () => {
    if (allSelected) {
      setSelected({})
    } else {
      const next = {}
      rows.forEach((r) => { next[r['ID']] = true })
      setSelected(next)
    }
  }

  // Site-filter options derive from the sites present in the loaded rows
  // (the View All Events pattern). Changing the filter clears the current
  // selection so a confirm never acts on now-hidden events.
  const siteOptions = Array.from(new Set(rows.map((r) => r['Site']).filter(Boolean))).sort()
  const handleSiteChange = (e) => {
    const s = e.target.value
    setSiteFilter(s)
    setPage(0)
    setSelected({})
    fetchQueue(0, s)
  }

  // Pagination also clears the selection — a confirm acts only on events
  // the administrator can currently see and chose (FR-016, spec Assumptions).
  const goToPage = (p) => {
    setPage(p)
    setSelected({})
    fetchQueue(p, siteFilter)
  }
  const hasPrev = page > 0
  const hasNext = (page + 1) * PAGE_SIZE < total

  const selCount = selectedIds().length
  const samePerson = twoReviewers && !!reviewerId && reviewerId === reviewer2Id

  // FR-018: spell out what is missing before a confirm is possible.
  const missing = []
  if (selCount === 0) missing.push('select at least one event')
  if (!reviewerId) missing.push(twoReviewers ? 'choose a first reviewer' : 'choose a reviewer')
  if (twoReviewers && !reviewer2Id) missing.push('choose a second reviewer')

  const canSubmit =
    selCount > 0 &&
    !!reviewerId &&
    (!twoReviewers || (!!reviewer2Id && !samePerson)) &&
    status !== 'saving'

  const handleAssign = async () => {
    const ids = selectedIds()
    if (!ids.length || !reviewerId) return
    if (twoReviewers && (!reviewer2Id || samePerson)) return

    setStatus('saving')
    setMessage('')
    const body = { event_ids: ids, reviewer_id: Number(reviewerId), slot: 'first' }
    // Two-reviewer deployments assign both reviewers in one atomic request
    // (research Decision 4) — the queue predicate is `assign_date IS NULL`,
    // so a per-slot call would drop the event before the second reviewer.
    if (twoReviewers) body.reviewer2_id = Number(reviewer2Id)

    try {
      const res = await fetch(`${API_BASE}/api/events/assign_many`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body),
      })
      let payload = null
      try { payload = await res.json() } catch { /* no/invalid JSON body */ }

      if (res.ok) {
        const n = (payload && payload.data && payload.data.updated) || ids.length
        setStatus('saved')
        showToast(`Assigned ${n} event${n === 1 ? '' : 's'} to the chosen reviewer${twoReviewers ? 's' : ''}.`, 'success')
        setSelected({})
        setReviewerId('')
        setReviewer2Id('')
        // Re-fetch so assigned events leave the queue without a reload (FR-014).
        fetchQueue(page, siteFilter)
      } else {
        setStatus('error')
        const fallback =
          res.status === 401 ? 'Your session has expired — please sign in again.'
          : res.status === 403 ? 'You are not authorized to assign reviewers.'
          : 'The assignment could not be completed. Please retry.'
        const msg = (payload && payload.error) || fallback
        setMessage(msg)
        showToast(msg, res.status === 401 ? 'warning' : 'error')
      }
    } catch {
      setStatus('error')
      const msg = 'Network or server error while assigning — nothing was changed. Please retry.'
      setMessage(msg)
      showToast(msg, 'error')
    }
  }

  return (
    <div>
      <h1>Assign Charts</h1>

      <h2>Step 1: Select Event(s)</h2>
      <div className="indent1">
        {loadError ? (
          <p>The assignment queue could not be loaded. Please reload the page.</p>
        ) : rows.length === 0 ? (
          <p>No events are awaiting reviewer assignment.</p>
        ) : (
          <>
            <div style={{ display: 'flex', gap: '8px', margin: '8px 0', alignItems: 'center' }}>
              {siteOptions.length > 0 && (
                <select value={siteFilter} onChange={handleSiteChange}>
                  <option value="">All Sites</option>
                  {siteOptions.map((s) => (
                    <option key={s} value={s}>{s}</option>
                  ))}
                </select>
              )}
              <span style={{ fontSize: '.9em', color: '#444' }}>
                {`Showing ${rows.length} of ${total}`}
              </span>
            </div>
            <table className="data-table">
              <thead>
                <tr>
                  <th>
                    <input
                      type="checkbox"
                      checked={allSelected}
                      onChange={toggleAll}
                      aria-label="Select all events on this page"
                    />
                  </th>
                  <th>Event Number</th>
                  <th>Event Date</th>
                  <th>Site</th>
                  <th>Criteria</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r['ID']}>
                    <td>
                      <input
                        type="checkbox"
                        checked={!!selected[r['ID']]}
                        onChange={() => toggle(r['ID'])}
                      />
                    </td>
                    <td>{r['ID']}</td>
                    <td>{r['Date']}</td>
                    <td>{r['Site']}</td>
                    <td>{r['Criteria']}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div style={{ display: 'flex', gap: '8px', marginTop: '8px', alignItems: 'center' }}>
              <button onClick={() => goToPage(page - 1)} disabled={!hasPrev}>Previous</button>
              <span style={{ fontSize: '.9em' }}>Page {page + 1}</span>
              <button onClick={() => goToPage(page + 1)} disabled={!hasNext}>Next</button>
            </div>
          </>
        )}
      </div>

      <h2 style={{ marginTop: '16px' }}>
        Step 2: Choose Reviewer{twoReviewers ? 's' : ''}
      </h2>
      <div className="indent1">
        {reviewersLoaded && reviewers.length === 0 ? (
          <p>No users currently hold the reviewer role, so no reviewer can be assigned.</p>
        ) : (
          <>
            <div>
              <label>
                {twoReviewers ? 'First reviewer: ' : 'Reviewer: '}
                <select value={reviewerId} onChange={(e) => setReviewerId(e.target.value)}>
                  <option value="">Select reviewer</option>
                  {reviewers.map((u) => (
                    <option key={u.id} value={u.id}>{u.username} ({u.site})</option>
                  ))}
                </select>
              </label>
            </div>
            {/* Second slot only when reviewer_count >= 2 — never a third
                slot here (FR-010, FR-020). */}
            {twoReviewers && (
              <div style={{ marginTop: '8px' }}>
                <label>
                  Second reviewer:{' '}
                  <select value={reviewer2Id} onChange={(e) => setReviewer2Id(e.target.value)}>
                    <option value="">Select reviewer</option>
                    {reviewers.map((u) => (
                      <option key={u.id} value={u.id}>{u.username} ({u.site})</option>
                    ))}
                  </select>
                </label>
              </div>
            )}
            {samePerson && (
              <p style={{ color: '#c62828' }}>
                The first and second reviewer must be different people.
              </p>
            )}
          </>
        )}
      </div>

      <div style={{ marginTop: '12px' }}>
        <button onClick={handleAssign} disabled={!canSubmit}>Assign</button>
        {missing.length > 0 && (
          <span style={{ marginLeft: '10px', fontSize: '.9em', color: '#444' }}>
            To assign, {missing.join(', ')}.
          </span>
        )}
      </div>

      {status === 'saving' && <p>Assigning…</p>}
      {status === 'error' && message && <p style={{ color: '#c62828' }}>{message}</p>}
    </div>
  )
}

export default EventAssignMany
