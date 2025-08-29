import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

function EventReview() {
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
  const [searchParams] = useSearchParams()
  const eventId = searchParams.get('event_id')

  // Core fields
  const [mci, setMci] = useState('')
  const [abnormalCeValues, setAbnormalCeValues] = useState(false)
  const [ceCriteria, setCeCriteria] = useState('')
  const [chestPain, setChestPain] = useState(false)
  const [ecgChanges, setEcgChanges] = useState(false)
  const [lvmByImaging, setLvmByImaging] = useState(false)

  const [ci, setCi] = useState('') // '1' or '0'
  const [ciType, setCiType] = useState('')

  const [type, setType] = useState('') // 'Primary' | 'Secondary'
  const [secondaryCause, setSecondaryCause] = useState('')
  const [otherCause, setOtherCause] = useState('')

  const [falsePositive, setFalsePositive] = useState(false)
  const [falsePositiveReason, setFalsePositiveReason] = useState('')
  const [falsePositiveOtherCause, setFalsePositiveOtherCause] = useState('')

  const [currentTobacco, setCurrentTobacco] = useState('') // '1' | '0'
  const [pastTobacco, setPastTobacco] = useState('')
  const [cocaine, setCocaine] = useState('')
  const [familyHistory, setFamilyHistory] = useState('')

  const [cardiacCath, setCardiacCath] = useState('')

  const [eventDetails, setEventDetails] = useState(null)

  // Option lists (could be loaded from API; inline for now)
  const mcis = ['Definite', 'Probable', 'No', 'No [resuscitated cardiac arrest]']
  const ceCriterias = ['Standard criteria', 'PTCA criteria', 'CABG criteria', 'Muscle trauma other than PTCA/CABG']
  const ciTypes = ['CABG/Surgery', 'PCI/Angioplasty', 'Stent', 'Unknown']
  const types = ['Primary', 'Secondary']
  const secondaryCauses = ['MVA', 'Overdose', 'Anaphlaxis', 'GI bleed', 'Sepsis/bacteremia', 'Procedure related', 'Arrhythmia', 'Cocaine or other illicit drug induced vasospasm', 'Hypertensive urgency/emergency', 'Hypoxia', 'Hypotension', 'COVID', 'Other']
  const falsePositiveReasons = ['Congestive heart failure', 'Myocarditis', 'Pericarditis', 'Pulmonary embolism', 'Renal failure', 'Severe sepsis/shock', 'Other']
  const ecgTypes = ['STEMI', 'non-STEMI', 'Other/Uninterpretable', 'New LBBB', 'Normal', 'No EKG']

  useEffect(() => {
    if (!eventId) return
    fetch(`${apiUrl}/api/events/${eventId}`, { credentials: 'include' })
      .then((r) => r.ok ? r.json() : Promise.reject(r))
      .then((j) => setEventDetails(j.data || null))
      .catch(() => setEventDetails(null))
  }, [apiUrl, eventId])

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

  const handleSubmit = (e) => {
    e.preventDefault()
    alert('Review submitted (placeholder).')
  }

  return (
    <div>
      <div className="infobox" style={{ width: '300px', fontSize: '.95em' }}>
        <h3>Review Instructions:</h3>
        <div style={{ marginTop: '8px' }}>
          View as: {" "}
          <a href={`${apiUrl}/files/CNICS MI reviewer instructions.doc`} download>.doc</a>
          {' | '}
          <a href={`${apiUrl}/files/CNICS MI reviewer instructions.pdf`} target="_blank" rel="noreferrer">.pdf</a>
        </div>
      </div>

      <h1>Review event: MI {eventId}</h1>
      {eventDetails && (
        <p>Date: {eventDetails.event_date || '—'}</p>
      )}

      <div className="indent1">
        <h2>Step 1: Review Charts</h2>
        <p>Review the packet for this event:</p>
        <ul>
          <li>
            <a href={`${apiUrl}/api/events/download/${eventId}`} target="_blank" rel="noreferrer">Download charts</a>
          </li>
        </ul>

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
                    <select id="ecgTypeSelect">
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
                  <td colSpan={2}><button type="submit">Submit</button></td>
                </tr>
              )}
            </tbody>
          </table>
        </form>
      </div>
    </div>
  )
}

export default EventReview
