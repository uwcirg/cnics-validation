// VTE (Venothromboembolic) Study Event Review Component
// This component is commented out until VTE study migration is needed
// Uncomment and modify as needed for VTE study deployment

/*
import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

function VTEEventReview() {
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
  const [searchParams] = useSearchParams()
  const eventId = searchParams.get('event_id')

  // VTE-specific form fields
  const [outcome, setOutcome] = useState('')
  const [vteType, setVteType] = useState('')
  const [dvtLocation, setDvtLocation] = useState('')
  const [peSeverity, setPeSeverity] = useState('')
  const [imagingEvidence, setImagingEvidence] = useState(false)
  const [anticoagulation, setAnticoagulation] = useState(false)
  const [thrombophiliaWorkup, setThrombophiliaWorkup] = useState(false)
  const [dvtSymptoms, setDvtSymptoms] = useState(false)
  const [peSymptoms, setPeSymptoms] = useState(false)
  const [riskFactors, setRiskFactors] = useState('')
  const [treatmentDurationDays, setTreatmentDurationDays] = useState('')

  // VTE-specific option lists
  const outcomes = ['Definite', 'Probable', 'No']
  const vteTypes = ['DVT', 'PE', 'Both', 'Other']
  const dvtLocations = ['Proximal', 'Distal', 'Upper', 'Other']
  const peSeverities = ['Massive', 'Submassive', 'Low_risk']

  const [eventDetails, setEventDetails] = useState(null)

  useEffect(() => {
    if (!eventId) return
    fetch(`${apiUrl}/api/events/${eventId}`, { credentials: 'include' })
      .then(res => res.json())
      .then(data => setEventDetails(data.data))
      .catch(console.error)
  }, [eventId, apiUrl])

  const handleSubmit = async (e) => {
    e.preventDefault()
    
    const reviewData = {
      event_id: eventId,
      outcome,
      vte_type: vteType,
      dvt_location: dvtLocation,
      pe_severity: peSeverity,
      imaging_evidence: imagingEvidence ? 1 : 0,
      anticoagulation: anticoagulation ? 1 : 0,
      thrombophilia_workup: thrombophiliaWorkup ? 1 : 0,
      dvt_symptoms: dvtSymptoms ? 1 : 0,
      pe_symptoms: peSymptoms ? 1 : 0,
      risk_factors: riskFactors,
      treatment_duration_days: treatmentDurationDays
    }

    try {
      const response = await fetch(`${apiUrl}/api/reviews`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(reviewData)
      })
      
      if (response.ok) {
        alert('VTE review submitted successfully!')
        // Redirect or update UI as needed
      } else {
        alert('Error submitting VTE review')
      }
    } catch (error) {
      console.error('Error:', error)
      alert('Error submitting VTE review')
    }
  }

  return (
    <div className="vte-event-review">
      <h2>VTE Event Review</h2>
      
      {eventDetails && (
        <div className="event-details">
          <h3>Event Details</h3>
          <p>Event ID: {eventDetails.id}</p>
          <p>Patient ID: {eventDetails.patient_id}</p>
          <p>Date: {eventDetails.date}</p>
        </div>
      )}

      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>VTE Outcome:</label>
          <select value={outcome} onChange={(e) => setOutcome(e.target.value)} required>
            <option value="">Select outcome</option>
            {outcomes.map(outcome => (
              <option key={outcome} value={outcome}>{outcome}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label>VTE Type:</label>
          <select value={vteType} onChange={(e) => setVteType(e.target.value)}>
            <option value="">Select VTE type</option>
            {vteTypes.map(type => (
              <option key={type} value={type}>{type}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label>DVT Location:</label>
          <select value={dvtLocation} onChange={(e) => setDvtLocation(e.target.value)}>
            <option value="">Select DVT location</option>
            {dvtLocations.map(location => (
              <option key={location} value={location}>{location}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label>PE Severity:</label>
          <select value={peSeverity} onChange={(e) => setPeSeverity(e.target.value)}>
            <option value="">Select PE severity</option>
            {peSeverities.map(severity => (
              <option key={severity} value={severity}>{severity}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label>
            <input 
              type="checkbox" 
              checked={imagingEvidence} 
              onChange={(e) => setImagingEvidence(e.target.checked)}
            />
            Imaging Evidence
          </label>
        </div>

        <div className="form-group">
          <label>
            <input 
              type="checkbox" 
              checked={anticoagulation} 
              onChange={(e) => setAnticoagulation(e.target.checked)}
            />
            Anticoagulation
          </label>
        </div>

        <div className="form-group">
          <label>
            <input 
              type="checkbox" 
              checked={thrombophiliaWorkup} 
              onChange={(e) => setThrombophiliaWorkup(e.target.checked)}
            />
            Thrombophilia Workup
          </label>
        </div>

        <div className="form-group">
          <label>
            <input 
              type="checkbox" 
              checked={dvtSymptoms} 
              onChange={(e) => setDvtSymptoms(e.target.checked)}
            />
            DVT Symptoms
          </label>
        </div>

        <div className="form-group">
          <label>
            <input 
              type="checkbox" 
              checked={peSymptoms} 
              onChange={(e) => setPeSymptoms(e.target.checked)}
            />
            PE Symptoms
          </label>
        </div>

        <div className="form-group">
          <label>Risk Factors:</label>
          <textarea 
            value={riskFactors} 
            onChange={(e) => setRiskFactors(e.target.value)}
            rows="3"
          />
        </div>

        <div className="form-group">
          <label>Treatment Duration (days):</label>
          <input 
            type="number" 
            value={treatmentDurationDays} 
            onChange={(e) => setTreatmentDurationDays(e.target.value)}
            min="0"
          />
        </div>

        <button type="submit">Submit VTE Review</button>
      </form>
    </div>
  )
}

export default VTEEventReview
*/
