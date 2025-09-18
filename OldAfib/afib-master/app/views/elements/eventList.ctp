<?php
if ($showPatientInfo) {
?>
<div class="boxright" id="infobox" style="width: 400px; font-size: .95em">
	<h3>Review packets should contain:</h3>
    <ol style="margin-bottom: 0.5em">
        <li>Physician's notes closest to potential AF date (admit, transfer, discharge, ER notes; autopsy report)</li>
        <li>First 3 outpatient cardiology consultations or visits after potential AF date (w/in 1 year)</li>
        <li>First 3 general HIV clinic visit notes after potential AF date (w/in 1 year)</li>
        <li>Inpatient cardiology notes or consults, 3 months prior to potential AF date to 3 months after</li>
        <li>Outpatient clinic note e.g. HIV clinic note, closest but prior to potential AF date</li>
        <li>All ECGs in the 6 months prior to and 3 months after the potential AF date</li>
        <li>All echo reports in the 2 years prior to and up to 3 months after the potential AF date</li>
        <li>All related procedure and diagnostic test results prior to and up to 3 months after potential AF date (cardiac ablation, cardiac radionuclide imaging, cardiac MRI, cardiac CT)</li>
        <li>Please redact the personal identifiers including name, birthday, and hospital number</li>
    </ol>
    <div>Full instructions: 
    <?php
    echo $html->link('.doc', '/files/' . $prefix . ' ' . $prefix2 . Event::UPLOAD_INSTRUCTIONS);
    echo " | ";
    echo $html->link('.pdf', '/files/' . $prefix . ' ' . $prefix2 . Event::UPLOAD_INSTRUCTIONS_PDF, array('target'=>'_blank'));
    ?>
    </div>
</div>
<?php
} else {
?>
<div class="boxright" id="infobox" style="width: 300px; font-size: .95em">
	<h3>Review Instructions:</h3>
    <br />
    <div>View as: 
    <?php
    echo $html->link('.doc', '/files/' . $prefix . Event::REVIEW_INSTRUCTIONS);
    echo " | ";
    echo $html->link('.pdf', '/files/' . $prefix . Event::REVIEW_INSTRUCTIONS_PDF, array('target'=>'_blank'));
    ?>
    </div>
</div>
<?php
}
?>

<?php
echo "<h1>$heading</h1>";

if (empty($events)) {
    echo "<p>No events to $verb</p>";
} else {
	echo "<p>$instructions</p>";
?>

<h3>Quick Search</h3>
<p>Filter the list of events by searching on any field:</p>

<?php
if ($showPatientInfo) {
?>
		<div style="font-style: italic; margin-left: 18px">Filter events: <input type="text" name="search" value="" id="id_search" placeholder="Search" autofocus /> <span class="loading">Loading...</span></div>
<?php
} else {
?>
		<div style="font-style: italic; margin-left: 18px">Filter events: <input type="text" name="search" value="" id="id_search_review" placeholder="Search" autofocus /> <span class="loading">Loading...</span></div>
<?php
}
?>        
<br />        

<?php
	echo "<h3>$eventlistinfo</h3>";
?>

<table class="table_list">
<thead>
<tr>
	<th>ID</th>
<?php
if ($showPatientInfo) {
?>
	<th>Patient ID</th>
<?php
}
?>
	<th>Date</th>
<?php
if ($showPatientInfo) {
?>
	<th>Criteria</th>
<?php
}
?>
 </tr>
</thead>
<?php
if ($showPatientInfo) {
?>
		<tbody id="uploader">
        <tr id="noresults">
            <td colspan="4">Nothing matches your search. Please try again.</td>
        </tr>
<?php
} else {
?>
		<tbody id="reviewer">
        <tr id="noresultsreviewer">
            <td colspan="4">Nothing matches your search. Please try again.</td>
        </tr>
<?php
}
?>
<?php
    foreach ($events as $index => $event) {
        $eventId = $event['Event']['id'];

        // show site and patientId if appropriate
        if ($showPatientInfo) {
            $patientInfo = ', ' . $event['Patient']['site'] . ', ' .  
                           $event['Patient']['site_patient_id'];
 
            $criteria = '<span class="criteria">';

            if (empty($event['Criteria'])) {
                $criteria .= 'None';
            } else {
                $firstCriterion = true;

                foreach ($event['Criteria'] as $criterion) {
                    $criteria .= $firstCriterion ? '' : ', ';
                    $criteria .= $criterion['name'] . ' = ' .
                                    $criterion['value'];
                    $firstCriterion = false;
                }
            }

            $criteria .= '</span>';
        } else {
            $patientInfo = '';
            $criteria = '';
        }
        
        /* allow $verb to change per event by adding on a suffix (if the
           array of suffixes exists */
        if (!empty($verbSuffixes)) {
            $thisVerb = $verb . $verbSuffixes[$index];
        } else {
            $thisVerb = $verb;
        }
		
       	echo '<tr><td>' .
            $html->link("Event {$eventId}",
                        "/events/$thisVerb/$eventId") .
            '</td>';
			if ($showPatientInfo) {
            	echo '<td>' . $event['Patient']['site_patient_id'] . '</td>';
			}
            echo '<td>' . $event['Event']['event_date'] . '</td>';
			if ($showPatientInfo) {
				echo '<td>' . $criteria . '</td>';
			}
            '</tr>';
    }
?>
</tbody>
</table>

<?php
}
?>

<!-- Begin script to make whole tr clickable -->
<script type="text/javascript">
$(document).ready(function() {
    $('tr').click(function() {
        var href = $(this).find("a").attr("href");
        if(href) {
            window.location = href;
        }
    });
});
</script>
<!-- End script to make whole tr clickable -->
