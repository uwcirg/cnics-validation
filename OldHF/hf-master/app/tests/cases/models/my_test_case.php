<?php

class MyTestCase extends CakeTestCase {
    function start() {
        if (!defined('CAKEPHP_UNIT_TEST_EXECUTION')) {
            define('CAKEPHP_UNIT_TEST_EXECUTION', true);
        }

        parent::start();
    }

    /**
     * Test that all the keys in one array (a1) are in another (a2), with the 
     * same values
     * @param a1 First array
     * @param a2 Second array 
     *    (must contain all of a1's keys, with the same values)
     * @return nothing.  All tests are done with PHPUnit assertions, so they
     *    will fail
     */
    protected function assertContainsFields($a1, $a2) {
        foreach ($a1 as $key => $value) {
            if ($value === null) {
                $this->assertNull($a2[$key], "$key should be null");
            } else {
                $this->assertNotNull($a2[$key], "$key should not be null");
            }

            $this->assertEqual($value, $a2[$key], "$key should be $value");
        }
    }

    /**
     * Check that a particular date field in an event is not before 'now', 
     * and not before its created date
     * @param event Event to check
     * @param now Date representing 'now'
     * @param field Name of the field to check
     */
    protected function checkDateField($event, $now, $field) {
        $this->assertTrue($now <= $event[$field], 
                          "$now is not <= $field ({$event[$field]})");
        $this->assertTrue($event['add_date'] <= $event[$field],
                          "add_date ({$event['add_date']}) is not
                           <= $field ({$event[$field]})");
    }

    /**
     * Check that two arrays are the same, and fail if they are not
     * @param a1 Array 1
     * @param a2 Array 2
     * @param message Message to include (along with a1) in case of error
     */
    protected function checkArrayDiff($a1, $a2, $message) {
        $this->assertEqual($a1, $a2, 
            "$message " . print_r($a1, true) . print_r($a2, true));
    }
}

?>
