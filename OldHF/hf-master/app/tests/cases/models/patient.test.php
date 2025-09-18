<?php

App::import('Model', 'Patient');

class PatientTestCase extends CakeTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');
    function start() {
        parent::start();
        $this->Patient =& ClassRegistry::init('Patient');
    }

    function testGetSiteArray() {
        $result = $this->Patient->getSiteArray();
        $this->assertEqual(count($result), 5, 'Should be 5 sites');
        $this->assertNotNull($result['UNC'], 'Missing UNC site');
        $this->assertEqual($result['UNC'], 'UNC', "Wrong value {$result['UNC']} for UNC");
        $this->assertNotNull($result['UW'], 'Missing UW site');
        $this->assertEqual($result['UW'], 'UW', "Wrong value {$result['UW']} for UW");
        $this->assertNotNull($result['CWRU'], 'Missing CWRU site');
        $this->assertEqual($result['CWRU'], 'CWRU', "Wrong value {$result['CWRU']} for CWRU");
        $this->assertNotNull($result['UCSF'], 'Missing UCSF site');
        $this->assertEqual($result['UCSF'], 'UCSF', "Wrong value {$result['UCSF']} for JH");
        $this->assertNotNull($result['JH'], 'Missing JH site');
        $this->assertEqual($result['JH'], 'JH', "Wrong value {$result['JH']} for JH");
    }
}

?>
