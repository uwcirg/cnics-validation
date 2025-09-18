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

    /** Value for a timing */
    const PRESENTED = 'Presented in AF';

    /** Value for a timing */
    const AFTER = 'AF started after admission';

    /** Value for AF type field */
    const PAROXYSMAL = 'paroxysmal';

    /** Value for AF type field */
    const PERSISTENT = 'persistent';

    /** Value for AF type field */
    const PERMANENT = 'permanent';

    /** Value for Drug use fields */
    const CURRENT = 'current';

    /** Value for Drug use fields */
    const PAST = 'past';

    /** Yes value for multiple choice questions */
    const YES = 'Yes';

    /** No value for multiple choice questions */
    const NO = 'No';

    /** Unknown value for multiple choice questions */
    const UNKNOWN = 'unknown';

    /** None value for multiple choice questions */
    const NONE = 'none';

    /** 
      Return the allowed values for the timing field.
     */
    function getTimings() {
       return array(self::PRESENTED => self::PRESENTED,
                    self::AFTER => self::AFTER);
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
      Return the allowed values for the AF type field.
     */
    function getAfTypes() {
       return array(self::PAROXYSMAL => self::PAROXYSMAL,
                    self::PERSISTENT => self::PERSISTENT,
                    self::PERMANENT => self::PERMANENT,
                    self::UNKNOWN => self::UNKNOWN);
    }
}
