<?php

require_once "my_controller_test_case.php";

class EventsControllerUploadDownloadTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
        $this->User =& ClassRegistry::init('User');
    }   

    function testUploadNoData() {
        $result = $this->testAction('/events/upload/6',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Full instructions.*?CNICS MI Review packet assembly instructions.doc.*?Packet for.*?MI\s+1006.*?Patient ID:.*?007x.*?Date:.*?2009-02-15.*?Criteria:.*?name1 = value1, name2 = value2.*?Choose a file to upload/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testUploadAgain() {
        $result = $this->testAction('/events/upload/9',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Packet for.*?MI\s+1009.*uploaded for this event on\s*2009-10-27.*Confirm re-upload/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    const CANTUPLOAD = 'You cannot perform upload on this event';

    function testUploadNotUploader() {
        $result = $this->testAction('/events/upload/1',
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTUPLOAD) !== false, 
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data; set confirm checkbox to avoid that error
        $data = array('Event' => array('id' => 1, 'confirmUpload' => 1));
        $result = $this->testAction('/events/upload',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTUPLOAD) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testUploadNoSuchEvent() {
        $result = $this->testAction('/events/upload/100',
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTUPLOAD) !== false, 
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data
        $data = array('Event' => array('id' => 100));
        $result = $this->testAction('/events/upload',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTUPLOAD) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testUploadNoConfirm() {
        $data = array('Event' => array('id' => 9, 'chartFile' => 'whee'));

        $result = $this->testAction('/events/upload',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $expected = '/You must select the confirm checkbox to re-upload.*Packet for.*?MI\s+1009.*uploaded for this event on\s*2009-10-27.*Confirm re-upload/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $this->noCakeErrors($result);
    }

    function testUpload() {
        $name = 'E-02Pdf.pdf';
        $data = array('Event' => array('id' => 6, 'chartFile' => array(
            'size' => 22919, 'name' => $name,
            'type' => 'application/pdf', 'error' => 0, 
            'tmp_name' => '/tmp/something')));

        $result = $this->testAction('/events/upload',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Charts file uploaded.  Size = 22919 bytes.') !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(6);
        
        $expected['Event']['upload_date'] = date('Y-m-d');
        $expected['Event']['uploader_id'] = 1;
        $expected['Event']['status'] = Event::UPLOADED;
        $expected['Event']['original_name'] = $name;

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
    }

    function testUploadFail() {
        $data = array('Event' => array('id' => 6, 'chartFile' => array(
            'size' => 22919, 'name' => 'E-02Pdf.pdf',
            'type' => 'application/pdf', 'error' => 0, 
            'tmp_name' => '/dev/something')));

        $result = $this->testAction('/events/upload',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Upload failed.') !== false, 'Pattern does not appear in result'); 
        $this->noCakeErrors($result);
    }

    function testScrubNoData() {
        $oldEvent = $this->Event->findById(9);
        $result = $this->testAction('/events/scrub/9',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Scrubbing Instructions.*?CNICS MI event scrubbing protocol.doc.*?Upload scrubbed charts for.*?MI\s+1009.*?Site:.*?UW.*?Patient ID:.*?007x.*?Date:.*?2009-02-18.*?Choose a scrubbed file to upload/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $this->assertTrue(strpos($result, 'Needs rescrubbing') === false, 
                          'Rescrubbing message appears!'); 
        $newEvent = $this->Event->findById(9);
        $this->checkArrayDiff($oldEvent, $newEvent, 
                              'event changed even though no data');
    
        $oldEvent = $this->Event->findById(15);
        $result = $this->testAction('/events/scrub/15',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Upload scrubbed charts for.*?MI\s+1015.*?Site:.*?UNC.*?Patient ID:.*?888a.*?Date:.*?2009-02-24.*?Choose a scrubbed file to upload/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $newEvent = $this->Event->findById(15);
        $this->checkArrayDiff($oldEvent, $newEvent, 
                              'event changed even though no data');
    }

    function testScrubNeedsRescrubbing() {
        $oldEvent = $this->Event->findById(17);
        $result = $this->testAction('/events/scrub/17',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/Upload scrubbed charts for.*?MI\s+1017.*?Site:.*?UW.*?Patient ID:.*?007x.*?Date:.*?2009-02-26.*?Needs rescrubbing.  Message:\s+99 problems.*?Choose a scrubbed file to upload/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
        $newEvent = $this->Event->findById(17);
        $this->checkArrayDiff($oldEvent, $newEvent, 
                              'event changed even though no data');
    }

    const CANTSCRUB = 'scrub cannot be performed on this event';
    const BADEVENT = 'No event specified';

    function testScrubBadStatus() {
        $result = $this->testAction('/events/scrub/1',
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTSCRUB) !== false, 
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data; set confirm checkbox to avoid that error
        $data = array('Event' => array('id' => 1, 'confirmScrub' => 1));
        $result = $this->testAction('/events/scrub',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::CANTSCRUB) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testScrubNoSuchEvent() {
        $result = $this->testAction('/events/scrub/100',
            array('return' => 'contents'));
        $this->assertTrue(strpos($result, self::BADEVENT) !== false, 
            'missing error message');
        $this->noCakeErrors($result);

        // same thing with post data
        $data = array('Event' => array('id' => 100));
        $result = $this->testAction('/events/scrub',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, self::BADEVENT) !== false, 
            'missing error message');
        $this->noCakeErrors($result);
    }

    function testScrub() {
        $name = 'E-02Pdf.pdf';
        $data = array('Event' => array('id' => 9, 'chartFile' => array(
            'size' => 22919, 'name' => $name,
            'type' => 'application/pdf', 'error' => 0, 
            'tmp_name' => '/tmp/something')));

        $result = $this->testAction('/events/scrub',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Scrubbed file uploaded.  Size = 22919 bytes.') !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(9);
        
        $expected['Event']['scrub_date'] = date('Y-m-d');
        $expected['Event']['scrubber_id'] = 1;
        $expected['Event']['status'] = Event::SCRUBBED;
        $expected['Event']['file_number'] = -1;

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
        $this->assertTrue(empty($event['Event']['original_file']));
    }

    function testRescrub() {
        $event = $this->Event->findById(15);
        // test with already scrubbed file
        $name = 'E-02Pdf.pdf';
        $data = array('Event' => array('id' => 15, 'chartFile' => array(
            'size' => 22919, 'name' => $name,
            'type' => 'application/pdf', 'error' => 0, 
            'tmp_name' => '/tmp/something')));

        $result = $this->testAction('/events/scrub',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Scrubbed file uploaded.  Size = 22919 bytes.') !== false, 
            'Pattern does not appear in result'); 
        $this->noErrors($result);
        $event = $this->Event->findById(15);
        
        $expected['Event']['scrub_date'] = date('Y-m-d');
        $expected['Event']['scrubber_id'] = 1;
        $expected['Event']['status'] = Event::SCRUBBED;
        $expected['Event']['file_number'] = -1;

        $this->assertContainsFields($expected['Event'], $event['Event'], 
                                    "Event fields don't match");
        $this->assertTrue(empty($event['Event']['original_file']));
    }

    function testScrubFail() {
        $data = array('Event' => array('id' => 9, 'chartFile' => array(
            'size' => 22919, 'name' => 'E-02Pdf.pdf',
            'type' => 'application/pdf', 'error' => 0, 
            'tmp_name' => '/dev/something')));

        $result = $this->testAction('/events/scrub',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'Upload failed.') !== false, 'Pattern does not appear in result'); 
        $this->noCakeErrors($result);
    }

    function testDownloadAdmin() {
        $result = $this->testAction('/events/download/5',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'You should not be downloading this file at this time') !== false, 
                'missing error message: bad status');

        // download for scrubbing, but no file
        $result = $this->testAction('/events/download/9',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            "Couldn't find file to download") !== false, 
                'missing error message: missing raw file');

        // raw download (for scrubbing)
        $filename = $this->Event->pathPrefix() . 
                    $this->Event->rawFileName(17, -1) . '.gz';
        touch($filename);
        $result = $this->testAction('/events/download/17',
            array('data' => null, 'method' => 'get', 'return' => 'vars'));
        $expected['sourcefile'] = $filename;
        $expected['destfile'] = Event::RAW_PREFIX . '_1017.gz';
        $expected['contentType'] = Event::GZIP_MIME1;
        $this->assertContainsFields($expected, $result, "Fields don't match");

        // scrubbed download (for screening)
        $filename = $this->Event->pathPrefix() . 
                    $this->Event->scrubbedFileName(15, -1) . '.pdf';
        touch($filename);
        $result = $this->testAction('/events/download/15',
            array('data' => null, 'method' => 'get', 'return' => 'vars'));
        $expected['sourcefile'] = $filename;
        $expected['destfile'] = Event::SCRUBBED_PREFIX . '_1015.pdf';
        $expected['contentType'] = Event::PDF_MIME;
        $this->assertContainsFields($expected, $result, "Fields don't match2");

        // download charts for an event that's all done
        $filename = $this->Event->pathPrefix() . 
                    $this->Event->scrubbedFileName(13, -1) . '.pdf';
        touch($filename);
        $result = $this->testAction('/events/download/13',
            array('data' => null, 'method' => 'get', 'return' => 'vars'));
        $expected['sourcefile'] = $filename;
        $expected['destfile'] = Event::SCRUBBED_PREFIX . '_1013.pdf';
        $expected['contentType'] = Event::PDF_MIME;
        $this->assertContainsFields($expected, $result, "Fields don't match");
    }

    function testDownloadNoAdmin() {
        $this->User->id = 1;
        $this->User->saveField('admin_flag', 0);

        $result = $this->testAction('/events/download/-5',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'Invalid event id') !== false, 
            'missing error message: bad id');
        $result = $this->testAction('/events/download/13',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            'You should not be downloading this file at this time') !== false, 
                'missing error message: bad status');
        $result = $this->testAction('/events/download/2',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 
            "Couldn't find file to download") !== false, 
                'missing error message: missing scrubbed file');
 
        // scrubbed download (for reviewer)
        $filename = $this->Event->pathPrefix() . 
                    $this->Event->scrubbedFileName(18, -1) . '.pdf';
        touch($filename);
        $result = $this->testAction('/events/download/18',
            array('data' => null, 'method' => 'get', 'return' => 'vars'));
        $expected['sourcefile'] = $filename;
        $expected['destfile'] = Event::SCRUBBED_PREFIX . '_1018.pdf';
        $expected['contentType'] = Event::PDF_MIME;
        $this->assertContainsFields($expected, $result, "Fields don't match2");

        $this->User->id = 1;
        $this->User->saveField('admin_flag', 1);
    }

    function testBeforeFilterAlias() {
        $result = $this->testAction('/events/testAlias',
            array('data' => null, 'method' => 'post', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'You cannot perform upload on events')
                              !== false);
        $this->noCakeErrors($result);
    }

}

?>
