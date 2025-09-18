<?php

require_once "my_controller_test_case.php";

class EventsController3rdAssignTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
    }   

    function testAssignManyNoData() {
        $result = $this->testAction('/events/assign3rdMany',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Assign third reviewer.*?events\/assign3rdMany.*?MI Number.*?Event date.*?Last Review.*?Reviewer 1.*?Reviewer 2.*?Assign.*?1001.*?2009-02-10.*?2010-01-23.*?gsbarnes@washington.edu.*?user3@example.com.*?checkbox.*?1030.*?2009-03-11.*?2010-05-23.*?user2@example.com.*?gsbarnes@washington.edu.*?checkbox.*?1031.*?2009-03-12.*?2010-06-01.*?user2@example.com.*?gsbarnes@washington.edu.*?checkbox.*?1032.*?2009-03-13.*?2010-06-10.*?user3@example.com.*?checkbox.*?Choose reviewer:.*?gsbarnes@washington.edu.*?user@example.com.*?user2@example\..*?Assign/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $result = $this->testAction('/events/assign3rdMany',
            array('return' => 'vars'));
        $this->assertTrue(!empty($result['reviewers']));
        $this->assertEqual(count($result['reviewers']), 5, 
                           'Should be 5 reviewers');
        $expected = array(1 => 'gsbarnes@washington.edu (1)',
                          2 => 'user3@example.com (2)',
                          4 => 'someone@ (4)',
                          5 => 'user@com. (5)',
                          6 => 'user2@example.com (6)');
        $this->assertContainsFields($expected, $result['reviewers'], 
                                    "Reviewers don't match");

        $this->assertTrue(!empty($result['thirdReviewers']));
        $this->assertEqual(count($result['thirdReviewers']), 3, 
                           'Should be 3 thirdReviewers');
        $expected = array(1 => 'gsbarnes@washington.edu (1)',
                          3 => 'user@example.com (3)',
                          7 => 'user2@example. (7)');
        $this->assertContainsFields($expected, $result['thirdReviewers'], 
                                    "thirdReviewers don't match");
    }

    function testAssignManyNoReviewer() {
        $data = array('Event' => array('assign19' => true));
        $result = $this->testAction('/events/assign3rdMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, Event::NO_REVIEWER) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testAssignManyBadReviewer() {
        $data = array('Event' => array('assign19' => true), 
                      'Assign' => array('reviewer_id' => 2));
        $result = $this->testAction('/events/assign3rdMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, Event::BAD_REVIEWER) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testAssignManyBadStatus() {
        $data = array('Event' => array('assign15' => true), 
                      'Assign' => array('reviewer_id' => 1));
        $result = $this->testAction('/events/assign3rdMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) cannot be assigned at this time: 15') !== false, 
            'missing error message');
    }

    function testAssignManyNoSuchEvent() {
        $data = array('Event' => array('assign100' => true), 
                      'Assign' => array('reviewer_id' => 1));
        $result = $this->testAction('/events/assign3rdMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) not found: 100') !== false, 
            'missing error message');
    }

    function testAssignManySameReviewer() {
        $data = array('Event' => array('assign30' => true),
                      'Assign' => array('reviewer_id' => 1));
        $result = $this->testAction('/events/assign3rdMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) cannot be assigned to the selected reviewer: 30') !== 
                false, 
            'missing error message');
    }

    function testAssignManyBadEmail() {
        $data = array('Event' => array('assign30' => true),
                      'Assign' => array('reviewer_id' => 7));
        $result = $this->testAction('/events/assign3rdMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) email failed: 30') !== false, 
            'missing error message');
    }

    function testAssignManyBadData() {
        $event15 = $this->Event->findById(15);
        $event31 = $this->Event->findById(31);
        $event32 = $this->Event->findById(32);
        $data = array('Event' => array('assign32' => true,
                                       'assign100' => true,
                                       'assign15' => true,
                                       'assign31' => true,
                                       'bogus' => true,
                                       'assign500' => false),
                      'Assign' => array('reviewer_id' => 1));
        $result = $this->testAction('/events/assign3rdMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $expected = '/1 event\(s\) assigned.*?1 event\(s\) not found: 100.*?1 event\(s\) cannot be assigned at this time: 15.*?1 event\(s\) cannot be assigned to the selected reviewer: 31/sm';
        $this->assertTrue(preg_match($expected, $result) == 1,
                          'Pattern does not appear in result');
        $event15new = $this->Event->findById(15);
        $event31new = $this->Event->findById(31);
        $event32new = $this->Event->findById(32);

        $event32['Event']['reviewer3_id'] = 1;
        $event32['Event']['assign3rd_date'] = date('Y-m-d');
        $event32['Event']['assigner3rd_id'] = 1;
        $event32['Event']['status'] = Event::THIRD_REVIEW_ASSIGNED;

        $this->assertContainsFields($event31['Event'], $event31['Event'], 
                                    "Event fields don't match (31)");
        $this->assertContainsFields($event15new['Event'], $event15['Event'], 
                                    "Event fields don't match (15)");
        $this->assertContainsFields($event32new['Event'], $event32['Event'], 
                                    "Event fields don't match (32)");
    }
}

?>
