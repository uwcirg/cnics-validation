<h1><?php echo $thirdReview ? 'Assign third reviewer' : 'Assign charts'; ?></h1>

<h2>Step 1: Select Event(s)</h2>

<div class="indent1">
    
<?php
    echo $form->create(null, array('controller' => 'events',
                                   'action' => $thirdReview ? 'assign3rdMany' :
                                                              'assignMany'));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
?>

<table class="eventTable">
  <tr>
    <th>Event Number</th>
    <th>Event date</th>
    <th><?php echo $thirdReview ? 'Last Review' : 'Screened'; ?></th>
    <th>Reviewer 1</th>
    <th>Reviewer 2</th>
    <th>Assign</th>
  </tr>

<?php
    foreach ($toBeAssigned as $event) {
        $reviewer1 = empty($event['Event']['reviewer1_id']) ? 'None' :
                         $reviewers[$event['Event']['reviewer1_id']];
        $reviewer2 = empty($event['Event']['reviewer2_id']) ? 'None' :
                         $reviewers[$event['Event']['reviewer2_id']];
        echo '<tr><td>' . ($event['Event']['id']) . '</td>';
        echo "<td>{$event['Event']['event_date']}</td>";

        if ($thirdReview) {
            if ($event['Event']['review1_date'] < 
                $event['Event']['review2_date']) 
            {
                $date = $event['Event']['review2_date'];
            } else {
                $date = $event['Event']['review1_date'];
            }
        } else {
            $date = $event['Event']['screen_date'];
        }

        echo "<td>$date</td>";
        echo "<td>{$reviewer1}</td>";
        echo "<td>{$reviewer2}</td>";
        echo '<td>' .
             $form->input("Event.assign{$event['Event']['id']}",
                      array('type' => 'checkbox', 'label' => '')) . 
             '</td></tr>';
    }
?>
</table>

</div>

<br />

<h2>Step 2: Choose Reviewer</h2>

<div class="indent1">
<p>
<?php
    echo 'Choose reviewer:&nbsp;' . 
         $form->select('Assign.reviewer_id', 
                       $thirdReview ? $thirdReviewers : $reviewers) . 
         '<br/>';
?>
</p>
</div>
<?php
    echo $form->submit('Assign');
    echo $form->end();
?>
</p>
</div>

<p>
<?php
    echo $html->link('< Return to View All Events', '/events/viewAll');
?>
</p>
