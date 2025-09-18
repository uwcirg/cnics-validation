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
     * @param hfType Type of heart failure
     * @param classification classification
     * @param lowLvefFlag low_lvef_flag
     * @param ddFlag dd_flag
     * @param presentation presentation
     * @param ischemicFlag ischemic_flag
     * @param nonischemicFlag nonischemic_flag
     * @param unknownFlag unknown_flag
     */
    private function saveEdd($eventId, $hfType, $classification, $lowLvefFlag,
	    $ddFlag, $presentation, $ischemicFlag, $nonischemicFlag,
	    $unknownFlag) 
    {
        $edd['event_id'] = $eventId;
        $edd['hf_type'] = $hfType;
        $edd['classification'] = $classification;
        $edd['low_lvef_flag'] = $lowLvefFlag;
        $edd['dd_flag'] = $ddFlag;
        $edd['presentation'] = $presentation;
        $edd['ischemic_flag'] = $ischemicFlag;
        $edd['nonischemic_flag'] = $nonischemicFlag;
        $edd['unknown_flag'] = $unknownFlag;
        $newEdd['EventDerivedData'] = $edd;
        $this->create();
        $this->saveAll($newEdd);
    }

    /**
     * Add an entry after two reviews that agree
     * @param review1 first review
     * @param review2 second review
     */
    /* Due to a very loose definition of agreement for this project, none of
     * the fields are guaranteed to have consensus, including the flag fields */
    function addAfterTwo($review1, $review2) {
        $eventId = $review1['event_id'];

        if ($this->eddAlreadyExists($eventId)) {  // don't overwrite an old one
            return;
        }
     
        $hfType = $this->consensus2('hf_type', $review1, $review2);

	if ($hfType == Review::NOT_HF) {
	    $this->saveEdd($eventId, $hfType, null, null, null, null, null, 
		null, null);
	} else {
	    $classification = $this->consensus2('classification', $review1, 
		$review2);
	    $lowLvefFlag = $this->flagToEnum($this->consensus2('low_lvef_flag', 
		$review1, $review2));
	    $ddFlag = $this->flagToEnum($this->consensus2('dd_flag', $review1, 
		$review2));
	    $presentation = $this->consensus2('presentation', $review1, 
		$review2);
	    $ischemicFlag = $this->flagToEnum($this->consensus2('ischemic_flag',
	       	$review1, $review2));
	    $nonischemicFlag = $this->flagToEnum(
		$this->consensus2('nonischemic_flag', $review1, $review2));
	    $unknownFlag = $this->flagToEnum($this->consensus2('unknown_flag', 
		$review1, $review2));

	    $this->saveEdd($eventId, $hfType, $classification, $lowLvefFlag, 
		$ddFlag, $presentation, $ischemicFlag, $nonischemicFlag,
		$unknownFlag);
        }
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
     * return the consensus value for a field for 2 reviews
     * @param field Name of the field
     * @param review1 first review
     * @param review2 second review
     * @return The shared value if the 2 versions of the field agree, or
     *   a special value if they do not
     */
    private function consensus2($field, $review1, $review2) {
        $f1 = $review1[$field];
        $f2 = $review2[$field];

        if ($f1 == $f2) {
            return $f1;
        } else {
            return self::NO_CONSENSUS;
        }
    }

    /**
     * Turn a flag field that could have no consensus into an enumerated value
     * @param value 1, 0 or NC
     * @return Yes, No or NC
     */
    function flagToEnum($value) {
        if ($value == self::NO_CONSENSUS) {
	    return $value;
	} else {
	    return empty($value) ? Review::NO : Review::YES;
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

        $hfType = $this->consensus('hf_type', $review1, $review2, $review3);

	if ($hfType == Review::NOT_HF) {
	    $this->saveEdd($eventId, $hfType, null, null, null, null, null, 
		null, null);
        } else {
	    $classification = $this->consensus('classification', $review1, 
		$review2, $review3);
	    $lowLvefFlag = $this->flagToEnum($this->consensus('low_lvef_flag', 
		$review1, $review2, $review3));
	    $ddFlag = $this->flagToEnum($this->consensus('dd_flag', $review1, 
		$review2, $review3));
	    $presentation = $this->consensus('presentation', $review1, $review2,
		$review3);
	    $ischemicFlag = $this->flagToEnum($this->consensus('ischemic_flag',
	       	$review1, $review2, $review3));
	    $nonischemicFlag = $this->flagToEnum(
		$this->consensus('nonischemic_flag', $review1, $review2, 
			$review3));
	    $unknownFlag = $this->flagToEnum($this->consensus('unknown_flag', 
		$review1, $review2, $review3));

	    $this->saveEdd($eventId, $hfType, $classification, $lowLvefFlag, 
		$ddFlag, $presentation, $ischemicFlag, $nonischemicFlag,
		$unknownFlag);
	}
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
