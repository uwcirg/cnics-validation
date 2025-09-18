<?php
$reason = $event['no_packet_reason'];

if (!empty($reason)) {
    echo "Reason = $reason";

    if ($reason == Event::OUTSIDE_HOSPITAL) {
       echo $separator . 'Two attempts made? ' .
         ($event['two_attempts_flag'] ? 'Yes' : 'No');
    } else if ($reason == Event::OTHER) {
       echo $separator . 'Other cause = ' . $event['other_cause'];
    } else if ($reason == Event::ASCERTAINMENT_PRIOR_EVENT) {
       echo $separator . 'Date = ' .
         (empty($event['prior_event_date']) ?
           'unknown' : $event['prior_event_date']);
       echo $separator . 'Onsite? ' .
         ($event['prior_event_onsite_flag'] ?  'Yes' : 'No');
    }
}
?>
