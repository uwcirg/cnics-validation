<h1>Send charts to reviewers</h1>

<?php
    echo $form->create(null, array('controller' => 'events',
                                   'action' => 'sendMany'));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
?>

<table class="eventTable">
  <tr>
    <th>Event Number</th>
    <th>Event date</th>
    <th>Assigned</th>
    <th>Reviewer 1</th>
    <th>Reviewer 2</th>
    <th>Send</th>
  </tr>

<?php
    foreach ($toBeSent as $event) {
        $reviewer1 = $reviewers[$event['Event']['reviewer1_id']];
        $reviewer2 = $reviewers[$event['Event']['reviewer2_id']];
        echo '<tr><td>' . ($event['Event']['id']) . '</td>';
        echo "<td>{$event['Event']['event_date']}</td>";
        echo "<td>{$event['Event']['assign_date']}</td>";
        echo "<td>{$reviewer1}</td>";
        echo "<td>{$reviewer2}</td>";
        echo '<td>' .
             $form->input("Event.send{$event['Event']['id']}",
                      array('type' => 'checkbox', 'label' => '')) . 
             '</td></tr>';
    }
?>
</table>

<p>
<?php
    echo $form->submit('Send');
    echo $form->end();
?>
</p>

<p>
<?php
    echo $html->link('< Return to View All Events', '/events/viewAll');
?>
</p>
