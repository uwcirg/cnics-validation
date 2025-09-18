<?php

require_once "my_controller_test_case.php";

class EventsControllerNoPacketTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
    }   

    function testNoPacketNoData() {
        $result = $this->testAction('/events/markNoPacket/6',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Choose a file to upload.*?If no packet is available.*?Please document why there is no event packet.*?The protocol requests that 2 attempts are made.*?Is approximate month.year of the prior event.*?Please enter the month.year.*?Did event occur while in care at your site.*?Other cause/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testNoPacketMissingOtherCause() {
        $data = array('Event' => array('id' => 6, 
                                       'no_packet_reason' => Event::OTHER,
                                       'two_attempts_flag' => true,
                                       'prior_event_date_known' => true,
                                       'priorDateMonth' => 
                                           array('month' => '00'),
                                       'priorDateYear' => '2007',
                                       'prior_event_onsite_flag' => true,
                                       'other_cause' => null));

        $result = $this->testAction('/events/markNoPacket',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, Event::OC_BLANK) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }


    function testNoPacketOther() {
        $data = array('Event' => array('id' => 6, 
                                       'no_packet_reason' => Event::OTHER,
                                       'two_attempts_flag' => true,
                                       'prior_event_date_known' => true,
                                       'priorDateMonth' => 
                                           array('month' => '00'),
                                       'priorDateYear' => '2007',
                                       'prior_event_onsite_flag' => true,
                                       'other_cause' => 'whee'));

        $result = $this->testAction('/events/markNoPacket',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, Event::NO_PACKET_SUCCESS) !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(6);
        
        $expected['Event']['markNoPacket_date'] = date('Y-m-d');
        $expected['Event']['marker_id'] = 1;
        $expected['Event']['status'] = Event::NO_PACKET_AVAILABLE;
        $expected['Event']['no_packet_reason'] = Event::OTHER;
        $expected['Event']['two_attempts_flag'] = null;
        $expected['Event']['prior_event_date'] = null;
        $expected['Event']['prior_event_onsite_flag'] = null;
        $expected['Event']['other_cause'] = 'whee';

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
    }

    function testNoPacketPrior() {
        $data = array('Event' => 
            array('id' => 6, 
                  'no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'whee'));

        $result = $this->testAction('/events/markNoPacket',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, Event::NO_PACKET_SUCCESS) !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(6);
        
        $expected['Event']['markNoPacket_date'] = date('Y-m-d');
        $expected['Event']['marker_id'] = 1;
        $expected['Event']['status'] = Event::NO_PACKET_AVAILABLE;
        $expected['Event']['no_packet_reason'] = 
            Event::ASCERTAINMENT_PRIOR_EVENT;
        $expected['Event']['two_attempts_flag'] = null;
        $expected['Event']['prior_event_date'] = '00-2007';
        $expected['Event']['prior_event_onsite_flag'] = true;
        $expected['Event']['other_cause'] = null;

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
    }

    const CANTBEMARKED = 'markNoPacket cannot be performed on this event';

    function testNoPacketBadStatus() {
    // get already tested in upload test
        $data = array('Event' => array('id' => 16, 
                                       'no_packet_reason' => Event::OTHER,
                                       'two_attempts_flag' => true,
                                       'prior_event_date_known' => true,
                                       'priorDateMonth' => 
                                           array('month' => '00'),
                                       'priorDateYear' => '2007',
                                       'prior_event_onsite_flag' => true,
                                       'other_cause' => 'whee'));

        $result = $this->testAction('/events/markNoPacket',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTBEMARKED) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    const CANTMARK = 'You cannot perform markNoPacket on this event';

    function testMarkNotUploader() {
        $result = $this->testAction('/events/markNoPacket/1',
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTMARK) !== false, 
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data; set confirm checkbox to avoid that error
        $data = array('Event' => array('id' => 1));
        $result = $this->testAction('/events/markNoPacket',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTMARK) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testMarkNoSuchEvent() {
        $result = $this->testAction('/events/markNoPacket/100',
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTMARK) !== false, 
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data
        $data = array('Event' => array('id' => 100));
        $result = $this->testAction('/events/markNoPacket',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTMARK) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

}

?>
