<?php

require_once "my_test_case.php";

App::import('Model', 'Event', 'Review', 'AppModel', 'CodedItemBehavior');

class EventTestCase extends MyTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');
    private $now;

    function start() {
        parent::start();
        $this->Event =& ClassRegistry::init('Event');
        $this->CodedItemBehavior =& ClassRegistry::init('CodedItemBehavior');
        $this->now = date('Y-m-d');
    }

    function testUpdateAfterReviewThirdReview() {
        $event = array('Event' => 
             array('id' => 11, 'status' => Event::THIRD_REVIEW_ASSIGNED));

        $this->Event->updateAfterReview(3, $event, null);
        $result = $this->Event->findById(11);

        $this->assertNotNull($result['Event']);
        $resultEvent = $result['Event'];

        $expected = array('id' => 11, 'patient_id' => 1, 
                          'reviewer1_id' => 6, 'reviewer2_id' => 3, 
                          'reviewer3_id' => 1,
                          'status' => Event::DONE);

        $this->assertContainsFields($expected, $resultEvent);
        $this->checkDateField($resultEvent, $this->now, 'review3_date');
    }

    /**
     * Test update after a first review
     * @param reviewerNumber number of the reviewer (1 or 2)
     */
    private function testUpdateAfterFirstOfTwoReviews($reviewerNumber) {
        $event = $this->Event->findById(2);
        $reviewer = array('User' => array('id' => $reviewerNumber));

        $this->Event->updateAfterReview($reviewerNumber, $event, $reviewer);
        $result = $this->Event->findById(2);
        $this->assertNotNull($result['Event']);
        $resultEvent = $result['Event'];

        $expected = array('id' => 2, 'patient_id' => 1, 
                          'reviewer1_id' => 1, 'reviewer2_id' => 2, 
                          'status' => $reviewerNumber == 1 ? 
                              Event::REVIEWER1_DONE : Event::REVIEWER2_DONE);

        $this->assertContainsFields($expected, $resultEvent);
        $this->checkDateField($resultEvent, $this->now, 
                              "review{$reviewerNumber}_date");
    }

    function testUpdateAfterReviewFirstReviewer1() {
        $this->testUpdateAfterFirstOfTwoReviews(1);
    }

    function testUpdateAfterReviewFirstReviewer2() {
        $this->testUpdateAfterFirstOfTwoReviews(2);
    }

    /**
     * Test update for the second reviewer of two
     * @param reviewer1 If true, reviewer1 is the second reviewer
     * @param review1 First review
     * @param mciAgree Do the two reviews agree on MCI?
     * @param ciAgree Do the two reviews agree on Cardiac Intervention?
     * @param typeAgree Do the two reviews agree on type?
     * @param falsePositiveAgree Do the two reviews agree on whether the review
     *    is a false positive?
     * @param falsePositiveReasonAgree Do the two reviews agree on the false
     *    positive reason?
     * @param simulateRace If we wish to simulate the case where both reviewers
     *        complete their reviews at nearly the same time
     */
    private function testUpdateAfterSecondReview($reviewer1, $review1, 
                                                 $mciAgree, $ciAgree,
                                                 $typeAgree, 
                                                 $falsePositiveAgree,
                                                 $falsePositiveReasonAgree,
                                                 $simulateRace = false) 
    {
        $this->Review =& ClassRegistry::init('Review');

        $eventId = $reviewer1 ? 35: 34;
        $reviewerNumber = $reviewer1 ? 1 : 2;

        $review1['Review']['event_id'] = $eventId;
        $review1['Review']['reviewer_id'] = 1;

        // save the 2 reviews
        $type1 = $review1['Review']['type'];
        $mci1 = $review1['Review']['mci'];
        $ci1 = $review1['Review']['ci'];
        $fp1 = $review1['Review']['false_positive_flag'];
        $fpr1 = $review1['Review']['false_positive_reason'];

        if ($typeAgree) {
            $type2 = $type1;
        } else {
            $type2 = ($type1 == Review::PRIMARY ? Review::SECONDARY :
                                                  Review::PRIMARY);
        }

        if ($mciAgree) {
            $mci2 = $mci1;
        } else {
            $mci2 = ($mci1 == Review::DEFINITE ? Review::PROBABLE :
                                                 Review::DEFINITE);
        }

        if ($ciAgree) {
            $ci2 = $ci1;
        } else {
            $ci2 = ($ci1 == true ? false : true);
        }

        if ($falsePositiveAgree) {
            $fp2 = $fp1;
        } else {
            $fp2 = ($fp1 == true ? false : true);

            if ($fp2 == true) {
                $fpr2 = Review::RF;
            }
        }

        if ($falsePositiveReasonAgree) {
            $fpr2 = $fpr1;
        } else {
            $fpr2 = ($fpr1 == Review::RF ? Review::PE : Review::RF);
        }

        $review2 = array('Review' => array('event_id' => $eventId, 
                                           'reviewer_id' => 2,
                                           'mci' => $mci2,
                                           'ci' => $ci2,
                                           'type' => $type2,
                                           'false_positive_flag' => $fp2,
                                           'false_positive_reason' => $fpr2));

        $this->Review->save($review1);
        $this->Review->create();
        $this->Review->save($review2);

        $event = $this->Event->findById($eventId);

        if ($simulateRace) {
            $event['Event']['status'] = Event::SENT;
        }
 
        $reviewer = array('User' => array('id' => $reviewer1 ? 1 : 2));

        $this->Event->updateAfterReview($reviewerNumber, $event, $reviewer);
        $result = $this->Event->findById($eventId);
        $this->assertNotNull($result['Event']);
        $resultEvent = $result['Event'];

        $event['Event']['status'] = 
            ($mciAgree && $typeAgree && $ciAgree && $falsePositiveAgree && 
             $falsePositiveReasonAgree) ? 
            Event::DONE : Event::THIRD_REVIEW_NEEDED;
        $dateField = $reviewer1 ? 'review1_date' : 'review2_date';
        $event['Event'][$dateField] = date('Y-m-d');

        $this->assertContainsFields($event['Event'], $resultEvent);
        $this->checkDateField($resultEvent, $this->now, $dateField);
    }

    function testUpdateAfterReviewSecondReviewer1Agree() {
        $review = array('Review' => 
            array('mci' => Review::DEFINITE,
                  'ci' => null,
                  'type' => Review::PRIMARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::RF));
        $this->testUpdateAfterSecondReview(true, $review, true, true, true,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeMci() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::SECONDARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(true, $review, false, true, true,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeCi() {
        $review = array('Review' => array('mci' => Review::NOT,
                                          'ci' => true,
                                          'type' => null,
                                          'false_positive_flag' => null,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(true, $review, true, false, true,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeType() {
        $review = array('Review' => array('mci' => Review::PROBABLE,
                                          'ci' => null,
                                          'type' => Review::PRIMARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(true, $review, true, true, false,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeFalsePositive() {
        $review = array('Review' => array('mci' => Review::PROBABLE,
                                          'ci' => null,
                                          'type' => Review::PRIMARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(true, $review, true, true, true,
                                           false, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeFalsePositiveReason() {
        $review = array('Review' => 
            array('mci' => Review::PROBABLE,
                  'ci' => null,
                  'type' => Review::PRIMARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::RF));
        $this->testUpdateAfterSecondReview(true, $review, true, true, true,
                                           true, false);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeMciFp() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::PRIMARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(true, $review, false, true, true,
                                           false, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeMciFpr() {
        $review = 
            array('Review' => array('mci' => Review::DEFINITE,
                  'ci' => null,
                  'type' => Review::PRIMARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::PERICARDITIS));
        $this->testUpdateAfterSecondReview(true, $review, false, true, true,
                                           true, false);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeTypeFp() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::SECONDARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(true, $review, true, true, false,
                                           false, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeTypeFpr() {
        $review = 
            array('Review' => array('mci' => Review::DEFINITE,
                  'ci' => null,
                  'type' => Review::SECONDARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::MYOCARDITIS));
        $this->testUpdateAfterSecondReview(true, $review, true, true, false,
                                           true, false);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeMciType() {
        $review = array('Review' => array('mci' => Review::PROBABLE,
                                          'ci' => null,
                                          'type' => Review::SECONDARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(true, $review, false, true, false,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeMciTypeFp() {
        $review = array('Review' => array('mci' => Review::PROBABLE,
                                          'ci' => null,
                                          'type' => Review::SECONDARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(true, $review, false, true, false,
                                           false, true);
    }

    function testUpdateAfterReviewSecondReviewer1DisagreeMciTypeFpr() {
        $review = array('Review' => 
            array('mci' => Review::PROBABLE,
                  'ci' => null,
                  'type' => Review::SECONDARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::OTHER));
        $this->testUpdateAfterSecondReview(true, $review, false, true, false,
                                           true, false);
    }

    function testUpdateAfterReviewSecondReviewer2Agree() {
        $review = array('Review' => array('mci' => Review::NOT,
                                          'ci' => null,
                                          'type' => null,
                                          'false_positive_flag' => null,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, true, true, true,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeMci() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::PRIMARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, false, true, true,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeCi() {
        $review = array('Review' => array('mci' => Review::RCA,
                                          'ci' => false,
                                          'type' => null,
                                          'false_positive_flag' => null,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, true, false, true,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeType() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::SECONDARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, true, true, false,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeFalsePositive() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::SECONDARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, true, true, true,
                                           false, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeFalsePositiveReason() {
        $review = 
            array('Review' => array('mci' => Review::DEFINITE,
                  'ci' => null,
                  'type' => Review::SECONDARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::SSS));
        $this->testUpdateAfterSecondReview(false, $review, true, true, true,
                                           true, false);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeMciFp() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::PRIMARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, false, true, true,
                                           false, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeMciFpr() {
        $review = 
            array('Review' => array('mci' => Review::DEFINITE,
                  'ci' => null,
                  'type' => Review::PRIMARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::PERICARDITIS));
        $this->testUpdateAfterSecondReview(false, $review, false, true, true,
                                           true, false);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeTypeFp() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::SECONDARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, true, true, false,
                                           false, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeTypeFpr() {
        $review = 
            array('Review' => array('mci' => Review::DEFINITE,
                  'ci' => null,
                  'type' => Review::SECONDARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::MYOCARDITIS));
        $this->testUpdateAfterSecondReview(false, $review, true, true, false,
                                           true, false);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeMciType() {
        $review = array('Review' => array('mci' => Review::PROBABLE,
                                          'ci' => null,
                                          'type' => Review::PRIMARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, false, true, false,
                                           true, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeMciTypeFp() {
        $review = array('Review' => array('mci' => Review::PROBABLE,
                                          'ci' => null,
                                          'type' => Review::PRIMARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, false, true, false,
                                           false, true);
    }

    function testUpdateAfterReviewSecondReviewer2DisagreeMciTypeFpr() {
        $review = 
            array('Review' => array('mci' => Review::PROBABLE,
                  'ci' => null,
                  'type' => Review::PRIMARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::CHF));
        $this->testUpdateAfterSecondReview(false, $review, false, true, false,
                                           true, false);
    }

    function testUpdateAfterReviewRaceAgree() {
        $review = array('Review' => 
            array('mci' => Review::PROBABLE,
                  'ci' => null,
                  'type' => Review::PRIMARY,
                  'false_positive_flag' => true,
                  'false_positive_reason' => Review::OTHER));
        $this->testUpdateAfterSecondReview(true, $review, true, true, true, 
                                           true, true, true);
    }

    function testUpdateAfterReviewRaceDisagree() {
        $review = array('Review' => array('mci' => Review::DEFINITE,
                                          'ci' => null,
                                          'type' => Review::PRIMARY,
                                          'false_positive_flag' => false,
                                          'false_positive_reason' => null));
        $this->testUpdateAfterSecondReview(false, $review, false, true, false, 
                                           true, true, true);
    }

    function testAwaitingReviewFirstReviewer() {
        $user = array('User' => array('id' => 1));
        $result = $this->Event->awaitingReview($user);

        $this->assertEqual(count($result), 8, 'Should be 8 events');

        $resultEvent1 = $result[0]['Event'];
        $expected1 = array('id' => 2, 'patient_id' => 1,
              'reviewer1_id' => 1, 'reviewer2_id' => 2, 'reviewer3_id' => null,
              'status' => 'sent',
              'add_date' => '2009-09-15');
        $this->assertContainsFields($expected1, $resultEvent1);

        $resultEvent2 = $result[2]['Event'];
        $expected2 = array('id' => 4, 'patient_id' => 1,
              'reviewer1_id' => 1, 'reviewer2_id' => 2, 'reviewer3_id' => null,
              'status' => 'reviewer2_done',
              'add_date' => '2009-09-15');

        $this->assertContainsFields($expected2, $resultEvent2);
    }

    function testAwaitingReviewSecondReviewer() {
        $user = array('User' => array('id' => 2));
        $result = $this->Event->awaitingReview($user);

        $this->assertEqual(count($result), 3, 'Should be 3 events');

        $resultEvent1 = $result[0]['Event'];
        $expected1 = array('id' => 2, 'patient_id' => 1,
              'reviewer1_id' => 1, 'reviewer2_id' => 2, 'reviewer3_id' => null,
              'status' => 'sent',
              'add_date' => '2009-09-15');
        $this->assertContainsFields($expected1, $resultEvent1);

        $resultEvent2 = $result[2]['Event'];
        $expected2 = array('id' => 26, 'patient_id' => 1,
              'reviewer1_id' => 1, 'reviewer2_id' => 2, 'reviewer3_id' => null,
              'status' => 'reviewer1_done',
              'add_date' => '2010-04-02');
        $this->assertContainsFields($expected2, $resultEvent2);
    }

    function testAwaitingReviewThirdReviewer() {
        $user = array('User' => array('id' => 1));
        $result = $this->Event->awaitingReview($user);

        $this->assertEqual(count($result), 8, 'Should be 8 events');

        $resultEvent = $result[4]['Event'];
        $expected = array('id' => 11, 'patient_id' => 1,
              'reviewer1_id' => 6, 'reviewer2_id' => 3, 'reviewer3_id' => 1,
              'status' => 'third_review_assigned',
              'add_date' => '2009-12-16');
        $this->assertContainsFields($expected, $resultEvent);
    }

    function testAwaitingReviewNoResults() {
        $user = array('User' => array('id' => 4));
        $result = $this->Event->awaitingReview($user);
        $this->assertEqual(count($result), 0, 'Should be no events');
    }

    function testAwaitingUpload() {
        $user = array('User' => array('id' => 1, 'site' => 'UW'));
        $result = $this->Event->awaitingUpload($user);
        $this->assertEqual(count($result), 3, 'Should be 3 events');

        $resultEvent1 = $result[0]['Event'];
        $expected1 = array('id' => 6, 'patient_id' => 5,
              'reviewer1_id' => null, 'reviewer2_id' => null, 
              'reviewer3_id' => null,
              'status' => 'created',
              'add_date' => '2009-10-15');
        $this->assertContainsFields($expected1, $resultEvent1);

        $resultEvent2 = $result[1]['Event'];
        $expected2 = array('id' => 7, 'patient_id' => 5,
              'reviewer1_id' => null, 'reviewer2_id' => null, 
              'reviewer3_id' => null,
              'status' => 'created',
              'add_date' => '2009-10-16');
        $this->assertContainsFields($expected2, $resultEvent2);
    }

    function testAwaitingUploadNoResults() {
        $user = array('User' => array('id' => 3, 'site' => 'UNC'));
        $result = $this->Event->awaitingUpload($user);
        $this->assertEqual(count($result), 0, 'Should be no events');
    }

    function testPossibleReupload() {
        $user = array('User' => array('id' => 1, 'site' => 'UW'));
        $result = $this->Event->possibleReupload($user);
        $this->assertEqual(count($result), 2, 'Should be 2 events');

        $resultEvent1 = $result[0]['Event'];
        $expected1 = array('id' => 9, 'patient_id' => 5,
              'reviewer1_id' => null, 'reviewer2_id' => null, 
              'reviewer3_id' => null,
              'status' => 'uploaded',
              'add_date' => '2009-10-26');
        $this->assertContainsFields($expected1, $resultEvent1);

        $resultEvent2 = $result[1]['Event'];
        $expected2 = array('id' => 17, 'patient_id' => 5,
              'reviewer1_id' => null, 'reviewer2_id' => null, 
              'reviewer3_id' => null,
              'status' => 'uploaded',
              'add_date' => '2010-02-24');
        $this->assertContainsFields($expected2, $resultEvent2);
    }

    function testPossibleReuploadNoResults() {
        $user = array('User' => array('id' => 3, 'site' => 'UCSF'));
        $result = $this->Event->possibleReupload($user);
        $this->assertEqual(count($result), 0, 'Should be no events');
    }

    function testPathPrefix() {
        $result = $this->Event->pathPrefix();
        $this->assertEqual($result, 
            '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/chartUploads/', 
            "pathPrefix: $result");
    }

    function testRawFileName() {
        $id = 25;
        $number = 12345678;
        $result = $this->Event->rawFileName($id, $number);
        $this->assertEqual($result, "orig_{$id}_$number", 
                           "rawFileName: $result");
    }

    function testScrubbedFileName() {
        $id = 25;
        $number = 12345678;
        $result = $this->Event->scrubbedFileName($id, $number);
        $this->assertEqual($result, "clean_{$id}_$number", 
                           "scrubbedFileName: $result");
    }

    function testGetSuffix() {
        $result = $this->Event->getSuffix('application/pdf');
        $this->assertEqual($result, '.pdf', "pdfSuffix: $result");

        $result = $this->Event->getSuffix('application/zip');
        $this->assertEqual($result, '.zip', "zip1Suffix: $result");

        $result = $this->Event->getSuffix('application/x-zip-compressed');
        $this->assertEqual($result, '.zip', "zip2Suffix: $result");

        $result = $this->Event->getSuffix('multipart/x-zip');
        $this->assertEqual($result, '.zip', "zip3Suffix: $result");

        $result = $this->Event->getSuffix('application/x-compressed');
        $this->assertEqual($result, '.zip', "zip4Suffix: $result");

        $result = $this->Event->getSuffix('application/x-gzip');
        $this->assertEqual($result, '.gz', "gzip1Suffix: $result");

        $result = $this->Event->getSuffix('application/gzip');
        $this->assertEqual($result, '.gz', "gzip2Suffix: $result");

        $result = $this->Event->getSuffix('');
        $this->assertNull($result, "$result should be null");

        $result = $this->Event->getSuffix('image/jpeg');
        $this->assertNull($result, "$result should be null for image");
    }

    function testActionChangesStatus() {
        $this->assertEqual($this->Event->actionChangesStatus('view'), false,
            'true for non-action view');
        $this->assertEqual($this->Event->actionChangesStatus(null), false,
            'true for null');
        $this->assertEqual($this->Event->actionChangesStatus('add'), true,
            'false for add');
        $this->assertEqual($this->Event->actionChangesStatus('upload'), true,
            'false for upload');
        $this->assertEqual($this->Event->actionChangesStatus('review3'), true,
            'false for review3');
    }

    function testIsCreationAction() {
        $this->assertEqual($this->Event->isCreationAction('view'), false,
            'true for non-action view');
        $this->assertEqual($this->Event->isCreationAction(null), false,
            'true for null');
        $this->assertEqual($this->Event->isCreationAction('add'), true,
            'false for add');
        $this->assertEqual($this->Event->isCreationAction('upload'), false,
            'true for upload');
        $this->assertEqual($this->Event->isCreationAction('review3'), false,
            'true for review3');
    }

    function testCanBePerformed() {
        $event1 = array('Event' => array('status' => Event::CREATED));
        $event2 = array('Event' => array('status' => Event::UPLOADED));
        $event3 = array('Event' => array('status' => Event::SCRUBBED));
        $event4 = array('Event' => array('status' => Event::ASSIGNED));

        $this->assertEqual($this->Event->canBePerformed(null, $event1), false,
            'true for non-existent action');
        $this->assertEqual($this->Event->canBePerformed('upload', null), false,
            'true for non-existent event');
        $this->assertEqual($this->Event->canBePerformed('upload', $event1), 
            true, "Supposedly can't upload created event");

        $this->assertEqual($this->Event->canBePerformed('upload', $event2), 
            true, "Supposedly can't upload uploaded event");
        $this->assertEqual($this->Event->canBePerformed('upload', $event3), 
            false, "Supposedly can upload scrubbed event");
        $this->assertEqual($this->Event->canBePerformed('upload', $event4), 
            false, "Supposedly can upload assigned event");
        $this->assertEqual($this->Event->canBePerformed('scrub', $event3), 
            true, "Supposedly can't re-scrub scrubbed event");
        $this->assertEqual($this->Event->canBePerformed('screen', $event3), 
            true, "Supposedly can't screen scrubbed event");
        $this->assertEqual($this->Event->canBePerformed('assign', $event1), 
            false, "Supposedly can assign created event");
    }

    function testCanPerformAction() {
        $user1 = array('User' => array('id' => 1,
                                       'uploader_flag' => 1, 
                                       'admin_flag' => 1,
                                       'reviewer_flag' => 1,
                                       'site' => 'UW'));
        $user2 = array('User' => array('uploader_flag' => 0, 
                                       'admin_flag' => 0,
                                       'reviewer_flag' => 0,
                                       'site' => 'UW'));
        $event1 = array('Event' => array('status' => Event::CREATED),
                        'Patient' => array('site' => 'UW'));
        $event2 = array('Event' => array('status' => Event::CREATED),
                        'Patient' => array('site' => 'UNC'));
        $event3 = array('Event' => array('status' => Event::SENT,
                                         'reviewer1_id' => 1));
        $event4 = array('Event' => array('status' => Event::SENT,
                                         'reviewer1_id' => 2));

        $this->assertEqual(
            $this->Event->canPerformAction('view', $user1, $event1),  
            false, 'true for non-action view');
        $this->assertEqual(
            $this->Event->canPerformAction(null, $user1, $event1),  
            false, 'true for null');
        $this->assertEqual(
            $this->Event->canPerformAction('upload', $user1, $event1),  
            true, 'false for upload good user/event');
        $this->assertEqual(
            $this->Event->canPerformAction('upload', $user2, $event1),  
            false, 'true for non-uploader');
        $this->assertEqual(
            $this->Event->canPerformAction('upload', $user1, $event2),  
            false, 'true for uploading for event from different site');
        $this->assertEqual(
            $this->Event->canPerformAction('assign', $user1, $event2),  
            true, 'false for admin');
        $this->assertEqual(
            $this->Event->canPerformAction('assign', $user2, $event2),  
            false, 'true for non-admin');
        $this->assertEqual(
            $this->Event->canPerformAction('review1', $user1, $event3),  
            true, 'false for proper reviewer1');
        $this->assertEqual(
            $this->Event->canPerformAction('review1', $user2, $event3),  
            false, 'true for non-reviewer');
        $this->assertEqual(
            $this->Event->canPerformAction('review1', $user1, $event4),  
            false, 'true for wrong reviewer');
    }

    function testIsUploaderForSite() {
        $user1 = array('User' => array('uploader_flag' => 1, 
                                       'site' => 'UW'));
        $user2 = array('User' => array('uploader_flag' => 0, 
                                       'site' => 'UW'));
        $event1 = array('Event' => array('status' => Event::CREATED),
                        'Patient' => array('site' => 'UW'));
        $event2 = array('Event' => array('status' => Event::CREATED),
                        'Patient' => array('site' => 'UNC'));

        $this->assertEqual(
            $this->Event->isUploaderForSite(null, $event1),
            false, 'true for null user');
        $this->assertEqual(
            $this->Event->isUploaderForSite($user1, null),
            false, 'true for null event');
        $this->assertEqual(
            $this->Event->isUploaderForSite($user1, $user1),
            false, 'true for bogus event');
        $this->assertEqual(
            $this->Event->isUploaderForSite($user2, $event1),
            false, 'true for non-uploader');
        $this->assertEqual(
            $this->Event->isUploaderForSite($user1, $event1),
            true, 'false for valid uploader');
        $this->assertEqual(
            $this->Event->isUploaderForSite($user1, $event2),
            false, 'true for wrong site');
    }

    /**
     * test the 'isReviewerN' functions (where N = 1, 2, or 3)
     */
    function checkIsReviewerN($reviewerNumber) {
        $user1 = array('User' => array('id' => 1, 'reviewer_flag' => 1));
        $user2 = array('User' => array('id' => 2, 'reviewer_flag' => 1));
        $user3 = array('User' => array('id' => null));
        $user4 = array('User' => null);
        $user5 = array('User' => array('id' => 1, 'reviewer_flag' => 0));
        $event1 = array('Event' => array("reviewer{$reviewerNumber}_id" => 1));
        $event2 = array('Event' => array("reviewer{$reviewerNumber}_id" => 
                                         null));
        $event3 = array('Event' => null);

        $function = array('Event', "is_reviewer$reviewerNumber");

        $this->assertEqual(call_user_func($function, null, $event1),
            false, 'true for null user');
        $this->assertEqual(call_user_func($function, $user1, null),
            false, 'true for null event');
        $this->assertEqual(call_user_func($function, $user4, $event1),
            false, 'true for bogus user4');
        $this->assertEqual(call_user_func($function, $user3, $event1),
            false, 'true for bogus user3');
        $this->assertEqual(call_user_func($function, $user1, $event2),
            false, 'true for bogus event2');
        $this->assertEqual(call_user_func($function, $user1, $event3),
            false, 'true for bogus event3');
        $this->assertEqual(call_user_func($function, $user1, $event1),
            true, 'false for valid reviewer');
        $this->assertEqual(call_user_func($function, $user2, $event1),
            false, 'true for wrong reviewer');
        $this->assertEqual(call_user_func($function, $user5, $event1),
            false, 'true for non-reviewer');
    }

    function testIsReviewer1() {
        $this->checkIsReviewerN(1);
    }

    function testIsReviewer2() {
        $this->checkIsReviewerN(2);
    }

    function testIsReviewer3() {
        $this->checkIsReviewerN(3);
    }
 
    /**
     * Test the addEvent function with different criteria
     * @param criteria The criteria
     */
    private function checkAddEvent($criteria) {
        $eventDate = '2008-03-05';
        $event = array('event_date' => $eventDate);
        $date = date('Y-m-d');
        $patientId = 5;

        $this->Event->setVars('add', 25);
        $this->Event->addEvent($patientId, $event, $criteria);

        // get the last one added
        $result = $this->Event->find('first', 
            array('conditions' => array('Event.patient_id' => $patientId),
                  'order' => array('Event.id DESC')));

        $this->assertNotNull($result['Event']);
        $resultEvent = $result['Event'];

        $expected = array('patient_id' => $patientId,
                          'creator_id' => 25,
                          'add_date' => $date,
                          'status' => Event::CREATED,
                          'event_date' => $eventDate);

        $this->assertContainsFields($expected, $resultEvent);

        $resultCriteria = $result['Criteria'];
       
        foreach ($resultCriteria as $key => $crit) {
            $this->assertContainsFields($criteria[$key], $resultCriteria[$key]);
        }
    }

    function testAddEvent() {
        $this->checkAddEvent(array());

        $criteria = array(0 => array('name' => 'name1', 'value' => 'value1'));
        $this->checkAddEvent($criteria);
        
        $criteria[1] = array('name' => 'name2', 'value' => 'value2');
        $criteria[2] = array('name' => 'name3', 'value' => 'value3');
        $this->checkAddEvent($criteria);
    }

    function testEditEvent() {
        $eventDate = '2009-12-25';
        $event = array('id' => 2, 'event_date' => $eventDate);
        $patientId = 5;
        $oldEvent = $this->Event->findById(2);

        $this->Event->setVars('edit', 25);
        $this->Event->editEvent($patientId, $event);

        // get the event after editing
        $newEvent = $this->Event->findById(2);

        $oldEvent['Event']['patient_id'] = $patientId;
        $oldEvent['Event']['event_date'] = $eventDate;
        $oldEvent['Patient'] = $newEvent['Patient'];

        $this->checkArrayDiff($oldEvent, $newEvent, 
            'New event not as expected');
    }

    function testVerifyUpload() {
        $file = array();
        $this->assertEqual($this->Event->verifyUpload($file), false, 
                           'true with empty file');
        $file['error'] = 1;
        $this->assertEqual($this->Event->verifyUpload($file), false, 
                           'true with error set');
        $file['error'] = 0;
        $this->assertEqual($this->Event->verifyUpload($file), false, 
                           'true with tmp_name empty');
        $file['tmp_name'] = 'none';
        $this->assertEqual($this->Event->verifyUpload($file), false, 
                           'true with tmp_name none');
        $file['tmp_name'] = '/etc/passwd';
        $this->assertEqual($this->Event->verifyUpload($file), false, 
                           'true with unuploaded tmp_name');
        /* true case possible only because of special logic in the function
           that can tell if we're testing */
        $file['tmp_name'] = '/tmp/whatever';
        $this->assertEqual($this->Event->verifyUpload($file), true, 
                           'false with supposed uploaded file');
    }

    function testSaveFile() {
        $file = array();
        $expected = array('success' => false, 'message' => 'Upload failed.');
        $result = $this->Event->saveFile($file, null);
        $this->checkArrayDiff($result, $expected, 'No failure for empty file');

        // make sure bogus upload file names are caught by verifyUpload
        $file['error'] = 0;
        $file['tmp_name'] = '/etc/passwd';
        $result = $this->Event->saveFile($file, null);
        $this->checkArrayDiff($result, $expected, 
            'Bad tmp_name should have caused failure in verifyUpload');

        $file['tmp_name'] = '/tmp/whatever';
        $file['type'] = 'bogus';
        $expected = array('success' => false, 
                          'message' => "Invalid file type {$file['type']}");
        $result = $this->Event->saveFile($file, null);
        $this->checkArrayDiff($result, $expected, 
                              'No failure for bogus file type');

        $file['type'] = 'application/pdf';
        $filename = '/../../../../../../../../etc/file';
        $expected = array('success' => false,
                          'message' => 'Cannot write file');
        $result = $this->Event->saveFile($file, $filename);
        $this->checkArrayDiff($result, $expected, 
                              "No failure for bad filename $filename");

        $filename = 'something';
        $expected = array('success' => true);
        $result = $this->Event->saveFile($file, $filename);
        $this->checkArrayDiff($result, $expected, 
            "Should have succeeded on $filename");
    }

    function testGetUploadMessage() {
        $this->assertEqual($this->Event->getUploadMessage('Something', 3), 
                           'Something.  Size = 3 bytes.');
                           
    }

    function testUploadCharts() {
        // test for failure
        $file = array('size' => 2, 'name' => 'boo');
        $event = array('Event' => array('id' => 5, 'patient_id' => 3));
        $expected = array('success' => false, 'message' => 'Upload failed.');
        $result = $this->Event->uploadCharts($event, $file, false);
        $this->checkArrayDiff($result, $expected, 
            'No failure for file missing data');

        // test for success: raw file
        $file['error'] = 0;
        $file['tmp_name'] = '/tmp/whatever';
        $file['type'] = 'application/pdf';
        $expected = array('success' => true, 'message' => 
            'Charts file uploaded.  Size = 2 bytes.');
        $this->Event->setVars('upload', 25);
        $result = $this->Event->uploadCharts($event, $file, false);
        $this->checkArrayDiff($result, $expected, 
                              'Raw file should have succeeded');

        $result = $this->Event->findById(5);

        $this->assertNotNull($result['Event']);
        $resultEvent = $result['Event'];

        $expected = array('id' => 5, 'patient_id' => 2, 
                          'status' => Event::UPLOADED,
                          'original_name' => 'boo',
                          'uploader_id' => 25,
                          'upload_date' => date('Y-m-d'));
        $this->assertContainsFields($expected, $resultEvent);
        $this->assertNotNull($resultEvent['file_number']);

        // test for success: scrubbed file
        $file['error'] = 0;
        $file['tmp_name'] = '/tmp/whatever';
        $file['type'] = 'application/pdf';
        $expected = array('success' => true, 'message' => 
            'Scrubbed file uploaded.  Size = 2 bytes.');
        $this->Event->setVars('scrub', 26);
        $event = array('Event' => array('id' => 9, 'patient_id' => 7, 
                                        'file_number' => -1));
        $result = $this->Event->uploadCharts($event, $file, true);
        $this->checkArrayDiff($result, $expected, 
                              'Scrubbed file should have succeeded');

        $result = $this->Event->findById(9);

        $this->assertNotNull($result['Event']);
        $resultEvent = $result['Event'];

        /* note: uploadCharts does not change scrubber_id or scrub_date for
           scrubbed files */
        $expected = array('id' => 9, 'patient_id' => 5, 
                          'status' => Event::UPLOADED,
                          'uploader_id' => 2,
                          'upload_date' => '2009-10-27',
                          'file_number' => -1,
                          'original_name' => null,
                          'scrubber_id' => null,
                          'scrub_date' => null);
        $this->assertContainsFields($expected, $resultEvent);

        // test for success: re-scrubbed file
        $file['error'] = 0;
        $file['tmp_name'] = '/tmp/whatever';
        $file['type'] = 'application/pdf';
        $expected = array('success' => true, 'message' => 
            'Scrubbed file uploaded.  Size = 2 bytes.');
        $this->Event->setVars('scrub', 25);
        $event = array('Event' => array('id' => 15, 'patient_id' => 7, 
                                        'file_number' => -1));
        $result = $this->Event->uploadCharts($event, $file, true);
        $this->checkArrayDiff($result, $expected, 
                              'Scrubbed file should have succeeded');

        $result = $this->Event->findById(15);

        $this->assertNotNull($result['Event']);
        $resultEvent = $result['Event'];

        /* note: uploadCharts does not change scrubber_id or scrub_date for
           scrubbed files */
        $expected = array('id' => 15, 'patient_id' => 1, 
                          'status' => Event::SCRUBBED,
                          'uploader_id' => 2,
                          'upload_date' => '2010-01-05',
                          'file_number' => -1,
                          'original_name' => null,
                          'scrubber_id' => 3,
                          'scrub_date' => '2010-02-10');
        $this->assertContainsFields($expected, $resultEvent);
    }

    function testCheckAddData() {
        $data = array('Patient' => array('site_patient_id' => null,
                                         'site' => null));
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::MISSING_PATIENT_DATA);
        $this->checkArrayDiff($result, $expected, 'Null sitePatientId');

        $data['Patient']['site_patient_id'] = 25;
        $result = $this->Event->checkAddData($data);
        $this->checkArrayDiff($result, $expected, 'Null site');

        $data['Patient']['site'] = 'UW';
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::MISSING_EVENT_DATE);
        $this->checkArrayDiff($result, $expected, 'Null event');

        $data['Event'] = array();
        $result = $this->Event->checkAddData($data);
        $this->checkArrayDiff($result, $expected, 'Null event_date');

        $data['Event']['event_date'] = '1900-06-06';
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::EVENT_DATE_TOO_EARLY);
        $this->checkArrayDiff($result, $expected, 'Event date too early');

        $data['Event']['event_date'] = '2008-04-04';
        $result = $this->Event->checkAddData($data);
        $expected = array(false,
                'No patient found with id 25 at site UW');
        $this->checkArrayDiff($result, $expected, 'Bogus patient');

        $data['Patient']['site_patient_id'] = '007x';
        $result = $this->Event->checkAddData($data);
        $expected = array(true, 5);
        $this->checkArrayDiff($result, $expected, 'Should have worked');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'TRUE');
        $data['Criteria'][1] = array('name' => 'ckmb_q', 'value' => '3.0');
        $result = $this->Event->checkAddData($data);
        $expected = array(true, 5);
        $this->checkArrayDiff($result, $expected, 
                              'Should have worked (criteria)');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'mi dx');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: mi dx');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'diagnosis');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: diagnosis');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'dx');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: dx');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'mi_dx');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: mi_dx');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 
                                     'value' => 'troponin t');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: troponin t');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'troponin');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: troponin');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 
                                               'value' => 'troponin i');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: troponin i');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'trop_i');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: trop_i');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'trop_t');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: trop_t');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 
                                     'value' => 'troponin i (tni)');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: troponin i (tni)');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 
                                     'value' => 'troponin t (tnt)');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: troponin t (tnt)');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 
                                     'value' => 'creatine kinase mb quotient');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 
                              'criteria: creatine kinase mb quotient');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'ckmb_q');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: ckmb_q');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 
                                     'value' => 'creatine kinase mb mass');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 
                              'criteria: creatine kinase mb mass');
    
        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'ckmb_m');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: ckmb_m');
    
        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'ckmb');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: ckmb');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'CKMB');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: CKMB');

        $data['Criteria'][0] = array('name' => 'MI_Dx', 'value' => 'TRUE');
        $data['Criteria'][1] = array('name' => 'ckmb_q', 'value' => 'ckmb');
        $result = $this->Event->checkAddData($data);
        $expected = array(false, Event::CRITERIA_PROBLEM);
        $this->checkArrayDiff($result, $expected, 'criteria: 2nd');
    }

    function testCheckReviewData() {
        $data = array('Review' => array('mci' => null, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 0,
                                        'ci' => null,
                                        'type' => null,
                                        'secondary_cause' => null,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'current_tobacco_use_flag' => '1',
                                        'past_tobacco_use_flag' => '1',
                                        'cocaine_use_flag' => '1',
                                        'family_history_flag' => '1'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::MCI_BLANK);
        $this->checkArrayDiff($result, $expected, 'null mci');

        $data = array('Review' => array('mci' => Review::NOT, 'ci' => '',
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 0,
                                        'type' => 'aa',
                                        'secondary_cause' => 'bb',
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc',
                                        'current_tobacco_use_flag' => '1',
                                        'past_tobacco_use_flag' => '1',
                                        'cocaine_use_flag' => '1',
                                        'family_history_flag' => '1'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::CI_BLANK);
        $this->checkArrayDiff($result, $expected, 'blank ci');

        $data = array('Review' => array('mci' => Review::RCA, 'ci' => 0,
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 0,
                                        'type' => 'aa',
                                        'secondary_cause' => 'bb',
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc',
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '1',
                                        'cocaine_use_flag' => '1',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 'not an mci [rca]');
        $expectedData = array('Review' => array('mci' => Review::RCA, 
                                                'ci' => 0));
        $this->checkArrayDiff($data, $expectedData, 
            'fields not unset for RCA');

        $data = array('Review' => array('mci' => Review::NOT, 'ci' => 1,
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 0,
                                        'type' => 'aa',
                                        'secondary_cause' => 'bb',
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc',
                                        'current_tobacco_use_flag' => '1',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '1',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 'not an mci');
        $expectedData = array('Review' => array('mci' => Review::NOT, 
                                                'ci' => 1));
        $this->checkArrayDiff($data, $expectedData, 
            'not an mci, but fields not unset');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 0,
                                        'ci' => null,
                                        'type' => null,
                                        'secondary_cause' => null, 
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::NO_CRITERIA);
        $this->checkArrayDiff($result, $expected, 'no criteria');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 1,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 0,
                                        'ci' => null,
                                        'type' => null,
                                        'secondary_cause' => null, 
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::NO_CE_CRITERIA);
        $this->checkArrayDiff($result, $expected, 
                              'no cardiac enzyme criteria');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 1,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 0,
                                        'ci' => null,
                                        'type' => null,
                                        'secondary_cause' => null, 
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::TYPE_BLANK);
        $this->checkArrayDiff($result, $expected, 'null type');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 1,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 0,
                                        'ci' => null,
                                        'type' => Review::PRIMARY,
                                        'secondary_cause' => 'bb',
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '1',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '1'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 'primary cause');
        $expectedData = array('Review' => 
            array('mci' => Review::DEFINITE,
                  'abnormal_ce_values_flag' => 0,
                  'chest_pain_flag' => 1,
                  'ecg_changes_flag' => 0,
                  'lvm_by_imaging_flag' => 0,
                  'type' => Review::PRIMARY,
                  'false_positive_flag' => 1,
                  'false_positive_reason' => Review::SSS,
                  'current_tobacco_use_flag' => '0',
                  'past_tobacco_use_flag' => '1',
                  'cocaine_use_flag' => '0',
                  'family_history_flag' => '1'));
        $this->checkArrayDiff($data, $expectedData, 
            'primary cause, but fields not unset');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 1,
                                        'lvm_by_imaging_flag' => 0,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => null, 
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::SC_BLANK);
        $this->checkArrayDiff($result, $expected, 'null secondary cause');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 1,
                                        'lvm_by_imaging_flag' => 0,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::MVA,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '1',
                                        'cocaine_use_flag' => '1',
                                        'family_history_flag' => '1'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 'other_cause should be unset');
        $expectedData = array('Review' => 
            array('mci' => Review::DEFINITE,
                  'abnormal_ce_values_flag' => 0,
                  'chest_pain_flag' => 0,
                  'ecg_changes_flag' => 1,
                  'lvm_by_imaging_flag' => 0,
                  'type' => Review::SECONDARY,
                  'secondary_cause' => Review::MVA,
                  'false_positive_flag' => 1,
                  'false_positive_reason' => Review::SSS,
                  'current_tobacco_use_flag' => '0',
                  'past_tobacco_use_flag' => '1',
                  'cocaine_use_flag' => '1',
                  'family_history_flag' => '1'));
        $this->checkArrayDiff($data, $expectedData, 
            'other_cause not unset');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => null, 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::OC_BLANK);
        $this->checkArrayDiff($result, $expected, 'null other cause');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 1,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => '<b>xx</b>', 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 'tags stripped');
        $expectedData = array('Review' => 
            array('mci' => Review::DEFINITE,
                  'abnormal_ce_values_flag' => 0,
                  'chest_pain_flag' => 0,
                  'ecg_changes_flag' => 1,
                  'lvm_by_imaging_flag' => 1,
                  'type' => Review::SECONDARY,
                  'secondary_cause' => Review::OTHER,
                  'false_positive_flag' => 1,
                  'false_positive_reason' => Review::SSS,
                  'other_cause' => 'xx', 
                  'current_tobacco_use_flag' => '0',
                  'past_tobacco_use_flag' => '0',
                  'cocaine_use_flag' => '0',
                  'family_history_flag' => '0'));
        $this->checkArrayDiff($data, $expectedData, 
            'Trying to strip tags');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 1,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'xx', 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 'tags stripped');
        $this->checkArrayDiff($data, $expectedData, 
            'All fields set correctly');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => null,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::FALSE_POSITIVE_REASON_BLANK);
        $this->checkArrayDiff($result, $expected, 'null false positive reason');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 1,
                                        'lvm_by_imaging_flag' => 0,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::MVA,
                                        'false_positive_flag' => 0,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '1',
                                        'cocaine_use_flag' => '1',
                                        'family_history_flag' => '1'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 
                              'false_positive_reason should be unset');
        $expectedData = array('Review' => 
            array('mci' => Review::DEFINITE,
                  'abnormal_ce_values_flag' => 0,
                  'chest_pain_flag' => 0,
                  'ecg_changes_flag' => 1,
                  'lvm_by_imaging_flag' => 0,
                  'type' => Review::SECONDARY,
                  'secondary_cause' => Review::MVA,
                  'false_positive_flag' => 0,
                  'current_tobacco_use_flag' => '0',
                  'past_tobacco_use_flag' => '1',
                  'cocaine_use_flag' => '1',
                  'family_history_flag' => '1'));
        $this->checkArrayDiff($data, $expectedData, 
            'false_positive_reason not unset');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::CURRENT_TOBACCO_USE_BLANK);
        $this->checkArrayDiff($result, $expected, 'null current tobacco use');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::PAST_TOBACCO_USE_BLANK);
        $this->checkArrayDiff($result, $expected, 'null past tobacco use');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::COCAINE_USE_BLANK);
        $this->checkArrayDiff($result, $expected, 'null cocaine use');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '0',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => ''));
        $result = $this->Event->checkReviewData($data);
        $expected = array(false, Event::FAMILY_HISTORY_BLANK);
        $this->checkArrayDiff($result, $expected, 'null family history');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '1',
                                        'past_tobacco_use_flag' => '',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 
                              'null past tobacco use should be okay');

        $data = array('Review' => array('mci' => Review::DEFINITE, 
                                        'abnormal_ce_values_flag' => 0,
                                        'ce_criteria' => null,
                                        'chest_pain_flag' => 0,
                                        'ecg_changes_flag' => 0,
                                        'lvm_by_imaging_flag' => 1,
                                        'ci' => null,
                                        'type' => Review::SECONDARY,
                                        'secondary_cause' => Review::OTHER,
                                        'false_positive_flag' => 1,
                                        'false_positive_reason' => Review::SSS,
                                        'other_cause' => 'cc', 
                                        'current_tobacco_use_flag' => '1',
                                        'past_tobacco_use_flag' => '0',
                                        'cocaine_use_flag' => '0',
                                        'family_history_flag' => '0'));
        $result = $this->Event->checkReviewData($data);
        $expected = array(true);
        $this->checkArrayDiff($result, $expected, 
                              'past tobacco use should be unset');

        $expectedData = array('Review' => 
            array('mci' => Review::DEFINITE, 
                  'abnormal_ce_values_flag' => 0,
                  'chest_pain_flag' => 0,
                  'ecg_changes_flag' => 0,
                  'lvm_by_imaging_flag' => 1,
                  'type' => Review::SECONDARY,
                  'secondary_cause' => Review::OTHER,
                  'false_positive_flag' => 1,
                  'false_positive_reason' => Review::SSS,
                  'other_cause' => 'cc', 
                  'current_tobacco_use_flag' => '1',
                  'cocaine_use_flag' => '0',
                  'family_history_flag' => '0'));
        $this->checkArrayDiff($data, $expectedData, 
            'past_tobacco_use not unset');
    }

    /** Id to use on all beforeSave calls */
    const AUTH_ID = 23;
   
    /**
     * Perform a test on the beforeSave function
     * @param action Action supposedly being performed
     * @param data Data for model
     * @param postData Data we expect after the call; if false, we expect 
     *    beforeSave will return false;
     * @param dataMessage message to print if data doesn't match
     * @param whitelist Whitelist before call
     * @param postWhitelist Whitelist after call
     * @param whitelistMessage message to print if whitelist doesn't match
     * @param authId Id of the authorized user (optional)
     */
    private function checkBeforeSave($action, $data, $postData, $dataMessage,
                                     $whitelist, $postWhitelist, 
                                     $whitelistMessage, $authId = self::AUTH_ID)
    {
        $this->Event->data = $data;
        $this->Event->setVars($action, $authId);
        $this->Event->whitelist = $whitelist;
 
        if ($postData === false) {
            $this->assertEqual(
                $this->CodedItemBehavior->beforeSave($this->Event, null), 
                false);
        } else {
            $this->assertEqual(
                $this->CodedItemBehavior->beforeSave($this->Event, null), true);
            $this->checkArrayDiff($postData['Event'], 
                                  $this->Event->data['Event'], 
                                  $dataMessage);
            $this->checkArrayDiff($postWhitelist, $this->Event->whitelist, 
                                  $whitelistMessage);
        }
    }

    const NOT_SEEN = 'This message should not be seen';

    function testBeforeSave() {
        $data = array('Event' => array('test' => 'whee'));

        // no change if data empty
        $this->checkBeforeSave('bogus', null, null, 'data changed when empty', 
                               null, null, 'whitelist changed when data empty');
        // action not in array
        $this->checkBeforeSave('bogus', $data, $data, 
                               'data changed on bogus action', 
                               null, null, 'whitelist changed on bogus action');

        // action in array, but no whitelist
        $expectedData = array('Event' => array('test' => 'whee', 
            'status' => Event::UPLOADED, 'upload_date' => date('Y-m-d'), 
            'uploader_id' => self::AUTH_ID));
        $this->checkBeforeSave('upload', $data, $expectedData, 
                               'data not changed as expected', 
                               null, null, 
                               'whitelist changed when whitelist empty');

        // action in array, whitelist
        $expectedWhitelist = array('test', 'status', 'upload_date', 
                                   'uploader_id');
        $this->checkBeforeSave('upload', $data, $expectedData, 
                               'Data not changed as expected (with whitelist)',
                               array('test'), $expectedWhitelist, 
                               'Whitelist not changed as expected');

        // action in array, whitelist, default new status, empty id
        $this->checkBeforeSave('assign', $data, false, self::NOT_SEEN,
            null, null, self::NOT_SEEN);

        // action in array, whitelist, default new status, id OK
        $dataWithId = array('Event' => array('id' => 19, 'test' => 'whee'));
        $expectedData = array('Event' => array('id' => 19, 'test' => 'whee', 
            'status' => Event::ASSIGNED, 'assign_date' => date('Y-m-d'), 
            'assigner_id' => self::AUTH_ID));
        $expectedWhitelist = array('test', 'status', 'assign_date', 
                                   'assigner_id');
        $this->checkBeforeSave('assign', $dataWithId, $expectedData, 
            'Data not changed as expected (assign)',
            array('test'), $expectedWhitelist, 
            'Whitelist not changed as expected (assign)');

        /* action in array, new status null */
        $dataForReview1 = array('Event' => array('id' => 2));
        $this->checkBeforeSave('review1', $dataForReview1, $dataForReview1,
            'Data changed when it should not have been (review1)',
            array('id'), array('id'),
            'Whitelist changed when it should not be (review1)');

        // action in array, whitelist, default new status, actor not changed
        $dataForReview3 = array('Event' => array('id' => 11));
        $expectedData = array('Event' => array('id' => 11,
            'status' => Event::DONE, 'review3_date' => date('Y-m-d')));
        $expectedWhitelist = array('test', 'status', 'review3_date');
        $this->checkBeforeSave('review3', $dataForReview3, $expectedData, 
            'Data not changed as expected (review3)',
            array('test'), $expectedWhitelist, 
            'Whitelist not changed as expected (review3)');

        // action in array, new status name bogus
        $dataWithBogusStatus = array('Event' => array('id' => 10));
        $this->checkBeforeSave('assign', $dataWithBogusStatus, false,
            self::NOT_SEEN, null, null, self::NOT_SEEN);
    }

    function testShouldUpdateStatus() {
        // action not in array
        $authId = self::AUTH_ID;
        $this->Event->setVars('bogus', $authId);
        $this->assertEqual(
            $this->CodedItemBehavior->shouldUpdateStatus($this->Event), false, 
            'bogus action updates status');
        // in array, changes status
        $this->Event->setVars('upload', $authId);
        $this->assertEqual(
            $this->CodedItemBehavior->shouldUpdateStatus($this->Event), true, 
            "upload doesn't update status");
        // in array, newStatus null
        $this->Event->setVars('review1', $authId);
        $this->assertEqual(
            $this->CodedItemBehavior->shouldUpdateStatus($this->Event), false, 
            "null new status, but should update status");
    }

    function testInsureSave() {
        $oldEvent = $this->Event->findById(5);
        $data = array('Event' => array('event_date' => '1999-12-19'));

        // action not in array
        $authId = self::AUTH_ID;
        $this->Event->setVars('bogus', $authId);
        $this->CodedItemBehavior->insureSave($this->Event, $data);
        $this->checkArrayDiff($oldEvent,
                              $this->Event->findById(5),
                              'bogus status, but insureSave changed event');

        // beforeSave already called
        $this->Event->data = null;
        $this->Event->setVars('upload', $authId);
        $this->CodedItemBehavior->beforeSave($this->Event, null);
        $this->CodedItemBehavior->insureSave($this->Event, $data);
        $this->checkArrayDiff($oldEvent, $this->Event->findById(5),
            'beforeSave called, but insureSave changed event');

        // call it
        $authId = self::AUTH_ID;
        $this->Event->setVars('upload', $authId);
        $this->CodedItemBehavior->insureSave($this->Event, $oldEvent);
        $oldEvent['Event']['uploader_id'] = self::AUTH_ID;
        $oldEvent['Event']['upload_date'] = date('Y-m-d');
        $oldEvent['Event']['status'] = Event::UPLOADED; 
        $this->checkArrayDiff($oldEvent,
                              $this->Event->findById(5),
                              'event should have changed');
    }

    // need dummy files set up for this in app/chartUploads
    function testFindSuffix() {
        $this->assertEqual($this->Event->findSuffix("test1.dontdelete"), 'gz');
        $this->assertEqual($this->Event->findSuffix("test2.dontdelete"), 
                           'pdf');
        $this->assertEqual($this->Event->findSuffix("test3.dontdelete"), 
                           'zip');
        // this file should *not* exist
        $this->assertNull($this->Event->findSuffix("test4.dontdelete"));
    }

    function testCanBeScrubbed() {
        $user1 = array('User' => array('id' => 1,
                                       'uploader_flag' => 1, 
                                       'admin_flag' => 1,
                                       'reviewer_flag' => 1,
                                       'site' => 'UW'));
        $user2 = array('User' => array('uploader_flag' => 0, 
                                       'admin_flag' => 0,
                                       'reviewer_flag' => 0,
                                       'site' => 'UW'));
        $event1 = array('Event' => array('status' => Event::CREATED),
                        'Patient' => array('site' => 'UW'));
        $event2 = array('Event' => array('status' => Event::UPLOADED),
                        'Patient' => array('site' => 'UNC'));
        $event3 = array('Event' => array('status' => Event::SCRUBBED),
                        'Patient' => array('site' => 'UNC'));
        $event4 = array('Event' => array('status' => Event::SCREENED),
                        'Patient' => array('site' => 'UNC'));

        $this->assertEqual($this->Event->canBeScrubbed($event1, $user1), false);
        $this->assertEqual($this->Event->canBeScrubbed($event2, $user1), true);
        $this->assertEqual($this->Event->canBeScrubbed($event3, $user1), true);
        $this->assertEqual($this->Event->canBeScrubbed($event4, $user1), false);
        $this->assertEqual($this->Event->canBeScrubbed($event2, $user2), false);
    }
    
    function testToBeReviewed() {
        $user1 = array('User' => array('id' => 1,
                                       'reviewer_flag' => 1));
        $user2 = array('User' => array('id' => 2,
                                       'reviewer_flag' => 1));
        $user3 = array('User' => array('id' => 3,
                                       'reviewer_flag' => 3));
        $event1 = array('Event' => array('status' => Event::CREATED));
        $event2 = array('Event' => array('status' => Event::SENT,
                                         'reviewer1_id' => 1,
                                         'reviewer2_id' => 2));
        $event3 = array('Event' => 
                             array('status' => Event::THIRD_REVIEW_ASSIGNED,
                                   'reviewer3_id' => 3));

        $this->assertEqual($this->Event->toBeReviewed($event1, $user1), false);
        $this->assertEqual($this->Event->toBeReviewed($event2, $user1), true);
        $this->assertEqual($this->Event->toBeReviewed($event2, $user2), true);
        $this->assertEqual($this->Event->toBeReviewed($event2, $user3), false);
        $this->assertEqual($this->Event->toBeReviewed($event3, $user3), true);
        $this->assertEqual($this->Event->toBeReviewed($event3, $user1), false);
    }

    function testHasChart() {
        $event1 = array('Event' => array('status' => Event::CREATED));
        $event2 = array('Event' => array('status' => Event::SENT));
        $this->assertEqual($this->Event->hasChart($event1), false);
        $this->assertEqual($this->Event->hasChart($event2), true);
    }

    function testGetStatusNames() {
        $this->checkArrayDiff($this->Event->getStatusNames(),
            array(
                Event::CREATED, Event::UPLOADED, Event::SCRUBBED, 
                Event::SCREENED,
                Event::ASSIGNED, 
                Event::SENT, Event::REVIEWER1_DONE, Event::REVIEWER2_DONE,
                Event::THIRD_REVIEW_NEEDED, Event::THIRD_REVIEW_ASSIGNED,
                Event::DONE, Event::REJECTED, Event::NO_PACKET_AVAILABLE),
            "Status arrays don't match");
    }

    function testGetNoPacketReasons() {
        $this->checkArrayDiff($this->Event->getNoPacketReasons(),
            array(
                Event::OUTSIDE_HOSPITAL => Event::OUTSIDE_HOSPITAL, 
                Event::ASCERTAINMENT_DIAGNOSIS_ERROR =>
                    Event::ASCERTAINMENT_DIAGNOSIS_ERROR, 
                Event::ASCERTAINMENT_PRIOR_EVENT =>
                    Event::ASCERTAINMENT_PRIOR_EVENT, 
                Event::OTHER => Event::OTHER),
            "No packet reasons don't match");
    }
}

?>
