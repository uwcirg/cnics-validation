<?php

require_once "my_controller_test_case.php";

class EventsControllerTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    const ADD_SUCCESS = 'Recorded new event MI ';

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
        $this->User =& ClassRegistry::init('User');
    }   

    function testAdd() {
        $data = array('Patient' => array('site_patient_id' => '888a', 
                                         'site' => 'UNC'), 
                      'Event' => array('event_date' => '2009-10-22'),
                      'Criteria' => array(0 => array('name' => 'name1',
                                                     'value' => 'value1')));
        $result = $this->testAction('/events/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->noErrors($result);

        // get last event added
        $event = $this->Event->find('first', array('order' => 'Event.id DESC'));
        $this->assertTrue(strpos($result, self::ADD_SUCCESS . 
            (1000 + $event['Event']['id'])) !== false);
        
        $data['Event']['patient_id'] = 1;
        $data['Event']['add_date'] = date('Y-m-d');
        $data['Event']['creator_id'] = 1;
        $data['Event']['status'] = Event::CREATED;

        $this->assertContainsFields($data['Event'], $event['Event'], 
                                    "Event fields don't match");
        $resultCriteria = $event['Criteria'];

        foreach ($resultCriteria as $key => $crit) {
            $this->assertContainsFields($data['Criteria'][$key], 
                                        $resultCriteria[$key]);
        }
    }

    function testAddNoId() {
        $data = array('Patient' => array('site_patient_id' => 0, 
                                         'site' => 'UNC'), 
                      'Event' => array('event_date' => '2009-10-22'));
        $result = $this->testAction('/events/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'Missing patient identifiers') 
                              !== false);
        $this->noCakeErrors($result);
    }

    function testAddNoSite() {
        $data = array('Patient' => array('site_patient_id' => '888a', 
                                         'site' => ''), 
                      'Event' => array('event_date' => '2009-10-22'));
        $result = $this->testAction('/events/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'Missing patient identifiers') 
                              !== false);
        $this->noCakeErrors($result);
    }

    function testAddNoDate() {
        $data = array('Patient' => array('site_patient_id' => '888a', 
                                         'site' => 'UNC'), 
                      'Event' => array('event_date' => ''));
        $result = $this->testAction('/events/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'Missing event date') !== false);
        $this->noCakeErrors($result);
    }

    function testViewAll() {
        $result = $this->testAction('/events/viewAll',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Summary.*?Total.*?41.*?4.*?2.*?2.*?3.*?5.*?2.*?3.*?3.*?4.*?2.*?5.*?5.*?1.*?To Be Uploaded/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Summary table wrong');
        $expected = '/To Be Uploaded.*?Created.*?Site.*?1005.*?2009-02-14.*?2009-09-15.*?DFCI.*?events\/edit\/5.*?edit.*1006.*?UW.*?1007.*?UW.*?1036.*?UW.*?To Be Scrubbed/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'To Be Uploaded Table wrong');
        $expected = '/To Be Scrubbed.*?Uploaded.*1009.*?2009-02-18.*?2009-10-27.*?UW.*?download.*?scrub.9.*?upload scrubbed.*?events\/edit\/9.*?edit.*?1017.*?UW.*To Be Screened/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'To Be Scrubbed Table wrong');
        $expected = '/To Be Screened.*?Scrubbed.*?1015.*?2009-02-24.*?2010-02-10.*?events\/download\/15.*?download.*?events\/scrub\/15.*?re-upload scrubbed.*?events\/screen\/15.*?screen.*?events\/edit\/15.*?edit.*?1016.*?To Be Assigned/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'To Be Screened Table wrong');
        $expected = '/To Be Assigned.*?Screened.*1019.*?2009-02-28.*?2010-02-26.*?events\/edit\/19.*?edit.*?1020.*?1021.*?\<\/table\>.*?events\/assignMany.*?Assign Reviewers.*?To Be Sent/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'To Be Assigned Table wrong');
        $expected = '/To Be Sent.*?Assigned.*1014.*?2009-02-23.*?2010-02-09.*?events\/edit\/14.*?edit.*?1022.*?1023.*?1024.*?1025.*?\<\/table\>.*?events\/sendMany.*?Select charts to send.*?Not Yet Reviewed/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'To Be Sent Table wrong');
        $expected = '/Not Yet Reviewed.*?Event Date.*?Sent.Last Review.*?Yet to review.*?1002.*?2009-02-11.*?2010-02-07.*?gsbarnes@washington\.edu.*?user3@example.com.*?events\/edit\/2.*?edit.*?1003.*?2009-02-12.*?2010-02-04.*?gsbarnes@washington\.edu.*?1004.*?2009-02-13.*?2010-02-01.*?gsbarnes@washington\.edu.*?1008.*?1018.*Third Review Needed/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Not Yet Reviewed Table wrong');
        $expected = '/Third Review Needed.*?Last Review.*1001.*?2009-02-10.*?2010-01-23.*?events\/edit\/1.*?edit.*?\<\/table\>.*?events\/assign3rdMany.*?Assign Third Reviewers.*?Third Reviewer Assigned/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Third Review Needed Table wrong');
        $expected = '/Third Reviewer Assigned.*?Event Date.*?3rd Reviewer Assigned.*?3rd Reviewer.*?1011.*?2009-02-20.*?2010-01-18.*?gsbarnes@washington\.edu.*?events\/edit\/11.*?1033.*?2009-03-14.*?2010-06-20.*?user2@example\..*?events\/edit\/33.*?edit.*?All Done/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Third Reviewer Assigned Table wrong');
        $expected = '/All Done.*?Last Review.*1012.*?2009-02-21.*?2010-01-12.*?events\/edit\/12.*?edit.*?1013.*?2009-02-22.*?2010-01-28.*?1027.*?2009-03-08.*?2010-04-18.*?1028.*?2009-03-09.*?2010-04-28.*?No Packet Available/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'All Done Table wrong');
        $expected = '/No Packet Available.*?Date Reported.*1037.*?2009-03-18.*?2010-05-07.*?events\/edit\/37.*?edit.*?Rejected/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'No Packet Available Table wrong');
        $expected = '/Rejected.*?Rejected.*1029.*?2009-03-10.*?2010-05-02.*?events\/edit\/29.*?edit/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Rejected Table wrong');
    }

    const UPLOADER_WELCOME = 'Welcome!';

    function testIndexAllRoles() {
        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) !== false);
        $expected = '/You are logged in as: gsbarnes@washington\.edu.*?href=".*?\?view=admin".*?Admin Tools.*?href=".*?\?view=upload".*?Upload New Packets.*?href=".*?\?view=reupload".*?Re-upload Existing Packets.*?href=".*?\?view=review".*?Review Events.*?\<h1\>Administrative Tools.*?Full instructions:.*?CNICS MI Review packet assembly instructions.doc.*?\<h1\>Upload New Packets.*?Review Instructions:.*?CNICS MI reviewer instructions.doc.*?\<h1\>Event Packets for Your Review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");
        $expectedAdminActions = '/Administrative Tools.*?href=.*?events\/viewAll.*?View all events.*?href=.*?events\/add.*?Add an event.*?href=.*?events\/addMany.*?Add multiple events from a CSV file.*?href=.*?events\/getCsv.*?Export all events as CSV.*?href=.*?users\/add.*?Add a user.*?href=.*?users\/viewAll.*?Edit\/Delete users/sm';
        $this->assertTrue(preg_match($expectedAdminActions, $result) == 1, 
                          "List of admin actions missing");
    }

    function testIndexOnlyReviewer() {
        $this->User->id = 1;
        $this->User->saveField('admin_flag', 0);
        $this->User->saveField('uploader_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) === false);
        $notExpected = '/href="#/sm';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Admin Tools/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Upload New Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Re-upload Existing Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/\<h1\>Event Packets for Your Review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('admin_flag', 1);
        $this->User->saveField('uploader_flag', 1);

    }

    function testIndexOnlyAdmin() {
        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 0);
        $this->User->saveField('uploader_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) === false);
        $notExpected = '/href="#/sm';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Review Events/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Upload New Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Re-upload Existing Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/\<h1\>Administrative Tools/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 1);
        $this->User->saveField('uploader_flag', 1);
    }

    function testIndexOnlyUploader() {
        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 0);
        $this->User->saveField('admin_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);

        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) !== false);
        $notExpected = '/Admin Tools/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Review Events/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/href=".*?\?view=upload".*?Upload New Packets.*?href=".*?\?view=reupload".*?Re-upload Existing Packets.*?\<h1\>Upload New Packets.*?/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 1);
        $this->User->saveField('admin_flag', 1);
    }


    function testIndexAdminUploader() {
        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 0);
        
        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) !== false);
        $notExpected = '/Review Events/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/href=".*?\?view=admin".*?Admin Tools.*?href=".*?\?view=upload".*?Upload New Packets.*?href=".*?\?view=reupload".*?Re-upload Existing Packets.*?\<h1\>Administrative Tools.*?\<h1\>Upload New Packets.*?/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 1);
    }

    function testIndexAdminReviewer() {
        $this->User->id = 1;
        $this->User->saveField('uploader_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) === false);
        $notExpected = '/Upload New Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Re-upload Existing Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/href=".*?\?view=admin".*?Admin Tools.*?href=".*?\?view=review".*?Review Events.*?\<h1\>Administrative Tools.*?\<h1\>Event Packets for Your Review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('uploader_flag', 1);
    }

    function testIndexUploaderReviewer() {
        $this->User->id = 1;
        $this->User->saveField('admin_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) !== false);
        $notExpected = '/Admin Tools/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/href=".*?\?view=upload".*?Upload New Packets.*?href=".*?\?view=reupload".*?Re-upload Existing Packets.*?href=".*?\?view=review".*?Review Events.*?\<h1\>Upload New Packets.*?\<h1\>Event Packets for Your Review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('admin_flag', 1);
    }

    function testIndex() {
        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Welcome\!.*?Upload New Packets.*?events\/upload.*?MI 1006.*?007x.*?2009-02-15.*?name1 = value1, name2 = value2.*?events\/upload.*?MI 1007.*?name3 = value3.*?events\/upload.*?MI 1036.*?None.*?Event Packets/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");
        $expected = '/Event Packets for Your Review.*?events\/review1.*?MI 1002.*?2009-02-11.*?events\/review2.*?MI 1003.*?events\/review1.*?MI 1004.*?events\/review2.*?MI 1008.*?events\/review3.*?MI 1011.*/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");
    }

    function testViewAdminByAdmin() {
        $result = $this->testAction('/?view=admin',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) !== false);
        $expectedAdminActions = '/Administrative Tools.*?href=.*?events\/viewAll.*?View all events.*?href=.*?events\/add.*?Add an event.*?href=.*?events\/addMany.*?Add multiple events from a CSV file.*?href=.*?events\/getCsv.*?Export all events as CSV.*?href=.*?users\/add.*?Add a user.*?href=.*?users\/viewAll.*?Edit\/Delete users/sm';
        $this->assertTrue(preg_match($expectedAdminActions, $result) == 1, 
                          "List of admin actions missing");
        $notExpected = '/Upload New Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Re-upload Existing Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/\<h1\>Event Packets for Your Review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");
    }

/*
    function testViewAdminByNonAdmin() {
        $this->User->id = 1;
        $this->User->saveField('admin_flag', 0);

        $result = $this->testAction('/?view=admin',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");
        $this->assertTrue(preg_match($expectedAdminActions, $result) == 1, 
                          "List of admin actions missing");
        $notExpected = '/Administrative Tools/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Upload New Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Re-upload Existing Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/\<h1\>Event Packets for Your Review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('admin_flag', 1);
    }

//%%%
    function testIndexReviewByReviewer() {
    }

    function testIndexOnlyAdmin() {
        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 0);
        $this->User->saveField('uploader_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) === false);
        $notExpected = '/href="#/sm';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Review Events/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Upload New Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Re-upload Existing Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/\<h1\>Administrative Tools/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 1);
        $this->User->saveField('uploader_flag', 1);
    }

    function testIndexOnlyUploader() {
        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 0);
        $this->User->saveField('admin_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);

        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) !== false);
        $notExpected = '/Admin Tools/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Review Events/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/href=".*?\?view=upload".*?Upload New Packets.*?href=".*?\?view=reupload".*?Re-upload Existing Packets.*?\<h1\>Upload New Packets.*?/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 1);
        $this->User->saveField('admin_flag', 1);
    }


    function testIndexAdminUploader() {
        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 0);
        
        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) !== false);
        $notExpected = '/Review Events/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/href=".*?\?view=admin".*?Admin Tools.*?href=".*?\?view=upload".*?Upload New Packets.*?href=".*?\?view=reupload".*?Re-upload Existing Packets.*?\<h1\>Administrative Tools.*?\<h1\>Upload New Packets.*?/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('reviewer_flag', 1);
    }

    function testIndexAdminReviewer() {
        $this->User->id = 1;
        $this->User->saveField('uploader_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) === false);
        $notExpected = '/Upload New Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $notExpected = '/Re-upload Existing Packets/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/href=".*?\?view=admin".*?Admin Tools.*?href=".*?\?view=review".*?Review Events.*?\<h1\>Administrative Tools.*?\<h1\>Event Packets for Your Review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('uploader_flag', 1);
    }

    function testIndexUploaderReviewer() {
        $this->User->id = 1;
        $this->User->saveField('admin_flag', 0);

        $result = $this->testAction('/events/index',
            array('return' => 'contents'));
        $this->noErrors($result);
        $this->assertTrue(strpos($result, self::UPLOADER_WELCOME) !== false);
        $notExpected = '/Admin Tools/';
        $this->assertTrue(preg_match($notExpected, $result) == 0, 
                          "Pattern '$notExpected' appears in '$result'");
        $expected = '/href=".*?\?view=upload".*?Upload New Packets.*?href=".*?\?view=reupload".*?Re-upload Existing Packets.*?href=".*?\?view=review".*?Review Events.*?\<h1\>Upload New Packets.*?\<h1\>Event Packets for Your Review/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern '$expected' does not appear in '$result'");

        $this->User->id = 1;
        $this->User->saveField('admin_flag', 1);
    }


// re-upload, upload, review page
// $expected = '/Events whose charts you can re-upload.*?events\/upload.*?MI 1009.*?2009-02-18, UW, 007x.*?Criteria: name4 = value4.*?events\/upload.*?MI 1017.*Event Packets for Your Review/sm';
*/
}
?>
