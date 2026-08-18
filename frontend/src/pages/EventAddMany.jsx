import { useState } from 'react'
import ImportResult from '../components/ImportResult'
import { useCsvImport } from '../components/useCsvImport'

function EventAddMany() {
  const [file, setFile] = useState(null)
  const { phase, elapsedSeconds, result, submit, dismiss } = useCsvImport()
  const submitting = phase === 'submitting'

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!file || submitting) return
    // Captured before awaiting: `e.currentTarget` is null by the time the
    // import resolves.
    const form = e.currentTarget
    const outcome = await submit(file)
    if (outcome && (outcome.outcome === 'imported' || outcome.outcome === 'partial')) {
      setFile(null)
      form.reset()
    }
  }

  return (
    <div>
      <h1>Add Multiple New Events</h1>
      <p>
      Select a CSV file where each line has this format:
      <blockquote>
      <code>site_patient_id, site_name, event_date, criteria</code>
      </blockquote>
      </p>

      <p>
      <code>event_date</code> is of the form <code>2000-12-25</code>, and corresponds to the date of the diagnosis of labs or procedure that trigger the review.
      </p>

      <p>
      <code>criteria</code> is a (possibly empty) list of criteria used to identify potential events.  Each criterion consists of two comma-separated fields. The first field is the name of a criterion, the second is the value of the criterion.  Some examples:
      </p>

      <ul>
      <li>
      CK,5
      </li>
      <li>
      troponins,2,CK,5
      </li>
      <li>
      troponins,2,CK,5,procedures,"CPR,defibrillation"
      </li>
      </ul>

      <p>
      Note: criteria fields that contain commas should be enclosed in double quotes
      </p>

      <form onSubmit={handleSubmit}>
        <div>
          <label>
            CSV File:
            <input
              type="file"
              name="events_csv"
              disabled={submitting}
              onChange={(e) => setFile(e.target.files[0])}
            />
          </label>
        </div>
        {/* The disabled attribute, not a flag inside the handler, is what
            closes the double-click window (FR-014), and it is driven by the
            same phase that drives the spinner below. */}
        <button type="submit" disabled={submitting}>
          {submitting ? 'Importing…' : 'Add'}
        </button>
      </form>

      {submitting && (
        <p role="status" style={{ display: 'flex', alignItems: 'center', gap: '0.6em' }}>
          <span className="spinner" aria-hidden="true" />
          <span>
            Importing&hellip; {elapsedSeconds}s elapsed. Each row is checked against
            the patient records, so this can take a minute for large files. Leave
            this page open.
          </span>
        </p>
      )}

      <ImportResult result={result} onDismiss={dismiss} />
    </div>
  )
}

export default EventAddMany
