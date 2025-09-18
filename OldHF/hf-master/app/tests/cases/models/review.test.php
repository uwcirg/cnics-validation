<?php

require_once "my_test_case.php";

App::import('User');

class ReviewTestCase extends MyTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');

    function start() {
        parent::start();
        $this->Review =& ClassRegistry::init('Review');
    }

    function testGetMcis() {
        $this->checkArrayDiff($this->Review->getMcis(),
             array('Definite' => 'Definite',
                   'Probable' => 'Probable', 
                   'No' => 'No', 
                   'No [resuscitated cardiac arrest]' => 
                       'No [resuscitated cardiac arrest]'),
             "Mci arrays don't match");
    }

    function testGetTypes() {
        $this->checkArrayDiff($this->Review->getTypes(),
             array('Primary' => 'Primary',
                   'Secondary' => 'Secondary'),
             "Type arrays don't match");
    }

    function testGetFalsePositiveReasons() {
        $this->checkArrayDiff($this->Review->getFalsePositiveReasons(),
             array('Congestive heart failure' => 'Congestive heart failure',
                   'Myocarditis' => 'Myocarditis',
                   'Pericarditis' => 'Pericarditis',
                   'Pulmonary embolism' => 'Pulmonary embolism',
                   'Renal failure' => 'Renal failure',
                   'Severe sepsis/shock' => 'Severe sepsis/shock',
                   'Other' => 'Other'),
             "False positive reason arrays don't match");
    }

    function testGetCriterias() {
        $this->checkArrayDiff($this->Review->getCriterias(),
             array('Standard criteria' => 'Standard criteria', 
                   'PTCA criteria' => 'PTCA criteria', 
                   'CABG criteria' => 'CABG criteria', 
                   'Muscle trauma other than PTCA/CABG' =>
                       'Muscle trauma other than PTCA/CABG'),
             "Criteria arrays don't match");
    }

    function testSecondaryCauses() {
        $this->checkArrayDiff($this->Review->getSecondaryCauses(),
             array('Anaphlaxis' => 'Anaphlaxis',
                   'Arrhythmia' => 'Arrhythmia',
                   'Cocaine or other illicit drug induced vasospasm' => 
                       'Cocaine or other illicit drug induced vasospasm',
                   'GI Bleed' => 'GI Bleed',
                   'MVA' => 'MVA',
                   'Overdose' => 'Overdose',
                   'Procedure related' => 'Procedure related',
                   'Sepsis/bacteremia' => 'Sepsis/bacteremia',
                   'Other' => 'Other'),
             "Secondary cause arrays don't match");
    }
}

?>
