<?php
echo "<h2>$heading</h2>";

if (empty($events)) {
?>
<p>None</p>

<?php
} else {
?>

<div class="eventTable">
<button class="hide">Hide table</button>
<button class="show">Show table</button>
<table class="eventTable hideable">
    <tr>
        <th><?php echo 'Event Number'; ?></th>
        <th><?php echo 'Event Date'; ?></th>
        <th><?php echo $dateFieldName; ?></th>
<?php
    if (!empty($showSite)) {
?>
        <th><?php echo 'Site'; ?></th>
<?php
    }

    if (!empty($reviewers)) {
?>
        <th><?php echo $reviewersFieldName; ?></th>
<?php
    }
?>
        <th>&nbsp;</th>
    </tr>
<?php
    $nextActions[] = 'edit';

    foreach($events as $event) {
        $eventId = $event['Event']['id'];

        if (is_array($dateField)) {
            $date = '';

            foreach ($dateField as $fieldName) {
                $d = $event['Event'][$fieldName];

                if (!empty($d)) {
                    if (empty($date) || $d > $date) {
                        $date = $d;
                    }
                }
            }
        } else {
            $date = $event['Event'][$dateField];
        }
?>
    <tr>
        <td><?php echo $eventId; ?></td>
        <td><?php echo $event['Event']['event_date']; ?></td>
        <td><?php echo $date; ?></td>

<?php
        if (!empty($showSite)) {
?>
            <td><?php echo $event['Patient']['site']; ?></td>
<?php
        }

        if (!empty($reviewers)) {
            if (is_array($reviewerNumber)) {
                $status = $event['Event']['status'];
                $revs = '';
    
                foreach ($reviewerNumber as $rn) {
                    if ($status != "reviewer{$rn}_done") {
                        $revs .= ' &nbsp; ' . $reviewers[
                            $event['Event']["reviewer{$rn}_id"]];
                    }
                }
            } else {
                $revs = 
                    $reviewers[$event['Event']["reviewer{$reviewerNumber}_id"]];
            }
?>
            <td><?php echo $revs; ?></td>
<?php
        }
?>
        <td><?php
            $first = true;

            foreach ($nextActions as $nextAction) {
                if (!$first) {
                    echo '&nbsp; | &nbsp;';
                } else {
                    $first = false;
                }

                if (is_array($nextAction)) {
                    $anchor = $nextAction['anchor'];
                    $action = $nextAction['action'];
                } else {
                    $anchor = $nextAction;
                    $action = $nextAction;
                }

                echo $html->link($anchor, "/events/$action/$eventId");
            }
        ?></td>
    </tr>
<?php
    }
?>
</table>
</div>
<?php
}
?>
