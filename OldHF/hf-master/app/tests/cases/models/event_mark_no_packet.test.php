<?php

require_once "my_test_case.php";

App::import('Model', 'Event', 'Review', 'AppModel', 'CodedItemBehavior');

class EventSetNoPacketTestCase extends MyTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');
    private $now;

    function start() {
        parent::start();
        $this->Event =& ClassRegistry::init('Event');
        $this->CodedItemBehavior =& ClassRegistry::init('CodedItemBehavior');
        $this->now = date('Y-m-d');
    }

    function testSetNoPacketOtherCauseMissing() {
        $event6 = $this->Event->findById(6);

        $data = array('Event' => 
            array('no_packet_reason' => Event::OTHER,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => null));
        $result = $this->Event->markNoPacketAvailable($event6, $data, 1);
        $expected = array('success' => false, 'message' => Event::OC_BLANK);
        $this->assertContainsFields($expected, $result);
    }
    
    function testSetNoPacketOtherCauseTags() {
        $event6 = $this->Event->findById(6);

        $data = array('Event' => 
            array('no_packet_reason' => Event::OTHER,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => '<b>Too many tags</b>'));
        $result = $this->Event->markNoPacketAvailable($event6, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(6);
        $event6['Event']['no_packet_reason'] = Event::OTHER;
        $event6['Event']['other_cause'] = 'Too many tags';
        $this->checkArrayDiff($event6['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (strip tags)");
    }
    
    function testOutsideHospital() {
        $event6 = $this->Event->findById(6);

        $data = array('Event' => 
            array('no_packet_reason' => Event::OUTSIDE_HOSPITAL,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event6, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(6);
        $event6['Event']['no_packet_reason'] = Event::OUTSIDE_HOSPITAL;
        $event6['Event']['two_attempts_flag'] = 1;
        $this->checkArrayDiff($event6['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (outside hospital)");
    }

    function testDiagnosisError() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_DIAGNOSIS_ERROR,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(36);
        $event36['Event']['no_packet_reason'] = 
            Event::ASCERTAINMENT_DIAGNOSIS_ERROR;
        $this->checkArrayDiff($event36['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (ascertainment error)");
    }

    function testPriorEvent() {
        $event7 = $this->Event->findById(7);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => false,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event7, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(7);
        $event7['Event']['no_packet_reason'] = Event::ASCERTAINMENT_PRIOR_EVENT;
        $event7['Event']['prior_event_onsite_flag'] = 1;
        $this->checkArrayDiff($event7['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (prior event, unknown date)");

        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(36);
        $event36['Event']['no_packet_reason'] = 
            Event::ASCERTAINMENT_PRIOR_EVENT;
        $event36['Event']['prior_event_date'] = '00-2007';
        $event36['Event']['prior_event_onsite_flag'] = 1;
        $this->checkArrayDiff($event36['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (prior event, fuzzy date)");
    }

    function testOther() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::OTHER,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(36);
        $event36['Event']['no_packet_reason'] = Event::OTHER;
        $event36['Event']['other_cause'] = 'missing';
        $this->checkArrayDiff($event36['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (other reason)");
    }

    function testHighDates() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '25'),
                  'priorDateYear' => '30000',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(36);
        $event36['Event']['no_packet_reason'] = 
            Event::ASCERTAINMENT_PRIOR_EVENT;
        $event36['Event']['prior_event_date'] = '00-0000';
        $event36['Event']['prior_event_onsite_flag'] = 1;
        $this->checkArrayDiff($event36['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (prior event, high dates)");
    }

    function testLowDates() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '-5'),
                  'priorDateYear' => '88',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(36);
        $event36['Event']['no_packet_reason'] = 
            Event::ASCERTAINMENT_PRIOR_EVENT;
        $event36['Event']['prior_event_date'] = '00-0000';
        $event36['Event']['prior_event_onsite_flag'] = 1;
        $this->checkArrayDiff($event36['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (prior event, low dates)");
    }

    function testSingleDigitMonth() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '5'),
                  'priorDateYear' => '2008',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(36);
        $event36['Event']['no_packet_reason'] = 
            Event::ASCERTAINMENT_PRIOR_EVENT;
        $event36['Event']['prior_event_date'] = '05-2008';
        $event36['Event']['prior_event_onsite_flag'] = 1;
        $this->checkArrayDiff($event36['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (prior event, single digit month)");
    }

    function testNotIntegers() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '03whee'),
                  'priorDateYear' => '2005whoo',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => true, 
                          'message' => Event::NO_PACKET_SUCCESS);
        $this->assertContainsFields($expected, $result);

        $newEvent = $this->Event->findById(36);
        $event36['Event']['no_packet_reason'] = 
            Event::ASCERTAINMENT_PRIOR_EVENT;
        $event36['Event']['prior_event_date'] = '03-2005';
        $event36['Event']['prior_event_onsite_flag'] = 1;
        $this->checkArrayDiff($event36['Event'], $newEvent['Event'], 
                              "Set no packet does not change as expected 
                               (prior event, not integers)");
    }

    function testTwoAttemptsFlagNotSet() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::OUTSIDE_HOSPITAL,
                  'two_attempts_flag' => '',
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => false, 
                          'message' => Event::TWO_ATTEMPTS_BLANK);
        $this->assertContainsFields($expected, $result);
    }

    function testPriorEventDateKnownFlagNotSet() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => '',
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => true,
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => false, 
                          'message' => Event::PRIOR_EVENT_DATE_KNOWN_BLANK);
        $this->assertContainsFields($expected, $result);
    }

    function testPriorEventOnsiteFlagNotSet() {
        $event36 = $this->Event->findById(36);

        $data = array('Event' => 
            array('no_packet_reason' => Event::ASCERTAINMENT_PRIOR_EVENT,
                  'two_attempts_flag' => true,
                  'prior_event_date_known' => true,
                  'priorDateMonth' => array('month' => '00'),
                  'priorDateYear' => '2007',
                  'prior_event_onsite_flag' => '',
                  'other_cause' => 'missing'));
        $result = $this->Event->markNoPacketAvailable($event36, $data, 1);
        $expected = array('success' => false, 
                          'message' => Event::PRIOR_EVENT_ONSITE_BLANK);
        $this->assertContainsFields($expected, $result);
    }
}

?>
