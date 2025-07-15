<?php
  /** 
   * Review class
   *
   * @author Greg Barnes
   * @version 0.1
   */
class Review extends AppModel
{
    var $name = 'Review';
    var $belongsTo = array('Event', 
                           'User' => array('className' => 'User',
                                           'foreignKey' => 'reviewer_id'));

    /** Value for mci field that indicates it was definitedly an MCI
        (Myocardial Infarction) */
    const DEFINITE = 'Definite';

    /** Value for mci field that indicates it was probably an MCI */
    const PROBABLE = 'Probable';

    /** Value for mci field that indicates it was not an MCI */
    const NOT = 'No';

    /** Value for mci field that indicates Rescuscitated Cardiac Arrest */
    const RCA = 'No [resuscitated cardiac arrest]';

    /** Value for type field that indicates MCI was primary 
        (cause of the event?) */
    const PRIMARY = 'Primary';

    /** Value for type field that indicates MCI was secondary */
    const SECONDARY = 'Secondary';

    /** Value for secondary_cause field */
    const MVA = 'MVA';

    /** Value for secondary_cause field */
    const OVERDOSE = 'Overdose';

    /** Value for secondary_cause field */
    const ANAPHLAXIS = 'Anaphlaxis';

    /** Value for secondary_cause field */
    const ARRHYTHMIA = 'Arrhythmia';

    /** Value for secondary_cause field */
    const COCAINE = 'Cocaine or other illicit drug induced vasospasm';

    /** Value for secondary_cause field */
    const COVID = 'COVID';

    /** Value for secondary_cause field */
    const GI_BLEED = 'GI Bleed';

    /** Value for secondary_cause field */
    const PROCEDURE_RELATED = 'Procedure related';

    /** Value for secondary_cause field */
    const SEPSIS = 'Sepsis/bacteremia';

    /** Value for secondary_cause field */
    const HYPERTENSIVE_URGENCY = 'Hypertensive urgency/emergency';

    /** Value for secondary_cause field */
    const HYPOXIA = 'Hypoxia';

    /** Value for secondary_cause field */
    const HYPOTENSION = 'Hypotension';

    /** Value for secondary_cause and false_positive_reason fields */
    const OTHER = 'Other';

    /** Value for the ce_criteria field */
    const STANDARD = 'Standard criteria'; 

    /** Value for the ce_criteria field */
    const PTCA = 'PTCA criteria'; 

    /** Value for the ce_criteria field */
    const CABG = 'CABG criteria'; 

    /** Value for the ce_criteria field */
    const MUSCLE = 'Muscle trauma other than PTCA/CABG';

    /** Value for the false_positive_reason field */
    const CHF = 'Congestive heart failure';

    /** Value for the false_positive_reason field */
    const MYOCARDITIS = 'Myocarditis';

    /** Value for the false_positive_reason field */
    const PERICARDITIS = 'Pericarditis';

    /** Value for the false_positive_reason field */
    const PE = 'Pulmonary embolism';

    /** Value for the false_positive_reason field */
    const RF = 'Renal failure';

    /** Value for the false_positive_reason field */
    const SSS = 'Severe sepsis/shock';

    /** Value for the ci_type field */
    const CABGS = 'CABG/Surgery';

    /** Value for the ci_type field */
    const PCI = 'PCI/Angioplasty';

    /** Value for the ci_type field */
    const STENT = 'Stent';

    /** Value for the ci_type field */
    const UNKNOWN = 'Unknown';

    /** Value for the ecg_type field */
    const STEMI = 'STEMI';

    /** Value for the ecg_type field */
    const NOSTEMI = 'non-STEMI';

    /** Value for the ecg_type field */
    const OTHERU = 'Other/Uninterpretable';

    /** Value for the ecg_type field */
    const LBBB = 'New LBBB';

    /** Value for the ecg_type field */
    const NORMAL = 'Normal';

    /** Value for the ecg_type field */
    const NOEKG = 'No EKG';

    /** 
      Return the allowed values for the mci field.
     */
    function getMcis() {
       return array(self::DEFINITE => self::DEFINITE,
                    self::PROBABLE => self::PROBABLE,
                    self::NOT => self::NOT,
                    self::RCA => self::RCA);
    }

    /** 
      Return the allowed values for the type field.
     */
    function getTypes() {
       return array(self::PRIMARY => self::PRIMARY,
                    self::SECONDARY => self::SECONDARY);
    }

    /** 
      Return the allowed values for the ce_criteria field.
     */
    function getCriterias() {
       return array(self::STANDARD => self::STANDARD,
                    self::PTCA => self::PTCA,
                    self::CABG => self::CABG,
                    self::MUSCLE => self::MUSCLE);
    }

    /** 
      Return the allowed values for the false_positive_reason field.
     */
    function getFalsePositiveReasons() {
       return array(self::CHF => self::CHF,
                    self::MYOCARDITIS => self::MYOCARDITIS,
                    self::PERICARDITIS => self::PERICARDITIS,
                    self::PE => self::PE,
                    self::RF => self::RF,
                    self::SSS => self::SSS,
                    self::OTHER => self::OTHER);
    }

    /** 
      Return the allowed values for the secondary_cause field.
     */
    function getSecondaryCauses() {
       return array(
                    self::ANAPHLAXIS => self::ANAPHLAXIS,
                    self::ARRHYTHMIA => self::ARRHYTHMIA,
                    self::COCAINE => self::COCAINE,
                    self::COVID => self::COVID,
                    self::GI_BLEED => self::GI_BLEED,
                    self::MVA => self::MVA,
                    self::OVERDOSE => self::OVERDOSE,
                    self::PROCEDURE_RELATED => self::PROCEDURE_RELATED,
                    self::SEPSIS => self::SEPSIS,
                    self::HYPERTENSIVE_URGENCY => self::HYPERTENSIVE_URGENCY,
                    self::HYPOXIA => self::HYPOXIA,
                    self::HYPOTENSION => self::HYPOTENSION,
                    self::OTHER => self::OTHER);
    }

    /** 
      Return the allowed values for the ci_type field.
     */
    function getCiTypes() {
       return array(self::CABGS => self::CABGS,
                    self::PCI => self::PCI,
                    self::STENT => self::STENT,
                    self::UNKNOWN => self::UNKNOWN);
    }

    /** 
      Return the allowed values for the ecg_type field.
     */
    function getEcgTypes() {
       return array(self::STEMI => self::STEMI,
                    self::NOSTEMI => self::NOSTEMI,
                    self::OTHERU => self::OTHERU,
                    self::LBBB => self::LBBB,
                    self::NORMAL => self::NORMAL,
                    self::NOEKG => self::NOEKG);
    }
}
