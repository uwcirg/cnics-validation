<?php

class PatientFixture extends CakeTestFixture {
    var $name = 'Patient';
    var $import = 'Patient';

    var $records = array(
        array('id' => 1, 'site_patient_id' => '888a',
              'site' => 'UNC'),
        array('id' => 2, 'site_patient_id' => '999b',
              'site' => 'DFCI'),
        array('id' => 3, 'site_patient_id' => '000c',
              'site' => 'UNC'),
        array('id' => 4, 'site_patient_id' => '111d',
              'site' => 'UCSD'),
        array('id' => 5, 'site_patient_id' => '007x',
              'site' => 'UW')
    );
}

?>
