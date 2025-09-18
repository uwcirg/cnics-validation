<?php

require_once "my_test_case.php";

App::import('Model', 'Event', 'Review', 'AppModel', 'CodedItemBehavior');

class EventAssignTestCase extends MyTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');
    private $now;

    function start() {
        parent::start();
        $this->Event =& ClassRegistry::init('Event');
        $this->CodedItemBehavior =& ClassRegistry::init('CodedItemBehavior');
        $this->now = date('Y-m-d');
    }

    function testAssignAll() {
        // no data
        $result = $this->Event->assignAll(null, null, null, false);
        $expected = array('error' => Event::NO_REVIEWER);
        $this->assertContainsFields($expected, $result);

        // bad reviewer id
        $data = array('Assign' => array('reviewer_id' => 25));
        $result = $this->Event->assignAll($data, null, null, false);
        $expected = array('error' => 'User 25' . Event::BAD_REVIEWER);
        $this->assertContainsFields($expected, $result);

        // no events
        $reviewers = array(1 => 'gsbarnes@washington.edu', 
                           2 => 'user@example.com');
        $data = array('Assign' => array('reviewer_id' => 2),
                      'Event' => array());
        $result = $this->Event->assignAll($data, $reviewers, null, false);
        $expected = array('error' => null,
                          'assigned' => 0,
                          'notFound' => 0,
                          'cannotAssign' => 0,
                          'cannotAssignReviewer' => 0,
                          'notFoundList' => '',
                          'cannotAssignList' => '',
                          'cannotAssignReviewerList' => '');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for no events');

        /* non-existant, non-assignable events, plus one event that 
           was already assigned to the reviewer in question */
        $data = array('Assign' => array('reviewer_id' => 2),
                      'Event' => array('assign100' => true,
                                       'assign99' => true,
                                       'assign15' => true,
                                       'assign16' => true,
                                       'assign20' => true));
        $result = $this->Event->assignAll($data, $reviewers, null, false);
        $expected = array('error' => null,
                          'assigned' => 0,
                          'notFound' => 2,
                          'cannotAssign' => 2,
                          'cannotAssignReviewer' => 1,
                          'notFoundList' => ' 100 99',
                          'cannotAssignList' => ' 15 16',
                          'cannotAssignReviewerList' => '20');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for bad events');

        // bad authuser
        $data = array('Assign' => array('reviewer_id' => 2),
                      'Event' => array('assign19' => true));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 0));
        $result = $this->Event->assignAll($data, $reviewers, $user1, false);
        $expected = array('error' => 'You cannot assign reviewers');
        $this->assertContainsFields($expected, $result);

        /* bad events as above, plus 2 that should work and some nonsense 
           variables */
        $event19 = $this->Event->findById(19);
        $event21 = $this->Event->findById(21);
        $data = array('Assign' => array('reviewer_id' => 2),
                      'Event' => array('assign100' => true,
                                       'assign99' => true,
                                       'assign15' => true,
                                       'assign16' => true,
                                       'assign20' => true,
                                       'assign19' => true,
                                       'assign21' => true,
                                       'bogus' => true,
                                       'assign500' => false));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 1));
        $result = $this->Event->assignAll($data, $reviewers, $user1, false);
        $expected = array('error' => null,
                          'assigned' => 2,
                          'notFound' => 2,
                          'cannotAssign' => 2,
                          'cannotAssignReviewer' => 1,
                          'notFoundList' => ' 100 99',
                          'cannotAssignList' => ' 15 16',
                          'cannotAssignReviewerList' => '20');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for bad/good events');
        $newEvent = $this->Event->findById(19);
        $event19['Event']['reviewer1_id'] = 2;
        $this->checkArrayDiff($event19['Event'], $newEvent['Event'], 
             'First assignment (19) does not change as expected');
        $newEvent = $this->Event->findById(21);
        $event21['Event']['reviewer1_id'] = 2;
        $this->checkArrayDiff($event21['Event'], $newEvent['Event'], 
             'First assignment (21) does not change as expected');

        // do it again to get 2nd reviewer assigned
        $event19 = $this->Event->findById(19);
        $event21 = $this->Event->findById(21);
        $data = array('Assign' => array('reviewer_id' => 1),
                      'Event' => array('assign19' => true,
                                       'assign21' => true));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 1));
        $result = $this->Event->assignAll($data, $reviewers, $user1, false);
        $expected = array('error' => null,
                          'assigned' => 2,
                          'notFound' => 0,
                          'cannotAssign' => 0,
                          'cannotAssignReviewer' => 0,
                          'notFoundList' => '',
                          'cannotAssignList' => '',
                          'cannotAssignReviewerList' => '');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for good events');
        $newEvent = $this->Event->findById(19);
        $event19['Event']['status'] = Event::ASSIGNED;
        $event19['Event']['assigner_id'] = 5;
        $event19['Event']['assign_date'] = date('Y-m-d');
        $event19['Event']['reviewer2_id'] = 1;
        $this->checkArrayDiff($event19['Event'], $newEvent['Event'], 
             'Second assignment (19) does not change as expected');
        $newEvent = $this->Event->findById(21);
        $event21['Event']['status'] = Event::ASSIGNED;
        $event21['Event']['assigner_id'] = 5;
        $event21['Event']['assign_date'] = date('Y-m-d');
        $event21['Event']['reviewer2_id'] = 1;
        $this->checkArrayDiff($event21['Event'], $newEvent['Event'], 
             'Second assignment (21) does not change as expected');
    }

    function testAssign() {
        $event19 = $this->Event->findById(19);
        $event20 = $this->Event->findById(20);

        $this->Event->assign($event19, 1, 2, false);
        $newEvent = $this->Event->findById(19);
        $event19['Event']['reviewer1_id'] = 1;
        $this->checkArrayDiff($event19['Event'], $newEvent['Event'], 
                              'First assignment does not change as expected');

        $this->Event->assign($event20, 1, 3, false);
        $newEvent = $this->Event->findById(20);
        $event20['Event']['status'] = Event::ASSIGNED;
        $event20['Event']['assign_date'] = date('Y-m-d');
        $event20['Event']['assigner_id'] = 3;
        $event20['Event']['reviewer2_id'] = 1;
        $this->checkArrayDiff($event20['Event'], $newEvent['Event'], 
                              'Second assignment does not change as expected');
    }

    function testToBeAssigned() {
        $event1 = array('Event' => array('status' => Event::CREATED));
        $event2 = array('Event' => array('status' => Event::ASSIGNED));
        $event3 = array('Event' => array('status' => Event::SCREENED));

        $this->assertEqual($this->Event->toBeAssigned($event1, false), false);
        $this->assertEqual($this->Event->toBeAssigned($event2, false), false);
        $this->assertEqual($this->Event->toBeAssigned($event3, false), true);
    }

    function testCanBeAssigned() {
        $event1 = array('Event' => array('reviewer1_id' => null));
        $event2 = array('Event' => array('reviewer1_id' => 1));
        $event3 = array('Event' => array('reviewer1_id' => 2));

        $this->assertEqual($this->Event->canBeAssigned($event1, 1, false), 
                           true);
        $this->assertEqual($this->Event->canBeAssigned($event2, 1, false), 
                           false);
        $this->assertEqual($this->Event->canBeAssigned($event3, 1, false), 
                           true);
    }

    function testCanAssign() {
        $user1 = array('User' => array('id' => 1,
                                       'admin_flag' => 1));
        $user2 = array('User' => array('id' => 1,
                                       'admin_flag' => 0));
        $event1 = array('Event' => array('status' => Event::SCREENED));

        $this->assertEqual($this->Event->canAssign($event1, $user1), true);
        $this->assertEqual($this->Event->canAssign($event1, $user2), false);
    }
}

?>
