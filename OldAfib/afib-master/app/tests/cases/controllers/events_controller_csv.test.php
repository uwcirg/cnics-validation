<?php

require_once "my_controller_test_case.php";

class EventsControllerCsvTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 'app.patient', 
                          'app.solicitation', 'app.user', 'app.criteria');

    function startCase() {
        parent::startCase();
        $this->Event =& ClassRegistry::init('Event');
    }

    function testCsvHeaders() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/^MI,"Patient Site","Site Patient ID","Event Date",Status,Creator,"Criteria: MI Dx","Criteria: CKMB_Q","Criteria: CKMB_M","Criteria: CKMB","Criteria: Troponin","Criteria: Other","Add Date",Uploader,"Upload Date","Marker \(no packet\)","No Packet Reason","Two Attempts\?","Prior Event Date","Prior Event Onsite\?","Other Cause","Mark No Packet Date",Scrubber,"Scrub Date",Screener,"Screen Date","Rescrub Message","Reject Message",Assigner,"Assign Date",Sender,"Send Date","Reviewer 1","Review 1 MI","Review 1 Abnormal CE Values\?","Review 1 CE Criteria","Review 1 Chest Pain\?","Review 1 ECG Changes\?","Review 1 LVM by Imaging\?","Review 1 Clinical Intervention\?","Review 1 Type","Review 1 Secondary Cause","Review 1 Other Cause","Review 1 False Positive\?","Review 1 False Positive Reason","Review 1 Current Tobacco Use\?","Review 1 Past Tobacco Use\?","Review 1 Cocaine Use\?","Review 1 Family History\\?","Review 1 Date","Reviewer 2","Review 2 MI","Review 2 Abnormal CE Values\?","Review 2 CE Criteria","Review 2 Chest Pain\?","Review 2 ECG Changes\?","Review 2 LVM by Imaging\?","Review 2 Clinical Intervention\?","Review 2 Type","Review 2 Secondary Cause","Review 2 Other Cause","Review 2 False Positive\?","Review 2 False Positive Reason","Review 2 Current Tobacco Use\?","Review 2 Past Tobacco Use\?","Review 2 Cocaine Use\?","Review 2 Family History\?","Review 2 Date","3rd Review Assigner","3rd Review Assign Date","Reviewer 3","Review 3 MI","Review 3 Abnormal CE Values\?","Review 3 CE Criteria","Review 3 Chest Pain\?","Review 3 ECG Changes\?","Review 3 LVM by Imaging\?","Review 3 Clinical Intervention\?","Review 3 Type","Review 3 Secondary Cause","Review 3 Other Cause","Review 3 False Positive\?","Review 3 False Positive Reason","Review 3 Current Tobacco Use\?","Review 3 Past Tobacco Use\?","Review 3 Cocaine Use\?","Review 3 Family History\?","Review 3 Date"/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvBasic() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected1 = '/"Review 3 Date"\n1001,UNC,888a,2009-02-10/sm';
        $this->assertTrue(preg_match($expected1, $result) == 1, 
                          'First line does not match'); 
        $expected2 = '/\n1036,UW,007x,2009-03-17,created,gsbarnes@washington.edu,,,,,,,2010-05-05,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected2, $result) == 1, 
                          'Basic event does not match'); 
    }

    function testCsvCriteriaMidx() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1008,UW,007x,2009-02-17,sent,gsbarnes@washington.edu,TRUE,,,,,,2009-10-17,user3@example.com,,2009-10-23,,,,,,,,user@example.com,2010-02-10,user3@example.com,2010-02-16,,,gsbarnes@washington.edu,2010-02-09,user3@example.com,2010-02-07/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: mi dx'); 
        $expected = '/\n1016,UW,007x,2009-02-25,scrubbed,gsbarnes@washington.edu,TRUE2,,,,,,2010-01-20,user3@example.com,,2010-01-21,,,,,,,,user@example.com,2010-02-22,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: mi_dx'); 
        $expected = '/\n1020,UNC,888a,2009-03-01,screened,gsbarnes@washington.edu,TRUE3,,,,,,2010-03-05/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: diagnosis'); 
        $expected = '/\n1018,UNC,888a,2009-02-27,reviewer2_done,gsbarnes@washington.edu,TRUE4,,,,,,2010-02-27/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: dx'); 
        $expected = '/\n1010,UW,007x,2009-02-19,[^\,]*,gsbarnes@washington.edu,TRUE5,,,,,,2009-12-14/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result MI_Dx'); 
    }

    function testCsvCriteriaCkmbq() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1021,UNC,888a,2009-03-02,screened,gsbarnes@washington.edu,,30.2,,,,,2010-03-09/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: ckmb_q'); 
        $expected = '/\n1022,UNC,888a,2009-03-03,assigned,gsbarnes@washington.edu,,31.3,,,,,2010-03-13/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: CKMB_Q'); 
        $expected = '/\n1023,UNC,888a,2009-03-04,assigned,gsbarnes@washington.edu,,32.4,,,,,2010-03-18/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
            'Pattern does not appear in result: creatine kinase mb quotient'); 
    }

    function testCsvCriteriaCkmbm() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1024,UNC,888a,2009-03-05,assigned,gsbarnes@washington.edu,,,300,,,,2010-03-23/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: ckmb_m'); 
        $expected = '/\n1025,UNC,888a,2009-03-06,assigned,gsbarnes@washington.edu,,,313,,,,2010-03-28/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: CKMB_M'); 
        $expected = '/\n1026,UNC,888a,2009-03-07,reviewer1_done,gsbarnes@washington.edu,,,324,,,,2010-04-02/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
            'Pattern does not appear in result: creatine kinase mb mass'); 
    }

    function testCsvCriteriaTroponin() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1028,UNC,888a,2009-03-09,done,gsbarnes@washington.edu,,,,,15,,2010-04-19/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: troponin'); 
        $expected = '/\n1030,UNC,888a,2009-03-11,third_review_needed,gsbarnes@washington.edu,,,,,16,,2010-05-03/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: TROPONIN'); 
        $expected = '/\n1031,UNC,888a,2009-03-12,third_review_needed,gsbarnes@washington.edu,,,,,17,,2010-05-25/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
            'Pattern does not appear in result: troponin t'); 
        $expected = '/\n1032,UNC,888a,2009-03-13,third_review_needed,gsbarnes@washington.edu,,,,,18,,2010-06-03/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
            'Pattern does not appear in result: troponin i'); 
        $expected = '/\n1033,UNC,888a,2009-03-14,third_review_assigned,gsbarnes@washington.edu,,,,,19,,2010-06-12/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
            'Pattern does not appear in result: trop_i'); 
        $expected = '/\n1034,UNC,888a,2009-03-15,reviewer1_done,gsbarnes@washington.edu,,,,,20,,2010-04-21/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
            'Pattern does not appear in result: trop_t'); 
        $expected = '/\n1035,UNC,888a,2009-03-16,reviewer2_done,gsbarnes@washington.edu,,,,,21,,2010-04-28/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
            'Pattern does not appear in result: troponin i (tni)'); 
        $expected = '/\n1001,UNC,888a,2009-02-10,third_review_needed,gsbarnes@washington.edu,,,,,22,,2009-09-15/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
            'Pattern does not appear in result: troponin t (tnt)'); 
    }

    function testCsvCriteriaOther() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1006,UW,007x,2009-02-15,created,gsbarnes@washington.edu,,,,,,name1:value1;name2:value2;,2009-10-15,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvCriteriaMultiple() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1002,UNC,888a,2009-02-11,sent,gsbarnes@washington.edu,TRUE7,7,7.7,77,777,name7:value7;name8:value8;,2009-09-15/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: all criteria'); 
        $expected = '/\n1003,UNC,888a,2009-02-12,reviewer1_done,gsbarnes@washington.edu,TRUE8,,,,,name9:value9;,2009-09-15/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: 2 criteria'); 
    }

    function testCsvReview1() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1003,UNC,888a,2009-02-12,reviewer1_done,gsbarnes@washington.edu,TRUE8,,,,,name9:value9;,2009-09-15,user3@example.com,,2009-10-20,,,,,,,,user@example.com,2010-02-01,user3@example.com,2010-02-14,,,gsbarnes@washington.edu,2010-02-02,user3@example.com,2010-02-03,user3@example.com,Probable,Yes,"Standard criteria",Yes,Yes,Yes,,Secondary,Overdose,,Yes,"Renal failure",Yes,Yes,No,No,2010-02-04,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvReview2() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1004,UNC,888a,2009-02-13,reviewer2_done,gsbarnes@washington.edu,,,,,,,2009-09-15,user3@example.com,,2009-10-21,,,,,,,,user@example.com,2010-01-29,user3@example.com,2010-02-15,,,gsbarnes@washington.edu,2010-01-30,user3@example.com,2010-01-31,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,,user3@example.com,Probable,Yes,"Standard criteria",Yes,Yes,Yes,,Secondary,Overdose,,Yes,"Renal failure",No,No,Yes,Yes,2010-02-01,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvReview12() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1001,UNC,888a,2009-02-10,third_review_needed,gsbarnes@washington.edu,,,,,22,,2009-09-15,user3@example.com,,2009-10-18,,,,,,,,user@example.com,2010-01-19,user3@example.com,2010-02-12,,,gsbarnes@washington.edu,2010-01-20,user3@example.com,2010-01-21,gsbarnes@washington.edu,No,,,,,,Yes,,,,,,,,,,2010-01-22,user3@example.com,Probable,Yes,"Standard criteria",Yes,Yes,Yes,,Secondary,Other,boo,Yes,"Renal failure",No,No,No,No,2010-01-23,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvReview123() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1027,UNC,888a,2009-03-08,done,gsbarnes@washington.edu,,,,,,,2010-04-09,user3@example.com,,2010-04-10,,,,,,,,user@example.com,2010-04-11,user3@example.com,2010-04-12,,,gsbarnes@washington.edu,2010-04-13,user3@example.com,2010-04-14,gsbarnes@washington.edu,Probable,Yes,"CABG criteria",No,No,Yes,,Secondary,Overdose,,Yes,"Renal failure",No,Yes,No,Yes,2010-04-15,user3@example.com,Definite,No,,No,Yes,Yes,,Primary,,,Yes,"Renal failure",Yes,No,Yes,No,2010-04-16,user3@example.com,2010-04-17,user2@example.com,No,,,,,,No,,,,,,,,,,2010-04-18/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: 1st 123'); 
        $expected = '/\n1028,UNC,888a,2009-03-09,done,gsbarnes@washington.edu,,,,,15,,2010-04-19,user3@example.com,,2010-04-20,,,,,,,,user@example.com,2010-04-21,user3@example.com,2010-04-22,,,gsbarnes@washington.edu,2010-04-23,user3@example.com,2010-04-24,gsbarnes@washington.edu,Definite,No,,No,Yes,Yes,,Secondary,Other,something,Yes,"Congestive heart failure",Yes,No,Yes,No,2010-04-25,user3@example.com,No,,,,,,No,,,,,,,,,,2010-04-26,user3@example.com,2010-04-27,user2@example.com,Probable,Yes,"CABG criteria",No,No,Yes,,Secondary,Other,whoo,Yes,Myocarditis,No,Yes,No,Yes,2010-04-28/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result: 2nd 123'); 
    }

    function testCsvNoPacketAvailableOutsideHospital() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1037,UW,007x,2009-03-18,no_packet_available,gsbarnes@washington.edu,,,,,,,2010-05-06,,,,user3@example.com,"Outside hospital",No,,,,2010-05-07,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvNoPacketAvailableAscertainmentDiagnosisError() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1038,UW,007x,2009-03-19,no_packet_available,gsbarnes@washington.edu,,,,,,,2010-05-08,,,,user3@example.com,"Ascertainment diagnosis error",,,,,2010-05-09,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvNoPacketAvailableAscertainmentDiagnosisPriorEvent1() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1039,UW,007x,2009-03-20,no_packet_available,gsbarnes@washington.edu,,,,,,,2010-05-10,,,,user3@example.com,"Ascertainment diagnosis referred to a prior event",,2003-00,Yes,,2010-05-11,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvNoPacketAvailableAscertainmentDiagnosisPriorEvent2() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1040,UW,007x,2009-03-21,no_packet_available,gsbarnes@washington.edu,,,,,,,2010-05-12,,,,user3@example.com,"Ascertainment diagnosis referred to a prior event",,unknown,No,,2010-05-13,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvNoPacketAvailableOther() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1041,UW,007x,2009-03-22,no_packet_available,gsbarnes@washington.edu,,,,,,,2010-05-14,,,,user3@example.com,Other,,,,"Bad fire",2010-05-15,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvThirdReviewDone() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1012,UNC,888a,2009-02-21,done,gsbarnes@washington.edu,,,,,,,2009-12-29,user3@example.com,,2009-12-30,,,,,,,,user@example.com,2010-01-06,user3@example.com,2010-02-19,,,gsbarnes@washington.edu,2010-01-07,user3@example.com,2010-01-08,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,2010-01-09,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,2010-01-10,user3@example.com,2010-01-11,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,2010-01-12/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvThirdReviewAssigned() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1011,UNC,888a,2009-02-20,third_review_assigned,gsbarnes@washington.edu,,,,,,,2009-12-16,user3@example.com,,2009-12-17,,,,,,,,user@example.com,2010-01-13,user3@example.com,2010-02-18,,,gsbarnes@washington.edu,2010-01-14,user3@example.com,2010-01-15,user2@example.com,,,,,,,,,,,,,,,,,2010-01-16,user@example.com,,,,,,,,,,,,,,,,,2010-01-17,user3@example.com,2010-01-18,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvThirdReviewNeeded() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1001,UNC,888a,2009-02-10,third_review_needed,gsbarnes@washington.edu,,,,,22,,2009-09-15,user3@example.com,,2009-10-18,,,,,,,,user@example.com,2010-01-19,user3@example.com,2010-02-12,,,gsbarnes@washington.edu,2010-01-20,user3@example.com,2010-01-21,gsbarnes@washington.edu,No,,,,,,Yes,,,,,,,,,,2010-01-22,user3@example.com,Probable,Yes,"Standard criteria",Yes,Yes,Yes,,Secondary,Other,boo,Yes,"Renal failure",No,No,No,No,2010-01-23,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvReviewed2Done() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1013,UNC,888a,2009-02-22,done,gsbarnes@washington.edu,,,,,,,2009-09-15,user3@example.com,,2010-01-01,,,,,,,,user@example.com,2010-01-24,user3@example.com,2010-02-20,,,gsbarnes@washington.edu,2010-01-25,user3@example.com,2010-01-26,user2@example.com,.*?,2010-01-28,gsbarnes@washington.edu,.*?,2010-01-27,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvReviewed2() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1004,UNC,888a,2009-02-13,reviewer2_done,gsbarnes@washington.edu,,,,,,,2009-09-15,user3@example.com,,2009-10-21,,,,,,,,user@example.com,2010-01-29,user3@example.com,2010-02-15,,,gsbarnes@washington.edu,2010-01-30,user3@example.com,2010-01-31,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,,user3@example.com,.*?,2010-02-01,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvReviewed1() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1003,UNC,888a,2009-02-12,reviewer1_done,gsbarnes@washington.edu,TRUE8,,,,,name9:value9;,2009-09-15,user3@example.com,,2009-10-20,,,,,,,,user@example.com,2010-02-01,user3@example.com,2010-02-14,,,gsbarnes@washington.edu,2010-02-02,user3@example.com,2010-02-03,user3@example.com,.*?,2010-02-04,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm'; 
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvSent() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1002,UNC,888a,2009-02-11,sent,gsbarnes@washington.edu,TRUE7,7,7.7,77,777,name7:value7;name8:value8;,2009-09-15,user3@example.com,,2009-10-19,,,,,,,,user@example.com,2010-02-05,user3@example.com,2010-02-13,,,gsbarnes@washington.edu,2010-02-06,user3@example.com,2010-02-07,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,,user3@example.com,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvAssigned() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1014,UNC,888a,2009-02-23,assigned,gsbarnes@washington.edu,,,,,,,2010-01-02,user3@example.com,,2010-01-03,,,,,,,,user@example.com,2010-02-08,user3@example.com,2010-02-21,,,gsbarnes@washington.edu,2010-02-09,,,user2@example.com,,,,,,,,,,,,,,,,,,gsbarnes@washington.edu,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvScreened() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1019,UNC,888a,2009-02-28,screened,gsbarnes@washington.edu,,,,,,,2010-02-23,user3@example.com,,2010-02-24,,,,,,,,user@example.com,2010-02-25,user3@example.com,2010-02-26,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvRejected() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1029,UNC,888a,2009-03-10,rejected,gsbarnes@washington.edu,,,,,,,2010-04-29,user3@example.com,,2010-04-30,,,,,,,,user@example.com,2010-05-01,user3@example.com,2010-05-02,,"shifty eyes",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvToBeRescrubbed() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1017,UW,007x,2009-02-26,uploaded,gsbarnes@washington.edu,,,,,,,2010-02-24,user3@example.com,,2010-02-25,,,,,,,,user@example.com,2010-05-03,user3@example.com,2010-05-04,"99 problems",,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvScrubbed() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1015,UNC,888a,2009-02-24,scrubbed,gsbarnes@washington.edu,,,,,,,2010-01-04,user3@example.com,,2010-01-05,,,,,,,,user@example.com,2010-02-10,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

    function testCsvUploaded() {
        $result = $this->testAction('/events/getCsv',
            array('return' => 'contents'));
        $this->noErrors($result);
        $expected = '/\n1009,UW,007x,2009-02-18,uploaded,gsbarnes@washington.edu,,,,,,name4:value4;,2009-10-26,user3@example.com,"Here it is",2009-10-27,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          'Pattern does not appear in result'); 
    }

// no packet available and created already covered above
}

?>
