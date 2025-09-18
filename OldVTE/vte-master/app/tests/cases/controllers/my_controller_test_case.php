<?php

require_once "/home/cnics/website/mci-dev/app/tests/cases/models/my_test_case.php";

class MyControllerTestCase extends MyTestCase {
    /**
     * Test that there are no messages that match a certain pattern on a page
     * @param pattern The pattern
     * @param contents Contents of the page
     */
    private function noMessages($pattern, $contents) {
        $matches = array();
        $flashMessage = preg_match($pattern, $contents, $matches);
        $message = $flashMessage == 0 ? '' : $matches[1];

        $this->assertTrue($flashMessage === 0, 
            "Flash message in returned page: '$message'");
    }

    /** Pattern that matches a cake flash message */
    private static $flashMessagePattern =
        '/div id=\"flashMessage\".*?>(.*?)<\/div>/sm';

    /** Pattern that matches a cake debug message */
    private static $cakeDebugPattern =
        '/pre class=\"cake-debug\".*?>(.*?)<\/pre>/sm';
    
    /**
     * Test that there is no Cake error message in the contents 
     * @param contents The contents to test
     */
/* not sure this works in the test environment; debug messages seem to be 
   captured */
    protected function noCakeErrors($contents) {
        $this->noMessages(self::$cakeDebugPattern, $contents);
    }

    /**
     * Test that there is no error message in the contents 
     * @param contents The contents to test
     */
    protected function noErrors($contents) {
        $this->noMessages(self::$flashMessagePattern, $contents);
        $this->noCakeErrors($contents);
    }

    function startTest($method) {
        echo '<h3>Starting method ' . $method . '</h3>';
    }

    function endTest($method) {
        echo '<hr />';
    } 
}

?>
