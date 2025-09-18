<h1>Add a new event</h1>
<?php
    echo $form->create(null, array('controller' => 'events',
                                   'action' => 'add'));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
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
  <th>Criterion used to flag this event</th>
  <td>
  <?php
    echo $form->input("Criteria.0.name");
    echo $form->input("Criteria.0.value");
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
