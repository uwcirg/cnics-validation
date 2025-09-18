<?php

class LogFixture extends CakeTestFixture {
    var $name = 'Log';
    var $import = 'Log';

    var $records = array(
        array('id' => 1, 'user_id' => 1, 'action' => 'login', 
              'time' => '2009-09-15 00:00:13')
    );
}

?>
