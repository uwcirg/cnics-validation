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

    /** Value for a catheter-induced thrombosis subtype */
    const CVAC = 'Central venous access catheter';

    /** Value for a catheter-induced thrombosis subtype */
    const DIALYSIS = 'Dialysis graft/shunt/fistula';

    /** Value for event fields that indicates it was definitely a particular 
        event */
    const DEFINITE = 'Definite';

    /** Value for event fields that indicates it was probably a particular
        event */
    const PROBABLE = 'Probable';

    /** Value for type field that indicates acute */
    const ACUTE = 'Acute';

    /** Value for type field that indicates chronic */
    const CHRONIC = 'Chronic';

    /** Value for type field that indicates unspecified */
    const UNSPECIFIED = 'Unspecified';

    /** Value for Drug use fields */
    const CURRENT = 'Current';

    /** Value for Drug use fields */
    const PAST = 'Past';

    /** Yes value for multiple choice questions */
    const YES = 'Yes';

    /** No value for multiple choice questions */
    const NO = 'No';

    /** Unknown value for multiple choice questions */
    const UNKNOWN = 'Unknown';

    /** None value for multiple choice questions */
    const NONE = 'None';

    /** Extra value for management_at field */
    const PRESCRIBED = 'Prescribed but not taking or inadequate use with subtherapeutic INR';

    /** Value for management_as field */
    const INPATIENT = 'Inpatient';

    /** Value for management_as field */
    const OUTPATIENT = 'Outpatient only (including ER)';

    /** Value for management_as field */
    const BOTH = 'Both';

    /** 
      Return the allowed values for the catheter-induced thrombosis fields.
     */
    function getCatSubtypes() {
       return array(self::CVAC => self::CVAC,
                    self::DIALYSIS => self::DIALYSIS);
    }

    /** 
      Return the allowed values for the definite/probable fields.
     */
    function getDps() {
       return array(self::DEFINITE => self::DEFINITE,
                    self::PROBABLE => self::PROBABLE);
    }

    /** 
      Return the allowed values for the IV Drug use fields.
     */
    function getIVDrugUses() {
       return array(self::CURRENT => self::CURRENT,
                    self::PAST => self::PAST);
    }

    /** 
      Return the allowed values for the Smoking field.
     */
    function getSmokingUses() {
       return array(self::CURRENT => self::CURRENT,
                    self::PAST => self::PAST,
                    self::UNKNOWN => self::UNKNOWN,
                    self::NONE => self::NONE);
    }

    /** 
      Return the allowed values for the type field.
     */
    function getTypes() {
       return array(self::ACUTE => self::ACUTE,
                    self::CHRONIC => self::CHRONIC,
                    self::UNSPECIFIED => self::UNSPECIFIED);
    }

    /** 
      Return the allowed values for the multiple choice fields.
     */
    function getMultipleChoices() {
       return array(self::YES => self::YES,
                    self::NO => self::NO,
                    self::UNKNOWN => self::UNKNOWN);
    }

    /** 
      Return the allowed values for the management_at field.
     */
    function getManagementAts() {
       return array(self::YES => self::YES,
                    self::PRESCRIBED => self::PRESCRIBED,
                    self::NO => self::NO,
                    self::UNKNOWN => self::UNKNOWN);
    }

    /** 
      Return the allowed values for the management_managedas field.
     */
    function getManagementManagedAs() {
       return array(
                    self::INPATIENT => self::INPATIENT,
                    self::OUTPATIENT => self::OUTPATIENT,
                    self::BOTH => self::BOTH,
                    self::UNKNOWN => self::UNKNOWN);
    }
}
