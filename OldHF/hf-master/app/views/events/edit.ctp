<?php $eventId = $this->data['Event']['id']; ?>

<h2>Edit Event <?php 
    echo $eventId;
?></h2>

<?php
    if ($canDownload) {
        echo '<p>' . $html->link('Download charts for this event', 
                                 "/events/download/$eventId" .
             '</p>');
    }
?>

<h3>Main Details</h3>
<br />
<div class="indent1">
<?php
    echo $form->create('Event', array('action' => 'edit'));
    echo $form->hidden('Event.id', array('value' => $eventId));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)));
?>
<table>
<tr>
  <th>Site Patient Id</th>
  <td>
  <?php
    echo $form->input('Patient.site_patient_id', array('label' => ''));
  ?>
  </td>
</tr>
<tr>
  <th><?php echo "<label for = \"Patient.site\">Site</label>";?></th>
  <td>
  <?php
    echo $form->select("Patient.site", $sites);
  ?>
  </td>
</tr>
<tr>
  <th><?php echo "<label for = \"Event.event_date\">Event date</label>";?></th>
  <td>
  <?php
    echo $form->input("Event.event_date", array('label' => '',
                                                'minYear' => '1985',
                                                'maxYear' => date('Y')));
  ?>
  </td>
</tr>
<tr>
  <th>Status:</th>
  <td><?php echo $this->data['Event']['status']; ?></td>
</tr>
<tr>
  <th>Creation Date:</th>
  <td><?php echo $this->data['Event']['add_date']; ?></td>
</tr>
<tr>
  <th>Creator:</th>
  <td><?php echo $this->data['Creator']['username']; ?></td>
</tr>

<?php
    if (!empty($this->data['Event']['upload_date'])) {
?>
<tr>
  <th>Upload Date:</th>
  <td><?php echo $this->data['Event']['upload_date']; ?></td>
</tr>
<tr>
  <th>Uploader:</th>
  <td><?php echo $this->data['Uploader']['username']; ?></td>
</tr>
<?php
    }

    if (!empty($this->data['Event']['markNoPacket_date'])) {
?>
<tr>
  <th>Date packet was marked as not available:</th>
  <td><?php echo $this->data['Event']['markNoPacket_date']; ?></td>
</tr>
<tr>
  <th>Person who marked packet as not available:</th>
  <td><?php echo $this->data['Marker']['username']; ?></td>
</tr>
<tr>
  <th><?php echo "No packet available details:"; ?></th>
  <td>
<?php 
        echo $this->element('markNoPacketData', 
                                array('event' => $this->data['Event'],
                                      'separator' => '; '));
?>
  </td>
</tr>
<?php
    }

    if (!empty($this->data['Event']['scrub_date'])) {
?>
<tr>
  <th>Scrub Date:</th>
  <td><?php echo $this->data['Event']['scrub_date']; ?></td>
</tr>
<tr>
  <th>Scrubber:</th>
  <td><?php echo $this->data['Scrubber']['username']; ?></td>
</tr>
<?php
        if (!empty($this->data['Event']['rescrub_message'])) {
?>
<tr>
  <th>Rescrub Message:</th>
  <td><?php echo $this->data['Event']['rescrub_message']; ?></td>
</tr>
<?php
        }
    }
?>

<?php
    if (!empty($this->data['Event']['screen_date'])) {
?>
<tr>
  <th>Screen Date:</th>
  <td><?php echo $this->data['Event']['screen_date']; ?></td>
</tr>
<tr>
  <th>Screener:</th>
  <td><?php echo $this->data['Screener']['username']; ?></td>
</tr>
<?php
        if (!empty($this->data['Event']['reject_message'])) {
?>
<tr>
  <th>Reject Message:</th>
  <td><?php echo $this->data['Event']['reject_message']; ?></td>
</tr>
<?php
        }
    }
?>

<?php
    if (!empty($this->data['Event']['assign_date'])) {
?>
<tr>
  <th>Assign Date:</th>
  <td><?php echo $this->data['Event']['assign_date']; ?></td>
</tr>
<tr>
  <th>Assigner:</th>
  <td><?php echo $this->data['Assigner']['username']; ?></td>
</tr>
<tr>
  <th>Reviewer 1:</th>
  <td><?php echo $this->data['Reviewer1']['username']; ?></td>
</tr>
<tr>
  <th>Reviewer 2:</th>
  <td><?php echo $this->data['Reviewer2']['username']; ?></td>
</tr>
<?php
    }
?>

<?php
    if (!empty($this->data['Event']['send_date'])) {
?>
<tr>
  <th>Send Date:</th>
  <td><?php echo $this->data['Event']['send_date']; ?></td>
</tr>
<tr>
  <th>Sender:</th>
  <td><?php echo $this->data['Sender']['username']; ?></td>
</tr>
<?php
    }
?>

