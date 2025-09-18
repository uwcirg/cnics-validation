<?php

require_once "my_controller_test_case.php";

class EventsControllerEditTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    const EDIT_SUCCESS = 'Changed event MI 1002';

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
    }

    function testEditNoCriteria() {
        $result = $this->testAction('/events/edit/41',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1041.*?Criteria.*No criteria currently listed.*Add Criterion/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditCriteria() {
        $result = $this->testAction('/events/edit/5',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1005.*Criteria.*name: value.*Add Criterion/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditNoDownload() {
        $result = $this->testAction('/events/edit/5',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1005<\/h2>\s*?<h3>Main Details/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditDownload() {
        $result = $this->testAction('/events/edit/8',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1008.*?\/events\/download\/8.*?Download charts for this event.*?Site Patient Id/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditNoReviews() {
        $result = $this->testAction('/events/edit/5',
            array('return' => 'vars'));
        $this->assertTrue(empty($result['review1']), 
                          'Review 1 set when it should not be'); 
        $this->assertTrue(empty($result['review2']), 
                          'Review 2 set when it should not be'); 
        $this->assertTrue(empty($result['review3']), 
                          'Review 3 set when it should not be'); 
    }

    function testEditNoSuchEvent() {
        $badId = 1525;
        $result = $this->testAction("/events/edit/$badId",
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "No such event: $badId.";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);

        // again with post
        $data = array('Patient' => array('site_patient_id' => '999b', 
                                         'site' => 'DFCI'), 
                      'Event' => array('id' => $badId, 
                                       'event_date' => '2006-09-02'));
        $result = $this->testAction("/events/edit",
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $expected = "No such event: $badId.";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);
    }

    function testEditReview12() {
        $result = $this->testAction('/events/edit/1',
            array('return' => 'vars'));
        $review1 = array('mci' => Review::NOT, 'ci' => 1);
        $review2 = array('mci' => Review::PROBABLE, 'ci' => null, 
                         'type' => Review::SECONDARY, 
                         'secondary_cause' => Review::OTHER, 
                         'other_cause' => 'boo');
        $this->assertContainsFields($review1, $result['review1'],
                          'Review 1 does not match'); 
        $this->assertContainsFields($review2, $result['review2'], 
                          'Review 2 does not match'); 
        $this->assertTrue(empty($result['review3']), 
                          'Review 3 set when it should not be'); 
        $result = $this->testAction('/events/edit/1',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1001.*?Review 1.*?MI.*?No.*?Cardiac intervention.*?Yes.*?Review 2.*?MI.*?Probable.*?Criteria: Abnormal cardiac enzyme values \(Standard criteria\); Chest pain; ECG changes; Loss of viable myocardium or regional wall abnormalities by imaging; .*?Primary.Secondary.*?Secondary.*?Secondary cause.*?Other.*?Other cause.*?boo.*?Possible false positive.*?Yes.*?False positive reason.*?Renal failure.*?Current tobacco use.*?No.*?Past tobacco use.*?No.*?Past or current cocaine or crack use.*?No.*?Family history of coronary artery disease.*?No/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $this->assertTrue(strpos($result, '<th>Review 3</th>') != 1, 
                          'Review 3 in result'); 
    }

    function testEditReview2() {
        $result = $this->testAction('/events/edit/18',
            array('return' => 'vars'));
        $review2 = array('mci' => Review::PROBABLE, 'ci' => null, 
                         'type' => Review::PRIMARY); 
        $this->assertTrue(empty($result['review1']), 
                          'Review 1 set when it should not be'); 
        $this->assertContainsFields($review2, $result['review2'], 
                          'Review 2 does not match'); 
        $this->assertTrue(empty($result['review3']), 
                          'Review 3 set when it should not be'); 
        $result = $this->testAction('/events/edit/18',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1018.*?Review 2.*?MI.*?Probable.*?Criteria: Abnormal cardiac enzyme values \(PTCA criteria\); Chest pain; .*?Primary.Secondary.*?Primary.*?Current tobacco use.*?Yes.*?Past tobacco use.*?Yes.*?Past or current cocaine or crack use.*?Yes.*?Family history of coronary artery disease.*?Yes/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $this->assertTrue(strpos($result, '<th>Review 1</th>') != 1, 
                          'Review 1 in result'); 
        $this->assertTrue(strpos($result, '<th>Review 3</th>') != 1, 
                          'Review 3 in result'); 
    }

    function testEditReview3() {
        $result = $this->testAction('/events/edit/27',
            array('return' => 'vars'));
        $review1 = array('mci' => Review::PROBABLE, 'ci' => null, 
                         'type' => Review::SECONDARY,
                         'secondary_cause' => Review::OVERDOSE); 
        $review2 = array('mci' => Review::DEFINITE, 'ci' => null, 
                         'type' => Review::PRIMARY); 
        $review3 = array('mci' => Review::NOT, 'ci' => 0);
        $this->assertContainsFields($review1, $result['review1'],
                          'Review 1 does not match'); 
        $this->assertContainsFields($review2, $result['review2'], 
                          'Review 2 does not match'); 
        $this->assertContainsFields($review3, $result['review3'], 
                          'Review 3 does not match'); 
        $result = $this->testAction('/events/edit/27',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1027.*?Review 1.*?MI.*?Probable.*?Criteria: Abnormal cardiac enzyme values \(CABG criteria\); Loss of viable myocardium or regional wall abnormalities by imaging; .*?Primary.Secondary.*?Secondary.*?Secondary cause.*?Overdose.*?Current tobacco use.*?No.*?Past tobacco use.*?Yes.*?Past or current cocaine or crack use.*?No.*?Family history of coronary artery disease.*?Yes.*?Review 2.*?MI.*?Definite.*?Criteria: ECG changes; Loss of viable myocardium or regional wall abnormalities by imaging; .*?Primary.Secondary.*?Primary.*?Current tobacco use.*?Yes.*?Past tobacco use.*?No.*?Past or current cocaine or crack use.*?Yes.*?Family history of coronary artery disease.*?No.*?Review 3.*?MI.*?No.*?Cardiac intervention.*?No/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditNoPacketAvailableOutsideHospital() {
        $result = $this->testAction('/events/edit/37',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1037.*?Reason = Outside hospital; Two attempts made\? No/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditNoPacketAvailableAscertainmentDiagnosisError() {
        $result = $this->testAction('/events/edit/38',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1038.*?Reason = Ascertainment diagnosis error/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditNoPacketAvailableAscertainmentDiagnosisPriorEvent1() {
        $result = $this->testAction('/events/edit/39',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1039.*?Reason = Ascertainment diagnosis referred to a prior event; Date = 2003-00; Onsite\? Yes/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditNoPacketAvailableAscertainmentDiagnosisPriorEvent2() {
        $result = $this->testAction('/events/edit/40',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1040.*?Reason = Ascertainment diagnosis referred to a prior event; Date = unknown; Onsite\? No/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditNoPacketAvailableOther() {
        $result = $this->testAction('/events/edit/41',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1041.*?Reason = Other; Other cause = Bad fire/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditFalsePositiveNo() {
        $result = $this->testAction('/events/edit/13',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1013.*?Possible false positive\? No/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditFalsePositiveYes() {
        $result = $this->testAction('/events/edit/18',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1018.*?Possible false positive\? Yes.*?False positive reason: Renal failure/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditReviewsMissing() {
        $result = $this->testAction('/events/edit/42',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1042.*?Review 1.*?No such review.*?Review 2.*?No such review.*?Review 3.*?No such review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEditNoSolicitations() {
        $result = $this->testAction('/events/edit/4',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1004.*Chart Solicitations.*No solicitations currently listed/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testEdit() {
        $oldEvent = $this->Event->findById(2);
        $newDate = '2009-12-28';

        $data = array('Patient' => array('site_patient_id' => '999b', 
                                         'site' => 'DFCI'), 
                      'Event' => array('id' => 2, 'event_date' => $newDate));

        $result = $this->testAction('/events/edit',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $expected = '/' . self::EDIT_SUCCESS . '.*Status.*?' . Event::SENT . 
                    '.*Creator.*?gsbarnes.*Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $this->noErrors($result);

        $event = $this->Event->findById(2);
        
        // change old event to new data
        $oldEvent['Event']['patient_id'] = 2;
        $oldEvent['Event']['event_date'] = $newDate;
        $oldEvent['Patient'] = $event['Patient'];

        $this->checkArrayDiff($oldEvent, $event, "Events don't match");
    }

    function testEditNoId() {
        $data = array('Patient' => array('site_patient_id' => 0, 
                                         'site' => 'UNC'), 
                      'Event' => array('id' => 2,
                                       'event_date' => '2009-10-22'));
        $result = $this->testAction('/events/edit',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'Missing patient identifiers') 
                              !== false);
        $this->noCakeErrors($result);
    }

    function testEditNoSite() {
        $data = array('Patient' => array('site_patient_id' => '888a', 
                                         'site' => ''), 
                      'Event' => array('id' => 2, 
                                       'event_date' => '2009-10-22'));
        $result = $this->testAction('/events/edit',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'Missing patient identifiers') 
                              !== false);
        $this->noCakeErrors($result);
    }

    function testEditNoDate() {
        $data = array('Patient' => array('site_patient_id' => '888a', 
                                         'site' => 'UNC'), 
                      'Event' => array('id' => 2, 'event_date' => ''));
        $result = $this->testAction('/events/edit',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'Missing event date') !== false);
        $this->noCakeErrors($result);
    }

    /* Strings that should only appear on the edit page for events with
       particular statuses */
    const MARKED_AS_NO_PACKET_BY = 'Person who marked packet as not available:';
    const NO_PACKET_AVAILABLE_DATE = 'Date packet was marked as not available:';
    const UPLOADER = 'Uploader:';
    const UPLOAD_DATE = 'Upload Date:';
    const SCRUBBER = 'Scrubber:';
    const SCRUB_DATE = 'Scrub Date:';
    const SCREENER = 'Screener:';
    const SCREEN_DATE = 'Screen Date:';
    const ASSIGNER = 'Assigner:';
    const ASSIGN_DATE = 'Assign Date:';
    const SENDER = 'Sender:';
    const SEND_DATE = 'Send Date:';
    const REVIEWER1 = 'Reviewer 1:';
    const REVIEW1_DATE = 'Review 1 Date:';
    const REVIEWER2 = 'Reviewer 2:';
    const REVIEW2_DATE = 'Review 2 Date:';
    const POSSIBLE_FALSE_POSITIVE = 'Possible false positive?';
    const FALSE_POSITIVE_REASON = 'False positive reason:';
    const THIRD_ASSIGNER = 'Third Review Assigner:';
    const THIRD_ASSIGN_DATE = 'Third Review Assign Date:';
    const REVIEWER3 = 'Reviewer 3:';
    const REVIEW3_DATE = 'Review 3 Date:';
    const UPLOAD_MESSAGE = 'Upload Message:';
    const RESCRUB_MESSAGE = 'Rescrub Message:';
    const REJECT_MESSAGE = 'Reject Message:';

    /**
     * Check that a field that is not supposed to be on the edit event
     * page does indeed not appear there
     * @param field The field
     * @param page The page, as text
     */
    function checkFieldAbsent($field, $page) {
        $this->assertTrue(strpos($page, $field) === false,
                          "$field appears, but no $field");
    }

    function testEditViewThirdReviewDone() {
        $result = $this->testAction('/events/edit/12',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1012.*?Status:.*?done.*?Creation Date:.*?2009-12-29.*?Creator:.*?gsbarnes@washington.edu.*?' . 
            self::UPLOAD_DATE . '.*2009-12-30.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-01-06.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-19.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::ASSIGN_DATE . '.*2010-01-07.*?' . 
            self::ASSIGNER . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER1 . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER2 . '.*?gsbarnes@washington.edu.*?' .
            self::SEND_DATE . '.*2010-01-08.*?' . 
            self::SENDER . '.*?user3@example.com.*?' .
            self::REVIEW1_DATE . '.*2010-01-09.*?' . 
            self::REVIEW2_DATE . '.*2010-01-10.*?' . 
            self::THIRD_ASSIGN_DATE . '.*2010-01-11.*?' . 
            self::THIRD_ASSIGNER . '.*?user3@example.com.*?' .
            self::REVIEWER3 . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEW3_DATE . '.*2010-01-12.*?' . 
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
    }

    function testEditViewThirdReviewAssigned() {
        $result = $this->testAction('/events/edit/11',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1011.*Status:.*third_review_assigned.*Creation Date:.*2009-12-16.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2009-12-17.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-01-13.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-18.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::ASSIGN_DATE . '.*2010-01-14.*?' . 
            self::ASSIGNER . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER1 . '.*?user2@example.com.*?' .
            self::REVIEWER2 . '.*?user@example.com.*?' .
            self::SEND_DATE . '.*2010-01-15.*?' . 
            self::SENDER . '.*?user3@example.com.*?' .
            self::REVIEW1_DATE . '.*2010-01-16.*?' . 
            self::REVIEW2_DATE . '.*2010-01-17.*?' . 
            self::THIRD_ASSIGN_DATE . '.*2010-01-18.*?' . 
            self::THIRD_ASSIGNER . '.*?user3@example.com.*?' .
            self::REVIEWER3 . '.*?gsbarnes@washington.edu.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
    }

    function testEditViewThirdReviewNeeded() {
        $result = $this->testAction('/events/edit/1',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1001.*Status:.*third_review_needed.*Creation Date:.*2009-09-15.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2009-10-18.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-01-19.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-12.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::ASSIGN_DATE . '.*2010-01-20.*?' . 
            self::ASSIGNER . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER1 . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER2 . '.*?user3@example.com.*?' .
            self::SEND_DATE . '.*2010-01-21.*?' . 
            self::SENDER . '.*?user3@example.com.*?' .
            self::REVIEW1_DATE . '.*2010-01-22.*?' . 
            self::REVIEW2_DATE . '.*2010-01-23.*?' . 
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
    }

    function testEditViewReviewed2Done() {
        $result = $this->testAction('/events/edit/13',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1013.*Status:.*done.*Creation Date:.*2009-09-15.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2010-01-01.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-01-24.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-20.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::ASSIGN_DATE . '.*2010-01-25.*?' . 
            self::ASSIGNER . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER1 . '.*?user2@example.com.*?' .
            self::REVIEWER2 . '.*?gsbarnes@washington.edu.*?' .
            self::SEND_DATE . '.*2010-01-26.*?' . 
            self::SENDER . '.*?user3@example.com.*?' .
            self::REVIEW1_DATE . '.*2010-01-28.*?' . 
            self::REVIEW2_DATE . '.*2010-01-27.*?' . 
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
    }

    function testEditViewReviewed2() {
        $result = $this->testAction('/events/edit/4',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1004.*Status:.*reviewer2_done.*Creation Date:.*2009-09-15.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2009-10-21.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-01-29.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-15.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::ASSIGN_DATE . '.*2010-01-30.*?' . 
            self::ASSIGNER . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER1 . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER2 . '.*?user3@example.com.*?' .
            self::SEND_DATE . '.*2010-01-31.*?' . 
            self::SENDER . '.*?user3@example.com.*?' .
            self::REVIEW2_DATE . '.*2010-02-01.*?' . 
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
    }

    function testEditViewReviewed1() {
        $result = $this->testAction('/events/edit/3',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1003.*Status:.*reviewer1_done.*Creation Date:.*2009-09-15.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2009-10-20.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-02-01.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-14.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::ASSIGN_DATE . '.*2010-02-02.*?' . 
            self::ASSIGNER . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER1 . '.*?user3@example.com.*?' .
            self::REVIEWER2 . '.*?gsbarnes@washington.edu.*?' .
            self::SEND_DATE . '.*2010-02-03.*?' . 
            self::SENDER . '.*?user3@example.com.*?' .
            self::REVIEW1_DATE . '.*2010-02-04.*?' . 
            self::POSSIBLE_FALSE_POSITIVE . ".*?" .
            self::FALSE_POSITIVE_REASON . ".*?" .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
    }

    function testEditViewSent() {
        $result = $this->testAction('/events/edit/2',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1002.*Status:.*sent.*Creation Date:.*2009-09-15.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2009-10-19.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-02-05.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-13.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::ASSIGN_DATE . '.*2010-02-06.*?' . 
            self::ASSIGNER . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER1 . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER2 . '.*?user3@example.com.*?' .
            self::SEND_DATE . '.*2010-02-07.*?' . 
            self::SENDER . '.*?user3@example.com.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditViewAssigned() {
        $result = $this->testAction('/events/edit/14',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1014.*Status:.*assigned.*Creation Date:.*2010-01-02.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2010-01-03.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-02-08.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-21.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::ASSIGN_DATE . '.*2010-02-09.*?' . 
            self::ASSIGNER . '.*?gsbarnes@washington.edu.*?' .
            self::REVIEWER1 . '.*?user2@example.com.*?' .
            self::REVIEWER2 . '.*?gsbarnes@washington.edu.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::SENDER, $result);
        $this->checkFieldAbsent(self::SEND_DATE, $result);
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditViewScreened() {
        $result = $this->testAction('/events/edit/19',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1019.*Status:.*screened.*Creation Date:.*2010-02-23.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2010-02-24.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-02-25.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-02-26.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::ASSIGNER, $result);
        $this->checkFieldAbsent(self::ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::SENDER, $result);
        $this->checkFieldAbsent(self::SEND_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER1, $result);
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER2, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditViewRejected() {
        $result = $this->testAction('/events/edit/29',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1029.*Status:.*rejected.*Creation Date:.*2010-04-29.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2010-04-30.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-05-01.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::SCREEN_DATE . '.*2010-05-02.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            self::REJECT_MESSAGE . '.*?shifty eyes.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::ASSIGNER, $result);
        $this->checkFieldAbsent(self::ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::SENDER, $result);
        $this->checkFieldAbsent(self::SEND_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER1, $result);
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER2, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditViewToBeRescrubbed() {
        $result = $this->testAction('/events/edit/17',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1017.*Status:.*uploaded.*Creation Date:.*2010-02-24.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2010-02-25.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-05-03.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            self::RESCRUB_MESSAGE . '.*?99 problems.*?' .
            self::SCREEN_DATE . '.*2010-05-04.*?' . 
            self::SCREENER . '.*?user3@example.com.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::ASSIGNER, $result);
        $this->checkFieldAbsent(self::ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::SENDER, $result);
        $this->checkFieldAbsent(self::SEND_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER1, $result);
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER2, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditViewScrubbed() {
        $result = $this->testAction('/events/edit/15',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1015.*Status:.*scrubbed.*Creation Date:.*2010-01-04.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2010-01-05.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            self::SCRUB_DATE . '.*2010-02-10.*?' . 
            self::SCRUBBER . '.*?user@example.com.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::SCREENER, $result);
        $this->checkFieldAbsent(self::SCREEN_DATE, $result);
        $this->checkFieldAbsent(self::ASSIGNER, $result);
        $this->checkFieldAbsent(self::ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::SENDER, $result);
        $this->checkFieldAbsent(self::SEND_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER1, $result);
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER2, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditViewUploaded() {
        $result = $this->testAction('/events/edit/9',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1009.*?Status:.*?uploaded.*?Creation Date:.*?2009-10-26.*?Creator:.*?gsbarnes@washington.edu.*?' .
            self::UPLOAD_DATE . '.*2009-10-27.*?' . 
            self::UPLOADER . '.*?user3@example.com.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::SCRUBBER, $result);
        $this->checkFieldAbsent(self::SCRUB_DATE, $result);
        $this->checkFieldAbsent(self::SCREENER, $result);
        $this->checkFieldAbsent(self::SCREEN_DATE, $result);
        $this->checkFieldAbsent(self::ASSIGNER, $result);
        $this->checkFieldAbsent(self::ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::SENDER, $result);
        $this->checkFieldAbsent(self::SEND_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER1, $result);
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER2, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditViewNoPacketAvailable() {
        $result = $this->testAction('/events/edit/37',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1037.*Status:.*no_packet_available.*Creation Date:.*2010-05-06.*Creator:.*?gsbarnes@washington.edu.*?' .
            self::NO_PACKET_AVAILABLE_DATE . '.*2010-05-07.*?' . 
            self::MARKED_AS_NO_PACKET_BY . '.*?user3@example.com.*?' .
            'Criteria/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::UPLOADER, $result);
        $this->checkFieldAbsent(self::UPLOAD_DATE, $result);
        $this->checkFieldAbsent(self::SCRUBBER, $result);
        $this->checkFieldAbsent(self::SCRUB_DATE, $result);
        $this->checkFieldAbsent(self::SCREENER, $result);
        $this->checkFieldAbsent(self::SCREEN_DATE, $result);
        $this->checkFieldAbsent(self::ASSIGNER, $result);
        $this->checkFieldAbsent(self::ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::SENDER, $result);
        $this->checkFieldAbsent(self::SEND_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER1, $result);
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER2, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditViewCreated() {
        $result = $this->testAction('/events/edit/5',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Event MI 1005.*Status:.*created.*Creation Date:.*2009-09-15.*Creator:.*?gsbarnes@washington.edu.*Criteria.*Some nonsense/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Basic pattern does not appear'); 
        $this->checkFieldAbsent(self::UPLOADER, $result);
        $this->checkFieldAbsent(self::UPLOAD_DATE, $result);
        $this->checkFieldAbsent(self::SCRUBBER, $result);
        $this->checkFieldAbsent(self::SCRUB_DATE, $result);
        $this->checkFieldAbsent(self::SCREENER, $result);
        $this->checkFieldAbsent(self::SCREEN_DATE, $result);
        $this->checkFieldAbsent(self::ASSIGNER, $result);
        $this->checkFieldAbsent(self::ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::SENDER, $result);
        $this->checkFieldAbsent(self::SEND_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER1, $result);
        $this->checkFieldAbsent(self::REVIEW1_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER2, $result);
        $this->checkFieldAbsent(self::REVIEW2_DATE, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGNER, $result);
        $this->checkFieldAbsent(self::THIRD_ASSIGN_DATE, $result);
        $this->checkFieldAbsent(self::REVIEWER3, $result);
        $this->checkFieldAbsent(self::REVIEW3_DATE, $result);
        $this->checkFieldAbsent(self::UPLOAD_MESSAGE, $result);
        $this->checkFieldAbsent(self::RESCRUB_MESSAGE, $result);
        $this->checkFieldAbsent(self::REJECT_MESSAGE, $result);
        $this->checkFieldAbsent(self::MARKED_AS_NO_PACKET_BY, $result);
        $this->checkFieldAbsent(self::NO_PACKET_AVAILABLE_DATE, $result);
        $this->checkFieldAbsent(self::POSSIBLE_FALSE_POSITIVE, $result);
        $this->checkFieldAbsent(self::FALSE_POSITIVE_REASON, $result);
    }

    function testEditEventDate() {
        $result = $this->testAction('/events/edit/5',
            array('return' => 'contents'));
        $expected = '/Event date.*?selected="selected">February.*?selected="selected">14.*?selected="selected">2009.*Status:/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Event date not found'); 
    }
}

?>
