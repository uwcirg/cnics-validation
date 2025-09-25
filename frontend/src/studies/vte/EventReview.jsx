import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import '../../pages/Home.css'

function VTEEventReview() {
  const apiUrl = import.meta.env.PROD ? '' : (import.meta.env.VITE_API_URL || '')
  const [searchParams] = useSearchParams()
  const eventId = searchParams.get('event_id')
  
  // Loading and error states
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  // VTE Type flags
  const [peFlag, setPeFlag] = useState(false)
  const [dvtFlag, setDvtFlag] = useState(false)
  const [catFlag, setCatFlag] = useState(false)
  const [noVteFlag, setNoVteFlag] = useState(false)

  // Definite/Probable selections
  const [peDp, setPeDp] = useState('')
  const [dvtDp, setDvtDp] = useState('')
  const [catDp, setCatDp] = useState('')

  // Type selections (Acute/Chronic/Unspecified)
  const [peType, setPeType] = useState('')
  const [dvtType, setDvtType] = useState('')
  const [catType, setCatType] = useState('')

  // Catheter subtype
  const [catSubtype, setCatSubtype] = useState('')

  // PE Location flags
  const [peMainFlag, setPeMainFlag] = useState(false)
  const [peLobarFlag, setPeLobarFlag] = useState(false)
  const [peSegmentalFlag, setPeSegmentalFlag] = useState(false)
  const [peSubsegmentalFlag, setPeSubsegmentalFlag] = useState(false)
  const [peUnknownFlag, setPeUnknownFlag] = useState(false)

  // DVT Location flags
  const [dvtUeFlag, setDvtUeFlag] = useState(false)
  const [dvtLeFlag, setDvtLeFlag] = useState(false)
  const [dvtOtherFlag, setDvtOtherFlag] = useState(false)
  const [dvtUnknownFlag, setDvtUnknownFlag] = useState(false)

  // DVT LE Location flags
  const [dvtLeProximalFlag, setDvtLeProximalFlag] = useState(false)
  const [dvtLeDistalFlag, setDvtLeDistalFlag] = useState(false)
  const [dvtLeUnknownFlag, setDvtLeUnknownFlag] = useState(false)

  // DVT Other Location flags (extensive)
  const [dvtOtherNeckFlag, setDvtOtherNeckFlag] = useState(false)
  const [dvtOtherVcFlag, setDvtOtherVcFlag] = useState(false)
  const [dvtOtherApFlag, setDvtOtherApFlag] = useState(false)
  const [dvtOtherPsFlag, setDvtOtherPsFlag] = useState(false)
  const [dvtOtherIcFlag, setDvtOtherIcFlag] = useState(false)
  const [dvtOtherOtherFlag, setDvtOtherOtherFlag] = useState(false)
  const [dvtOtherUnknownFlag, setDvtOtherUnknownFlag] = useState(false)

  // DVT Other Location detailed flags
  const [dvtOtherNeckJugularFlag, setDvtOtherNeckJugularFlag] = useState(false)
  const [dvtOtherNeckSubclavianFlag, setDvtOtherNeckSubclavianFlag] = useState(false)
  const [dvtOtherNeckBrachFlag, setDvtOtherNeckBrachFlag] = useState(false)
  const [dvtOtherNeckUnknownFlag, setDvtOtherNeckUnknownFlag] = useState(false)
  const [dvtOtherApRenalFlag, setDvtOtherApRenalFlag] = useState(false)
  const [dvtOtherApHepaticFlag, setDvtOtherApHepaticFlag] = useState(false)
  const [dvtOtherApPelvicFlag, setDvtOtherApPelvicFlag] = useState(false)
  const [dvtOtherApIliacFlag, setDvtOtherApIliacFlag] = useState(false)
  const [dvtOtherApUnknownFlag, setDvtOtherApUnknownFlag] = useState(false)
  const [dvtOtherPsHepaticFlag, setDvtOtherPsHepaticFlag] = useState(false)
  const [dvtOtherPsSplenicFlag, setDvtOtherPsSplenicFlag] = useState(false)
  const [dvtOtherPsMesentericFlag, setDvtOtherPsMesentericFlag] = useState(false)
  const [dvtOtherPsUnknownFlag, setDvtOtherPsUnknownFlag] = useState(false)
  const [dvtOtherIcSstFlag, setDvtOtherIcSstFlag] = useState(false)
  const [dvtOtherIcTstFlag, setDvtOtherIcTstFlag] = useState(false)
  const [dvtOtherIcRvtFlag, setDvtOtherIcRvtFlag] = useState(false)
  const [dvtOtherIcUnknownFlag, setDvtOtherIcUnknownFlag] = useState(false)

  // Contributing conditions
  const [ccMalignancyFlag, setCcMalignancyFlag] = useState(false)
  const [ccChemoFlag, setCcChemoFlag] = useState(false)
  const [ccHeartfailureFlag, setCcHeartfailureFlag] = useState(false)
  const [ccNsFlag, setCcNsFlag] = useState(false)
  const [ccDialysisFlag, setCcDialysisFlag] = useState(false)
  const [ccHospFlag, setCcHospFlag] = useState(false)
  const [ccMtFlag, setCcMtFlag] = useState(false)
  const [ccImmobFlag, setCcImmobFlag] = useState(false)
  const [ccLongrideFlag, setCcLongrideFlag] = useState(false)
  const [ccSurgeryFlag, setCcSurgeryFlag] = useState(false)
  const [ccInfectionFlag, setCcInfectionFlag] = useState(false)
  const [ccTransfusionFlag, setCcTransfusionFlag] = useState(false)
  const [ccInheritedFlag, setCcInheritedFlag] = useState(false)
  const [ccIvdrugFlag, setCcIvdrugFlag] = useState(false)
  const [ccCopdFlag, setCcCopdFlag] = useState(false)
  const [ccPhFlag, setCcPhFlag] = useState(false)
  const [ccSteroidFlag, setCcSteroidFlag] = useState(false)
  const [ccPregnancyFlag, setCcPregnancyFlag] = useState(false)
  const [ccOtherFlag, setCcOtherFlag] = useState(false)
  const [ccUnknownFlag, setCcUnknownFlag] = useState(false)
  const [ccNoneFlag, setCcNoneFlag] = useState(false)

  // Infection subcategories
  const [ccInfectionPneumoniaFlag, setCcInfectionPneumoniaFlag] = useState(false)
  const [ccInfectionSepsisFlag, setCcInfectionSepsisFlag] = useState(false)
  const [ccInfectionUtiFlag, setCcInfectionUtiFlag] = useState(false)
  const [ccInfectionEndocarditisFlag, setCcInfectionEndocarditisFlag] = useState(false)
  const [ccInfectionOsteomyelitisFlag, setCcInfectionOsteomyelitisFlag] = useState(false)
  const [ccInfectionMeningitisFlag, setCcInfectionMeningitisFlag] = useState(false)
  const [ccInfectionCellulitisFlag, setCcInfectionCellulitisFlag] = useState(false)
  const [ccInfectionCovidFlag, setCcInfectionCovidFlag] = useState(false)
  const [ccInfectionOtherFlag, setCcInfectionOtherFlag] = useState(false)

  // Additional fields
  const [ivDrugUse, setIvDrugUse] = useState('')
  const [ccOther, setCcOther] = useState('')
  const [ccInfectionOther, setCcInfectionOther] = useState('')
  const [dvtOther, setDvtOther] = useState('')

  // Medical history
  const [smokingUse, setSmokingUse] = useState('')
  const [vtehistoryPeFlag, setVtehistoryPeFlag] = useState(false)
  const [vtehistoryDvtFlag, setVtehistoryDvtFlag] = useState(false)
  const [vtehistoryUnknowntypeFlag, setVtehistoryUnknowntypeFlag] = useState(false)
  const [vtehistoryNoneFlag, setVtehistoryNoneFlag] = useState(false)
  const [vtehistoryUnknownFlag, setVtehistoryUnknownFlag] = useState(false)
  const [familyHistory, setFamilyHistory] = useState('')

  // Management
  const [managementInfo, setManagementInfo] = useState('')
  const [managementAt, setManagementAt] = useState('')
  const [managementHosp, setManagementHosp] = useState('')
  const [managementVcf, setManagementVcf] = useState('')
  const [managementTt, setManagementTt] = useState('')
  const [managementThrombectomy, setManagementThrombectomy] = useState('')
  const [managementManagedas, setManagementManagedas] = useState('')

  const [eventDetails, setEventDetails] = useState(null)

  // Option lists
  const dps = ['', 'Definite', 'Probable']
  const types = ['', 'Acute', 'Chronic', 'Unspecified']
  const catSubtypes = ['', 'Central venous catheter', 'Peripheral venous catheter', 'Arterial catheter', 'Other']
  const ivDrugUses = ['', 'Current', 'Past', 'Unknown']
  const multipleChoices = ['', 'Yes', 'No', 'Unknown']
  const managementAts = ['', 'Yes - anticoagulation', 'Yes - VC filter', 'Yes - both', 'No', 'Unknown']
  const managementManagedAs = ['', 'Outpatient', 'Inpatient', 'Unknown']

  useEffect(() => {
    if (!eventId) return
    
    setLoading(true)
    setError(null)
    
    fetch(`${apiUrl}/api/events/${eventId}`, { credentials: 'include' })
      .then((r) => {
        if (!r.ok) {
          throw new Error(`Failed to load event: ${r.status} ${r.statusText}`)
        }
        return r.json()
      })
      .then((j) => setEventDetails(j.data || null))
      .catch((err) => {
        setError(err.message)
        setEventDetails(null)
      })
      .finally(() => setLoading(false))
  }, [apiUrl, eventId])

  const show = useMemo(() => {
    const res = {
      types: true,
      peMore: false,
      peType: false,
      dvtMore: false,
      dvtType: false,
      catSubtype: false,
      catMore: false,
      catType: false,
      location: false,
      peLocation: false,
      dvtLocation: false,
      dvtLeLocation: false,
      dvtOtherLocation: false,
      dvtNcLocation: false,
      dvtApLocation: false,
      dvtPsLocation: false,
      dvtIcLocation: false,
      dvtOtherOtherLocation: false,
      contcond: false,
      ccInfection: false,
      ccInfectionOther: false,
      ivDrug: false,
      ccOther: false,
      mc: false,
      management: false,
      managementMore: false,
      submit: false,
    }

    // Show PE more details if PE is checked
    if (peFlag) {
      res.peMore = true
      if (peDp !== '') {
        res.peType = true
      }
    }

    // Show DVT more details if DVT is checked
    if (dvtFlag) {
      res.dvtMore = true
      if (dvtDp !== '') {
        res.dvtType = true
      }
    }

    // Show catheter more details if catheter is checked
    if (catFlag) {
      res.catSubtype = true
      if (catSubtype !== '') {
        res.catMore = true
        if (catDp !== '') {
          res.catType = true
        }
      }
    }

    // Show submit if no VTE is checked
    if (noVteFlag) {
      res.submit = true
    }

    // Check if any VTE type is completed
    const peDone = peFlag && peDp !== '' && peType !== ''
    const dvtDone = dvtFlag && dvtDp !== '' && dvtType !== ''
    const catDone = catFlag && catSubtype !== '' && catDp !== '' && catType !== ''

    if (peDone || dvtDone || catDone) {
      res.location = true
      
      if (peDone) {
        res.peLocation = true
      }
      
      if (dvtDone || catDone) {
        res.dvtLocation = true
        
        if (dvtLeFlag) {
          res.dvtLeLocation = true
        }
        
        if (dvtOtherFlag) {
          res.dvtOtherLocation = true
          
          if (dvtOtherNeckFlag) {
            res.dvtNcLocation = true
          }
          
          if (dvtOtherApFlag) {
            res.dvtApLocation = true
          }
          
          if (dvtOtherPsFlag) {
            res.dvtPsLocation = true
          }
          
          if (dvtOtherIcFlag) {
            res.dvtIcLocation = true
          }
          
          if (dvtOtherOtherFlag) {
            res.dvtOtherOtherLocation = true
          }
        }
      }
    }

    // Check if location is completed
    const peLocationDone = peMainFlag || peLobarFlag || peSegmentalFlag || peSubsegmentalFlag || peUnknownFlag
    const dvtLocationDone = dvtUeFlag || dvtLeProximalFlag || dvtLeDistalFlag || dvtLeUnknownFlag ||
                           dvtOtherNeckJugularFlag || dvtOtherNeckSubclavianFlag || dvtOtherNeckBrachFlag ||
                           dvtOtherNeckUnknownFlag || dvtOtherVcFlag || dvtOtherApRenalFlag ||
                           dvtOtherApHepaticFlag || dvtOtherApPelvicFlag || dvtOtherApIliacFlag ||
                           dvtOtherApUnknownFlag || dvtOtherPsHepaticFlag || dvtOtherPsSplenicFlag ||
                           dvtOtherPsMesentericFlag || dvtOtherPsUnknownFlag || dvtOtherIcSstFlag ||
                           dvtOtherIcTstFlag || dvtOtherIcRvtFlag || dvtOtherIcUnknownFlag ||
                           dvtOtherOtherFlag || dvtOtherUnknownFlag || dvtUnknownFlag

    if ((peDone && peLocationDone) || ((dvtDone || catDone) && dvtLocationDone)) {
      res.contcond = true
      
      if (ccInfectionFlag) {
        res.ccInfection = true
        if (ccInfectionOtherFlag) {
          res.ccInfectionOther = true
        }
      }
      
      if (ccIvdrugFlag) {
        res.ivDrug = true
      }
      
      if (ccOtherFlag) {
        res.ccOther = true
      }

      // Check if contributing conditions are completed
      const ccDone = ccMalignancyFlag || ccChemoFlag || ccHeartfailureFlag || ccNsFlag ||
                     ccDialysisFlag || ccHospFlag || ccMtFlag || ccImmobFlag ||
                     ccLongrideFlag || ccSurgeryFlag || ccInfectionPneumoniaFlag ||
                     ccInfectionSepsisFlag || ccInfectionUtiFlag || ccInfectionEndocarditisFlag ||
                     ccInfectionOsteomyelitisFlag || ccInfectionMeningitisFlag ||
                     ccInfectionCellulitisFlag || ccInfectionCovidFlag || ccInfectionOtherFlag ||
                     ccTransfusionFlag || ccInheritedFlag || ivDrugUse !== '' ||
                     ccCopdFlag || ccPhFlag || ccSteroidFlag || ccPregnancyFlag ||
                     ccOtherFlag || ccUnknownFlag || ccNoneFlag

      if (ccDone) {
        res.mc = true
        
        // Check if medical history is completed
        const mcDone = smokingUse !== '' && familyHistory !== '' &&
                      (vtehistoryPeFlag || vtehistoryDvtFlag || vtehistoryUnknowntypeFlag ||
                       vtehistoryNoneFlag || vtehistoryUnknownFlag)

        if (mcDone) {
          res.management = true
          
          if (managementInfo === '1') {
            res.managementMore = true
          }
          
          // Check if management is completed
          const managementDone = managementInfo === '0' ||
                                (managementInfo === '1' && managementAt !== '' && managementHosp !== '' &&
                                 managementVcf !== '' && managementTt !== '' && managementThrombectomy !== '' &&
                                 managementManagedas !== '')
          
          if (managementDone) {
            res.submit = true
          }
        }
      }
    }

    return res
  }, [
    peFlag, peDp, peType, dvtFlag, dvtDp, dvtType, catFlag, catSubtype, catDp, catType, noVteFlag,
    peMainFlag, peLobarFlag, peSegmentalFlag, peSubsegmentalFlag, peUnknownFlag,
    dvtUeFlag, dvtLeFlag, dvtOtherFlag, dvtUnknownFlag,
    dvtLeProximalFlag, dvtLeDistalFlag, dvtLeUnknownFlag,
    dvtOtherNeckFlag, dvtOtherVcFlag, dvtOtherApFlag, dvtOtherPsFlag, dvtOtherIcFlag,
    dvtOtherOtherFlag, dvtOtherUnknownFlag,
    dvtOtherNeckJugularFlag, dvtOtherNeckSubclavianFlag, dvtOtherNeckBrachFlag, dvtOtherNeckUnknownFlag,
    dvtOtherApRenalFlag, dvtOtherApHepaticFlag, dvtOtherApPelvicFlag, dvtOtherApIliacFlag, dvtOtherApUnknownFlag,
    dvtOtherPsHepaticFlag, dvtOtherPsSplenicFlag, dvtOtherPsMesentericFlag, dvtOtherPsUnknownFlag,
    dvtOtherIcSstFlag, dvtOtherIcTstFlag, dvtOtherIcRvtFlag, dvtOtherIcUnknownFlag,
    ccMalignancyFlag, ccChemoFlag, ccHeartfailureFlag, ccNsFlag, ccDialysisFlag, ccHospFlag,
    ccMtFlag, ccImmobFlag, ccLongrideFlag, ccSurgeryFlag, ccInfectionFlag, ccTransfusionFlag,
    ccInheritedFlag, ccIvdrugFlag, ccCopdFlag, ccPhFlag, ccSteroidFlag, ccPregnancyFlag,
    ccOtherFlag, ccUnknownFlag, ccNoneFlag,
    ccInfectionPneumoniaFlag, ccInfectionSepsisFlag, ccInfectionUtiFlag, ccInfectionEndocarditisFlag,
    ccInfectionOsteomyelitisFlag, ccInfectionMeningitisFlag, ccInfectionCellulitisFlag,
    ccInfectionCovidFlag, ccInfectionOtherFlag,
    ivDrugUse, ccOther, ccInfectionOther,
    smokingUse, vtehistoryPeFlag, vtehistoryDvtFlag, vtehistoryUnknowntypeFlag,
    vtehistoryNoneFlag, vtehistoryUnknownFlag, familyHistory,
    managementInfo, managementAt, managementHosp, managementVcf, managementTt,
    managementThrombectomy, managementManagedas
  ])

  const handleSubmit = async (e) => {
    e.preventDefault()
    
    if (!eventId) {
      setError('No event ID provided')
      return
    }
    
    setSubmitting(true)
    setError(null)
    
    try {
      // Prepare the review data
      const reviewData = {
        event_id: parseInt(eventId),
        // VTE-specific fields
        pe_flag: peFlag,
        dvt_flag: dvtFlag,
        cat_flag: catFlag,
        no_vte_flag: noVteFlag,
        pe_dp: peDp,
        dvt_dp: dvtDp,
        cat_dp: catDp,
        pe_type: peType,
        dvt_type: dvtType,
        cat_type: catType,
        cat_subtype: catSubtype,
        // Location data
        pe_main_flag: peMainFlag,
        pe_lobar_flag: peLobarFlag,
        pe_segmental_flag: peSegmentalFlag,
        pe_subsegmental_flag: peSubsegmentalFlag,
        pe_unknown_flag: peUnknownFlag,
        dvt_ue_flag: dvtUeFlag,
        dvt_le_flag: dvtLeFlag,
        dvt_other_flag: dvtOtherFlag,
        dvt_unknown_flag: dvtUnknownFlag,
        // Contributing conditions
        cc_malignancy_flag: ccMalignancyFlag,
        cc_chemo_flag: ccChemoFlag,
        cc_heartfailure_flag: ccHeartfailureFlag,
        cc_ns_flag: ccNsFlag,
        cc_dialysis_flag: ccDialysisFlag,
        cc_hosp_flag: ccHospFlag,
        cc_mt_flag: ccMtFlag,
        cc_immob_flag: ccImmobFlag,
        cc_longride_flag: ccLongrideFlag,
        cc_surgery_flag: ccSurgeryFlag,
        cc_infection_flag: ccInfectionFlag,
        cc_transfusion_flag: ccTransfusionFlag,
        cc_inherited_flag: ccInheritedFlag,
        cc_ivdrug_flag: ccIvdrugFlag,
        cc_copd_flag: ccCopdFlag,
        cc_ph_flag: ccPhFlag,
        cc_steroid_flag: ccSteroidFlag,
        cc_pregnancy_flag: ccPregnancyFlag,
        cc_other_flag: ccOtherFlag,
        cc_unknown_flag: ccUnknownFlag,
        cc_none_flag: ccNoneFlag,
        // Additional fields
        iv_drug_use: ivDrugUse,
        cc_other: ccOther,
        cc_infection_other: ccInfectionOther,
        dvt_other: dvtOther,
        smoking_use: smokingUse,
        vte_history_pe_flag: vtehistoryPeFlag,
        vte_history_dvt_flag: vtehistoryDvtFlag,
        vte_history_unknown_type_flag: vtehistoryUnknowntypeFlag,
        vte_history_none_flag: vtehistoryNoneFlag,
        vte_history_unknown_flag: vtehistoryUnknownFlag,
        family_history: familyHistory,
        // Management
        management_info: managementInfo,
        management_at: managementAt,
        management_hosp: managementHosp,
        management_vcf: managementVcf,
        management_tt: managementTt,
        management_thrombectomy: managementThrombectomy,
        management_managed_as: managementManagedas
      }
      
      const response = await fetch(`${apiUrl}/api/vte/reviews`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(reviewData)
      })
      
      if (!response.ok) {
        throw new Error(`Failed to submit review: ${response.status} ${response.statusText}`)
      }
      
      const result = await response.json()
      
      // Show success message and redirect or reset form
      alert('VTE Review submitted successfully!')
      
      // Reset form or redirect
      window.location.href = '/vte/review'
      
    } catch (err) {
      setError(err.message)
      console.error('Error submitting VTE review:', err)
    } finally {
      setSubmitting(false)
    }
  }

  // Show loading state
  if (loading) {
    return (
      <div className="home-container">
        <h1>Loading VTE Event Review...</h1>
        <p>Please wait while we load the event details.</p>
      </div>
    )
  }

  // Show error state
  if (error) {
    return (
      <div className="home-container">
        <h1>Error Loading VTE Event Review</h1>
        <div style={{ color: 'red', margin: '20px 0' }}>
          <strong>Error:</strong> {error}
        </div>
        <button onClick={() => window.location.reload()}>
          Try Again
        </button>
      </div>
    )
  }

  return (
    <div className="home-container">
      {/* Top-right CNICS logo */}
      <img className="cnics-logo" src="/cnics_logo.png" alt="CNICS" />
      
      <div className="boxright" style={{ width: '300px', fontSize: '.95em' }}>
        <h3>VTE Review Instructions:</h3>
        <div style={{ marginTop: '8px' }}>
          View as: {" "}
          <a href={`${apiUrl}/files/CNICS VTE reviewer instructions.doc`} download>.doc</a>
          {' | '}
          <a href={`${apiUrl}/files/CNICS VTE reviewer instructions.pdf`} target="_blank" rel="noreferrer">.pdf</a>
        </div>
      </div>

      <h1>Review event: VTE {eventId}</h1>
      {eventDetails && (
        <p>Date: {eventDetails.event_date || '—'}</p>
      )}
      
      {error && (
        <div style={{ color: 'red', margin: '20px 0', padding: '10px', border: '1px solid red', borderRadius: '4px' }}>
          <strong>Error:</strong> {error}
        </div>
      )}

      <div className="indent1">
        <h2>Step 1: Review Charts</h2>
        <p>Review the packet for this event:</p>
        <ul>
          <li>
            <a 
              href={`${apiUrl}/api/events/download/${eventId}`} 
              download=""
              style={{ 
                display: 'inline-block',
                padding: '6px 12px',
                backgroundColor: '#007bff',
                color: 'white',
                textDecoration: 'none',
                borderRadius: '4px',
                fontSize: '14px'
              }}
            >
              📥 Download Charts
            </a>
          </li>
        </ul>

        <h2>Step 2: Enter Decision</h2>
      <form onSubmit={handleSubmit}>
          <table id="reviewForm">
            <tbody>
              <tr id="types">
                <th>Please mark any VTE events.<br/><em>Mark all that apply</em></th>
                <td>
                  <label>
                    <input 
                      type="checkbox" 
                      checked={peFlag} 
                      onChange={(e) => setPeFlag(e.target.checked)}
                    />
                    PE
                  </label>
                  {show.peMore && (
                    <div>
                      Definite or Probable?
                      <br/>
                      <select value={peDp} onChange={(e) => setPeDp(e.target.value)}>
                        {dps.map((o) => <option key={o} value={o}>{o}</option>)}
                      </select>
                      {show.peType && (
                        <div>
                          Acute/Chronic/Unspecified?
                          <br/>
                          <select value={peType} onChange={(e) => setPeType(e.target.value)}>
                            {types.map((o) => <option key={o} value={o}>{o}</option>)}
          </select>
        </div>
                      )}
                    </div>
                  )}

                  <br/>
                  <label>
                    <input 
                      type="checkbox" 
                      checked={dvtFlag} 
                      onChange={(e) => setDvtFlag(e.target.checked)}
                    />
                    DVT
                  </label>
                  {show.dvtMore && (
                    <div>
                      Definite or Probable?
                      <br/>
                      <select value={dvtDp} onChange={(e) => setDvtDp(e.target.value)}>
                        {dps.map((o) => <option key={o} value={o}>{o}</option>)}
                      </select>
                      {show.dvtType && (
                        <div>
                          Acute/Chronic/Unspecified?
                          <br/>
                          <select value={dvtType} onChange={(e) => setDvtType(e.target.value)}>
                            {types.map((o) => <option key={o} value={o}>{o}</option>)}
          </select>
        </div>
                      )}
                    </div>
                  )}

                  <br/>
                  <label>
                    <input 
                      type="checkbox" 
                      checked={catFlag} 
                      onChange={(e) => setCatFlag(e.target.checked)}
                    />
                    Catheter-induced thrombosis
                  </label>
                  {show.catSubtype && (
                    <div>
                      Type:
                      <br/>
                      <select value={catSubtype} onChange={(e) => setCatSubtype(e.target.value)}>
                        {catSubtypes.map((o) => <option key={o} value={o}>{o}</option>)}
                      </select>
                      {show.catMore && (
                        <div>
                          Definite or Probable?
                          <br/>
                          <select value={catDp} onChange={(e) => setCatDp(e.target.value)}>
                            {dps.map((o) => <option key={o} value={o}>{o}</option>)}
                          </select>
                          {show.catType && (
                            <div>
                              Acute/Chronic/Unspecified?
                              <br/>
                              <select value={catType} onChange={(e) => setCatType(e.target.value)}>
                                {types.map((o) => <option key={o} value={o}>{o}</option>)}
          </select>
        </div>
                          )}
                        </div>
                      )}
                    </div>
                  )}

                  <br/>
                  <label>
                    <input 
                      type="checkbox" 
                      checked={noVteFlag} 
                      onChange={(e) => setNoVteFlag(e.target.checked)}
                    />
                    No VTE
                  </label>
                </td>
              </tr>

              {show.location && (
                <>
                  <tr className="location">
                    <td colSpan={2}><hr/></td>
                  </tr>
                  <tr className="location">
                    <th>
                      Location
                      <br/><em>Mark all that apply</em>
                    </th>
                    <td>
                      {show.peLocation && (
                        <div>
                          <br/>
                          Please identify the location of the PE.
                          <br/>
                          <label>
                            <input 
                              type="checkbox" 
                              checked={peMainFlag} 
                              onChange={(e) => setPeMainFlag(e.target.checked)}
                            />
                            Main pulmonary artery(ies)
                          </label>
                          <br/>
                          <label>
                            <input 
                              type="checkbox" 
                              checked={peLobarFlag} 
                              onChange={(e) => setPeLobarFlag(e.target.checked)}
                            />
                            Lobar
                          </label>
                          <br/>
                          <label>
                            <input 
                              type="checkbox" 
                              checked={peSegmentalFlag} 
                              onChange={(e) => setPeSegmentalFlag(e.target.checked)}
                            />
                            Segmental
                          </label>
                          <br/>
                          <label>
                            <input 
                              type="checkbox" 
                              checked={peSubsegmentalFlag} 
                              onChange={(e) => setPeSubsegmentalFlag(e.target.checked)}
                            />
                            Sub-segmental
                          </label>
                          <br/>
                          <label>
                            <input 
                              type="checkbox" 
                              checked={peUnknownFlag} 
                              onChange={(e) => setPeUnknownFlag(e.target.checked)}
                            />
                            Unknown
                          </label>
        </div>
                      )}
                      
                      {show.dvtLocation && (
                        <div>
                          <br/>
                          Please identify the location of the DVT.
                          <br/>
                          <label>
                            <input 
                              type="checkbox" 
                              checked={dvtUeFlag} 
                              onChange={(e) => setDvtUeFlag(e.target.checked)}
                            />
                            UE
                          </label>
                          <br/>
                          <label>
                            <input 
                              type="checkbox" 
                              checked={dvtLeFlag} 
                              onChange={(e) => setDvtLeFlag(e.target.checked)}
                            />
                            LE
                          </label>
                          {show.dvtLeLocation && (
                            <div>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtLeProximalFlag} 
                                  onChange={(e) => setDvtLeProximalFlag(e.target.checked)}
                                />
                                Proximal (popliteal, femoral, iliac)
                              </label>
                              <br/>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtLeDistalFlag} 
                                  onChange={(e) => setDvtLeDistalFlag(e.target.checked)}
                                />
                                Distal
                              </label>
                              <br/>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtLeUnknownFlag} 
                                  onChange={(e) => setDvtLeUnknownFlag(e.target.checked)}
                                />
                                Unknown
                              </label>
                            </div>
                          )}
                          <br/>
                          <label>
                            <input 
                              type="checkbox" 
                              checked={dvtOtherFlag} 
                              onChange={(e) => setDvtOtherFlag(e.target.checked)}
                            />
                            Other location
                          </label>
                          {show.dvtOtherLocation && (
                            <div>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtOtherNeckFlag} 
                                  onChange={(e) => setDvtOtherNeckFlag(e.target.checked)}
                                />
                                Neck/Chest
                              </label>
                              {show.dvtNcLocation && (
                                <div>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherNeckJugularFlag} 
                                      onChange={(e) => setDvtOtherNeckJugularFlag(e.target.checked)}
                                    />
                                    Jugular
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherNeckSubclavianFlag} 
                                      onChange={(e) => setDvtOtherNeckSubclavianFlag(e.target.checked)}
                                    />
                                    Subclavian
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherNeckBrachFlag} 
                                      onChange={(e) => setDvtOtherNeckBrachFlag(e.target.checked)}
                                    />
                                    Brachiocephalic (innominate)
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherNeckUnknownFlag} 
                                      onChange={(e) => setDvtOtherNeckUnknownFlag(e.target.checked)}
                                    />
                                    Unknown
                                  </label>
                                </div>
                              )}
                              <br/>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtOtherVcFlag} 
                                  onChange={(e) => setDvtOtherVcFlag(e.target.checked)}
                                />
                                Vena cava (superior or inferior)
                              </label>
                              <br/>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtOtherApFlag} 
                                  onChange={(e) => setDvtOtherApFlag(e.target.checked)}
                                />
                                Abdomen/pelvis
                              </label>
                              {show.dvtApLocation && (
                                <div>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherApRenalFlag} 
                                      onChange={(e) => setDvtOtherApRenalFlag(e.target.checked)}
                                    />
                                    Renal
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherApHepaticFlag} 
                                      onChange={(e) => setDvtOtherApHepaticFlag(e.target.checked)}
                                    />
                                    Hepatic
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherApPelvicFlag} 
                                      onChange={(e) => setDvtOtherApPelvicFlag(e.target.checked)}
                                    />
                                    Pelvic
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherApIliacFlag} 
                                      onChange={(e) => setDvtOtherApIliacFlag(e.target.checked)}
                                    />
                                    Iliac
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherApUnknownFlag} 
                                      onChange={(e) => setDvtOtherApUnknownFlag(e.target.checked)}
                                    />
                                    Unknown
                                  </label>
        </div>
                              )}
                              <br/>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtOtherPsFlag} 
                                  onChange={(e) => setDvtOtherPsFlag(e.target.checked)}
                                />
                                Portal system
                              </label>
                              {show.dvtPsLocation && (
                                <div>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherPsHepaticFlag} 
                                      onChange={(e) => setDvtOtherPsHepaticFlag(e.target.checked)}
                                    />
                                    Hepatic portal
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherPsSplenicFlag} 
                                      onChange={(e) => setDvtOtherPsSplenicFlag(e.target.checked)}
                                    />
                                    Splenic
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherPsMesentericFlag} 
                                      onChange={(e) => setDvtOtherPsMesentericFlag(e.target.checked)}
                                    />
                                    Mesenteric
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherPsUnknownFlag} 
                                      onChange={(e) => setDvtOtherPsUnknownFlag(e.target.checked)}
                                    />
                                    Unknown
                                  </label>
        </div>
                              )}
                              <br/>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtOtherIcFlag} 
                                  onChange={(e) => setDvtOtherIcFlag(e.target.checked)}
                                />
                                Intracranial
                              </label>
                              {show.dvtIcLocation && (
                                <div>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherIcSstFlag} 
                                      onChange={(e) => setDvtOtherIcSstFlag(e.target.checked)}
                                    />
                                    Sagittal sinus thrombosis
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherIcTstFlag} 
                                      onChange={(e) => setDvtOtherIcTstFlag(e.target.checked)}
                                    />
                                    Transverse sinus thrombosis
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherIcRvtFlag} 
                                      onChange={(e) => setDvtOtherIcRvtFlag(e.target.checked)}
                                    />
                                    Retinal vein thrombosis
                                  </label>
                                  <br/>
                                  &nbsp;&nbsp;&nbsp;&nbsp;
                                  <label>
                                    <input 
                                      type="checkbox" 
                                      checked={dvtOtherIcUnknownFlag} 
                                      onChange={(e) => setDvtOtherIcUnknownFlag(e.target.checked)}
                                    />
                                    Unknown
                                  </label>
        </div>
                              )}
                              <br/>
                              &nbsp;&nbsp;
                              <label>
                                <input 
                                  type="checkbox" 
                                  checked={dvtOtherOtherFlag} 
                                  onChange={(e) => setDvtOtherOtherFlag(e.target.checked)}
                                />
                                Other
                              </label>
                              {show.dvtOtherOtherLocation && (
                                <div>
                                  <input 
                                    type="text" 
                                    value={dvtOther} 
                                    onChange={(e) => setDvtOther(e.target.value)}
                                    placeholder="Specify:"
                                  />
                                </div>
                              )}
                              <br/>
                              &nbsp;&nbsp;
          <label>
            <input 
              type="checkbox" 
                                  checked={dvtOtherUnknownFlag} 
                                  onChange={(e) => setDvtOtherUnknownFlag(e.target.checked)}
            />
                                Unknown
          </label>
        </div>
                          )}
                          <br/>
          <label>
            <input 
              type="checkbox" 
                              checked={dvtUnknownFlag} 
                              onChange={(e) => setDvtUnknownFlag(e.target.checked)}
            />
                            Unknown
          </label>
        </div>
                      )}
                    </td>
                  </tr>
                </>
              )}

              {show.contcond && (
                <>
                  <tr className="contcond">
                    <td colSpan={2}><hr/></td>
                  </tr>
                  <tr className="contcond">
                    <th>
                      Possible contributing conditions
                      <br/><em>Mark all that apply</em>
                    </th>
                    <td>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccMalignancyFlag} 
                          onChange={(e) => setCcMalignancyFlag(e.target.checked)}
                        />
                        Malignancy, active in the past year
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccChemoFlag} 
                          onChange={(e) => setCcChemoFlag(e.target.checked)}
                        />
                        Chemotherapy in the past 90 days
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccHeartfailureFlag} 
                          onChange={(e) => setCcHeartfailureFlag(e.target.checked)}
                        />
                        Heart failure prior to event
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccNsFlag} 
                          onChange={(e) => setCcNsFlag(e.target.checked)}
                        />
                        Nephrotic syndrome
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccDialysisFlag} 
                          onChange={(e) => setCcDialysisFlag(e.target.checked)}
                        />
                        Dialysis
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccHospFlag} 
                          onChange={(e) => setCcHospFlag(e.target.checked)}
                        />
                        Hospitalization in the past 90 days
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccMtFlag} 
                          onChange={(e) => setCcMtFlag(e.target.checked)}
                        />
                        Major trauma including fracture in past 90 days
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccImmobFlag} 
                          onChange={(e) => setCcImmobFlag(e.target.checked)}
                        />
                        Immobilization/bed rest in the past 90 days
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccLongrideFlag} 
                          onChange={(e) => setCcLongrideFlag(e.target.checked)}
                        />
                        Long plane ride/prolonged sitting in the past 30 days
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccSurgeryFlag} 
                          onChange={(e) => setCcSurgeryFlag(e.target.checked)}
                        />
                        Surgery in past 90 days
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccInfectionFlag} 
                          onChange={(e) => setCcInfectionFlag(e.target.checked)}
                        />
                        Infection in the past 90 days
                      </label>
                      {show.ccInfection && (
                        <div>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionPneumoniaFlag} 
                              onChange={(e) => setCcInfectionPneumoniaFlag(e.target.checked)}
                            />
                            Pneumonia
                          </label>
                          <br/>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionSepsisFlag} 
                              onChange={(e) => setCcInfectionSepsisFlag(e.target.checked)}
                            />
                            Sepsis/bacteremia
                          </label>
                          <br/>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionUtiFlag} 
                              onChange={(e) => setCcInfectionUtiFlag(e.target.checked)}
                            />
                            UTI/pyelonephritis
                          </label>
                          <br/>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionEndocarditisFlag} 
                              onChange={(e) => setCcInfectionEndocarditisFlag(e.target.checked)}
                            />
                            Endocarditis
                          </label>
                          <br/>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionOsteomyelitisFlag} 
                              onChange={(e) => setCcInfectionOsteomyelitisFlag(e.target.checked)}
                            />
                            Osteomyelitis
                          </label>
                          <br/>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionMeningitisFlag} 
                              onChange={(e) => setCcInfectionMeningitisFlag(e.target.checked)}
                            />
                            Meningitis
                          </label>
                          <br/>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionCellulitisFlag} 
                              onChange={(e) => setCcInfectionCellulitisFlag(e.target.checked)}
                            />
                            Cellulitis/skin abscess
                          </label>
                          <br/>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionCovidFlag} 
                              onChange={(e) => setCcInfectionCovidFlag(e.target.checked)}
                            />
                            COVID
                          </label>
                          <br/>
                          &nbsp;&nbsp;
                          <label>
                            <input 
                              type="checkbox" 
                              checked={ccInfectionOtherFlag} 
                              onChange={(e) => setCcInfectionOtherFlag(e.target.checked)}
                            />
                            Other
                          </label>
                          {show.ccInfectionOther && (
                            <div>
                              <input 
                                type="text" 
                                value={ccInfectionOther} 
                                onChange={(e) => setCcInfectionOther(e.target.value)}
                                placeholder="Specify:"
          />
        </div>
                          )}
                        </div>
                      )}
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccTransfusionFlag} 
                          onChange={(e) => setCcTransfusionFlag(e.target.checked)}
                        />
                        Transfusion in past 30 days
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccInheritedFlag} 
                          onChange={(e) => setCcInheritedFlag(e.target.checked)}
                        />
                        Inherited or acquired thrombophilia (other than malignancy)
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccIvdrugFlag} 
                          onChange={(e) => setCcIvdrugFlag(e.target.checked)}
                        />
                        IV drug use
                      </label>
                      {show.ivDrug && (
                        <div>
                          Current or Past?
                          <br/>
                          <select value={ivDrugUse} onChange={(e) => setIvDrugUse(e.target.value)}>
                            {ivDrugUses.map((o) => <option key={o} value={o}>{o}</option>)}
                          </select>
                        </div>
                      )}
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccCopdFlag} 
                          onChange={(e) => setCcCopdFlag(e.target.checked)}
                        />
                        COPD
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccPhFlag} 
                          onChange={(e) => setCcPhFlag(e.target.checked)}
                        />
                        Pulmonary hypertension
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccSteroidFlag} 
                          onChange={(e) => setCcSteroidFlag(e.target.checked)}
                        />
                        Estrogen and/or progestin or anabolic steroid use in last 30 days
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccPregnancyFlag} 
                          onChange={(e) => setCcPregnancyFlag(e.target.checked)}
                        />
                        Current pregnancy or within 3 months post-partum
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccOtherFlag} 
                          onChange={(e) => setCcOtherFlag(e.target.checked)}
                        />
                        Other conditions predisposing to VTE
                      </label>
                      {show.ccOther && (
                        <div>
          <input 
                            type="text" 
                            value={ccOther} 
                            onChange={(e) => setCcOther(e.target.value)}
                            placeholder="Specify:"
          />
        </div>
                      )}
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccUnknownFlag} 
                          onChange={(e) => setCcUnknownFlag(e.target.checked)}
                        />
                        Unknown
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={ccNoneFlag} 
                          onChange={(e) => setCcNoneFlag(e.target.checked)}
                        />
                        None
                      </label>
                    </td>
                  </tr>
                </>
              )}

              {show.mc && (
                <>
                  <tr className="mc">
                    <td colSpan={2}><hr/></td>
                  </tr>
                  <tr className="mc">
                    <th>Please provide the following additional information</th>
                    <td>
                      Smoking status?
                      <br/>
                      <select value={smokingUse} onChange={(e) => setSmokingUse(e.target.value)}>
                        {multipleChoices.map((o) => <option key={o} value={o}>{o}</option>)}
                      </select>
                      <br/>
                      <br/>
                      History of prior VTE? (mark all that apply)
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={vtehistoryPeFlag} 
                          onChange={(e) => setVtehistoryPeFlag(e.target.checked)}
                        />
                        PE
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={vtehistoryDvtFlag} 
                          onChange={(e) => setVtehistoryDvtFlag(e.target.checked)}
                        />
                        DVT
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={vtehistoryUnknowntypeFlag} 
                          onChange={(e) => setVtehistoryUnknowntypeFlag(e.target.checked)}
                        />
                        Unknown type
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={vtehistoryNoneFlag} 
                          onChange={(e) => setVtehistoryNoneFlag(e.target.checked)}
                        />
                        None
                      </label>
                      <br/>
                      <label>
                        <input 
                          type="checkbox" 
                          checked={vtehistoryUnknownFlag} 
                          onChange={(e) => setVtehistoryUnknownFlag(e.target.checked)}
                        />
                        Unknown
                      </label>
                      <br/>
                      <br/>
                      Family history of VTE?
                      <br/>
                      <select value={familyHistory} onChange={(e) => setFamilyHistory(e.target.value)}>
                        {multipleChoices.map((o) => <option key={o} value={o}>{o}</option>)}
                      </select>
                    </td>
                  </tr>
                </>
              )}

              {show.management && (
                <>
                  <tr className="management">
                    <td colSpan={2}><hr/></td>
                  </tr>
                  <tr className="management">
                    <th>Please provide the following information regarding management.</th>
                    <td>
                      Is information available on management?
                      <br/>
                      <label>
                        <input 
                          type="radio" 
                          name="managementInfo" 
                          value="1" 
                          checked={managementInfo === '1'} 
                          onChange={(e) => setManagementInfo(e.target.value)}
                        />
                        Yes
                      </label>
                      &nbsp;&nbsp;
                      <label>
                        <input 
                          type="radio" 
                          name="managementInfo" 
                          value="0" 
                          checked={managementInfo === '0'} 
                          onChange={(e) => setManagementInfo(e.target.value)}
                        />
                        No
                      </label>

                      {show.managementMore && (
                        <div>
                          <br/>
                          Already on anticoagulation therapy or have a VC filter at time of VTE diagnosis?
                          <br/>
                          <select value={managementAt} onChange={(e) => setManagementAt(e.target.value)}>
                            {managementAts.map((o) => <option key={o} value={o}>{o}</option>)}
                          </select>
                          <br/>
                          VTE occurred after admission to the hospital?
                          <br/>
                          <select value={managementHosp} onChange={(e) => setManagementHosp(e.target.value)}>
                            {multipleChoices.map((o) => <option key={o} value={o}>{o}</option>)}
                          </select>
                          <br/>
                          Vena cava filter placed?
                          <br/>
                          <select value={managementVcf} onChange={(e) => setManagementVcf(e.target.value)}>
                            {multipleChoices.map((o) => <option key={o} value={o}>{o}</option>)}
                          </select>
                          <br/>
                          Thrombolytic therapy?
                          <br/>
                          <select value={managementTt} onChange={(e) => setManagementTt(e.target.value)}>
                            {multipleChoices.map((o) => <option key={o} value={o}>{o}</option>)}
                          </select>
                          <br/>
                          Thrombectomy?
                          <br/>
                          <select value={managementThrombectomy} onChange={(e) => setManagementThrombectomy(e.target.value)}>
                            {multipleChoices.map((o) => <option key={o} value={o}>{o}</option>)}
                          </select>
                          <br/>
                          Managed as:
                          <br/>
                          <select value={managementManagedas} onChange={(e) => setManagementManagedas(e.target.value)}>
                            {managementManagedAs.map((o) => <option key={o} value={o}>{o}</option>)}
                          </select>
                        </div>
                      )}
                    </td>
                  </tr>
                </>
              )}

              {show.submit && (
                <tr id="submit">
                  <td colSpan={2}>
                    <button 
                      type="submit" 
                      disabled={submitting}
                      style={{
                        padding: '10px 20px',
                        fontSize: '16px',
                        backgroundColor: submitting ? '#ccc' : '#007bff',
                        color: 'white',
                        border: 'none',
                        borderRadius: '4px',
                        cursor: submitting ? 'not-allowed' : 'pointer'
                      }}
                    >
                      {submitting ? 'Submitting...' : 'Submit VTE Review'}
                    </button>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
      </form>
      </div>
    </div>
  )
}

export default VTEEventReview
