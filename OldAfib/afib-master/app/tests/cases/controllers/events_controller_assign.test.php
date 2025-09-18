<?php

require_once "my_controller_test_case.php";

class EventsControllerAssignTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
    }   

    function testAssignManyNoData() {
        $result = $this->testAction('/events/assignMany',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Assign charts.*?events\/assignMany.*?MI Number.*?Event date.*?Screened.*?Reviewer 1.*?Reviewer 2.*?Assign.*?1019.*?2009-02-28.*?2010-02-26.*?None.*?None.*?checkbox.*?1020.*?user3@example.com.*?None.*?checkbox.*?1021.*?None.*?gsbarnes@washington.edu.*?checkbox.*?Choose reviewer:.*?gsbarnes@washington.edu.*?user3@example.com.*?Assign/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $result = $this->testAction('/events/assignMany',
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
        $this->assertTrue(empty($result['thirdReviewers']));
    }

    function testAssignManyNoReviewer() {
        $data = array('Event' => array('assign19' => true));
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, Event::NO_REVIEWER) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testAssignManyBadReviewer() {
        $data = array('Event' => array('assign19' => true), 
                      'Assign' => array('reviewer_id' => 3));
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, Event::BAD_REVIEWER) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testAssignManyBadStatus() {
        $data = array('Event' => array('assign15' => true), 
                      'Assign' => array('reviewer_id' => 1));
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) cannot be assigned at this time: 15') !== false, 
            'missing error message');
    }

    function testAssignManyNoSuchEvent() {
        $data = array('Event' => array('assign100' => true), 
                      'Assign' => array('reviewer_id' => 1));
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) not found: 100') !== false, 
            'missing error message');
    }

    function testAssignManySameReviewer() {
        $data = array('Event' => array('assign20' => true),
                      'Assign' => array('reviewer_id' => 2));
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) cannot be assigned to the selected reviewer: 20') !== 
                false, 
            'missing error message');
    }

    function testAssignManyBadData() {
        $event15 = $this->Event->findById(15);
        $event19 = $this->Event->findById(19);
        $event20 = $this->Event->findById(20);
        $data = array('Event' => array('assign20' => true,
                                       'assign100' => true,
                                       'assign15' => true,
                                       'assign19' => true,
                                       'bogus' => true,
                                       'assign500' => false),
                      'Assign' => array('reviewer_id' => 2));
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $expected = '/1 event\(s\) assigned.*?1 event\(s\) not found: 100.*?1 event\(s\) cannot be assigned at this time: 15.*?1 event\(s\) cannot be assigned to the selected reviewer: 20/sm';
        $this->assertTrue(preg_match($expected, $result) == 1,
                          'Pattern does not appear in result');
        $event15new = $this->Event->findById(15);
        $event19new = $this->Event->findById(19);
        $event20new = $this->Event->findById(20);
        
        // no other fields change if this is the first reviewer
        $event19['Event']['reviewer1_id'] = 2;

        $this->assertContainsFields($event19new['Event'], $event19['Event'], 
                                    "Event fields don't match (19)");
        $this->assertContainsFields($event15new['Event'], $event15['Event'], 
                                    "Event fields don't match (15)");
        $this->assertContainsFields($event20new['Event'], $event20['Event'], 
                                    "Event fields don't match (20)");
    }

    // tests 0 and 1 assignments
    function testAssignMany() {
        $event19 = $this->Event->findById(19);

        $data = array('Assign' => array('reviewer_id' => 1));
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, '0 event(s) assigned'));
        $this->noErrors($result);

        $data['Event'] = array('assign19' => true);
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, '1 event(s) assigned'));
        $this->noErrors($result);
        $event19new = $this->Event->findById(19);

        // no other fields change if this is the first reviewer
        $event19['Event']['reviewer1_id'] = 1;
        $this->assertContainsFields($event19new['Event'], $event19['Event'], 
                                    "Event fields don't match (19)");
    }

    // tests 2 assignments
    function testAssignMany2() {
        $event19 = $this->Event->findById(19);
        $event20 = $this->Event->findById(20);

        $data = array('Event' => array('assign19' => true,
                                       'assign20' => true),
                      'Assign' => array('reviewer_id' => 1));
        $result = $this->testAction('/events/assignMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, '2 event(s) assigned'));
        $this->noErrors($result);
        $event19new = $this->Event->findById(19);
        $event20new = $this->Event->findById(20);

        // no other fields change if this is the first reviewer
        $event19['Event']['reviewer1_id'] = 1;
        $this->assertContainsFields($event19new['Event'], $event19['Event'], 
                                    "Event fields don't match (19)");

        $event20['Event']['assign_date'] = date('Y-m-d');
        $event20['Event']['assigner_id'] = 1;
        $event20['Event']['status'] = Event::ASSIGNED;
        $event20['Event']['reviewer2_id'] = 1;

        $this->assertContainsFields($event20new['Event'], $event20['Event'], 
                                    "Event fields don't match (20)");
    }
}

?>
