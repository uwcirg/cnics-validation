<?php

require_once "my_controller_test_case.php";

class EventsControllerReviewTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
        $this->Review =& ClassRegistry::init('Review');
        $this->User =& ClassRegistry::init('User');

        // previous review for both events 3 and 4
        $this->previousReview = array(
            'mci' => Review::PROBABLE,
            'abnormal_ce_values_flag' => 0,
            'ce_criteria' => null,
            'chest_pain_flag' => 1,
            'ecg_changes_flag' => 1,
            'lvm_by_imaging_flag' => 1,
            'ci' => null, 'type' => Review::SECONDARY,
            'secondary_cause' => Review::OVERDOSE,
            'false_positive_flag' => 1,
            'false_positive_reason' => Review::RF,
            'current_tobacco_use_flag' => '0',
            'past_tobacco_use_flag' => '1',
            'cocaine_use_flag' => '1',
            'family_history_flag' => '1');
    }   

    private function checkReviewNoData($reviewerNumber, $event) {
        $eventId = $event['Event']['id'];
        $miNumber = 1000 + $eventId;
        $eventDate = $event['Event']['event_date'];
        $action = "/events/review{$reviewerNumber}/{$eventId}";
        $result = $this->testAction($action, array('return' => 'contents'));
        $this->noErrors($result);
        $expected = "/Review Instructions.*?CNICS MI reviewer instructions.doc.*?Review event: MI{$miNumber}.*?Date:.*?{$eventDate}.*?events\/review{$reviewerNumber}.*?events\/download\/{$eventId}.*?Download charts.*?Was the event a Myocardial Infarction.*?Please identify all criteria that indicated possible or definite MI.*?Did the patient have a cardiac intervention.*?Was the myocardial infarction Primary or Secondary.*?If Secondary, what was the cause.*?Other cause.*?Meets criteria for an MI but has a credible reason to be potentially a false positive.*?Reason for the potential false positive result.*?Is there any mention of current tobacco use.*?Is there any mention of past tobacco use.*?Is there any mention of past or current cocaine or crack use.*?Is there any mention of a family history of coronary artery disease.*?Submit/sm";
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 

        $result = $this->testAction($action, array('return' => 'vars'));
        $this->checkArrayDiff($result['mcis'], $this->Review->getMcis(),
                              'mcis array does not match');
        $this->checkArrayDiff($result['types'], $this->Review->getTypes(),
                              'types array does not match');
        $this->checkArrayDiff($result['secondaryCauses'], 
                              $this->Review->getSecondaryCauses(),
                              'secondary causes array does not match');
        $this->checkArrayDiff($result['falsePositiveReasons'], 
                              $this->Review->getFalsePositiveReasons(),
                              'false positive reasons array does not match');
        $this->checkArrayDiff($result['event'], $event,
                              'event does not match');
        $this->assertEqual($result['eventId'], $eventId, 
                           'Event id does not match');
        $this->assertEqual($result['reviewerNumber'], $reviewerNumber, 
                           'reviewer number does not match');
    }
   
    /**
     * Check that a first review that should be successful is successful
     * @param reviewerNumber Number of the reviewer
     * @param event The event to review
     * @param review Review data
     * @param expectedReview Review data that should be stored
     * @param firstReview Is this the first review?
     */
    private function submitFirstOrThirdReview($reviewerNumber, $event, $review, 
                                              $expectedReview, $firstReview) 
    {
        $eventId = $event['Event']['id'];
        $data = array('Event' => array('id' => $eventId));
        $data['Review'] = $review;

        $result = $this->testAction("/events/review{$reviewerNumber}", 
             array('data' => $data, 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 'Review saved') != false, 
            'missing success message');

        $newEvent = $this->Event->findById($eventId);

        if ($reviewerNumber == 3) {
            $newStatus = Event::DONE;
            $dateField = 'review3_date';
        } else if ($reviewerNumber == 1) {
            $newStatus = Event::REVIEWER1_DONE;
            $dateField = 'review1_date';
        } else {
            $newStatus = Event::REVIEWER2_DONE;
            $dateField = 'review2_date';
        }

        $event['Event']['status'] = $newStatus;
        $event['Event'][$dateField] = date('Y-m-d');
        $this->checkArrayDiff($event['Event'], $newEvent['Event'], 
            'event not changed as expected');

        $newReview = $this->Review->findByEventId($eventId); 
            // should only be one
        $expectedReview['event_id'] = $eventId;
        $expectedReview['reviewer_id'] = 1;
        $this->assertContainsFields($expectedReview, $newReview['Review'], 
                                    "Review doesn't match");
    }

    private function checkFirstOrThirdReviewSuccess($reviewerNumber, $event,
                                                    $firstReview) 
    {
        $review = array('mci' => Review::DEFINITE,
                        'chest_pain_flag' => 1,
                        'ci' => null,
                        'type' => Review::SECONDARY,
                        'secondary_cause' => Review::OTHER,
                        'false_positive_flag' => 1,
                        'false_positive_reason' => Review::RF,
                        'other_cause' => 'whatever',
                        'current_tobacco_use_flag' => '0',
                        'past_tobacco_use_flag' => '0',
                        'cocaine_use_flag' => '0',
                        'family_history_flag' => '0');
        $this->submitFirstOrThirdReview($reviewerNumber, $event, $review, 
                                        $review, $firstReview);
    }

    private function checkNotReviewer($reviewerNumber) {
        $errorMessage = 
            "You cannot perform review$reviewerNumber on this event";
        $result = $this->testAction(
            "/events/review{$reviewerNumber}/8", 
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, $errorMessage) !== false,
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data

        $review = array('mci' => Review::NOT);
        $data = array('Event' => array('id' => 8), 
                      'Review' => $review);
        $result = $this->testAction("/events/review{$reviewerNumber}", 
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, $errorMessage) !== false,
            'missing error message');
        $this->noCakeErrors($result);
    }

    const NOT_TO_BE_REVIEWED_ID = 12;
    const BAD_EVENT_ID = 200;
  
    /**
     * Check review # $reviewerNumber on a non-existent event
     */
    private function checkReviewNoSuchEvent($reviewerNumber) {
        $errorMessage = 
            "You cannot perform review$reviewerNumber on this event";
        $result = $this->testAction(
            "/events/review{$reviewerNumber}/" . self::BAD_EVENT_ID, 
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, $errorMessage) !== false,
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data

        $review = array('mci' => Review::NOT);
        $data = array('Event' => array('id' => self::BAD_EVENT_ID), 
                      'Review' => $review);
        $result = $this->testAction("/events/review{$reviewerNumber}", 
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, $errorMessage) !== false,
            'missing error message');
        $this->noCakeErrors($result);
    }

    /**
     * Check review # $reviewerNumber on an event that should not be reviewed
     */
    private function checkReviewBadStatus($reviewerNumber) {
        $errorMessage = 
            "review$reviewerNumber cannot be performed on this event";
        $result = $this->testAction(
            "/events/review{$reviewerNumber}/" . self::NOT_TO_BE_REVIEWED_ID, 
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, $errorMessage) !== false,
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data

        $review = array('mci' => Review::NOT);
        $data = array('Event' => array('id' => self::NOT_TO_BE_REVIEWED_ID), 
                      'Review' => $review);
        $result = $this->testAction("/events/review{$reviewerNumber}", 
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, $errorMessage) !== false,
            'missing error message');
        $this->noCakeErrors($result);
    }

    /**
     * Check review # $reviewerNumber on an event for which the user is not
     * reviewer number $reviewerNumber
     */
    private function checkReviewWrongReviewer($reviewerNumber, $id) {
        $errorMessage = 
            "You cannot perform review$reviewerNumber on this event";
        $result = $this->testAction(
            "/events/review{$reviewerNumber}/" . $id, 
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, $errorMessage) !== false,
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data

        $review = array('mci' => Review::NOT);
        $data = array('Event' => array('id' => $id), 
                      'Review' => $review);
        $result = $this->testAction("/events/review{$reviewerNumber}", 
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, $errorMessage) !== false,
            'missing error message');
        $this->noCakeErrors($result);
    }

    /* eventId should need reviewing by the authuser as reviewerNumber
       badEventId should need to be reviewed, but not *this* review */
    private function checkFirstOrThirdReview($reviewerNumber, $eventId, 
                                             $badEventId, $firstReview)
    {
        $event = $this->Event->findById($eventId);
        $this->checkReviewNoData($reviewerNumber, $event);
        $this->checkReviewNoSuchEvent($reviewerNumber);
        $this->checkReviewBadStatus($reviewerNumber);
        $this->checkReviewWrongReviewer($reviewerNumber, $badEventId);
        $this->checkFirstOrThirdReviewSuccess($reviewerNumber, $event, 
                                              $firstReview);
    }

    function testReview1() {
        $this->checkFirstOrThirdReview(1, 2, 3, true);
    }

    function testReview2() {
        $this->checkFirstOrThirdReview(2, 8, 2, true);
    }

    /**
     * Check a second (successful) review 
     * @param reviewerNumber Number of the reviewer
     * @param eventId Id of the event to review
     * @param match Whether this review matches the first review
     * @param review Review data
     * @param expectedReview Review data that should be stored
     */
    private function submitSecondReview($reviewerNumber, $eventId, $match,
                                        $review, $expectedReview) 
    {
        $event = $this->Event->findById($eventId);
        $data = array('Event' => array('id' => $eventId));
        $data['Review'] = $review;

        $result = $this->testAction("/events/review{$reviewerNumber}", 
             array('data' => $data, 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 'Review saved') != false, 
            'missing success message');

        $newEvent = $this->Event->findById($eventId);

        if ($match) {
            $newStatus = Event::DONE;
        } else {
            $newStatus = Event::THIRD_REVIEW_NEEDED;
        }
  
        $dateField = "review{$reviewerNumber}_date";

        $event['Event']['status'] = $newStatus;
        $event['Event'][$dateField] = date('Y-m-d');
        $this->checkArrayDiff($event['Event'], $newEvent['Event'], 
            'event not changed as expected');

        $newReview = $this->Review->find('first', array(
            'conditions' => array('Review.event_id' => $eventId,
                                  'Review.reviewer_id' => 1)));
            // should only be one
        $expectedReview['event_id'] = $eventId;
        $expectedReview['reviewer_id'] = 1;
        $this->assertContainsFields($expectedReview, $newReview['Review'], 
                                    "Review doesn't match");
    }

    function test2ndReviewNoThird() {
        $expected = $this->previousReview;
        $expected['other_cause'] = null;
        $this->submitSecondReview(1, 4, true, $this->previousReview, $expected);
    }

    function test2ndReviewNeedsThird() {
        $newReview = $this->previousReview;
        $newReview['type'] = Review::PRIMARY;
        $expected = $newReview;
        $expected['secondary_cause'] = null;
        $expected['other_cause'] = null;
        $this->submitSecondReview(2, 3, false, $newReview, $expected);
    }

    function testReviewNotReviewer() {
        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 0);
        $this->User->saveField('third_reviewer_flag', 0);

        $this->checkNotReviewer(1);
        $this->checkNotReviewer(2);
        $this->checkNotReviewer(3);

        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 1);
        $this->User->saveField('third_reviewer_flag', 1);
    }

    /**
     * Test the various shortcuts in the review form
     * @param review Review as specified in the form
     * @param expected Review with other fields set to null (as it should be
     *    saved in the db
     */
    private function checkReviewShortcut($review, $expected) {
        $event = $this->Event->findById(2);
        $this->submitFirstOrThirdReview(1, $event, $review, $expected, true);
    }

    /* test that shortcut answers yield null values for questions that 
       shouldn't have been asked */

    function testReviewShortcutOther() {
        $review = array('mci' => Review::DEFINITE,
                        'abnormal_ce_values_flag' => 1,
                        'ce_criteria' => Review::MUSCLE,
                        'chest_pain_flag' => 1,
                        'ecg_changes_flag' => 1,
                        'lvm_by_imaging_flag' => 1,
                        'ci' => null,
                        'type' => Review::SECONDARY,
                        'secondary_cause' => Review::SEPSIS,
                        'false_positive_flag' => 1,
                        'false_positive_reason' => Review::RF,
                        'current_tobacco_use_flag' => '0',
                        'past_tobacco_use_flag' => '0',
                        'cocaine_use_flag' => '0',
                        'family_history_flag' => '0');
        $expected = $review;
        $expected['other_cause'] = null;
        $this->checkReviewShortcut($review, $expected);
    }

    function testReviewShortcutSecondaryCause() {
        $review = array('mci' => Review::DEFINITE,
                        'abnormal_ce_values_flag' => 1,
                        'ce_criteria' => Review::MUSCLE,
                        'chest_pain_flag' => 1,
                        'ecg_changes_flag' => 1,
                        'lvm_by_imaging_flag' => 1,
                        'ci' => null,
                        'type' => Review::PRIMARY,
                        'false_positive_flag' => 1,
                        'false_positive_reason' => Review::RF,
                        'current_tobacco_use_flag' => '0',
                        'past_tobacco_use_flag' => '0',
                        'cocaine_use_flag' => '0',
                        'family_history_flag' => '0');
        $expected = $review;
        $expected['secondary_cause'] = null;
        $expected['other_cause'] = null;
        $this->checkReviewShortcut($review, $expected);
    }

    function testReviewShortcutTypeAndCriteria() {
        $review = array('mci' => Review::NOT,
                        'abnormal_ce_values_flag' => 1,
                        'ce_criteria' => Review::MUSCLE,
                        'chest_pain_flag' => 1,
                        'ecg_changes_flag' => 1,
                        'lvm_by_imaging_flag' => 1,
                        'ci' => 0,
                        'type' => Review::PRIMARY,
                        'secondary_cause' => Review::SEPSIS,
                        'other_cause' => 'ignore',
                        'false_positive_flag' => 1,
                        'false_positive_reason' => Review::RF,
                        'current_tobacco_use_flag' => '0',
                        'past_tobacco_use_flag' => '1',
                        'cocaine_use_flag' => '1',
                        'family_history_flag' => '1');
        $expected = $review;
        $expected['abnormal_ce_values_flag'] = null;
        $expected['ce_criteria'] = null;
        $expected['chest_pain_flag'] = null;
        $expected['ecg_changes_flag'] = null;
        $expected['lvm_by_imaging_flag'] = null;
        $expected['ci'] = 0;
        $expected['type'] = null;
        $expected['secondary_cause'] = null;
        $expected['other_cause'] = null;
        $expected['false_positive_flag'] = null;
        $expected['false_positive_reason'] = null;
        $expected['current_tobacco_use_flag'] = null;
        $expected['past_tobacco_use_flag'] = null;
        $expected['cocaine_use_flag'] = null;
        $expected['family_history_flag'] = null;
        $this->checkReviewShortcut($review, $expected);
    }

    function testReviewShortcutFPReason() {
        $review = array('mci' => Review::DEFINITE,
                        'abnormal_ce_values_flag' => 1,
                        'ce_criteria' => Review::MUSCLE,
                        'chest_pain_flag' => 1,
                        'ecg_changes_flag' => 1,
                        'lvm_by_imaging_flag' => 1,
                        'ci' => null,
                        'type' => Review::SECONDARY,
                        'secondary_cause' => Review::OTHER,
                        'other_cause' => 'whatever',
                        'false_positive_flag' => 0,
                        'false_positive_reason' => Review::RF,
                        'current_tobacco_use_flag' => '1',
                        'cocaine_use_flag' => '0',
                        'family_history_flag' => '0');
        $expected = $review;
        $expected['false_positive_reason'] = null;
        $this->checkReviewShortcut($review, $expected);
    }

    function testReviewShortcutPastTobacco() {
        $review = array('mci' => Review::DEFINITE,
                        'abnormal_ce_values_flag' => 1,
                        'ce_criteria' => Review::MUSCLE,
                        'chest_pain_flag' => 1,
                        'ecg_changes_flag' => 1,
                        'lvm_by_imaging_flag' => 1,
                        'ci' => null,
                        'type' => Review::SECONDARY,
                        'secondary_cause' => Review::OTHER,
                        'other_cause' => 'whatever',
                        'false_positive_flag' => 1,
                        'false_positive_reason' => Review::RF,
                        'current_tobacco_use_flag' => '1',
                        'cocaine_use_flag' => '0',
                        'family_history_flag' => '0');
        $expected = $review;
        $expected['past_tobacco_use_flag'] = null;
        $this->checkReviewShortcut($review, $expected);
    }

    function testThirdReview() {
        $this->checkFirstOrThirdReview(3, 11, 2, false);
    }
}

?>
