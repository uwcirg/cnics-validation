<?php

require_once "my_test_case.php";

App::import('Model', 'Event', 'Review', 'AppModel', 'CodedItemBehavior');

class EventSendTestCase extends MyTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');
    private $now;

    function start() {
        parent::start();
        $this->Event =& ClassRegistry::init('Event');
        $this->CodedItemBehavior =& ClassRegistry::init('CodedItemBehavior');
        $this->EmailerBehavior =& ClassRegistry::init('EmailerBehavior');
        $this->now = date('Y-m-d');
    }

    function testSendAllErrors() {
        // no events
        $reviewers = array(1 => 'gsbarnes@washington.edu', 
                           2 => 'user@example.com');
        $data = array('Send' => array('reviewer_id' => 2),
                      'Event' => array());
        $result = $this->Event->sendAll($data, null);
        $expected = array('error' => null,
                          'sent' => 0,
                          'notFound' => 0,
                          'badEmail' => 0,
                          'cannotSend' => 0,
                          'notFoundList' => '',
                          'cannotSendList' => '',
                          'badEmailList' => '');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for no events');

        /* non-existant, non-sendable events */
        $data = array('Event' => array('send100' => true,
                                       'send99' => true,
                                       'send15' => true,
                                       'send16' => true,
                                       'send20' => true,
                                       'send24' => true));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 1));
        $result = $this->Event->sendAll($data, $user1);
        $expected = array('error' => null,
                          'sent' => 0,
                          'notFound' => 2,
                          'cannotSend' => 3,
                          'badEmail' => 1,
                          'notFoundList' => ' 100 99',
                          'cannotSendList' => ' 15 16 20',
                          'badEmailList' => ' 24');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for bad events');

        // bad authuser
        $data = array('Event' => array('send22' => true));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 0));
        $result = $this->Event->sendAll($data, $user1);
        $expected = array('error' => 'You cannot send packets');
        $this->assertContainsFields($expected, $result);
   }

   function testSendAll() {
        /* bad events as above, plus 2 that should work and some 
           nonsense variables */
        $event22 = $this->Event->findById(22);
        $event23 = $this->Event->findById(23);
        $event24 = $this->Event->findById(24);
        $data = array('Event' => array('send100' => true,
                                       'send99' => true,
                                       'send15' => true,
                                       'send16' => true,
                                       'send20' => true,
                                       'send24' => true,
                                       'send22' => true,
                                       'send23' => true,
                                       'boo' => true,
                                       'send500' => false));
        $user1 = array('User' => array('id' => 5,
                                       'admin_flag' => 1));
        $result = $this->Event->sendAll($data, $user1);
        $expected = array('error' => null,
                          'sent' => 2,
                          'notFound' => 2,
                          'cannotSend' => 3,
                          'badEmail' => 1,
                          'notFoundList' => ' 100 99',
                          'cannotSendList' => ' 15 16 20',
                          'badEmailList' => ' 24');
        $this->checkArrayDiff($result, $expected, 
                              'mismatched return value for bad/good events');
        $newEvent = $this->Event->findById(22);
        $event22['Event']['status'] = Event::SENT;
        $event22['Event']['sender_id'] = 5;
        $event22['Event']['send_date'] = date('Y-m-d');
        $this->checkArrayDiff($event22['Event'], $newEvent['Event'], 
             'Sending (22) does not change as expected');
        $newEvent = $this->Event->findById(23);
        $event23['Event']['status'] = Event::SENT;
        $event23['Event']['sender_id'] = 5;
        $event23['Event']['send_date'] = date('Y-m-d');
        $this->checkArrayDiff($event23['Event'], $newEvent['Event'], 
             'Sending (23) does not change as expected');
        // 24 has a reviewer with a bad e-mail, but event still gets changed
        $newEvent = $this->Event->findById(24);
        $event24['Event']['status'] = Event::SENT;
        $event24['Event']['sender_id'] = 5;
        $event24['Event']['send_date'] = date('Y-m-d');
        $this->checkArrayDiff($event24['Event'], $newEvent['Event'], 
             'Sending (24) does not change as expected');
    }

    function testSend() {
        $event25 = $this->Event->findById(25);
        $result = $this->Event->send($event25, 3);
        $this->assertEqual($result, 'Email to someone@ and user@com. failed.');

        $event24 = $this->Event->findById(24);
        $result = $this->Event->send($event24, 3);
        $this->assertEqual($result, 'Email to user@com. failed.');

        $event14 = $this->Event->findById(14);
        $result = $this->Event->send($event14, 3);
        $this->assertNull($result);
        $newEvent = $this->Event->findById(14);
        $event14['Event']['status'] = Event::SENT;
        $event14['Event']['send_date'] = date('Y-m-d');
        $event14['Event']['sender_id'] = 3;
        $this->checkArrayDiff($event14['Event'], $newEvent['Event'], 
                              'sending does not change as expected');
    }

    function testToBeSent() {
        $event1 = array('Event' => array('status' => Event::CREATED));
        $event2 = array('Event' => array('status' => Event::SENT));
        $event3 = array('Event' => array('status' => Event::ASSIGNED));

        $this->assertEqual($this->Event->toBeSent($event1), false);
        $this->assertEqual($this->Event->toBeSent($event2), false);
        $this->assertEqual($this->Event->toBeSent($event3), true);
    }

    function testCanSend() {
        $user1 = array('User' => array('id' => 1,
                                       'admin_flag' => 1));
        $user2 = array('User' => array('id' => 1,
                                       'admin_flag' => 0));
        $event1 = array('Event' => array('status' => Event::ASSIGNED));

        $this->assertEqual($this->Event->canSend($event1, $user1), true);
        $this->assertEqual($this->Event->canSend($event1, $user2), false);
    }

    function testEmailPacket() {
        $applicationUC = strpos(Router::url('/', true), 'cnics') != false ?                     'CNICS' : 'NA-ACCORD';
        $applicationLC = $applicationUC == 'CNICS' ? 'cnics' : 'naaccord';
    
        /* Help email address */
        $help = $applicationLC . '@rt.cirg.washington.edu';
 
        /* Subject for a packet email */
        $packetSubject = "$applicationUC " . LONG_NAME . " event ready for review"; 
        
        /* Packet email body, part 1, when attachment included */
        $packetBody1Attachment =                                                                "You have been assigned to review an event for the $applicationUC " . LONG_NAME . " Project.  The packet associated with the event is attached to this e-mail message.  You can also download the packet from the project website at this URL:.";
                
        /* Packet email body, part 1, no attachment */
        $packetBody1NoAttachment =                                                              "You have been assigned to review an event for the $applicationUC " . LONG_NAME . " Project.  You can download the packet associated with the event from the project website at this URL:.";
                
        /* Packet email body, part 2 */
        $packetBody2 = "To review the event, please visit the following URL:";
        
        /* Packet email body, part 3 */
        $packetBody3 = "A list of your outstanding reviews can be found here:";
        
        /* Packet sig */                                                                $packetSig = "\r\nThank you so much for completing reviews!\r\n\r\nSincerely,\r\nThe $applicationUC " . LONG_NAME . " team";

        $reviewer1 = array('username' => 'user@example.com',
                           'first_name' => 'Joe', 'last_name' => 'User');
        $reviewer2 = array('username' => 'someone@',
                           'first_name' => 'Some', 'last_name' => 'Boddy');
        $event = array('Event' => array('id' => 25, 'file_number' => -1));
        $this->assertEqual($this->Event->emailPacket($reviewer2, 1, $event),
                           'Email failed');

        $this->assertNull($this->Event->emailPacket($reviewer1, 2, $event));

        // test with no attachment
        $result = $this->Event->emailPacket($reviewer1, 2, $event, true, false);
        $expectedBody = 
                        "Dear Joe User, \r\n\r\n" .
                        $packetBody1NoAttachment. 
                        "\r\n\r\n" .
                        Router::url("/events/download/25", true) . "\r\n\r\n" .
                        $packetBody2 . "\r\n\r\n" .
                        Router::url("/events/review2/25", true) . "\r\n\r\n" .
                        $packetBody3 . "\r\n\r\n" .
                        Router::url('/', true) . "\r\n\r\n" .
                        $packetSig . "\r\n";
                        
        $this->assertEqual('user@example.com', $result[0], 
                           'User names do not match');
        $this->assertEqual($packetSubject, $result[1], 
                           'Subjects do not match');
        $this->assertEqual($result[2], $expectedBody, 'body does not match');
        $this->assertEqual($result[3], 
            $this->EmailerBehavior->fromHeaders($help),
            'headers do not match');

        // test with attachment
        $result = $this->Event->emailPacket($reviewer1, 2, $event, true, true);
        $expectedBodyStart = 
            "/This is a multi-part message in MIME format.*?-------=.*?Content-Type: text\/plain; charset=ISO-8859-1.*?Content-Transfer-Encoding: 7bit.*?Dear Joe User,/sm";
        $expectedBodyMiddle = 
                        "Dear Joe User, \r\n\r\n" .
                        $packetBody1Attachment . 
                        "\r\n\r\n" .
                        Router::url("/events/download/25", true) . "\r\n\r\n" .
                        $packetBody2 . "\r\n\r\n" .
                        Router::url("/events/review2/25", true) . "\r\n\r\n" .
                        $packetBody3 . "\r\n\r\n" .
                        Router::url('/', true) . "\r\n\r\n" .
                        $packetSig . "\r\n\n--";
        $expectedBodyEnd = "/-------=.*?Content-Type: application\/pdf; name=\"clean_1025.pdf\".*?Content-Transfer-Encoding: base64.*?Content-Disposition: attachment; filename=\"clean_1025.pdf\".*?MTMyMix/sm";
        $expectedHeadersStart = 
            $this->EmailerBehavior->fromHeaders($help) .
            "\nMIME-Version: 1.0\nContent-Type: multipart/mixed; boundary=\"-----";
        $expectedHeadersEnd = "/-----=[0-9,a-f]+\"$/sm";
                        
        $this->assertEqual('user@example.com', $result[0], 
                           'User names do not match');
        $this->assertEqual($packetSubject, $result[1], 
                           'Subjects do not match');
        // must split up body so that we can match on random boundary
        $this->assertTrue(preg_match($expectedBodyStart, $result[2]) == 1,
                          "start of body did not match");
        $this->assertTrue(strpos($result[2], $expectedBodyMiddle) > 0,
                          "couldn't find middle of body");
        $this->assertTrue(preg_match($expectedBodyEnd, $result[2]) == 1,
                          "end of body did not match");
        $this->assertTrue(strpos($result[3], $expectedHeadersStart) !== false,
                          "couldn't find start of header");
        $this->assertTrue(preg_match($expectedHeadersEnd, $result[3]) == 1,
                          "end of header did not match");
    }

    function testDownloadUrl() {
        $event = array('Event' => array('id' => 5));
        $this->assertEqual($this->Event->downloadUrl($event), 
                           Router::url("/events/download/5", true));
    }

    function testReviewUrl() {
        $event = array('Event' => array('id' => 5));
        $this->assertEqual($this->Event->reviewUrl($event, 2), 
                           Router::url("/events/review2/5", true));
    }

    function testIndexUrl() {
        $this->assertEqual($this->Event->indexUrl(), Router::url('/', true));
    }

    function testFromHeaders() {
        $address = 'someone@example.com';
        $this->assertEqual($this->EmailerBehavior->fromHeaders($address),
                           "From: {$address}\nReply-To: {$address}\n" .
                           'X-Mailer: PHP/' . phpversion());
    }

    function testAttachHeaders() {
        $boundary = "blah blah blah";
        $this->assertEqual($this->EmailerBehavior->attachHeaders($boundary),
                           "MIME-Version: 1.0\nContent-Type: multipart/mixed;" .
                           " boundary=\"$boundary\"");
    }

    function testMultipartHeaders() {
        $filename = "whee";
        $mimetype = "application/pdf";
        $this->assertEqual(
            $this->EmailerBehavior->multipartHeaders('whee', 'application/pdf'),
            "Content-Type: application/pdf; name=\"whee\"\n" .
            "Content-Transfer-Encoding: base64\n" .
            "Content-Disposition: attachment; filename=\"whee\"\n\n");

    }

    function testMimeStart() {
        $boundary = "blah blah blah";
        $this->assertEqual($this->EmailerBehavior->mimeStart($boundary),
                           EmailerBehavior::MIMESTART1 . $boundary .
                           EmailerBehavior::MIMESTART2);
    }

    function testGetDownloadInfo() {
        $pathPrefix = $this->Event->pathPrefix();

        $result = $this->Event->getDownloadInfo("whee", "pdf", "dprefix", 5);
        $expected = array('sourcefile' => "{$pathPrefix}whee.pdf",
                          'destfile' => 'dprefix_1005.pdf',
                          'contentType' => Event::PDF_MIME);
        $this->checkArrayDiff($expected, $result, 'Bad result for pdf');

        $result = $this->Event->getDownloadInfo("whee", "gz", "dprefix", 5);
        $expected = array('sourcefile' => "{$pathPrefix}whee.gz",
                          'destfile' => 'dprefix_1005.gz',
                          'contentType' => Event::GZIP_MIME1);
        $this->checkArrayDiff($expected, $result, 'Bad result for gz');

        $result = $this->Event->getDownloadInfo("whee", "zip", "dprefix", 5);
        $expected = array('sourcefile' => "{$pathPrefix}whee.zip",
                          'destfile' => 'dprefix_1005.zip',
                          'contentType' => Event::ZIP_MIME_DEFAULT);
        $this->checkArrayDiff($expected, $result, 'Bad result for zip');
    }

    const ATTACH_CONTENTS = 
    "MTMyMixVVywyMDA4LTEwLTEwLAoxMzIyLEpILDIwMDgtMTAtMTAsCjEzMjIsCmJvPGI+b29ib2Jv\r\nYm9ib2I8L2I+CjEzMjIsQ1dSVSwyMDA4LTEwLTEwLAoxMzIyLENXUlUsMjAwOC0xMC0xMCxDSyw1\r\nCjEzMjIsVVcsMjAwOC0xMC0xMCxDSyw1CjEzMjIsSkgsMjAwOC0xMC0xMCxDSyw1CjEzMjIsVUNT\r\nRiwyMDA4LTEwLTEwLHRyb3BvbmlucywyLENLLDUKMTMyMixVTkMsMjAwOC0xMC0xMCx0cm9wb25p\r\nbnMsMixDSyw1LHByb2NlZHVyZXMsIkNQUixkZWZpYnJpbGxhdGlvbiIKMTMyMixVQ1NGLCxDSyw1\r\nCjEzMjIsVVcsMjAwOC0xMC0xMCwKMTMyMiw8Yj5KSDwvYj4sMjAwOC0xMC0xMCxDSyw1CjEzMjIs\r\nCmJvb29ib2JvYm9ib2IKMTMyMiwgQ1dSVSwgMjAwMy0wMy0wNCwgQ0sgTUIgTSwgMzYsIENLIE1C\r\nIE0sIDMyCg==\r\n";

    function testAttachment() {
        $boundary = "blah blah blah";
        $event = array('Event' => array('id' => 25, 'file_number' => '-1'));
        $result = $this->EmailerBehavior->attachment($this->Event, $event, 
                                                     $boundary);
        $this->assertEqual($result, 
            "\n--blah blah blah\n" .
            $this->EmailerBehavior->multipartHeaders('clean_1025.pdf', 
                                                     'application/pdf') .
            self::ATTACH_CONTENTS . "\n");
    }
}

?>
