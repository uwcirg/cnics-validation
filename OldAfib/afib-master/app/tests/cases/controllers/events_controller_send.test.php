<?php

require_once "my_controller_test_case.php";

class EventsControllerSendTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
    }   

    function testSendManyNoData() {
        $result = $this->testAction('/events/sendMany',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Send charts.*?MI Number.*?Event date.*?Assigned.*?Reviewer 1.*?Reviewer 2.*?Send.*?1014.*?2009-02-23.*?2010-02-09.*?user2@example.com.*?gsbarnes@washington.edu.*?checkbox.*?1022.*?gsbarnes@washington.edu.*?user2@example.com.*?checkbox.*?1023.*?user2@example.com.*?gsbarnes@washington.edu.*?checkbox.*?1024.*?user2@example.com.*?user@com\..*?checkbox.*?1025.*?someone@.*?user@com\..*?checkbox.*?Send/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $result = $this->testAction('/events/sendMany',
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
    }

    function testSendManyBadStatus() {
        $data = array('Event' => array('send15' => true));
        $result = $this->testAction('/events/sendMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) cannot be sent at this time: 15') !== false, 
            'missing error message');
    }

    function testSendManyNoSuchEvent() {
        $data = array('Event' => array('send100' => true));
        $result = $this->testAction('/events/sendMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, 
            '1 event(s) not found: 100') !== false, 
            'missing error message');
    }

    function testSendManyBadData() {
        $event15 = $this->Event->findById(15);
        $event22 = $this->Event->findById(22);
        $event24 = $this->Event->findById(24);
        $data = array('Event' => array('send15' => true,
                                       'send100' => true,
                                       'send22' => true,
                                       'send24' => true,
                                       'bogus' => true,
                                       'send500' => false));
        $result = $this->testAction('/events/sendMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);
        $expected = '/1 event\(s\) sent.*?1 event\(s\) not found: 100.*?1 event\(s\) cannot be sent at this time: 15.*?1 event\(s\) had bad reviewer e-mail addresses.*?: 24/sm';
        $this->assertTrue(preg_match($expected, $result) == 1,
                          'Pattern does not appear in result');
        $event15new = $this->Event->findById(15);
        $event22new = $this->Event->findById(22);
        $event24new = $this->Event->findById(24);
        
        $event22['Event']['send_date'] = date('Y-m-d');
        $event22['Event']['sender_id'] = 1;
        $event22['Event']['status'] = Event::SENT;
        $event24['Event']['send_date'] = date('Y-m-d');
        $event24['Event']['sender_id'] = 1;
        $event24['Event']['status'] = Event::SENT;

        $this->assertContainsFields($event15new['Event'], $event15['Event'], 
                                    "Event fields don't match (15)");
        $this->assertContainsFields($event22new['Event'], $event22['Event'], 
                                    "Event fields don't match (22)");
        $this->assertContainsFields($event24new['Event'], $event24['Event'], 
                                    "Event fields don't match (24)");
    }

    // tests sending many, no errors
    function testSendMany() {
        $event22 = $this->Event->findById(22);
        $event23 = $this->Event->findById(23);

        $data = array('Event' => array('send22' => true,
                                       'send23' => true));
        $result = $this->testAction('/events/sendMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, '2 event(s) sent'));
        $this->noErrors($result);
        $event22new = $this->Event->findById(22);
        $event23new = $this->Event->findById(23);

        $event22['Event']['send_date'] = date('Y-m-d');
        $event22['Event']['sender_id'] = 1;
        $event22['Event']['status'] = Event::SENT;
        $this->assertContainsFields($event22new['Event'], $event22['Event'], 
                                    "Event fields don't match (22)");

        $event23['Event']['send_date'] = date('Y-m-d');
        $event23['Event']['sender_id'] = 1;
        $event23['Event']['status'] = Event::SENT;

        $this->assertContainsFields($event23new['Event'], $event23['Event'], 
                                    "Event fields don't match (23)");
    }
}

?>