<?php
    if (!empty($this->data['Event']['review1_date'])) {
?>
<tr>
  <th>Review 1 Date:</th>
  <td><?php echo $this->data['Event']['review1_date']; ?></td>
</tr>
<tr>
  <th>Review 1</th>
  <td>
  <?php 
      echo $this->element('review', array('review' => $review1, 
                                          'separator' => '<br/>')); 
    ?>
  </td>
</tr>
<?php
    }
?>

<?php
    if (!empty($this->data['Event']['review2_date'])) {
?>
<tr>
  <th>Review 2 Date:</th>
  <td><?php echo $this->data['Event']['review2_date']; ?></td>
</tr>
<tr>
  <th>Review 2</th>
  <td>
    <?php 
      echo $this->element('review', array('review' => $review2, 
                                          'separator' => '<br/>')); 
    ?>
  </td>
</tr>
<?php
    }
?>

<?php
    if (!empty($this->data['Event']['assign3rd_date'])) {
?>
<tr>
  <th>Third Review Assign Date:</th>
  <td><?php echo $this->data['Event']['assign3rd_date']; ?></td>
</tr>
<tr>
  <th>Third Review Assigner:</th>
  <td><?php echo $this->data['Assigner3rd']['username']; ?></td>
</tr>
<tr>
  <th>Reviewer 3:</th>
  <td><?php echo $this->data['Reviewer3']['username']; ?></td>
</tr>
<?php
    }
?>

<?php
    if (!empty($this->data['Event']['review3_date'])) {
?>
<tr>
  <th>Review 3 Date:</th>
  <td><?php echo $this->data['Event']['review3_date']; ?></td>
</tr>
<tr>
  <th>Review 3</th>
  <td>
    <?php 
      echo $this->element('review', array('review' => $review3, 
                                          'separator' => '<br/>')); 
    ?>
  </td>
</tr>
<?php
    }
?>

<tr>
  <td colspan="2">
  <?php
    echo $form->submit('Edit');
  ?>
  </td>
</tr>

<?php
    echo $form->end();
?>
</table>
</div>
<br />
<hr/>
<?php
if ($this->data['Event']['status'] == Event::DONE) {
?>
<h3>Overall fields</h3>
<br />
<div class="indent1">
<?php
    echo $form->create('EventDerivedData', array('action' => 'edit'));
    echo $form->hidden('EventDerivedData.id', 
                       array('value' => $this->data['EventDerivedData']['id']));
    echo $form->hidden('EventDerivedData.event_id', array('value' => $eventId));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)));
?>
<table>
<tr>
  <th>Outcome</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.outcome', $outcomes, null, 
                       array('id' => 'outcomeSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>Primary vs. Secondary</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.primary_secondary', $types, null,
                       array('id' => 'psSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>False Positive Event?</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.false_positive_event', $flagChoices,
                       null, array('id' => 'fpeSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>Secondary Cause</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.secondary_cause', $secondaryCauses,
                       null, array('id' => 'scSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>Secondary Cause Other</th>
  <td>
  <?php
    echo $form->input('EventDerivedData.secondary_cause_other', array('label' => ''));
  ?>
  </td>
</tr>
<tr>
  <th>False Positive Cause</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.false_positive_reason', 
                       $falsePositiveReasons, null, array('id' => 'fprSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>Cardiac Intervention?</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.ci', $flagChoices,
                       null, array('id' => 'ciSelect'));
  ?>
  </td>
</tr>
<tr>
  <td colspan="2">
  <?php
    echo $form->submit('Edit overall fields');
  ?>
  </td>
</tr>

<?php
    echo $form->end();
?>
</table>
</div>
<br />
<hr/>
<?php
}
?>
<h3>Criteria</h3>
<p><?php 
    if (empty($this->data['Criteria'])) {
        echo 'No criteria currently listed.';
    } else {
        echo '<br/>';

        foreach ($this->data['Criteria'] as $criteria) {
            echo $this->element('criteria', 
                                array('criteria' => $criteria,
                                      'separator' => '<br/>',
                                      'showDeleteLink' => true));
        }
    }
?>
</p>

<div class="indent1">
<strong>Add Criterion</strong><br />
<?php
    echo $form->create('Criteria', array('action' => 'add'));
    echo $form->hidden('Criteria.event_id', array('value' => $eventId));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
?>
<table>
<tr>
  <th><?php echo "<label for = \"Criteria.name\">Name</label>";?></th>
  <td>
  <?php
    echo $form->input("Criteria.name", array('label' => ''));
  ?>
  </td>
</tr>
<tr>
  <th><?php echo "<label for = \"Criteria.value\">Value</label>";?></th>
  <td>
  <?php
    echo $form->input("Criteria.value", array('label' => ''));
  ?>
  </td>
</tr>
<tr>
  <td colspan="2">
  <?php
    echo $form->submit('Add');
  ?>
  </td>
</tr>

<?php
    echo $form->end();
?>
</table>
</div>
<br />
<p>
<?php
    echo $html->link('< Return to View All Events', '/events/viewAll');
?>
</p>

</p>
