import { useEffect, useRef, useState } from 'react'
import './ScansReviewForm.css'

// The scans (DEXA) review form: Step 2 onwards of the review page (spec 012).
//
// The reviewer records every body region reported in the DEXA packet, each
// with an optional laterality and optional T and Z scores. Regions repeat
// ("Is there another body region?") until Submit. Selected by
// components/review/reviewForms.js from `review_form: "scans"` on
// GET /api/config. The page shell (instructions box, heading, Step 1) lives
// in pages/EventReview.jsx.
//
// UI contract: specs/012-dexa-review-form/contracts/review-ui.md.

// --- Validation rules: keep in sync with flask_backend/models/studies/scans.py
// [stored value, display label], in dropdown order.
const SCANS_REGIONS = [
  ['Spine', 'Spine'],
  ['Lumbar spine', 'Lumbar spine'],
  ['Thoracic spine', 'Thoracic spine'],
  ['Cervical spine', 'Cervical spine'],
  ['Forearm/radius', 'Forearm/radius'],
  ['Hip', 'Hip'],
  ['Proximal femur', 'Proximal femur'],
  ['Femoral neck', 'Femoral neck'],
  ['Wrist', 'Wrist'],
  ['Other', 'Other: please specify'],
]
const SCANS_LATERALITIES = ['Left', 'Right', 'Both', 'Unspecified']
const SCORE_RE = /^[+-]?(\d{1,2}(\.\d{0,2})?|\.\d{1,2})$/
const SCORE_MIN = -10
const SCORE_MAX = 10
const MAX_REGIONS = 20
const REGION_OTHER_MAX = 100
// --- end of rules kept in sync

const OTHER_MESSAGE = 'Please specify the body region'
const scoreMessage = (label) =>
  `${label} score must be a number from -10 to 10, with at most 2 decimal places`

// Fields in display order, used to find the first invalid input to focus.
const FIELD_ORDER = ['region', 'region_other', 'laterality', 't_score', 'z_score']

function scoreError(value, label) {
  const token = value.trim()
  if (token === '') return null
  if (!SCORE_RE.test(token)) return scoreMessage(label)
  const n = Number(token)
  if (Number.isNaN(n) || n < SCORE_MIN || n > SCORE_MAX) return scoreMessage(label)
  return null
}

// Client-side errors for one entry, as { field: message }.
function entryErrors(entry) {
  const errs = {}
  if (entry.region === 'Other') {
    const other = entry.regionOther.trim()
    if (!other || other.length > REGION_OTHER_MAX) errs.region_other = OTHER_MESSAGE
  }
  const t = scoreError(entry.tScore, 'T')
  if (t) errs.t_score = t
  const z = scoreError(entry.zScore, 'Z')
  if (z) errs.z_score = z
  return errs
}

function RegionSelect({ id, value, onChange, placeholder, disabled }) {
  return (
    <select id={id} value={value} onChange={(e) => onChange(e.target.value)} disabled={disabled}>
      {placeholder && <option value="">{placeholder}</option>}
      {SCANS_REGIONS.map(([v, label]) => <option key={v} value={v}>{label}</option>)}
    </select>
  )
}

function FieldError({ id, message }) {
  if (!message) return null
  return <div id={id} className="scans-field-error" role="alert">{message}</div>
}

