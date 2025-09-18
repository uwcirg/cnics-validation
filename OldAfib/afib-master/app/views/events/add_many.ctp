<h1>Add multiple new events</h1>

<p>
Select a CSV file where each line has this format:
<blockquote>
<code>site_patient_id, site_name, event_date, criteria</code>
</blockquote>
</p>

<p>
<code>event_date</code> is of the form <code>2000-12-25</code>, and 
corresponds to the date of the diagnosis of labs or procedure that
trigger the review.
</p>

<p>
<code>criteria</code> is a (possibly empty) list of criteria used
to identify potential events.  Each criterion consists of two comma-separated
fields.
The first field is the name of a criterion, the second is the value of the
criterion.  Some examples:  
</p>

<ul>
<li>
CK,5
</li>
<li>
troponins,2,CK,5
</li>
<li>
troponins,2,CK,5,procedures,"CPR,defibrillation" 
</li>
</ul>

<p>
Note: criteria fields that contain commas should be enclosed in double quotes
</p>

<?php
    echo $form->create(null, array('controller' => 'events',
                                   'action' => 'addMany',
                                   'enctype' => 'multipart/form-data'));
    // 100 Kbytes (roughly)
    echo '<input type="hidden" name="MAX_FILE_SIZE" value="100000"/>';

    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));

    echo '<label for = \"newEventsFile\">Choose a file:</label>';
    echo $form->file('Event.newEventsFile');

    echo $form->submit('Add');
    echo $form->end();
?>
