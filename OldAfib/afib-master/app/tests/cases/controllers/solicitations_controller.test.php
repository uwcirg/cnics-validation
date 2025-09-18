<?php

require_once "my_controller_test_case.php";

class SolicitationsControllerTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    const SUCCESS = 'Saved new solicitation';
    const DELETED = 'Solicitation deleted';

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
        $this->Solicitation =& ClassRegistry::init('Solicitation');
    }

    function testAdd() {
        $contactText = 'Just added this';
        $contactDate = '2008-03-05';
        $solicitation = array('event_id' => 6, 'date' => $contactDate, 
                              'contact' => $contactText);
        $data = array('Solicitation' => $solicitation);
        $result = $this->testAction('/solicitations/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::SUCCESS) !== false);
        $this->noErrors($result);

        $result = $this->Event->findById(6);
        // get the last solicitation added
        $newSolicitation = 
            $result['Solicitation'][count($result['Solicitation']) - 1];
        $this->assertContainsFields($solicitation, $newSolicitation);
    }

    function testAddTagInContact() {
        $hello = 'Hello';
        $contactText = "$hello <b>$hello</b> $hello";
        $contactDate = '2010-03-05';
        $data = array('Solicitation' => array('event_id' => 6, 
                      'date' => $contactDate, 'contact' => $contactText));
        $result = $this->testAction('/solicitations/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::SUCCESS) !== false);
        $this->noErrors($result);

        $result = $this->Event->findById(6);
        // get the last solicitation added
        $newSolicitation = 
            $result['Solicitation'][count($result['Solicitation']) - 1];
        $expected = array('event_id' => 6, 'date' => $contactDate, 
                          'contact' => "$hello $hello $hello");
        $this->assertContainsFields($expected, $newSolicitation);
    }

    function testAddBadEventId() {
        $contactText = 'Bad Event id';
        $badEventId = 250;
        $contactDate = '2010-03-05';
        $data = array('Solicitation' => array('event_id' => $badEventId, 
                      'date' => $contactDate, 'contact' => $contactText));
        $result = $this->testAction('/solicitations/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $expected = "No such event $badEventId";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);
    }

    function testAddNoData() {
        $result = $this->testAction('/solicitations/add',
            array('data' => null, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'No solicitation data!') !== false);
        $this->noCakeErrors($result);
    }

    function testDelete() {
        $result = $this->testAction('/solicitations/delete/1/11',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::DELETED) !== false);
        $this->noErrors($result);

        $result = $this->Solicitation->findById(1);
        // get the last solicitation added
        $this->assertEqual($result, null, 
           'Found supposedly deleted solicitation');

        $badId = 5;
        $result = $this->testAction("/solicitations/delete/$badId/11",
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "No such solicitation: $badId";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);

        $badId = '7hey';
        $result = $this->testAction("/solicitations/delete/$badId/11",
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "No such solicitation: 7";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);
    }
}

?>
