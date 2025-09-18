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
     */
    private function saveEdd($eventId, $pe_flag, $dvt_flag, $cat_flag,
                             $no_vte_flag, $cat_subtype, $pe_dp,
                             $dvt_dp, $cat_dp, $pe_type, $dvt_type,
                             $cat_type, $pe_location, $dvt_location,
                             $dvt_le_location, $dvt_other_location,
                             $dvt_other_neck_location,
                             $dvt_other_ap_location, $dvt_other_ps_location,
                             $dvt_other_ic_location)
    {
        $edd['event_id'] = $eventId;
        $edd['pe_flag'] = $pe_flag;
        $edd['dvt_flag'] = $dvt_flag;
        $edd['cat_flag'] = $cat_flag;
        $edd['no_vte_flag'] = $no_vte_flag;
        $edd['cat_subtype'] = $cat_subtype;
        $edd['pe_dp'] = $pe_dp;
        $edd['dvt_dp'] = $dvt_dp;
        $edd['cat_dp'] = $cat_dp;
        $edd['pe_type'] = $pe_type;
        $edd['dvt_type'] = $dvt_type;
        $edd['cat_type'] = $cat_type;
        $edd['pe_location'] = $pe_location;
        $edd['dvt_location'] = $dvt_location;
        $edd['dvt_le_location'] = $dvt_le_location;
        $edd['dvt_other_location'] = $dvt_other_location;
        $edd['dvt_other_neck_location'] = $dvt_other_neck_location;
        $edd['dvt_other_ap_location'] = $dvt_other_ap_location;
        $edd['dvt_other_ps_location'] = $dvt_other_ps_location;
        $edd['dvt_other_ic_location'] = $dvt_other_ic_location;
        $newEdd['EventDerivedData'] = $edd;
        $this->create();
        $this->saveAll($newEdd);
    }

    var $pe_locs =
        array(
            'pe_main' => 'Main pulmonary artery(ies)',
            'pe_lobar' => 'Lobar',
            'pe_segmental' => 'Segmental',
            'pe_subsegmental' => 'Sub-segmental',
            'pe_unknown' => 'Unknown'
        );

    const LE = 'LE';
    const OTHER = 'OTHER';

    var $dvt_locs =
        array(
            'dvt_ue' => 'UE',
            'dvt_le' => self::LE,
            'dvt_other' => self::OTHER,
            'dvt_unknown' => 'Unknown'
        );

    var $dvt_le_locs =
        array(
              'dvt_le_proximal' => 'Proximal (popliteal, femoral, iliac)',
              'dvt_le_distal' => 'Distal',
              'dvt_le_unknown' => 'Unknown',
        );

    const OTHER_NECK = 'Neck/Chest';
    const OTHER_AP = 'Abdomen/pelvis';
    const OTHER_PS = 'Portal system';
    const OTHER_IC = 'Intercranial';

    var $dvt_other_locs =
        array(
              'dvt_other_neck' => self::OTHER_NECK,
              'dvt_other_vc' => 'Vena cava (superior or inferior)',
              'dvt_other_ap' => self::OTHER_AP,
              'dvt_other_ps' => self::OTHER_PS,
              'dvt_other_ic' => self::OTHER_IC,
              'dvt_other_other' => 'Other',
              'dvt_other_unknown' => 'Unknown'
        );

    var $dvt_other_neck_locs =
        array(
                'dvt_other_neck_jugular' => 'Jugular',
                'dvt_other_neck_subclavian' => 'Subclavian',
                'dvt_other_neck_brach' => 'Brachiocephalic (innominate)',
                'dvt_other_neck_unknown' => 'Unknown'
        );

    var $dvt_other_ap_locs =
        array(
                'dvt_other_ap_renal' => 'Renal',
                'dvt_other_ap_hepatic' => 'Hepatic',
                'dvt_other_ap_pelvic' => 'Pelvic',
                'dvt_other_ap_iliac' => 'Iliac',
                'dvt_other_ap_unknown' => 'Unknown'
        );

    var $dvt_other_ps_locs =
        array(
                'dvt_other_ps_hepatic' => 'Hepatic portal',
                'dvt_other_ps_splenic' => 'Splenic',
                'dvt_other_ps_mesenteric' => 'Mesenteric',
                'dvt_other_ps_unknown' => 'Unknown'
        );

    var $dvt_other_ic_locs =
        array(
                'dvt_other_ic_sst' => 'Sagittal sinus thrombosis',
                'dvt_other_ic_tst' => 'Transverse sinus thrombosis',
                'dvt_other_ic_rvt' => 'Retinal vein thrombosis',
                'dvt_other_ic_unknown' => 'Unknown'
        );

    /**
     * Get the agreement for a collection of related (location) fields
     * @param locs associative array (review field name -> display name)
     * @param review1 first review
     * @param review2 second review
     * @param review3 optional 3rd review
     * @return The agreement as a string
     */
    private function agreement($locs, $review1, $review2, $review3 = null) {
        $retval = '';

        foreach ($locs as $fieldName => $displayName) {
	    $fn = $fieldName .= '_flag';

            if (empty($review3)) {
                if ($review1[$fn] && $review2[$fn]) {
                    $retval .= "$displayName; ";
                }
            } else {
		$c = $this->consensus($fn, $review1, $review2, $review3);

                if ($c != self::NO_CONSENSUS && $c === '1') {
                    $retval .= "$displayName; ";
                }
            }
        }

        if ($retval === '') {
            return self::NO_CONSENSUS;
	} else {
            return $retval;
        }
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

        if ($review1['pe_flag']) {
            if ($review1['pe_dp'] == $review2['pe_dp']) {
                $pe_dp = $review1['pe_dp'];
            } else {
                $pe_dp = self::NO_CONSENSUS;
            }

            if ($review1['pe_type'] == $review2['pe_type']) {
                $pe_type = $review1['pe_type'];
            } else {
                $pe_type = self::NO_CONSENSUS;
            }

            $pe_location = $this->agreement($this->pe_locs, $review1, $review2);
        } else {
            $pe_dp = null;
            $pe_type = null;
            $pe_location = null;
        }

        if ($review1['dvt_flag']) {
            if ($review1['dvt_dp'] == $review2['dvt_dp']) {
                $dvt_dp = $review1['dvt_dp'];
            } else {
                $dvt_dp = self::NO_CONSENSUS;
            }

            if ($review1['dvt_type'] == $review2['dvt_type']) {
                $dvt_type = $review1['dvt_type'];
            } else {
                $dvt_type = self::NO_CONSENSUS;
            }
        } else {
            $dvt_dp = null;
            $dvt_type = null;
        }

        if ($review1['cat_flag']) {
            if ($review1['cat_dp'] == $review2['cat_dp']) {
                $cat_dp = $review1['cat_dp'];
            } else {
                $cat_dp = self::NO_CONSENSUS;
            }

            if ($review1['cat_type'] == $review2['cat_type']) {
                $cat_type = $review1['cat_type'];
            } else {
                $cat_type = self::NO_CONSENSUS;
            }

            if ($review1['cat_subtype'] == $review2['cat_subtype']) {
                $cat_subtype = $review1['cat_subtype'];
            } else {
                $cat_subtype = self::NO_CONSENSUS;
            }
        } else {
            $cat_dp = null;
            $cat_type = null;
            $cat_subtype = null;
        }

	$dvt_location = null;
	$dvt_le_location = null;
	$dvt_other_location = null;
	$dvt_other_neck_location = null;
	$dvt_other_ap_location = null;
	$dvt_other_ps_location = null;
	$dvt_other_ic_location = null;

        if ($review1['dvt_flag'] || $review1['cat_flag']) {
            $dvt_location = $this->agreement($this->dvt_locs, $review1, $review2);

            if (strpos($dvt_location, self::LE) !== false) {
                $dvt_le_location = $this->agreement($this->dvt_le_locs,
                                                    $review1, $review2);
            }

            if (strpos($dvt_location, self::OTHER) !== false) {
                $dvt_other_location = $this->agreement($this->dvt_other_locs,
                        $review1, $review2);

                if (strpos($dvt_other_location, self::OTHER_NECK) !== false) {
                    $dvt_other_neck_location =
                        $this->agreement($this->dvt_other_neck_locs,
                             $review1, $review2);
                }

                if (strpos($dvt_other_location, self::OTHER_AP) !== false) {
                    $dvt_other_ap_location =
                        $this->agreement($this->dvt_other_ap_locs,
                            $review1, $review2);
                }

                if (strpos($dvt_other_location, self::OTHER_PS) !== false) {
                    $dvt_other_ps_location =
                        $this->agreement($this->dvt_other_ps_locs,
                            $review1, $review2);
                }

                if (strpos($dvt_other_location, self::OTHER_IC) !== false) {
                    $dvt_other_ic_location =
                        $this->agreement($this->dvt_other_ic_locs,
                            $review1, $review2);
                }
            }
        }

        // flag values must be the same to be done after 2 reviews
        $this->saveEdd($eventId,
                $review1['pe_flag'],
                $review1['dvt_flag'],
                $review1['cat_flag'],
                $review1['no_vte_flag'],
                $cat_subtype, $pe_dp,
                $dvt_dp, $cat_dp, $pe_type, $dvt_type,
                $cat_type, $pe_location, $dvt_location,
                $dvt_le_location, $dvt_other_location,
                $dvt_other_neck_location,
                $dvt_other_ap_location, $dvt_other_ps_location,
                $dvt_other_ic_location);
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

        $pe_flag = $this->consensus('pe_flag', $review1, $review2, $review3);
        $dvt_flag = $this->consensus('dvt_flag', $review1, $review2,
                                     $review3);
        $cat_flag = $this->consensus('cat_flag', $review1, $review2,
                                     $review3);
        $no_vte_flag = $this->consensus('no_vte_flag', $review1, $review2,
                                        $review3);

        if ($pe_flag) {
            $pe_dp = $this->consensus('pe_dp', $review1, $review2, $review3);
            $pe_type = $this->consensus('pe_type', $review1, $review2,
                                        $review3);
            $pe_location = $this->agreement($this->pe_locs, $review1, $review2,
                                            $review3);
        } else {
            $pe_dp = null;
            $pe_type = null;
            $pe_location = null;
        }

        if ($dvt_flag) {
            $dvt_dp = $this->consensus('dvt_dp', $review1, $review2,
                                       $review3);
            $dvt_type = $this->consensus('dvt_type', $review1, $review2,
                                         $review3);
        } else {
            $dvt_dp = null;
            $dvt_type = null;
        }

        if ($cat_flag) {
            $cat_dp = $this->consensus('cat_dp', $review1, $review2,
                                       $review3);
            $cat_type = $this->consensus('cat_type', $review1, $review2,
                                         $review3);
            $cat_subtype = $this->consensus('cat_subtype', $review1,
                                            $review2, $review3);
        } else {
            $cat_dp = null;
            $cat_type = null;
            $cat_subtype = null;
        }

	$dvt_location = null;
	$dvt_le_location = null;
	$dvt_other_location = null;
	$dvt_other_neck_location = null;
	$dvt_other_ap_location = null;
	$dvt_other_ps_location = null;
	$dvt_other_ic_location = null;

        if ($dvt_flag || $cat_flag) {
	    $dvt_location = $this->agreement($this->dvt_locs, $review1, 
		$review2, $review3);

            if (strpos($dvt_location, self::LE) !== false) {
                $dvt_le_location = $this->agreement($this->dvt_le_locs,
                    $review1, $review2, $review3);
            }

            if (strpos($dvt_location, self::OTHER) !== false) {
                $dvt_other_location = $this->agreement($this->dvt_other_locs,
                    $review1, $review2, $review3);

                if (strpos($dvt_other_location, self::OTHER_NECK) !== false) {
                    $dvt_other_neck_location =
                        $this->agreement($this->dvt_other_neck_locs,
                            $review1, $review2, $review3);
                }

                if (strpos($dvt_other_location, self::OTHER_AP) !== false) {
                    $dvt_other_ap_location =
                        $this->agreement($this->dvt_other_ap_locs,
                            $review1, $review2, $review3);
                }

                if (strpos($dvt_other_location, self::OTHER_PS) !== false) {
                    $dvt_other_ps_location =
                        $this->agreement($this->dvt_other_ps_locs,
                            $review1, $review2, $review3);
                }

                if (strpos($dvt_other_location, self::OTHER_IC) !== false) {
                    $dvt_other_ic_location =
                        $this->agreement($this->dvt_other_ic_locs,
                            $review1, $review2, $review3);
                }
            }
        }

        $this->saveEdd($eventId,
                $pe_flag, $dvt_flag, $cat_flag, $no_vte_flag,
                $cat_subtype, $pe_dp,
                $dvt_dp, $cat_dp, $pe_type, $dvt_type,
                $cat_type, $pe_location, $dvt_location,
                $dvt_le_location, $dvt_other_location,
                $dvt_other_neck_location,
                $dvt_other_ap_location, $dvt_other_ps_location,
                $dvt_other_ic_location);
    }

    function catchup() {
        $events = $this->Event->findAll();

        foreach ($events as $event) {
            if ($event['Event']['status'] == Event::DONE) {
$this->log("catchup " . $event['Event']['id']);
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
