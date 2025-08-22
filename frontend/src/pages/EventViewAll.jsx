import { useState } from 'react'
import DataTable from '../components/DataTable'

const API_BASE = import.meta.env.VITE_API_URL || 'https://backend.cnics-validation.pm.ssingh20.dev.cirg.uw.edu'
const PAGE_SIZE = 20

function TableSection({ title, endpoint }) {
  const [rows, setRows] = useState([])
  const [totalCount, setTotalCount] = useState(null)
  const [open, setOpen] = useState(false)

  const fetchPage = (p) => {
    fetch(`${API_BASE}${endpoint}?limit=${PAGE_SIZE}&offset=${(p - 1) * PAGE_SIZE}`,
      { credentials: 'include' })
      .then((res) => res.json())
      .then((json) => {
        const payload = json || {}
        setRows(payload.data || [])
        if (typeof payload.total === 'number') setTotalCount(payload.total)
      })
      .catch(() => {})
  }

  const toggle = () => {
    const next = !open
    setOpen(next)
    if (next && rows.length === 0) fetchPage(1)
  }

  return (
    <section>
      <h3>{title}</h3>
      <div>
        {open ? (
          <button onClick={toggle} className="hide">Hide</button>
        ) : (
          <button onClick={toggle} className="show">Show</button>
        )}
      </div>
      {open && (
        <div className="eventTable">
          <DataTable rows={rows} onPageChange={fetchPage} totalCount={totalCount} />
        </div>
      )}
    </section>
  )
}

function EventViewAll() {
  return (
    <div>
      <h1>Events Summary</h1>
      <TableSection title="All Events" endpoint="/api/events" />
      <TableSection title="To Be Uploaded" endpoint="/api/events/need_packets" />
      <TableSection title="Not Yet Reviewed" endpoint="/api/events/for_review" />
      <TableSection title="To Be Scrubbed" endpoint="/api/events/by_status/scrubbed" />
      <TableSection title="To Be Screened" endpoint="/api/events/by_status/screened" />
      <TableSection title="To Be Assigned" endpoint="/api/events/by_status/assigned" />
      <TableSection title="To Be Sent" endpoint="/api/events/by_status/sent" />
      <TableSection title="Third Review Needed" endpoint="/api/events/by_status/third_review_needed" />
      <TableSection title="Third Reviewer Assigned" endpoint="/api/events/by_status/third_review_assigned" />
      <TableSection title="All Done" endpoint="/api/events/by_status/done" />
      <TableSection title="No Packet Available" endpoint="/api/events/by_status/no_packet_available" />
      <TableSection title="Rejected" endpoint="/api/events/by_status/rejected" />
    </div>
  )
}

export default EventViewAll
