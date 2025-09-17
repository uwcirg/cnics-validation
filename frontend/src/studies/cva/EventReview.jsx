// CVA (Cerebrovascular Events - Stroke) Study Event Review Component
// This component is commented out until CVA study migration is needed
// Uncomment and modify as needed for CVA study deployment

/*
import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'

function CVAEventReview() {
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
  const [searchParams] = useSearchParams()
  const eventId = searchParams.get('event_id')

  // CVA-specific form fields
  const [outcome, setOutcome] = useState('')
  const [strokeType, setStrokeType] = useState('')
  const [nihssScore, setNihssScore] = useState('')
  const [imagingEvidence, setImagingEvidence] = useState(false)
  const [timeToTreatment, setTimeToTreatment] = useState('')
  const [thrombolysis, setThrombolysis] = useState(false)
  const [mechanicalThrombectomy, setMechanicalThrombectomy] = useState(false)
  const [strokeLocation, setStrokeLocation] = useState('')
  const [strokeMechanism, setStrokeMechanism] = useState('')
  const [modifiedRankinScore, setModifiedRankinScore] = useState('')
  const [dischargeDestination, setDischargeDestination] = useState('')

  // CVA-specific option lists
  const outcomes = ['Definite', 'Probable', 'Possible', 'No']
  const strokeTypes = ['Ischemic', 'Hemorrhagic', 'TIA', 'Other']
  const strokeLocations = ['Anterior', 'Posterior', 'Lacunar', 'Other']
  const strokeMechanisms = ['Large_vessel', 'Cardioembolic', 'Small_vessel', 'Other']
  const dischargeDestinations = ['Home', 'Rehab', 'SNF', 'Other']

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
      stroke_type: strokeType,
      nihss_score: nihssScore,
      imaging_evidence: imagingEvidence ? 1 : 0,
      time_to_treatment: timeToTreatment,
      thrombolysis: thrombolysis ? 1 : 0,
      mechanical_thrombectomy: mechanicalThrombectomy ? 1 : 0,
      stroke_location: strokeLocation,
      stroke_mechanism: strokeMechanism,
      modified_rankin_score: modifiedRankinScore,
      discharge_destination: dischargeDestination
    }

    try {
      const response = await fetch(`${apiUrl}/api/reviews`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(reviewData)
      })
      
      if (response.ok) {
        alert('CVA review submitted successfully!')
        // Redirect or update UI as needed
      } else {
        alert('Error submitting CVA review')
      }
    } catch (error) {
      console.error('Error:', error)
      alert('Error submitting CVA review')
    }
  }

  return (
    <div className="cva-event-review">
      <h2>CVA Event Review</h2>
      
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
          <label>CVA Outcome:</label>
          <select value={outcome} onChange={(e) => setOutcome(e.target.value)} required>
            <option value="">Select outcome</option>
            {outcomes.map(outcome => (
              <option key={outcome} value={outcome}>{outcome}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label>Stroke Type:</label>
          <select value={strokeType} onChange={(e) => setStrokeType(e.target.value)}>
            <option value="">Select stroke type</option>
            {strokeTypes.map(type => (
              <option key={type} value={type}>{type}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label>NIHSS Score:</label>
          <input 
            type="number" 
            value={nihssScore} 
            onChange={(e) => setNihssScore(e.target.value)}
            min="0"
            max="42"
          />
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
          <label>Time to Treatment (minutes):</label>
          <input 
            type="number" 
            value={timeToTreatment} 
            onChange={(e) => setTimeToTreatment(e.target.value)}
            min="0"
          />
        </div>

        <div className="form-group">
          <label>
            <input 
              type="checkbox" 
              checked={thrombolysis} 
              onChange={(e) => setThrombolysis(e.target.checked)}
            />
            Thrombolysis
          </label>
        </div>

        <div className="form-group">
          <label>
            <input 
              type="checkbox" 
              checked={mechanicalThrombectomy} 
              onChange={(e) => setMechanicalThrombectomy(e.target.checked)}
            />
            Mechanical Thrombectomy
          </label>
        </div>

        <div className="form-group">
          <label>Stroke Location:</label>
          <select value={strokeLocation} onChange={(e) => setStrokeLocation(e.target.value)}>
            <option value="">Select stroke location</option>
            {strokeLocations.map(location => (
              <option key={location} value={location}>{location}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label>Stroke Mechanism:</label>
          <select value={strokeMechanism} onChange={(e) => setStrokeMechanism(e.target.value)}>
            <option value="">Select stroke mechanism</option>
            {strokeMechanisms.map(mechanism => (
              <option key={mechanism} value={mechanism}>{mechanism}</option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label>Modified Rankin Score:</label>
          <input 
            type="number" 
            value={modifiedRankinScore} 
            onChange={(e) => setModifiedRankinScore(e.target.value)}
            min="0"
            max="6"
          />
        </div>

        <div className="form-group">
          <label>Discharge Destination:</label>
          <select value={dischargeDestination} onChange={(e) => setDischargeDestination(e.target.value)}>
            <option value="">Select discharge destination</option>
            {dischargeDestinations.map(destination => (
              <option key={destination} value={destination}>{destination}</option>
            ))}
          </select>
        </div>

        <button type="submit">Submit CVA Review</button>
      </form>
    </div>
  )
}

export default CVAEventReview
*/
