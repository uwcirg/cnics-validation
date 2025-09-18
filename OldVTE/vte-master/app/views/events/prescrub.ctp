<?php
    $eventId = $event['Event']['id'];
?>

<h1>Prescrub charts for 
<?php 
    echo 'Event ' . $eventId;
?>
</h1>

<p>
<?php
  echo 'Site: ' . $event['Patient']['site'] . "<br/>Patient ID: " . $event['Patient']['site_patient_id'] . "<br />Date: " . $event['Event']['event_date'];
?>
</p>

<p>
<?php 
    echo $html->link('Download charts for the event', 
                     "/events/download/$eventId"); 
?>
</p>
<p>Criteria: 
<?php
    if (empty($event['Criteria'])) {
        echo 'No criteria currently listed.';
    } else {
        $i = 0;
        foreach ($event['Criteria'] as $criteria) {
            $i++;
            $sep = ($i == count($event['Criteria'])) ? '' : ', ';
            echo $this->element('criteria',
                                array('criteria' => $criteria,
                                      'separator' => $sep,
                                      'showDeleteLink' => false));
        }
    }
?>
</p>


<?php

    echo $form->create(null, array('controller' => 'events',
                                   'action' => 'prescrub'));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
    echo $form->hidden('Event.id', array('value' => $eventId));

    echo $form->input('Event.prescrubAccept',
                      array('type' => 'radio',
                          'before' => '',
                          'legend' => false,
                          'selected' => null,
                          'default' => null,
                          'options' => array(
                              Event::ACCEPT => Event::ACCEPT,
                              Event::REJECT => Event::REJECT)));

    echo $form->submit('Prescrub');
    echo $form->end();
?>

<p>
<?php
    echo $html->link('< Return to View All Events', '/events/viewAll');
?>
</p>