function ScansReviewForm({ eventId, apiUrl }) {
  const [entries, setEntries] = useState([])
  // Errors keyed by `${entry.key}.${field}`. Keys are stable across removals,
  // so removing an entry never moves another entry's errors (FR-011).
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [submitted, setSubmitted] = useState(false)
  const [submitMessage, setSubmitMessage] = useState(null)
  const nextKey = useRef(1)
  const inputs = useRef({})
  const [focusTarget, setFocusTarget] = useState(null)

  // Focus requests are applied after render, once the target input exists.
  useEffect(() => {
    if (!focusTarget) return
    const el = inputs.current[focusTarget]
    if (el) el.focus()
    setFocusTarget(null)
  }, [focusTarget])

  const locked = submitting || submitted
  const inputRef = (name) => (el) => {
    if (el) inputs.current[name] = el
    else delete inputs.current[name]
  }
  const errorFor = (key, field) => errors[`${key}.${field}`]

  function setFieldErrors(key, fieldErrs, fields) {
    setErrors((prev) => {
      const next = { ...prev }
      for (const f of fields) delete next[`${key}.${f}`]
      for (const [f, msg] of Object.entries(fieldErrs)) next[`${key}.${f}`] = msg
      return next
    })
  }

  function addEntry(region) {
    if (!region || entries.length >= MAX_REGIONS) return
    const key = `r${nextKey.current++}`
    setEntries((prev) => [...prev, { key, region, regionOther: '', laterality: '', tScore: '', zScore: '' }])
    setFocusTarget(region === 'Other' ? `${key}.region_other` : `${key}.laterality`)
  }

  function updateEntry(key, patch) {
    setEntries((prev) => prev.map((e) => (e.key === key ? { ...e, ...patch } : e)))
  }

  // Changing an entry's region keeps its laterality and scores (FR-011).
  // The "Other" text is cleared only when the entry stops being "Other".
  function changeRegion(entry, region) {
    if (entry.region === 'Other' && region !== 'Other') {
      updateEntry(entry.key, { region, regionOther: '' })
      setFieldErrors(entry.key, {}, ['region', 'region_other'])
    } else {
      updateEntry(entry.key, { region })
      setFieldErrors(entry.key, {}, ['region'])
      if (region === 'Other') setFocusTarget(`${entry.key}.region_other`)
    }
  }

  function removeEntry(key) {
    setEntries((prev) => prev.filter((e) => e.key !== key))
    setErrors((prev) => {
      const next = {}
      for (const [k, v] of Object.entries(prev)) if (!k.startsWith(`${key}.`)) next[k] = v
      return next
    })
  }

  // Validate one field when the reviewer leaves it.
  function validateOnBlur(entry, field) {
    const errs = entryErrors(entry)
    setFieldErrors(entry.key, errs[field] ? { [field]: errs[field] } : {}, [field])
  }

  function focusFirstError(errMap, list) {
    for (const entry of list) {
      for (const f of FIELD_ORDER) {
        if (errMap[`${entry.key}.${f}`]) {
          setFocusTarget(`${entry.key}.${f}`)
          return
        }
      }
    }
  }

  async function handleSubmit(e) {
    e.preventDefault()
    if (locked || entries.length === 0) return
    if (!eventId) {
      setSubmitMessage({ type: 'error', text: 'No event selected.' })
      return
    }

    const clientErrs = {}
    for (const entry of entries) {
      for (const [f, msg] of Object.entries(entryErrors(entry))) clientErrs[`${entry.key}.${f}`] = msg
    }
    setErrors(clientErrs)
    if (Object.keys(clientErrs).length > 0) {
      setSubmitMessage({ type: 'error', text: 'Please correct the highlighted fields.' })
      focusFirstError(clientErrs, entries)
      return
    }

    const submittedEntries = entries
    const body = {
      regions: submittedEntries.map((entry) => ({
        region: entry.region,
        region_other: entry.region === 'Other' ? entry.regionOther.trim() : '',
        laterality: entry.laterality,
        t_score: entry.tScore.trim(),
        z_score: entry.zScore.trim(),
      })),
    }

    setSubmitting(true)
    setSubmitMessage(null)
    try {
      const res = await fetch(`${apiUrl}/api/events/${eventId}/review`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body),
      })
      const json = await res.json().catch(() => ({}))
      if (res.ok) {
        const status = json?.data?.status || 'updated'
        setSubmitted(true)
        setSubmitMessage({ type: 'success', text: `Review submitted. Event status: ${status}.` })
      } else {
        // Map server field errors (by 0-based entry index) onto the inputs.
        const serverErrs = {}
        for (const f of json?.fields || []) {
          const entry = submittedEntries[f.index]
          if (entry && FIELD_ORDER.includes(f.field)) serverErrs[`${entry.key}.${f.field}`] = f.message
        }
        setErrors(serverErrs)
        focusFirstError(serverErrs, submittedEntries)
        setSubmitMessage({ type: 'error', text: json?.error || `Submission failed (${res.status}).` })
      }
    } catch {
      setSubmitMessage({ type: 'error', text: 'Submission failed: network error.' })
    } finally {
      setSubmitting(false)
    }
  }

  const scoreInput = (entry, field, prop, label) => {
    const name = `${entry.key}.${field}`
    const err = errorFor(entry.key, field)
    return (
      <>
        <input
          id={`scans-${name}`}
          ref={inputRef(name)}
          type="text"
          inputMode="decimal"
          size={8}
          value={entry[prop]}
          disabled={locked}
          aria-invalid={err ? 'true' : undefined}
          aria-describedby={err ? `scans-${name}-error` : undefined}
          aria-label={`${label}, region ${entries.indexOf(entry) + 1}`}
          onChange={(e) => updateEntry(entry.key, { [prop]: e.target.value })}
          onBlur={() => validateOnBlur(entry, field)}
        />
        <FieldError id={`scans-${name}-error`} message={err} />
      </>
    )
  }

  return (
    <form className="scans-review-form" onSubmit={handleSubmit} noValidate>
      <h2>Step 2: Enter Body Region</h2>

      {entries.length === 0 ? (
        <table className="scans-region-table">
          <tbody>
            <tr>
              <th><label htmlFor="scans-first-region">Body region</label></th>
              <td>
                <RegionSelect id="scans-first-region" value="" placeholder="Select…" onChange={addEntry} disabled={locked} />
              </td>
            </tr>
          </tbody>
        </table>
      ) : (
        entries.map((entry, i) => {
          const k = entry.key
          const otherErr = errorFor(k, 'region_other')
          return (
            <table className="scans-region-table" key={k}>
              <caption>
                <span>Region {i + 1}</span>
                <button type="button" className="scans-remove" onClick={() => removeEntry(k)} disabled={locked}>
                  Remove
                </button>
              </caption>
              <tbody>
                <tr>
                  <th><label htmlFor={`scans-${k}.region`}>Body region</label></th>
                  <td>
                    <RegionSelect
                      id={`scans-${k}.region`}
                      value={entry.region}
                      onChange={(v) => changeRegion(entry, v)}
                      disabled={locked}
                    />
                    <FieldError id={`scans-${k}.region-error`} message={errorFor(k, 'region')} />
                  </td>
                </tr>
                {entry.region === 'Other' && (
                  <tr>
                    <th><label htmlFor={`scans-${k}.region_other`}>Please specify</label></th>
                    <td>
                      <input
                        id={`scans-${k}.region_other`}
                        ref={inputRef(`${k}.region_other`)}
                        type="text"
                        maxLength={REGION_OTHER_MAX}
                        value={entry.regionOther}
                        disabled={locked}
                        aria-invalid={otherErr ? 'true' : undefined}
                        aria-describedby={otherErr ? `scans-${k}.region_other-error` : undefined}
                        onChange={(e) => updateEntry(k, { regionOther: e.target.value })}
                        onBlur={() => validateOnBlur(entry, 'region_other')}
                      />
                      <FieldError id={`scans-${k}.region_other-error`} message={otherErr} />
                    </td>
                  </tr>
                )}
                <tr>
                  <th><label htmlFor={`scans-${k}.laterality`}>Laterality (optional)</label></th>
                  <td>
                    <select
                      id={`scans-${k}.laterality`}
                      ref={inputRef(`${k}.laterality`)}
                      value={entry.laterality}
                      disabled={locked}
                      onChange={(e) => updateEntry(k, { laterality: e.target.value })}
                    >
                      <option value=""></option>
                      {SCANS_LATERALITIES.map((l) => <option key={l} value={l}>{l}</option>)}
                    </select>
                    <FieldError id={`scans-${k}.laterality-error`} message={errorFor(k, 'laterality')} />
                  </td>
                </tr>
                <tr>
                  <th><label htmlFor={`scans-${k}.t_score`}>T score</label></th>
                  <td>{scoreInput(entry, 't_score', 'tScore', 'T score')}</td>
                </tr>
                <tr>
                  <th><label htmlFor={`scans-${k}.z_score`}>Z score</label></th>
                  <td>{scoreInput(entry, 'z_score', 'zScore', 'Z score')}</td>
                </tr>
              </tbody>
            </table>
          )
        })
      )}

      {entries.length > 0 && (
        <>
          <h2>Step 3: Is there another body region? If not, click &quot;Submit&quot;.</h2>
          {entries.length < MAX_REGIONS ? (
            <table className="scans-region-table">
              <tbody>
                <tr>
                  <th><label htmlFor="scans-next-region">Another body region</label></th>
                  <td>
                    <RegionSelect id="scans-next-region" value="" placeholder="Select…" onChange={addEntry} disabled={locked} />
                  </td>
                </tr>
              </tbody>
            </table>
          ) : (
            <p>The maximum of {MAX_REGIONS} body regions has been reached.</p>
          )}
          <div className="scans-submit">
            <button type="submit" disabled={locked}>
              {submitting ? 'Submitting…' : 'Submit'}
            </button>
            {submitMessage && (
              <span
                role="status"
                style={{
                  marginLeft: '12px',
                  color: submitMessage.type === 'success' ? '#1a7f37' : '#b3261e',
                }}
              >
                {submitMessage.text}
              </span>
            )}
          </div>
        </>
      )}
    </form>
  )
}

export default ScansReviewForm
