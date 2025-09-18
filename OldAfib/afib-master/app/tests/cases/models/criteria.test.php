<?php

require_once "my_test_case.php";

App::import('Criteria', 'Model');

class CriteriaTestCase extends MyTestCase {
    var $fixtures = array('app.user', 'app.review', 
                          'app.event', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.criteria');

    function start() {
        parent::start();
        $this->Criteria =& ClassRegistry::init('Criteria');
    }

    function testExtractCriteriaFromArray() {
        $a = array();
        $emptyArray = array();
        $errorArray = array('error' => 1);
        $result = $this->Criteria->extractCriteriaFromArray($a, 0);

        $this->checkArrayDiff($result, $emptyArray,
             "Empty array doesn't match");

        $a[0] = 'zero';
        $a[1] = 'one';
        $expected = array();
        $expected[0] = array('name' => 'zero', 'value' => 'one');
        $result = $this->Criteria->extractCriteriaFromArray($a, 0);

        $this->checkArrayDiff($result, $expected,
             "Two-element array doesn't match");
        $this->checkArrayDiff($result[0], $expected[0], 
                              "Subarray doesn't match");

        $result = $this->Criteria->extractCriteriaFromArray($a, 1);
        $this->checkArrayDiff($result, $errorArray,
             "No match (1 element to pair up = error)");

        $result = $this->Criteria->extractCriteriaFromArray($a, 2);
        $this->checkArrayDiff($result, $emptyArray,
             "No match (no elements to pair up)");

        $a[2] = 'two';
        $result = $this->Criteria->extractCriteriaFromArray($a, 0);
        $this->checkArrayDiff($result, $errorArray,
             "No match (3 elements to pair up = error)");

        $a[3] = 'three';
        $expected[1] = array('name' => 'two', 'value' => 'three');
        $result = $this->Criteria->extractCriteriaFromArray($a, 0);

        $this->checkArrayDiff($result, $expected,
             "Four-element array doesn't match");
        $this->checkArrayDiff($result[0], $expected[0], 
                              "First subarray doesn't match");
        $this->checkArrayDiff($result[1], $expected[1], 
                              "Second subarray doesn't match");
    }
    
}

?>
