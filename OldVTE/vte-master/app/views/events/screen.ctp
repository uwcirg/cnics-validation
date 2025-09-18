<?php
    $eventId = $event['Event']['id'];
?>

<h1>Screen charts for 
<?php 
    echo PROJECT_NAME == 'MI' ? "MI " .($eventId + 1000) : $eventId;
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

<?php

    echo $form->create(null, array('controller' => 'events',
                                   'action' => 'screen'));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
    echo $form->hidden('Event.id', array('value' => $eventId));

    echo $form->input('Event.screenAccept',
                      array('type' => 'radio',
                          'before' => '',
                          'legend' => false,
                          'selected' => null,
                          'default' => null,
                          'options' => array(
                              Event::ACCEPT => Event::ACCEPT,
                              Event::RESCRUB => Event::RESCRUB,
                              Event::REJECT => Event::REJECT)));

    echo $form->input('Event.message', array('label' => 'Message:&nbsp;'));

    echo $form->submit('Screen');
    echo $form->end();
?>

<p>
<?php
    echo $html->link('< Return to View All Events', '/events/viewAll');
?>
</p>
