<?php
    $eventId = $event['Event']['id'];
?>

<div class="boxright" id="infobox" style="width: 300px; font-size: .95em">
	<h3>Scrubbing Instructions:</h3>
    <br />
    <div>View as: 
    <?php
    echo $html->link('.doc', '/files/' . Event::SCRUB_INSTRUCTIONS);
    echo " | ";
    echo $html->link('.pdf', '/files/' . Event::SCRUB_INSTRUCTIONS_PDF, array('target'=>'_blank'));
    ?>
    </div>
</div>

<h1>Upload scrubbed charts for
<?php 
    echo PROJECT_NAME == 'MI' ? "MI " .($eventId + 1000) : $eventId;
?>
</h1>

<p>
<?php
  echo "Site: " . $event['Patient']['site'] . "<br/>Patient ID: " . $event['Patient']['site_patient_id'] . "<br />Date: " . $event['Event']['event_date'];
?>
</p>

<?php
    if (!empty($event['Event']['rescrub_message'])) {
?>
<h2 id="rescrub">
    Needs rescrubbing.  Message: 
    <?php echo $event['Event']['rescrub_message']; ?>
</h2>

<?php
    }

    echo $form->create(null, array('controller' => 'events',
                                   'action' => 'scrub',
                                   'enctype' => 'multipart/form-data'));
    // 100 Mbytes (roughly)
    echo '<input type="hidden" name="MAX_FILE_SIZE" value="100000000"/>';

    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
    echo $form->hidden('Event.id', array('value' => $eventId));

    echo '<label for = \"chartFile\">Choose a scrubbed file to upload:</label>';
    echo $form->file('Event.chartFile');

    echo $form->submit('Upload scrubbed');
    echo $form->end();
?>

<p>
<?php
    echo $html->link('< Return to View All Events', '/events/viewAll');
?>
</p>
