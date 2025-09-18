<?php

require_once "my_controller_test_case.php";

class EventsControllerScreenTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
    }   

    function testScreenNoData() {
        $result = $this->testAction('/events/screen/15',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Screen charts for.*?MI\s+1015.*?Site:.*?UNC.*?Patient ID:.*?888a.*?Date:.*?2009-02-24.*?download\/15.*?Accept.*?Needs Rescrubbing.*?Reject.*?Message/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }


    const CANTSCREEN = 'screen cannot be performed on this event';
    const BADEVENT = 'No event specified';

    function testScreenBadStatus() {
        $result = $this->testAction('/events/screen/19',
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTSCREEN) !== false, 
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data; set confirm checkbox to avoid that error
        $data = array('Event' => array('id' => 19, 
                                       'screenAccept' => Event::ACCEPT));
        $result = $this->testAction('/events/screen',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTSCREEN) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testScreenNoSuchEvent() {
        $result = $this->testAction('/events/screen/100',
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, self::BADEVENT) !== false, 
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data
        $data = array('Event' => array('id' => 100, 
                                       'screenAccept' => Event::ACCEPT));
        $result = $this->testAction('/events/screen',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::BADEVENT) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testScreenBadData() {
        $data = array('Event' => array('id' => 15));
        $expected = $this->Event->findById(15);

        $result = $this->testAction('/events/screen',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, Event::BAD_SCREEN_ACCEPT) !== false, 
            'Pattern does not appear in result'); 
        $this->noCakeErrors($result);

        $event = $this->Event->findById(15);
        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");

        $data = array('Event' => array('id' => 15, 'screenAccept' => 'fail'));
        $expected = $this->Event->findById(15);

        $result = $this->testAction('/events/screen',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'You must either Accept, Reject, or return for Rescrubbing') 
                 !== false, 
            'Pattern does not appear in result'); 
        $this->noCakeErrors($result);

        $event = $this->Event->findById(15);
        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
    }

    function testScreen() {
        $data = array('Event' => array('id' => 15, 
                                       'screenAccept' => Event::ACCEPT));
        $expected = $this->Event->findById(15);

        $result = $this->testAction('/events/screen',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Screened event MI 1015 (Accepted)') !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(15);
        
        $expected['Event']['screen_date'] = date('Y-m-d');
        $expected['Event']['screener_id'] = 1;
        $expected['Event']['status'] = Event::SCREENED;

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");

        $message = 'Ugly tie';
        $data = array('Event' => array('id' => 16, 
                                       'screenAccept' => Event::REJECT,
                                       'message' => "<b>$message</b>"));
        $expected = $this->Event->findById(16);

        $result = $this->testAction('/events/screen',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Screened event MI 1016 (Rejected)') !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(16);
        
        $expected['Event']['screen_date'] = date('Y-m-d');
        $expected['Event']['screener_id'] = 1;
        $expected['Event']['status'] = Event::REJECTED;
        $expected['Event']['reject_message'] = $message;

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
    }

    function testScreenRescrub() {
        $message = 'Bad hair';
        $data = array('Event' => array('id' => 16, 
                                       'screenAccept' => Event::RESCRUB,
                                       'message' => "<b>$message</b>"));
        $expected = $this->Event->findById(16);

        $result = $this->testAction('/events/screen',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Screened event MI 1016 (Needs Rescrubbing)') !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(16);
        
        $expected['Event']['screen_date'] = date('Y-m-d');
        $expected['Event']['screener_id'] = 1;
        $expected['Event']['status'] = Event::UPLOADED;
        $expected['Event']['rescrub_message'] = $message;

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
        
        // no message
        $data = array('Event' => array('id' => 15, 
                                       'screenAccept' => Event::RESCRUB));
        $expected = $this->Event->findById(15);

        $result = $this->testAction('/events/screen',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Screened event MI 1015 (Needs Rescrubbing)') !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(15);
        
        $expected['Event']['screen_date'] = date('Y-m-d');
        $expected['Event']['screener_id'] = 1;
        $expected['Event']['status'] = Event::UPLOADED;

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
    }
}

?>
