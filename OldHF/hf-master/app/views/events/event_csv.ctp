<?php

function flag_output($flag) {
    if (empty($flag)) {
        return '';
    } else if ($flag) {
        return 'yes';
    } else {  // isn't going to happen; 0  is empty
        return 'no';
    }
}

$reviewHeaders = array(
  'Signs?', 
  'Diagnosed?',
  'Prescribed?',
  'Onset Date',
  'Congestion',
  'Echo?',
  'LVEF',
  'Low LVEF?',
  'Structural abnormalities?',
  'LVCD?',
  'Diastolic Dysfunction?',
  'Valve Disease?',
  'Lab',
  'Type',
  'Classification',
  'Ischemic?',
  'Non-ischemic?',
  'NI_Valvular?',
  'NI_Infiltrative?',
  'NI_IM?',
  'NI_Obstructive?',
  'NI_Recreational?',
  'NI_Prescription?',
  'NI_Hypertensive?',
  'NI_Renal?',
  'NI_Cardiomyopathy?',
  'NI_Covid?',
  'NI_Sepsis?',
  'NI_Metabolic?',
  'NI_PD?',
  'NI_Hypertrophic?',
  'NI_Other?',
  'NI_Other',
  'Unknown Etiology?',
  'Presentation',
  'More Info needed?',
  'More Info Text');

    // headings
    $csv->addField('HF');
    $csv->addField('Patient ID');
    $csv->addField('Patient Site');
    $csv->addField('Site Patient ID');
    $csv->addField('Event Date');
    $csv->addField('Status');
    $csv->addField('Creator');
    $csv->addField('Criteria: Dx');
    $csv->addField('Criteria: Proc');
    $csv->addField('Criteria: Other');
    $csv->addField('Add Date');
    $csv->addField('Uploader');
    $csv->addField('Upload Date');
    $csv->addField('Marker (no packet)');
    $csv->addField('No Packet Reason');
    $csv->addField('Two Attempts?');
    $csv->addField('Prior Event Date');
    $csv->addField('Prior Event Onsite?');
    $csv->addField('Other Cause');
    $csv->addField('Mark No Packet Date');
    $csv->addField('Scrubber');
    $csv->addField('Scrub Date');
    $csv->addField('Screener');
    $csv->addField('Screen Date');
    $csv->addField('Rescrub Message');
    $csv->addField('Reject Message');
    $csv->addField('Assigner');
    $csv->addField('Assign Date');
    $csv->addField('Sender');
    $csv->addField('Send Date');
    $csv->addField('3rd Review Assigner');
    $csv->addField('3rd Review Assign Date');

    foreach (array('1', '2', '3') as $reviewerNum) {
        $csv->addField("Reviewer $reviewerNum");
        $csv->addField("Review $reviewerNum date");

        foreach ($reviewHeaders as $rh) {
            $csv->addField("Review $reviewerNum $rh");
        }
    }

    $csv->addField('Overall Type');
    $csv->addField('Overall Classification');
    $csv->addField('Overall Low LVEF?');
    $csv->addField('Overall Diastolic Dysfunction?');
    $csv->addField('Overall Presentation');
    $csv->addField('Overall Ischemic?');
    $csv->addField('Overall Non-ischemic?');
    $csv->addField('Overall Unknown Etiology?');
    $csv->endRow();

    foreach ($events as $event) {
        $csv->addField($event['Event']['id']);
        $csv->addField($event['Patient']['id']);
        $csv->addField($event['Patient']['site']);
        $csv->addField($event['Patient']['site_patient_id']);
        $csv->addField($event['Event']['event_date']);
        $csv->addField($event['Event']['status']);
        $csv->addField($this->element('actor', 
            array('user' => $event['Creator'])));

        $criterias = array('dx' => '', 'proc' => '', 'other' => '');

        foreach ($event['Criteria'] as $criteria) {
            $c = strtolower($criteria['name']);

            if ($c == 'diagnosis' || $c == 'dx' || $c == 'dx_dt') {
                $c = 'dx';
            } else if ($c == 'proc') {
                $c = 'proc';
            } else {
                $c = 'other';
                /* 'other' values get concatenated.  The rest should only 
                    appear once */
                $criteria['value'] = $criterias['other'] . $criteria['name'] . 
                                     ':' .  $criteria['value'] . ';';
            }

            $criterias[$c] = $criteria['value'];
        }

        $csv->addField($criterias['dx']);
        $csv->addField($criterias['proc']);
        $csv->addField($criterias['other']);
        $csv->addField($event['Event']['add_date']);
        $csv->addField($this->element('actor', 
            array('user' => $event['Uploader'])));
        $csv->addField($event['Event']['upload_date']);
        $csv->addField($this->element('actor', 
            array('user' => $event['Marker'])));

        $noPacketReason = $event['Event']['no_packet_reason'];
        $csv->addField($noPacketReason);

        if ($noPacketReason == Event::OUTSIDE_HOSPITAL) {
            $csv->addField($event['Event']['two_attempts_flag'] ? 
                           'Yes' : 'No');
        } else {
            $csv->addField('');
        }

        if ($noPacketReason == Event::ASCERTAINMENT_PRIOR_EVENT) {
            $priorDate = $event['Event']['prior_event_date'];
            $csv->addField(empty($priorDate) ? 'unknown' : $priorDate);
            $csv->addField($event['Event']['prior_event_onsite_flag'] ? 
                           'Yes' : 'No');
        } else {
            $csv->addField('');
            $csv->addField('');
        }

        $csv->addField($event['Event']['other_cause']);

        $csv->addField($event['Event']['markNoPacket_date']);
        $csv->addField($this->element('actor', 
            array('user' => $event['Scrubber'])));
        $csv->addField($event['Event']['scrub_date']);
        $csv->addField($this->element('actor', 
            array('user' => $event['Screener'])));
        $csv->addField($event['Event']['screen_date']);
        $csv->addField($event['Event']['rescrub_message']);
        $csv->addField($event['Event']['reject_message']);
        $csv->addField($this->element('actor', 
            array('user' => $event['Assigner'])));
        $csv->addField($event['Event']['assign_date']);
        $csv->addField($this->element('actor', 
            array('user' => $event['Sender'])));
        $csv->addField($event['Event']['send_date']);
        $csv->addField($this->element('actor', 
            array('user' => $event['Assigner3rd'])));
        $csv->addField($event['Event']['assign3rd_date']);

        $csv->addField($this->element('actor', 
            array('user' => $event['Reviewer1'])));
        $csv->addField($event['Event']['review1_date']);
        $this->element('reviewcsv', array('review' => $event['Review1']));

        $csv->addField($this->element('actor', 
            array('user' => $event['Reviewer2'])));
        $csv->addField($event['Event']['review2_date']);
        $this->element('reviewcsv', array('review' => $event['Review2']));

        $csv->addField($this->element('actor', 
            array('user' => $event['Reviewer3'])));
        $csv->addField($event['Event']['review3_date']);
        $this->element('reviewcsv', array('review' => $event['Review3']));

        if (empty($event['EventDerivedData'])) {
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
            $csv->addField('');
        } else {
            $eed = $event['EventDerivedData'];
            $csv->addField($eed['hf_type']);
            $csv->addField($eed['classification']);
            $csv->addField($eed['low_lvef_flag']);
            $csv->addField($eed['dd_flag']);
            $csv->addField($eed['presentation']);
            $csv->addField($eed['ischemic_flag']);
            $csv->addField($eed['nonischemic_flag']);
            $csv->addField($eed['unknown_flag']);
        }

        $csv->endRow();
    }

    $datetime = str_replace(' ', '_', date('Y-m-d'));

    if (!defined('CAKEPHP_UNIT_TEST_EXECUTION')) {
        echo $csv->render("event.$datetime.csv");
    } else {
        echo $csv->render(false);      // for testing, just output as page
    }
?>
