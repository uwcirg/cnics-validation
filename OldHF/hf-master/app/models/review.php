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

    /** Value for congestion */
    const NO_XRAY = 'N/A - No Xray';

    /** Value for a LVEF */
    const NOT_REPORTED = 'not reported';

    /** minimum value for a LVEF */
    const MIN_LVEF = 1;

    /** maximum value for a LVEF */
    const MAX_LVEF = 80;

    /** Value for a lab*/
    const NO_LABS = 'No labs';

    /** Value for HF type field */
    const NOT_HF = 'Not HF';

    /** Value for HF type field */
    const PROBABLE_HF = 'Probable HF';

    /** Value for HF type field */
    const DEFINITE_HF = 'Definite HF';

    /** new Value for HF type field */
    const DEF_OR_PROB_HF = 'Definite/Probable HF';

    /** Value for classificaiton fields */
    const PRESERVED = 'Preserved (LVEF >=50%)';

    /** Value for classificaiton fields */
    const INTERMEDIATE = 'Intermediate / mid-range EF (LVEF 40-49%)';

    /** Value for classificaiton fields */
    const REDUCED = 'Reduced (LVEF < 40%)';

    /** Value for presentation fields */
    const LEFT = 'Predominantly L-sided HF';

    /** Value for presentation fields */
    const RIGHT = 'Predominantly R-sided HF';

    /** Value for presentation fields */
    const COMBINED = 'Combined L- and R-sided dysfunction as contributors to HF';

    /** Yes value for multiple choice questions */
    const YES = 'Yes';

    /** No value for multiple choice questions */
    const NO = 'No';

    /** Unknown value for multiple choice questions */
    const UNKNOWN = 'unknown';

    /** None value for multiple choice questions */
    const NONE = 'none';

    /** 
      Return the allowed values for the congestion field.
     */
    function getCongestions() {
       return array(self::YES => self::YES,
                    self::NO => self::NO,
                    self::NO_XRAY => self::NO_XRAY
       );
    }

    /** 
      Return the allowed values for the lvef field.
     */
    function getLvefs() {
       $retval = array(self::NOT_REPORTED => self::NOT_REPORTED);

       for ($i = self::MIN_LVEF; $i<=self::MAX_LVEF; $i++) {
            $retval['' . $i] = '' . $i;
       }

       return $retval;
    }

    /** 
      Return the allowed values for the lab field.
     */
    function getLabs() {
       return array(self::YES => self::YES,
                    self::NO => self::NO,
                    self::NO_LABS => self::NO_LABS
       );
    }

    /** 
      Return the allowed values for the hf_type field.
     */
    function getHfTypes() {
       return array(self::NOT_HF => self::NOT_HF,
                    self::PROBABLE_HF => self::PROBABLE_HF,
                    self::DEFINITE_HF => self::DEFINITE_HF,
       );
    }

    /** 
      Return the allowed values for the classification field.
     */
    function getClassifications() {
       return array(self::PRESERVED => self::PRESERVED,
                    self::INTERMEDIATE => self::INTERMEDIATE,
                    self::REDUCED => self::REDUCED,
                    self::UNKNOWN => self::UNKNOWN,
       );
    }

    /** 
      Return the allowed values for the presentation field.
     */
    function getPresentations() {
       return array(self::LEFT => self::LEFT,
                    self::RIGHT => self::RIGHT,
                    self::COMBINED => self::COMBINED,
                    self::UNKNOWN => self::UNKNOWN,
       );
    }
}
