<script>
  $(document).ready(function() {
    $('button.hide').hide();
    $('table.hideable').hide();

    $('button.hide').click(function() {
      $(this).hide();
      $(this).parent().children('button.show').show();
      $(this).parent().children('.eventTable').hide();
    });

    $('button.show').click(function() {
      $(this).hide();
      $(this).parent().children('button.hide').show();
      $(this).parent().children('.eventTable').show();
    });
  });
</script>

<h1>Events Summary</h1>

<div class="indent1">
	<p><strong>Total Events</strong>: <?php echo $summary['total']; ?></p>
    
    <ul>
    	<li>Created: <?php echo $summary[Event::CREATED]; ?></li>
    	<li>Uploaded: <?php echo $summary[Event::UPLOADED]; ?></li>
    	<li>Prescrubbed: <?php echo $summary[Event::PRESCRUBBED]; ?></li>
    	<li>Scrubbed: <?php echo $summary[Event::SCRUBBED]; ?></li>
    	<li>Screened: <?php echo $summary[Event::SCREENED]; ?></li>
    	<li>Assigned: <?php echo $summary[Event::ASSIGNED]; ?></li>
    	<li>Sent: <?php echo $summary[Event::SENT]; ?></li>
    	<li>1st reviewer done: <?php echo $summary[Event::REVIEWER1_DONE]; ?></li>
    	<li>2nd reviewer done: <?php echo $summary[Event::REVIEWER2_DONE]; ?></li>
    	<li>3rd review needed: <?php echo $summary[Event::THIRD_REVIEW_NEEDED]; ?></li>
    	<li>3rd review assigned: <?php echo $summary[Event::THIRD_REVIEW_ASSIGNED]; ?></li>
    	<li>Done: <?php echo $summary[Event::DONE]; ?></li>
    	<li>No packet available: <?php echo $summary[Event::NO_PACKET_AVAILABLE]; ?></li>
    	<li>Rejected: <?php echo $summary[Event::REJECTED]; ?></li>
    	<li>Prescrub Rejected: <?php echo $summary[Event::PSREJECTED]; ?></li>
    </ul>
    
</div>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'To Be Uploaded',
              'nextActions' => array('upload'),
	      	  'events' => $toBeUploaded,
              'dateField' => 'add_date',
              'dateFieldName' => 'Created',
              'reviewers' => null,
              'showSite' => true));
?>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'To Be Prescrubbed',
              'nextActions' => array('prescrub'),
              'events' => $toBePrescrubbed,
              'dateField' => 'upload_date',
              'dateFieldName' => 'Uploaded',
              'reviewers' => null,
              'showSite' => true));
?>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'To Be Scrubbed',
              'nextActions' => array('download', 
                                     array('anchor' => 'upload scrubbed',
                                           'action' => 'scrub')),
              'events' => $toBeScrubbed,
              'dateField' => 'prescrub_date',
              'dateFieldName' => 'Prescrubbed',
              'reviewers' => null,
              'showSite' => true));
?>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'To Be Screened',
              'nextActions' => array('download', 
                                     array('anchor' => 're-upload scrubbed',
                                           'action' => 'scrub'),
                                     'screen'),
	      'events' => $toBeScreened,
              'dateField' => 'scrub_date',
              'dateFieldName' => 'Scrubbed',
              'reviewers' => null));
?>

<?php 
    echo $this->element('eventTable', array('heading' => 'To Be Assigned',
                                            'nextActions' => array(),
			                    'events' => $toBeAssigned,
                                            'dateField' => 'screen_date',
                                            'dateFieldName' => 'Screened',
                                            'reviewers' => null));
?>
<p> <?php 
    if (count($toBeAssigned) > 0) {
        echo $html->link('Assign Reviewers', '/events/assignMany');
    }
?> </p>

<?php 
    echo $this->element('eventTable', array('heading' => 'To Be Sent',
                                            'nextActions' => array(),
			                    'events' => $toBeSent,
                                            'dateField' => 'assign_date',
                                            'dateFieldName' => 'Assigned',
                                            'reviewers' => null));
?>
<p> <?php 
    if (count($toBeSent) > 0) {
        echo $html->link('Select charts to send', '/events/sendMany');
    }
?> </p>


<?php 
    echo $this->element('eventTable', 
         array('heading' => 'Not Yet Reviewed',
               'nextActions' => array(),
	       'events' => $toBeReviewed,
               'dateField' => array('send_date', 'review1_date', 
                                    'review2_date'),
               'dateFieldName' => 'Sent/Last Review',
               'reviewerNumber' => array(1, 2),
               'reviewersFieldName' => 'Yet to review',
               'reviewers' => $reviewers));
?>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'Third Review Needed',
              'nextActions' => array(),
	      'events' => $thirdReviewNeeded,
              'dateField' => array('review1_date', 'review2_date'),
              'dateFieldName' => 'Last Review',
              'reviewers' => null));
?>
<p> <?php 
    if (count($thirdReviewNeeded) > 0) {
        echo $html->link('Assign Third Reviewers', '/events/assign3rdMany');
    }
?> </p>


<?php 
    echo $this->element('eventTable', 
        array('heading' => 'Third Reviewer Assigned',
              'nextActions' => array(),
              'events' => $thirdReviewerAssigned,
              'dateField' => 'assign3rd_date',
              'dateFieldName' => '3rd Reviewer Assigned',
              'reviewerNumber' => 3,
              'reviewersFieldName' => '3rd Reviewer',
              'reviewers' => $thirdReviewers));
?>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'All Done',
              'nextActions' => array(),
	      'events' => $allDone,
              'dateField' => array('review1_date', 'review2_date', 
                                   'review3_date'),
              'dateFieldName' => 'Last Review',
              'reviewers' => null));
?>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'No Packet Available',
              'nextActions' => array(),
	      'events' => $noPacketAvailable,
              'dateField' => 'markNoPacket_date',
              'dateFieldName' => 'Date Reported',
              'reviewers' => null));
?>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'Rejected',
              'nextActions' => array(),
	      'events' => $rejected,
              'dateField' => 'screen_date',
              'dateFieldName' => 'Rejected',
              'reviewers' => null));
?>

<?php 
    echo $this->element('eventTable', 
        array('heading' => 'Prescrub Rejected',
              'nextActions' => array(),
	      'events' => $psrejected,
              'dateField' => 'prescrub_date',
              'dateFieldName' => 'Prescrub Rejected',
              'reviewers' => null));
?>
