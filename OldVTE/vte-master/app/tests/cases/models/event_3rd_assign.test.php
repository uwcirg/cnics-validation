<?php

require_once "my_test_case.php";

App::import('Model', 'Event', 'Review', 'AppModel', 'CodedItemBehavior');

class Event3rdAssignTestCase extends MyTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');
    private $now;

    function start() {
        parent::start();
        $this->Event =& ClassRegistry::init('Event');
        $this->CodedItemBehavior =& ClassRegistry::init('CodedItemBehavior');
        $this->now = date('Y-m-d');
    }

    function testAssignAllEmailFail() {
        $reviewers = array(1 => 'gsbarnes@washington.edu', 
                           8 => 'user2@example.');
        /* bad events as above, plus 2 that should work and some nonsense 
           variables, all with a reviewer with a bad e-mail address */
        $event30 = $this->Event->findById(30);
        $event31 = $this->Event->findById(31);
        $data = array('Assign' => array('reviewer_id' => 8),
                      'Event' => array('assign100' => true,
                                       'assign99' => true,
                                       'assign15' => true,
                                       'assign16' => true,
                                       'assign11' => true,
                                       'assign30' => true,
                                       'assign31' => true,
                                       'assign32' => true,
                                       'bogus' => true,
                                       'assign500' => false));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 1));
        $result = $this->Event->assignAll($data, $reviewers, $user1, true);
        $expected = array('error' => null,
                          'assigned' => 0,
                          'notFound' => 2,
                          'cannotAssign' => 3,
                          'cannotAssignReviewer' => 1,
                          'notFoundList' => ' 100 99',
                          'cannotAssignList' => ' 15 16 11',
                          'cannotAssignReviewerList' => ' 32',
                          'emailFailed' => 2,
                          'emailFailedList' => ' 30 31');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for email failure');
        $newEvent = $this->Event->findById(30);
        $this->checkArrayDiff($event30['Event'], $newEvent['Event'], 
             '30 changed when email failed');
        $newEvent = $this->Event->findById(31);
        $this->checkArrayDiff($event31['Event'], $newEvent['Event'], 
             '31 changed when email failed');
    }

    function testAssignAll() {
        // no data
        $result = $this->Event->assignAll(null, null, null, true);
        $expected = array('error' => Event::NO_REVIEWER);
        $this->assertContainsFields($expected, $result);

        // bad reviewer id
        $data = array('Assign' => array('reviewer_id' => 25));
        $result = $this->Event->assignAll($data, null, null, true);
        $expected = array('error' => 'User 25' . Event::BAD_REVIEWER);
        $this->assertContainsFields($expected, $result);

        // reviewer, but not 3rd reviewer
        $data = array('Assign' => array('reviewer_id' => 4));
        $result = $this->Event->assignAll($data, null, null, true);
        $expected = array('error' => 'User 4' . Event::BAD_REVIEWER);
        $this->assertContainsFields($expected, $result);

        // no events
        $reviewers = array(1 => 'gsbarnes@washington.edu', 
                           2 => 'user@example.com',
                           8 => 'user2@example.com');
        $data = array('Assign' => array('reviewer_id' => 2),
                      'Event' => array());
        $result = $this->Event->assignAll($data, $reviewers, null, true);
        $expected = array('error' => null,
                          'assigned' => 0,
                          'notFound' => 0,
                          'cannotAssign' => 0,
                          'cannotAssignReviewer' => 0,
                          'emailFailed' => 0,
                          'notFoundList' => '',
                          'cannotAssignList' => '',
                          'cannotAssignReviewerList' => '',
                          'emailFailedList' => '');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for no events');

        /* non-existant, non-assignable events, plus one event that 
           was already assigned to the reviewer in question */
        $data = array('Assign' => array('reviewer_id' => 2),
                      'Event' => array('assign100' => true,
                                       'assign99' => true,
                                       'assign15' => true,
                                       'assign16' => true,
                                       'assign11' => true,
                                       'assign32' => true));
        $result = $this->Event->assignAll($data, $reviewers, null, true);
        $expected = array('error' => null,
                          'assigned' => 0,
                          'notFound' => 2,
                          'cannotAssign' => 3,
                          'cannotAssignReviewer' => 1,
                          'emailFailed' => 0,
                          'notFoundList' => ' 100 99',
                          'cannotAssignList' => ' 15 16 11',
                          'cannotAssignReviewerList' => ' 32',
                          'emailFailedList' => '');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for bad events');

        // bad authuser
        $data = array('Assign' => array('reviewer_id' => 2),
                      'Event' => array('assign30' => true));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 0));
        $result = $this->Event->assignAll($data, $reviewers, $user1, true);
        $expected = array('error' => 'You cannot assign reviewers');
        $this->assertContainsFields($expected, $result);

        /* bad events as above, plus 2 that should work and some nonsense 
           variables */
        $event30 = $this->Event->findById(30);
        $event31 = $this->Event->findById(31);
        $data = array('Assign' => array('reviewer_id' => 2),
                      'Event' => array('assign100' => true,
                                       'assign99' => true,
                                       'assign15' => true,
                                       'assign16' => true,
                                       'assign11' => true,
                                       'assign30' => true,
                                       'assign31' => true,
                                       'assign32' => true,
                                       'bogus' => true,
                                       'assign500' => false));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 1));
        $result = $this->Event->assignAll($data, $reviewers, $user1, true);
        $expected = array('error' => null,
                          'assigned' => 2,
                          'notFound' => 2,
                          'cannotAssign' => 3,
                          'cannotAssignReviewer' => 1,
                          'emailFailed' => 0,
                          'notFoundList' => ' 100 99',
                          'cannotAssignList' => ' 15 16 11',
                          'cannotAssignReviewerList' => ' 32',
                          'emailFailedList' => '');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for bad/good events');
        $newEvent = $this->Event->findById(30);
        $event30['Event']['status'] = Event::THIRD_REVIEW_ASSIGNED;
        $event30['Event']['assigner3rd_id'] = 5;
        $event30['Event']['assign3rd_date'] = date('Y-m-d');
        $event30['Event']['reviewer3_id'] = 2;
        $this->checkArrayDiff($event30['Event'], $newEvent['Event'], 
             'assignment (30) does not change as expected');
        $newEvent = $this->Event->findById(31);
        $event31['Event']['status'] = Event::THIRD_REVIEW_ASSIGNED;
        $event31['Event']['assigner3rd_id'] = 5;
        $event31['Event']['assign3rd_date'] = date('Y-m-d');
        $event31['Event']['reviewer3_id'] = 2;
        $this->checkArrayDiff($event31['Event'], $newEvent['Event'], 
             'assignment (31) does not change as expected');
    }

    function testAssign() {
        $event31 = $this->Event->findById(31);

        $this->Event->assign($event31, 1, 3, true);
        $newEvent = $this->Event->findById(31);
        $event31['Event']['status'] = Event::THIRD_REVIEW_ASSIGNED;
        $event31['Event']['assign3rd_date'] = date('Y-m-d');
        $event31['Event']['assigner3rd_id'] = 3;
        $event31['Event']['reviewer3_id'] = 1;
        $this->checkArrayDiff($event31['Event'], $newEvent['Event'], 
                              'assignment does not change as expected');
    }

    function testToBeAssigned() {
        $event1 = array('Event' => array('status' => Event::CREATED));
        $event2 = array('Event' => array('status' => 
                                         Event::THIRD_REVIEW_ASSIGNED));
        $event3 = array('Event' => array('status' => 
                                         Event::THIRD_REVIEW_NEEDED));

        $this->assertEqual($this->Event->toBeAssigned($event1, true), false);
        $this->assertEqual($this->Event->toBeAssigned($event2, true), false);
        $this->assertEqual($this->Event->toBeAssigned($event3, true), true);
    }

    function testCanBeAssigned() {
        $event1 = array('Event' => array('reviewer1_id' => null,
                                         'reviewer2_id' => null));
        $event2 = array('Event' => array('reviewer1_id' => 1));
        $event3 = array('Event' => array('reviewer1_id' => 2, 
                                         'reviewer2_id' => 1));
        $event4 = array('Event' => array('reviewer1_id' => 2, 
                                         'reviewer2_id' => 3));

        $this->assertEqual($this->Event->canBeAssigned($event1, 1, true), 
                           true);
        $this->assertEqual($this->Event->canBeAssigned($event2, 1, true), 
                           false);
        $this->assertEqual($this->Event->canBeAssigned($event3, 1, true), 
                           false);
        $this->assertEqual($this->Event->canBeAssigned($event4, 1, true), 
                           true);
    }
}

?>
