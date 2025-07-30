import { useState, useEffect } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import DataTable from '../components/DataTable'
import './EventUpload.css'

const PAGE_SIZE = 20

function EventUpload() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const eventId = searchParams.get('event_id')
  const patientId = searchParams.get('patient_id')
  const date = searchParams.get('date')
  const criteria = searchParams.get('criteria')
  const [rows, setRows] = useState([])
  const [search, setSearch] = useState('')
  const [noPacketReason, setNoPacketReason] = useState('')
  const [priorEventDateKnown, setPriorEventDateKnown] = useState('')

  const API_BASE = import.meta.env.VITE_API_URL || ''

  const noPacketReasons = [
    'Outside hospital',
    'Ascertainment diagnosis error',
    'Ascertainment diagnosis referred to a prior event',
    'Other',
  ]

  const showTwoAttempts = noPacketReason === 'Outside hospital'
  const showPriorEvent =
    noPacketReason === 'Ascertainment diagnosis referred to a prior event'
  const showOtherCause = noPacketReason === 'Other'

  const fetchPage = (p) => {
    fetch(`${API_BASE}/api/events/need_packets?limit=${PAGE_SIZE}&offset=${(p - 1) * PAGE_SIZE}`)
      .then((res) => res.json())
      .then((json) => setRows(json.data || []))
      .catch(() => {})
  }

  useEffect(() => {
    fetchPage(1)
  }, [API_BASE])

  const filteredRows = rows.filter((row) =>
    Object.values(row).some((v) =>
      String(v).toLowerCase().includes(search.toLowerCase())
    )
  )

  return (
    <div>
      <h1>Upload Event Packet</h1>

      {!eventId && (
        <>
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
          <DataTable
            rows={filteredRows}
            onRowClick={(row) =>
              navigate(
                `/events/upload?event_id=${row['ID']}&patient_id=${row['Patient ID']}&date=${row['Date']}&criteria=${encodeURIComponent(row['Criteria'])}`
              )
            }
            onPageChange={fetchPage}
          />
        </>
      )}

      {eventId && (
        <div className="infobox">
          <div>Packet for MI {eventId}</div>
          <div>Patient ID: {patientId}</div>
          <div>Date: {date}</div>
          <div>Criteria: {criteria}</div>
        </div>
      )}

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
          <a href="/files/CNICS MI Review packet assembly instructions.doc" download>
            .doc
          </a>{' '}
          |{' '}
          <a
            href="/files/CNICS MI Review packet assembly instructions.pdf"
            target="_blank"
          >
            .pdf
          </a>
        </div>
      </div>

      {eventId && (
        <>
          <h2 className="indent1" style={{ paddingTop: '6px' }}>
            If packet is available:
          </h2>
          <div className="indent2">
            <form>
              <div>
                <label>
                  Choose a file to upload: <input type="file" name="packet" />
                </label>
              </div>
              <button type="submit">Upload</button>
            </form>
          </div>

          <h2 className="indent1" style={{ paddingTop: '6px' }}>
            If no packet is available:
          </h2>
          <div className="indent2">
            <form>
          <div id="noPacketReason" style={{ marginBottom: '12px' }}>
            Please document why there is no event packet:{' '}
            <select
              id="noPacketReasonSelect"
              value={noPacketReason}
              onChange={(e) => setNoPacketReason(e.target.value)}
            >
              <option value="">Select a reason</option>
              {noPacketReasons.map((reason) => (
                <option value={reason} key={reason}>
                  {reason}
                </option>
              ))}
            </select>
          </div>

          {showTwoAttempts && (
            <div id="twoAttempts">
              <div>
                The protocol requests that 2 attempts are made to obtain medical
                records for all events that occurred at outside hospitals if the
                location is known. Have you made 2 attempts to request the
                medical records from the outside hospital?
              </div>
              <div style={{ marginTop: '8px' }} className="indent3">
                <label>
                  <input type="radio" name="twoAttemptsFlag" value="1" /> Yes, 2
                  attempts were made
                </label>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <label>
                  <input type="radio" name="twoAttemptsFlag" value="0" /> No
                </label>
              </div>
            </div>
          )}

          {showPriorEvent && (
            <div id="priorEventDateKnown">
              <div>
                Is approximate month/year of the prior event known?
                <span className="indent3">
                  <label>
                    <input
                      type="radio"
                      name="priorEventDateKnown"
                      value="1"
                      checked={priorEventDateKnown === '1'}
                      onChange={(e) => setPriorEventDateKnown(e.target.value)}
                    />
                    {' '}Yes
                  </label>
                  &nbsp;&nbsp;&nbsp;&nbsp;
                  <label>
                    <input
                      type="radio"
                      name="priorEventDateKnown"
                      value="0"
                      checked={priorEventDateKnown === '0'}
                      onChange={(e) => setPriorEventDateKnown(e.target.value)}
                    />
                    {' '}No
                  </label>
                </span>
              </div>
            </div>
          )}

          {showPriorEvent && priorEventDateKnown === '1' && (
            <div id="priorEventDate" style={{ paddingTop: '12px' }}>
              <div>
                Please enter the month/year of the prior event. Leave a field
                blank if it is unknown:
              </div>
              <div style={{ paddingTop: '6px' }} className="indent3">
                Month: <input type="number" min="1" max="12" />{' '}
                Year:{' '}
                <input type="text" size="4" />
              </div>
            </div>
          )}

          {showPriorEvent && (
            <div id="priorEventOnsite" style={{ paddingTop: '12px' }}>
              <div>
                Did event occur while in care at your site?
                <span className="indent3">
                  <label>
                    <input type="radio" name="priorEventOnsite" value="1" /> Yes
                  </label>
                  &nbsp;&nbsp;&nbsp;&nbsp;
                  <label>
                    <input type="radio" name="priorEventOnsite" value="0" /> No
                  </label>
                </span>
              </div>
            </div>
          )}

          {showOtherCause && (
            <div id="otherCause">
              <label>
                Other cause: <input type="text" name="otherCause" />
              </label>
            </div>
          )}

          {(noPacketReason && (
            <div id="submit" style={{ paddingTop: '12px' }}>
              <button type="submit">Submit</button>
            </div>
          )) || null}
        </form>
      </div>
        </>
      )}
    </div>
  )
}

export default EventUpload
