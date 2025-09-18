<?php

require_once "my_controller_test_case.php";

class EventsControllerAddManyTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
        $this->User =& ClassRegistry::init('User');
    }   

    function testAddManyNotAdmin() {
        $this->User->id = 1;
        $this->User->saveField('admin_flag', 0);

        $result = $this->testAction('/events/addMany',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'You cannot perform add on events') !== false, 
                'missing error message');

        $this->User->id = 1;
        $this->User->saveField('admin_flag', 1);
    }

    function testAddManyNoData() {
        $result = $this->testAction('/events/addMany',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Add multiple new events.*?events\/addMany.*?Choose a file.*?Add/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testAddManyBadFile() {
        $data = array('Event' => array('newEventsFile' => '/tmp/doesntExist'));
        $result = $this->testAction('/events/addMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'Upload failed') !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testAddManyBadMimetype() {
        $type = 'application/pdf';
        $data = array('Event' => array('newEventsFile' => array(
            'size' => 22919, 'name' => 'E-02Pdf.pdf',
            'type' => $type, 'error' => 0, 
            'tmp_name' => '/tmp/something')));

        $result = $this->testAction('/events/addMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, "Bad file type $type") !== false, 
            'Pattern does not appear in result'); 
        $this->noCakeErrors($result);
    }

    function testAddManyBadTempFile() {
        $noSuchFile = '/tmp/doesntexist';
        $data = array('Event' => array('newEventsFile' => array(
                                  'size' => 0, 'name' => 'whatever',
                                  'type' => 'text/csv', 'error' => 0, 
                                  'tmp_name' => $noSuchFile)));
        $result = $this->testAction('/events/addMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
                                 "Failure: couldn't open temp file $noSuchFile")
                                      !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    /** check adding many from one file
     * @param file File (as an array)
     * @param numEvents Number of events in the file
     * @param missing Number of lines in the file with missing data 
     * @param missingList List of lines in the file with missing data 
     * @param notFound Number of lines in the file with non-existent patients 
     * @param notFoundList List of lines in the file with non-existent patients
     * @param criteriaProblem Number of lines in the file with criteria problems
     * @param criteriaProblemList List of lines in the file with criteria 
     *     problems
     */
    private function checkOneFile($file, $numEvents, $missing = 0, 
                                  $missingList = null,
                                  $notFound = 0, $notFoundList = null,
                                  $criteriaProblem = 0, 
                                  $criteriaProblemList = null) 
    {
        $data = array('Event' => array('newEventsFile' => $file));
        $result = $this->testAction('/events/addMany',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            "$numEvents new events added.") !== false, 
            'Count of new events does not appear in result'); 

        if (empty($missingList)) {
            $this->assertTrue(strpos($result, 
                "missing patient data") == false, 
                'Missing patient data, but there should be none'); 
        } else {
            $this->assertTrue(strpos($result, 
                "$missing lines missing patient data: $missingList") !== false, 
                'Missing patient data summary does not appear in result'); 
        }

        if (empty($notFoundList)) {
            $this->assertTrue(strpos($result, 
                "patients not found") == false, 
                'Some patients not found, but there should be none'); 
        } else {
            $this->assertTrue(strpos($result, 
                "$notFound patients not found: $notFoundList") !== false, 
                'Not found data summary does not appear in result'); 
        }

        if (empty($criteriaProblemList)) {
            $this->assertTrue(strpos($result, 
                "criteria problems") == false, 
                'Criteria problems, but there should be none'); 
        } else {
            $this->assertTrue(strpos($result, 
                "$criteriaProblem lines with criteria problems: $criteriaProblemList") !== false, 
                'Criteria problem data summary does not appear in result'); 
        }

        $this->noErrors($result);
    }

    function testAddManyEmpty() {
        $this->checkOneFile(array('size' => 0, 'name' => 'whatever',
                                  'type' => 'text/csv', 'error' => 0, 
                                  'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest0.csv'),
                            0);
    }

    function testAddManyMimeTypes() {
        // do it with all 4 possible mime types
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest1.csv');
        $this->checkOneFile($file, 1);
 
        $file['type'] = 'application/csv';
        $this->checkOneFile($file, 1);

        $file['type'] = 'application/vnd.ms-excel';
        $this->checkOneFile($file, 1);

        $file['type'] = 'text/comma-separated-values';
        $this->checkOneFile($file, 1);
    }

    private function checkEvent($event, $expected, $criteria) {
        $this->assertContainsFields($expected, $event['Event'],
                                    "Event fields don't match");
        $resultCriteria = $event['Criteria'];
        $numCriteria = count($resultCriteria);
        $expectedNumCriteria = count($criteria);
        $this->assertEqual($numCriteria, $expectedNumCriteria,
             "Should be $expectedNumCriteria criteria, found $numCriteria");

        foreach ($resultCriteria as $key => $crit) {
            $this->assertContainsFields($criteria[$key],
                                        $resultCriteria[$key]);
        }
    }

    function testAddMany1Item() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest1.csv');
        $this->checkOneFile($file, 1);

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'CK', 'value' => 5);
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }

    function testAddManySomeNotFound() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest2.csv');
        $this->checkOneFile($file, 1, 0, null, 2, 
            "<br/> Line 2: XXX, UNC.<br/> Line 3: 888a, UCSD.");

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'CK', 'value' => 5);
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }

    function testAddManySomeMissingData() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest3.csv');
        $this->checkOneFile($file, 1, 3, " 1 3 4");

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'CK', 'value' => 5);
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }

    function testAddManyEarlyDates() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest10.csv');
        $this->checkOneFile($file, 1, 2, " 1 2");

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'CK', 'value' => 5);
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }

    function testAddManyNoCriteria() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest4.csv');
        $this->checkOneFile($file, 1);

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $this->checkEvent($event, $data['Event'], null);
    }

    function testAddManyManyCriteria() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest5.csv');
        $this->checkOneFile($file, 1);

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'name1', 'value' => 'value1');
        $data['Criteria'][1] = array('name' => 'name2', 'value' => 'value2');
        $data['Criteria'][2] = array('name' => 'name3', 'value' => 'value3');
        $data['Criteria'][3] = array('name' => 'name4', 'value' => 'value4');
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }

    function testAddManyStripTags() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest6.csv');
        $this->checkOneFile($file, 1);

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'name1', 'value' => 'value1');
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }

    function testAddManyWrongDateFormat() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest7.csv');
        $this->checkOneFile($file, 1);

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'name1', 'value' => 'value1');
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }

    function testAddManyBadCriteria() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest11.csv');
        $this->checkOneFile($file, 1, 0, null, 0, null, 2, " 1 3");

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'CK', 'value' => '5');
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }

    function testAddManyMultipleAdds() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest8.csv');
        $this->checkOneFile($file, 5, 5, " 5 7 8 9 10", 2,
            "<br/> Line 3: XXX, UNC.<br/> Line 4: 888a, UCSD.", 2, " 11 12");

        $events = $this->Event->find('all', array('order' => 'Event.id DESC'));

        $event1 = $events[4];
        $event2 = $events[3];
        $event3 = $events[2];
        $event4 = $events[1];
        $event5 = $events[0];

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'CK', 'value' => '5');
        $this->checkEvent($event1, $data['Event'], $data['Criteria']);

        $data['Event']['patient_id'] = 2;
        $data['Event']['event_date'] = '2003-06-09';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'CK', 'value' => '5');
        $this->checkEvent($event2, $data['Event'], $data['Criteria']);

        $data['Event']['patient_id'] = 3;
        $data['Event']['event_date'] = '2003-07-10';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'CK', 'value' => '5');
        $this->checkEvent($event3, $data['Event'], $data['Criteria']);

        $data['Event']['patient_id'] = 4;
        $data['Event']['event_date'] = '2003-08-11';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'] = array();
        $this->checkEvent($event4, $data['Event'], $data['Criteria']);

        $data['Event']['patient_id'] = 5;
        $data['Event']['event_date'] = '2003-09-12';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => 'name1', 'value' => 'value1');
        $data['Criteria'][1] = array('name' => 'name2', 'value' => 'value2');
        $data['Criteria'][2] = array('name' => 'name3', 'value' => 'value3');
        $data['Criteria'][3] = array('name' => 'name4', 'value' => 'value4');
        $this->checkEvent($event5, $data['Event'], $data['Criteria']);
    }

    function testAddManyQuotedFields() {
        $file = array(
            'size' => 0, 'name' => 'whatever',
            'type' => 'text/csv', 'error' => 0, 
            'tmp_name' => '/srv/www/cnics.cirg.washington.edu/htdocs/mci-dev/app/tests/cases/controllers/addManyTest9.csv');
        $this->checkOneFile($file, 1);

        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));

        $data['Event']['patient_id'] = 1;
        $data['Event']['event_date'] = '2003-05-08';
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;
        $data['Criteria'][0] = array('name' => "name,'1", 'value' => "val,'ue1");
        $this->checkEvent($event, $data['Event'], $data['Criteria']);
    }
}

?>
