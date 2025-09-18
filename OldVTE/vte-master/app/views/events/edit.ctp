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

    if (!empty($this->data['Event']['prescrub_date'])) {
?>
<tr>
  <th>Prescrub Date:</th>
  <td><?php echo $this->data['Event']['prescrub_date']; ?></td>
</tr>
<tr>
  <th>Prescrubber:</th>
  <td><?php echo $this->data['Prescrubber']['username']; ?></td>
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
    echo $form->hidden('EventDerivedData.pe_location', 
	    array('value' => 
	        $this->data['EventDerivedData']['pe_location']));
    echo $form->hidden('EventDerivedData.dvt_location', 
	    array('value' => 
	        $this->data['EventDerivedData']['dvt_location']));
    echo $form->hidden('EventDerivedData.dvt_le_location', 
	    array('value' => 
	        $this->data['EventDerivedData']['dvt_le_location']));
    echo $form->hidden('EventDerivedData.dvt_other_location', 
	    array('value' => 
	        $this->data['EventDerivedData']['dvt_other_location']));
    echo $form->hidden('EventDerivedData.dvt_other_neck_location', 
	    array('value' => 
	        $this->data['EventDerivedData']['dvt_other_neck_location']));
    echo $form->hidden('EventDerivedData.dvt_other_ap_location', 
	    array('value' => 
	        $this->data['EventDerivedData']['dvt_other_ap_location']));
    echo $form->hidden('EventDerivedData.dvt_other_ps_location', 
	    array('value' => 
	        $this->data['EventDerivedData']['dvt_other_ps_location']));
    echo $form->hidden('EventDerivedData.dvt_other_ic_location', 
	    array('value' => 
	        $this->data['EventDerivedData']['dvt_other_ic_location']));
?>
<table>
<tr>
  <th>PE?</th>
  <td>
  <?php
      echo $form->select('EventDerivedData.pe_flag', $flagChoices,
        null, array('id' => 'pefSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>DVT?</th>
  <td>
  <?php
      echo $form->select('EventDerivedData.dvt_flag', $flagChoices,
        null, array('id' => 'dvtfSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>CAT?</th>
  <td>
  <?php
      echo $form->select('EventDerivedData.cat_flag', $flagChoices,
        null, array('id' => 'catfSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>No VTE?</th>
  <td>
  <?php
      echo $form->select('EventDerivedData.no_vte_flag', $flagChoices,
        null, array('id' => 'novtefSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>PE Definite/Probable</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.pe_dp', $dps,
                       null, array('id' => 'pedpSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>DVT Definite/Probable</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.dvt_dp', $dps,
                       null, array('id' => 'dvtdpSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>CAT Definite/Probable</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.cat_dp', $dps,
                       null, array('id' => 'catdpSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>PE Type</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.pe_type', 
                       $types, null, array('id' => 'petSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>DVT Type</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.dvt_type', 
                       $types, null, array('id' => 'dvttSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>CAT Type</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.cat_type', 
                       $types, null, array('id' => 'cattSelect'));
  ?>
  </td>
</tr>
<tr>
  <th>CAT subtype</th>
  <td>
  <?php
    echo $form->select('EventDerivedData.cat_subtype', $catSubtypes, null,
                       array('id' => 'cselect'));
  ?>
  </td>
</tr>
<tr>
  <th>PE Location</th>
  <td>
  <?php
      echo $this->data['EventDerivedData']['pe_location'];
  ?>
  </td>
</tr>
<tr>
  <th>DVT Location</th>
  <td>
  <?php
      echo $this->data['EventDerivedData']['dvt_location'];
  ?>
  </td>
</tr>
<tr>
  <th>DVT LE Location</th>
  <td>
  <?php
      echo $this->data['EventDerivedData']['dvt_le_location'];
  ?>
  </td>
</tr>
<tr>
  <th>DVT Other Location</th>
  <td>
  <?php
      echo $this->data['EventDerivedData']['dvt_other_location'];
  ?>
  </td>
</tr>
<tr>
  <th>DVT Other Neck Location</th>
  <td>
  <?php
      echo $this->data['EventDerivedData']['dvt_other_neck_location'];
  ?>
  </td>
</tr>
<tr>
  <th>DVT Other Abdomen/Pelvis Location</th>
  <td>
  <?php
      echo $this->data['EventDerivedData']['dvt_other_ap_location'];
  ?>
  </td>
</tr>
<tr>
  <th>DVT Other Portal System Location</th>
  <td>
  <?php
      echo $this->data['EventDerivedData']['dvt_other_ps_location'];
  ?>
  </td>
</tr>
<tr>
  <th>DVT Other Intercranial Location</th>
  <td>
  <?php
      echo $this->data['EventDerivedData']['dvt_other_ic_location'];
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
