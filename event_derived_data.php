<?php
  /** 
   * Review class
   *
   * @author Greg Barnes
   * @version 0.1
   */
class EventDerivedData extends AppModel
{
    var $name = 'EventDerivedData';
    var $belongsTo = array('Event');

    const NO_CONSENSUS = 'NC';

    /**
     * Does an edd already exist for an event?
     * @param eventId Id of the event
     * @return true if there's already an entry for this event
     */
    private function eddAlreadyExists($eventId) {
        $oldEdd = $this->findByEventId($eventId);
       
        return (!empty($oldEdd));
    }

    /**
     * Save an events_derived_data entry
     * @param eventId Id of the event
     * @param outcome outcome
     * @param ps primary_secondary
     * @param fpe false_positive_event
     * @param fpr false_positive_reason
     * @param sc secondary_cause
     * @param sco secondary_cause_other
     * @param ci ci
     * @param ciType CI Type
     * @param ecgType ECG Type
     */
    private function saveEdd($eventId, $outcome, $ps, $fpe, $fpr, $sc, $sco, 
                             $ci, $ciType, $ecgType) 
    {
        $edd['event_id'] = $eventId;
        $edd['outcome'] = $outcome;
        $edd['primary_secondary'] = $ps;
        $edd['false_positive_event'] = $fpe;
        $edd['false_positive_reason'] = $fpr;
        $edd['secondary_cause'] = $sc;
        $edd['secondary_cause_other'] = $sco;
        $edd['ci'] = $ci;
        $edd['ci_type'] = $ciType;
        $edd['ecg_type'] = $ecgType;
        $newEdd['EventDerivedData'] = $edd;
        $this->create();
        $this->saveAll($newEdd);
    }

    /**
     * Add an entry after two reviews that agree
     * @param review1 first review
     * @param review2 second review
     */
    function addAfterTwo($review1, $review2) {
        $eventId = $review1['event_id'];

        if ($this->eddAlreadyExists($eventId)) {  // don't overwrite an old one
            return;
        }
     
        if ($review1['secondary_cause'] == $review2['secondary_cause']) {
            $sc = $review1['secondary_cause'];

            if ($review1['other_cause'] == $review2['other_cause']) {
                $sco = $review1['other_cause'];
            } else {
                $sco = self::NO_CONSENSUS;
            }
        } else {
            $sc = self::NO_CONSENSUS;
            $sco = self::NO_CONSENSUS;
        }

        if ($review1['ci_type'] == $review2['ci_type']) {
            $ciType = $review1['ci_type'];
        } else {
            $ciType = self::NO_CONSENSUS;
        }

        if ($review1['ecg_type'] == $review2['ecg_type']) {
            $ecgType = $review1['ecg_type'];
        } else {
            $ecgType = self::NO_CONSENSUS;
        }

        $this->saveEdd($eventId, 
                $review1['mci'], 
                $review1['type'], 
                $review1['false_positive_flag'], 
                $review1['false_positive_reason'],
                $sc,
                $sco,
                $review1['ci'],
                $ciType,
                $ecgType);
    }

    /**
     * return the consensus value for a field for 3 reviews
     * @param field Name of the field
     * @param review1 first review
     * @param review2 second review
     * @param review3 third review
     * @param strong If true, all 3 must be the same to achieve consensus
     * @return The shared value if 2 (or 3) versions of the field agree, or
     *   a special value if they do not
     */
    private function consensus($field, $review1, $review2, $review3, 
        $strong = false) 
    {
        $f1 = $review1[$field];
        $f2 = $review2[$field];
        $f3 = $review3[$field];

        if ($strong) {
            return ($f1 == $f2 && $f1 == $f3) ? $f1 : self::NO_CONSENSUS;
        }

        // only need 2 to agree
        if ($f1 == $f2 || $f1 == $f3) {
            return $f1;
        } else if ($f2 == $f3) {
            return $f2;
        } else {
            return self::NO_CONSENSUS;
        }
    }

    /**
     * Add an entry after three reviews for an event
     * @param event event
     */
    function addAfterThree($event) {
        $eventId = $event['Event']['id'];

        if ($this->eddAlreadyExists($eventId)) {  // don't overwrite an old one
            return;
        }
     
        $reviews = $this->Event->Review->findAllByEventId($eventId);
        $review1 = $reviews[0]['Review'];
        $review2 = $reviews[1]['Review'];
        $review3 = $reviews[2]['Review'];

        $outcome = $this->consensus('mci', $review1, $review2, $review3);
        $ps = $this->consensus('type', $review1, $review2, $review3);
        $fpe = $this->consensus('false_positive_flag', $review1, $review2, 
                                $review3);
        $fpr = $this->consensus('false_positive_reason', $review1, $review2, 
                                $review3);
        $ci = $this->consensus('ci', $review1, $review2, $review3);

        if ($outcome == self::NO_CONSENSUS || $ps == self::NO_CONSENSUS ||
            $fpe == self::NO_CONSENSUS || $fpr == self::NO_CONSENSUS ||
            $ci == self::NO_CONSENSUS) 
        {   // won't write record if we can't get agreement here
            return;
        }

	// Per Heidi (6/12/2018):  secondary, other no longer require unanimity
        if ($ps == Review::SECONDARY) {
            $sc = $this->consensus('secondary_cause', $review1, $review2, 
                                   $review3);
            $sco = $this->consensus('other_cause', $review1, $review2, 
                                    $review3);
        } else {
            $sc = null;
            $sco = null;
        }

        $ciType = $this->consensus('ci_type', $review1, $review2, $review3);
        $ecgType = $this->consensus('ecg_type', $review1, $review2, $review3);

        $this->saveEdd($eventId, $outcome, $ps, $fpe, $fpr, $sc, $sco, $ci, 
                       $ciType, $ecgType);
    }

    function catchup() {
        $events = $this->Event->findAll();
       
        foreach ($events as $event) {
            if ($event['Event']['status'] == Event::DONE) {
$this->log($event, true);
                if (!empty($event['Event']['reviewer3_id'])) {
                    $this->addAfterThree($event);
                } else {
                    $review1 = $event['Review'][0];
                    $review2 = $event['Review'][1];
                    $this->addAfterTwo($review1, $review2);
                }
            }
        }
    }
}
