import { useMemo, useState } from 'react'

// The MI (`mci`) review form: Step 2 onwards of the review page.
//
// Moved verbatim out of `pages/EventReview.jsx` by spec 012 (task T013), so
// each study's form is its own component under `studies/<study>/`
// (constitution Principle IV, mechanism 3), selected by
// `components/review/reviewForms.js` from `review_form` on GET /api/config.
// The page shell — instructions box, heading, date, Step 1 — stays in
// EventReview.jsx and is shared by every study. Behaviour is unchanged.
//
// Spec 014: the same form edits a submitted review. With `mode="edit"` it is
// pre-filled from `initialReview` (the reviewer's own `reviews` row, as
// served by GET …/review `mine.answers`) and saves with PUT.

// A stored 0/1 flag as the radio value '1'/'0', or '' when not answered.
const radioValue = (v) => (v === null || v === undefined || v === '' ? '' : (Number(v) ? '1' : '0'))
// A stored text column as an input value ('' for null).
const textValue = (v) => (v === null || v === undefined ? '' : String(v))

function MciReviewForm({ eventId, apiUrl, initialReview = null, mode = 'create', onSaved, onConflict }) {
  const r = initialReview || {}
  const editing = mode === 'edit'

  // Core fields
  const [mci, setMci] = useState(textValue(r.mci))
  const [abnormalCeValues, setAbnormalCeValues] = useState(Boolean(r.abnormal_ce_values_flag))
  const [ceCriteria, setCeCriteria] = useState(textValue(r.ce_criteria))
  const [chestPain, setChestPain] = useState(Boolean(r.chest_pain_flag))
  const [ecgChanges, setEcgChanges] = useState(Boolean(r.ecg_changes_flag))
  const [lvmByImaging, setLvmByImaging] = useState(Boolean(r.lvm_by_imaging_flag))

  const [ci, setCi] = useState(radioValue(r.ci)) // '1' or '0'
  const [ciType, setCiType] = useState(textValue(r.ci_type))

  const [type, setType] = useState(textValue(r.type)) // 'Primary' | 'Secondary'
  const [secondaryCause, setSecondaryCause] = useState(textValue(r.secondary_cause))
  const [otherCause, setOtherCause] = useState(textValue(r.other_cause))

  const [falsePositive, setFalsePositive] = useState(Boolean(r.false_positive_flag))
  const [falsePositiveReason, setFalsePositiveReason] = useState(textValue(r.false_positive_reason))
  const [falsePositiveOtherCause, setFalsePositiveOtherCause] = useState(textValue(r.false_positive_other_cause))

  const [ecgType, setEcgType] = useState(textValue(r.ecg_type))

  const [currentTobacco, setCurrentTobacco] = useState(radioValue(r.current_tobacco_use_flag)) // '1' | '0'
  const [pastTobacco, setPastTobacco] = useState(radioValue(r.past_tobacco_use_flag))
  const [cocaine, setCocaine] = useState(radioValue(r.cocaine_use_flag))
  const [familyHistory, setFamilyHistory] = useState(radioValue(r.family_history_flag))

  const [cardiacCath, setCardiacCath] = useState(radioValue(r.cardiac_cath))

  const [submitting, setSubmitting] = useState(false)
  const [submitMessage, setSubmitMessage] = useState(null)


  // Option lists (could be loaded from API; inline for now)
  const mcis = ['Definite', 'Probable', 'No', 'No [resuscitated cardiac arrest]']
  const ceCriterias = ['Standard criteria', 'PTCA criteria', 'CABG criteria', 'Muscle trauma other than PTCA/CABG']
  const ciTypes = ['CABG/Surgery', 'PCI/Angioplasty', 'Stent', 'Unknown']
  const types = ['Primary', 'Secondary']
  const secondaryCauses = ['MVA', 'Overdose', 'Anaphlaxis', 'GI bleed', 'Sepsis/bacteremia', 'Procedure related', 'Arrhythmia', 'Cocaine or other illicit drug induced vasospasm', 'Hypertensive urgency/emergency', 'Hypoxia', 'Hypotension', 'COVID', 'Other']
  const falsePositiveReasons = ['Congestive heart failure', 'Myocarditis', 'Pericarditis', 'Pulmonary embolism', 'Renal failure', 'Severe sepsis/shock', 'Other']
  const ecgTypes = ['STEMI', 'non-STEMI', 'Other/Uninterpretable', 'New LBBB', 'Normal', 'No EKG']

  const show = useMemo(() => {
    const res = {
      mci: true,
      criteria: false,
      ceCriteria: false,
      type: false,
      ci: false,
      ciType: false,
      secondaryCause: false,
      otherCause: false,
      falsePositiveBar: false,
      ecgType: false,
      falsePositive: false,
      falsePositiveReason: false,
      falsePositiveOtherCause: false,
      question: false,
      pastSmoking: false,
      cardiacCath: false,
      submit: false,
    }

    if (mci === 'No' || mci === 'No [resuscitated cardiac arrest]') {
      res.ci = true
      if (ci === '1') {
        res.ciType = true
        if (ciType !== '') {
          res.cardiacCath = true
          res.submit = true
        }
      } else if (ci === '0') {
        res.cardiacCath = true
        res.submit = true
      }
    } else if (mci !== '') {
      res.criteria = true
      if (abnormalCeValues) res.ceCriteria = true

      const ceOk = !abnormalCeValues || ceCriteria !== ''
      const anyCore = abnormalCeValues || chestPain || ecgChanges || lvmByImaging
      if (ceOk && anyCore) {
        res.type = true
        if (type === 'Secondary') {
          res.secondaryCause = true
          if (secondaryCause === 'Other') res.otherCause = true
        }
        if (type === 'Primary' || (type === 'Secondary' && secondaryCause !== '')) {
          res.falsePositiveBar = true
          res.ecgType = true
          res.falsePositive = true
          if (falsePositive) {
            res.falsePositiveReason = true
            if (falsePositiveReason === 'Other') res.falsePositiveOtherCause = true
          }
          res.question = true
          if (currentTobacco === '0') res.pastSmoking = true
          res.cardiacCath = true
          res.submit = true
        }
      }
    }
    return res
  }, [mci, ci, ciType, type, secondaryCause, abnormalCeValues, chestPain, ecgChanges, lvmByImaging, ceCriteria, currentTobacco, falsePositive, falsePositiveReason])

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!eventId) {
      setSubmitMessage({ type: 'error', text: 'No event selected.' })
      return
    }
    setSubmitting(true)
    setSubmitMessage(null)
    // Map the form state onto the shared `reviews` columns the backend
    // expects. Submitting advances the event lifecycle (POST .../review);
    // in a single-reviewer study this completes the event.
    const body = {
      mci,
      cardiac_cath: cardiacCath,
      abnormal_ce_values_flag: abnormalCeValues,
      ce_criteria: ceCriteria,
      chest_pain_flag: chestPain,
      ecg_changes_flag: ecgChanges,
      lvm_by_imaging_flag: lvmByImaging,
      ci,
      ci_type: ciType,
      type,
      secondary_cause: secondaryCause,
      other_cause: otherCause,
      false_positive_flag: falsePositive,
      false_positive_reason: falsePositiveReason,
      false_positive_other_cause: falsePositiveOtherCause,
      ecg_type: ecgType,
      current_tobacco_use_flag: currentTobacco,
      past_tobacco_use_flag: pastTobacco,
      cocaine_use_flag: cocaine,
      family_history_flag: familyHistory,
    }
    try {
      const res = await fetch(`${apiUrl}/api/events/${eventId}/review`, {
        method: editing ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body),
      })
      const json = await res.json().catch(() => ({}))
      if (res.ok) {
        if (editing) {
          setSubmitMessage({ type: 'success', text: 'Changes saved.' })
        } else {
          const status = json?.data?.status || 'updated'
          setSubmitMessage({ type: 'success', text: `Review submitted. Event status: ${status}.` })
        }
        onSaved?.()
      } else {
        const text = json?.error || `Submission failed (${res.status}).`
        setSubmitMessage({ type: 'error', text })
        // spec 014: the review can no longer be edited (e.g. the other
        // reviewer has just submitted); the page switches to read-only.
        if (res.status === 409) onConflict?.(text)
      }
    } catch {
      setSubmitMessage({ type: 'error', text: 'Submission failed: network error.' })
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <>
      <h2>Step 2: Enter Decision</h2>
      <form onSubmit={handleSubmit}>
        <table id="reviewForm">
          <tbody>
            <tr id="mci">
              <th>Was the event a Myocardial Infarction?</th>
              <td>
                <select id="mciSelect" value={mci} onChange={(e) => setMci(e.target.value)}>
                  <option value="">Select</option>
                  {mcis.map((o) => <option key={o} value={o}>{o}</option>)}
                </select>
              </td>
            </tr>

            {show.criteria && (
              <tr id="criteria">
                <th>
                  Please identify all criteria that indicated possible or definite MI. Each patient will likely have at least 2.
                </th>
                <td>
                  <div>
                    <label>
                      <input id="abnormalCeValuesFlag" type="checkbox" checked={abnormalCeValues} onChange={(e) => setAbnormalCeValues(e.target.checked)} /> Abnormal cardiac enzyme values
                    </label>
                    {show.ceCriteria && (
                      <div id="ceCriteria" style={{ marginTop: '6px' }}>
                        <div>Select which cardiac enzyme criteria were appropriate</div>
                        <select id="ceCriteriaSelect" value={ceCriteria} onChange={(e) => setCeCriteria(e.target.value)}>
                          <option value="">Select</option>
                          {ceCriterias.map((o) => <option key={o} value={o}>{o}</option>)}
                        </select>
                      </div>
                    )}
                  </div>
                  <div style={{ marginTop: '6px' }}>
                    <label>
                      <input id="chestPainFlag" type="checkbox" checked={chestPain} onChange={(e) => setChestPain(e.target.checked)} /> Chest pain
                    </label>
                  </div>
                  <div>
                    <label>
                      <input id="ecgChangesFlag" type="checkbox" checked={ecgChanges} onChange={(e) => setEcgChanges(e.target.checked)} /> ECG changes
                    </label>
                  </div>
                  <div>
                    <label>
                      <input id="lvmByImagingFlag" type="checkbox" checked={lvmByImaging} onChange={(e) => setLvmByImaging(e.target.checked)} /> Loss of viable myocardium or regional wall abnormalities by imaging
                    </label>
                  </div>
                </td>
              </tr>
            )}

            {show.ci && (
              <tr id="ci">
                <th>Did the patient have a cardiac intervention (e.g. CABG, PTCA, stent)?</th>
                <td>
                  <label>
                    <input id="CiRadio1" type="radio" name="ci" value="1" checked={ci === '1'} onChange={(e) => setCi(e.target.value)} /> Yes
                  </label>
                  &nbsp;&nbsp;
                  <label>
                    <input id="CiRadio0" type="radio" name="ci" value="0" checked={ci === '0'} onChange={(e) => setCi(e.target.value)} /> No
                  </label>
                </td>
              </tr>
            )}

            {show.ciType && (
              <tr id="ciType">
                <th>Type of CI?</th>
                <td>
                  <select id="ciTypeSelect" value={ciType} onChange={(e) => setCiType(e.target.value)}>
                    <option value="">Select</option>
                    {ciTypes.map((o) => <option key={o} value={o}>{o}</option>)}
                  </select>
                </td>
              </tr>
            )}

            {show.type && (
              <tr id="type">
                <th>Was the myocardial infarction Primary or Secondary?</th>
                <td>
                  <select id="typeSelect" value={type} onChange={(e) => setType(e.target.value)}>
                    <option value="">Select</option>
                    {types.map((o) => <option key={o} value={o}>{o}</option>)}
                  </select>
                </td>
              </tr>
            )}

            {show.secondaryCause && (
              <tr id="secondaryCause">
                <th>If Secondary, what was the cause?</th>
                <td>
                  <select id="secondaryCauseSelect" value={secondaryCause} onChange={(e) => setSecondaryCause(e.target.value)}>
                    <option value="">Select</option>
                    {secondaryCauses.map((o) => <option key={o} value={o}>{o}</option>)}
                  </select>
                </td>
              </tr>
            )}

            {show.otherCause && (
              <tr id="otherCause">
                <th>Other cause</th>
                <td>
                  <input id="otherCauseInput" value={otherCause} onChange={(e) => setOtherCause(e.target.value)} />
                </td>
              </tr>
            )}

            {show.falsePositiveBar && (
              <tr id="falsePositiveBar">
                <td colSpan={2}><hr /></td>
              </tr>
            )}

            {show.ecgType && (
              <tr id="ecgType">
                <th>ECG based type</th>
                <td>
                  <select id="ecgTypeSelect" value={ecgType} onChange={(e) => setEcgType(e.target.value)}>
                    <option value="">Select</option>
                    {ecgTypes.map((o) => <option key={o} value={o}>{o}</option>)}
                  </select>
                </td>
              </tr>
            )}

            {show.falsePositive && (
              <tr id="falsePositive">
                <th>Meets criteria for an MI but has a credible reason to be potentially a false positive</th>
                <td>
                  <label>
                    <input id="falsePositiveFlag" type="checkbox" checked={falsePositive} onChange={(e) => setFalsePositive(e.target.checked)} />
                  </label>
                </td>
              </tr>
            )}

            {show.falsePositiveReason && (
              <tr id="falsePositiveReason">
                <th>Reason for the potential false positive result</th>
                <td>
                  <select id="falsePositiveSelect" value={falsePositiveReason} onChange={(e) => setFalsePositiveReason(e.target.value)}>
                    <option value="">Select</option>
                    {falsePositiveReasons.map((o) => <option key={o} value={o}>{o}</option>)}
                  </select>
                </td>
              </tr>
            )}

            {show.falsePositiveOtherCause && (
              <tr id="falsePositiveOtherCause">
                <th>Other cause</th>
                <td>
                  <input id="falsePositiveOtherCauseInput" value={falsePositiveOtherCause} onChange={(e) => setFalsePositiveOtherCause(e.target.value)} />
                </td>
              </tr>
            )}

            {show.question && (
              <>
                <tr className="question">
                  <td colSpan={2}><hr /></td>
                </tr>
                <tr className="question">
                  <th>Is there any mention of current tobacco use?</th>
                  <td>
                    <label>
                      <input id="CurrentSmoking1" type="radio" name="CurrentSmoking" value="1" checked={currentTobacco === '1'} onChange={(e) => setCurrentTobacco(e.target.value)} /> Yes
                    </label>
                    &nbsp;&nbsp;
                    <label>
                      <input id="CurrentSmoking0" type="radio" name="CurrentSmoking" value="0" checked={currentTobacco === '0'} onChange={(e) => setCurrentTobacco(e.target.value)} /> No
                    </label>
                  </td>
                </tr>
                {show.pastSmoking && (
                  <tr id="pastSmoking">
                    <th>Is there any mention of past tobacco use?</th>
                    <td>
                      <label>
                        <input type="radio" name="PastSmoking" value="1" checked={pastTobacco === '1'} onChange={(e) => setPastTobacco(e.target.value)} /> Yes
                      </label>
                      &nbsp;&nbsp;
                      <label>
                        <input type="radio" name="PastSmoking" value="0" checked={pastTobacco === '0'} onChange={(e) => setPastTobacco(e.target.value)} /> No
                      </label>
                    </td>
                  </tr>
                )}
                <tr className="question">
                  <th>Is there any mention of past or current cocaine or crack use?</th>
                  <td>
                    <label>
                      <input type="radio" name="Cocaine" value="1" checked={cocaine === '1'} onChange={(e) => setCocaine(e.target.value)} /> Yes
                    </label>
                    &nbsp;&nbsp;
                    <label>
                      <input type="radio" name="Cocaine" value="0" checked={cocaine === '0'} onChange={(e) => setCocaine(e.target.value)} /> No
                    </label>
                  </td>
                </tr>
                <tr className="question">
                  <th>Is there any mention of a family history of coronary artery disease?</th>
                  <td>
                    <label>
                      <input type="radio" name="FamilyHistory" value="1" checked={familyHistory === '1'} onChange={(e) => setFamilyHistory(e.target.value)} /> Yes
                    </label>
                    &nbsp;&nbsp;
                    <label>
                      <input type="radio" name="FamilyHistory" value="0" checked={familyHistory === '0'} onChange={(e) => setFamilyHistory(e.target.value)} /> No
                    </label>
                  </td>
                </tr>
              </>
            )}

            {show.cardiacCath && (
              <>
                <tr className="cardiacCath"><td colSpan={2}><hr /></td></tr>
                <tr className="cardiacCath">
                  <th>Did the patient undergo a cardiac cath?</th>
                  <td>
                    <label>
                      <input id="ccRadio1" type="radio" name="CardiacCath" value="1" checked={cardiacCath === '1'} onChange={(e) => setCardiacCath(e.target.value)} /> Yes
                    </label>
                    &nbsp;&nbsp;
                    <label>
                      <input id="ccRadio0" type="radio" name="CardiacCath" value="0" checked={cardiacCath === '0'} onChange={(e) => setCardiacCath(e.target.value)} /> No
                    </label>
                  </td>
                </tr>
              </>
            )}

            {show.submit && (
              <tr id="submit">
                <td colSpan={2}>
                  <button type="submit" disabled={submitting}>
                    {submitting ? (editing ? 'Saving…' : 'Submitting…') : (editing ? 'Save changes' : 'Submit')}
                  </button>
                  {submitMessage && (
                    <span
                      style={{
                        marginLeft: '12px',
                        color: submitMessage.type === 'success' ? '#1a7f37' : '#b3261e',
                      }}
                    >
                      {submitMessage.text}
                    </span>
                  )}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </form>
    </>
  )
}

export default MciReviewForm
