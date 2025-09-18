<?php

require_once "my_test_case.php";

App::import('Model', 'Event', 'Review', 'AppModel', 'CodedItemBehavior');

class EventScreenTestCase extends MyTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');
    private $now;

    function start() {
        parent::start();
        $this->Event =& ClassRegistry::init('Event');
        $this->CodedItemBehavior =& ClassRegistry::init('CodedItemBehavior');
        $this->now = date('Y-m-d');
    }

    function testScreen() {
        $message = 'blah';
        $dataEventBad = array('screenAccept' => 'boo');
        $dataEventAccept = array('screenAccept' => Event::ACCEPT, 
                                 'message' => 'should not matter');
        $dataEventReject = array('screenAccept' => Event::REJECT,
                                 'message' => $message);
        $event15 = $this->Event->findById(15);
        $event16 = $this->Event->findById(16);

        // test bad screen accepts
        $result = $this->Event->screen(null, null, 1);
        $expected = array('success' => false, 
                          'message' => Event::BAD_SCREEN_ACCEPT);
        $this->checkArrayDiff($result, $expected, 
                              'Null screenAccept gets wrong result');
        $result = $this->Event->screen(null, $dataEventBad, 1);
        $this->checkArrayDiff($result, $expected, 
                              'Bad screenAccept gets wrong result');

        $result = $this->Event->screen($event15, $dataEventAccept, 3);
        $expected = array('success' => true, 
                          'message' => 'Screened event MI 1015 (Accepted)');
        $this->checkArrayDiff($result, $expected, 
                              'accept gets wrong result');
        $newEvent = $this->Event->findById(15);
        $event15['Event']['status'] = Event::SCREENED;
        $event15['Event']['screen_date'] = date('Y-m-d');
        $event15['Event']['screener_id'] = 3;
        $this->checkArrayDiff($event15['Event'], $newEvent['Event'], 
                              'Accepted event not changed as expected');

        $result = $this->Event->screen($event16, $dataEventReject, 3);
        $expected = array('success' => true, 
                          'message' => 'Screened event MI 1016 (Rejected)');
        $this->checkArrayDiff($result, $expected, 
                              'reject gets wrong result');
        $newEvent = $this->Event->findById(16);
        $event16['Event']['status'] = Event::REJECTED;
        $event16['Event']['screen_date'] = date('Y-m-d');
        $event16['Event']['screener_id'] = 3;
        $event16['Event']['reject_message'] = $message;
        $this->checkArrayDiff($event16['Event'], $newEvent['Event'], 
                              'Rejected event not changed as expected');
    }

    function testScreenRescrub() {
        $message = 'try again';
        $dataEventRescrub = array('screenAccept' => Event::RESCRUB,
                                  'message' => $message);
        $event16 = $this->Event->findById(16);

        $result = $this->Event->screen($event16, $dataEventRescrub, 3);
        $expected = array('success' => true, 
            'message' => 'Screened event MI 1016 (Needs Rescrubbing)');
        $this->checkArrayDiff($result, $expected, 
                              'rescrub gets wrong result');
        $newEvent = $this->Event->findById(16);
        $event16['Event']['status'] = Event::UPLOADED;
        $event16['Event']['screen_date'] = date('Y-m-d');
        $event16['Event']['screener_id'] = 3;
        $event16['Event']['rescrub_message'] = $message;
        $this->checkArrayDiff($event16['Event'], $newEvent['Event'], 
                              'Rescrub event not changed as expected');
    }

    function testToBeScreened() {
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
        $event2 = array('Event' => array('status' => Event::SCRUBBED),
                        'Patient' => array('site' => 'UNC'));
        $event3 = array('Event' => array('status' => Event::SCREENED),
                        'Patient' => array('site' => 'UNC'));

        $this->assertEqual($this->Event->toBeScreened($event1, $user1), false);
        $this->assertEqual($this->Event->toBeScreened($event2, $user1), true);
        $this->assertEqual($this->Event->toBeScreened($event3, $user1), false);
        $this->assertEqual($this->Event->toBeScreened($event2, $user2), false);
    }
}

?>
