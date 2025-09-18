<?php

require_once "my_controller_test_case.php";

class CriteriasControllerTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    const SUCCESS = 'Saved new criterion';
    const DELETED = 'Criterion deleted';

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
        $this->Criteria =& ClassRegistry::init('Criteria');
    }

    function testAdd() {
        $name = 'new name';
        $value = 'new value';
        $criteria = array('event_id' => 6, 'name' => $name, 
                           'value' => $value);
        $data = array('Criteria' => $criteria);
        $result = $this->testAction('/criterias/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::SUCCESS) !== false);
        $this->noErrors($result);

        $result = $this->Event->findById(6);
        // get the last criterion added
        $newCriteria = 
            $result['Criteria'][count($result['Criteria']) - 1];
        $this->assertContainsFields($criteria, $newCriteria);
    }

    function testAddTagsInFields() {
        $hello = 'Hello';
        $hi = 'Hi';
        $name = "$hello <b>$hello</b> $hello";
        $value = "$hi <b>$hi</b> $hi";
        $data = array('Criteria' => array('event_id' => 6, 
                      'value' => $value, 'name' => $name));
        $result = $this->testAction('/criterias/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::SUCCESS) !== false);
        $this->noErrors($result);

        $result = $this->Event->findById(6);
        // get the last criterion added
        $newCriteria = 
            $result['Criteria'][count($result['Criteria']) - 1];
        $expected = array('event_id' => 6, 'value' => "$hi $hi $hi", 
                          'name' => "$hello $hello $hello");
        $this->assertContainsFields($expected, $newCriteria);
    }

    function testAddBadEventId() {
        $name = 'Bad Event id';
        $badEventId = 250;
        $value = '2010-03-05';
        $data = array('Criteria' => array('event_id' => $badEventId, 
                      'value' => $value, 'name' => $name));
        $result = $this->testAction('/criterias/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $expected = "No such event $badEventId";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);
    }

    function testAddNoData() {
        $result = $this->testAction('/criterias/add',
            array('data' => null, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'No criterion data!') !== false);
        $this->noCakeErrors($result);
    }

    function testDelete() {
        $result = $this->testAction('/criterias/delete/1/11',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::DELETED) !== false);
        $this->noErrors($result);

        $result = $this->Criteria->findById(1);
        // get the last criterion added
        $this->assertEqual($result, null, 
           'Found supposedly deleted criterion');

        $badId = 500;
        $result = $this->testAction("/criterias/delete/$badId/11",
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "No such criterion: $badId";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);

        $badId = 'hey';
        $result = $this->testAction("/criterias/delete/$badId/11",
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "No such criterion: 0";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);
    }
}

?>
