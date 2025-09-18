<?php
$admin = $authUser['User']['admin_flag'] ? 1 : 0;
$reviewer = $authUser['User']['reviewer_flag'] ? 1 : 0;
$uploader = $authUser['User']['uploader_flag'] ? 1 : 0;
if ( isset($_GET['view'])) {
	$view = $_GET['view'];
} else {
	$view = 'none';
}
?>

<?php
if ($admin AND ($view=="none" OR $view=="admin")) {
?>

<h1>Administrative Tools</h1>

<?php $session->flash(); ?>

<h3>Events</h3>

<ul>
  <li>
<?php
    echo $html->link('View all events', '/events/viewAll');
?>
  </li>
  <li>
<?php
    echo $html->link('Add an event', '/events/add');
?>
  </li>
  <li>
<?php
    echo $html->link('Add multiple events from a CSV file', '/events/addMany');
?>
  </li>
  <li>
<?php
    echo $html->link('Export all events as CSV', '/events/getCsv');
?>
  </li>
</ul>

<h3>Users</h3>
<ul>
  <li>
<?php
    echo $html->link('Add a user', '/users/add');
?>
  </li>
  <li>
<?php
    echo $html->link('Edit/Delete users', '/users/viewAll');
?>
  </li>
</ul>
          
<?php
}

if ($uploader AND ($view=="none" OR $view=="upload" OR $view=="reupload")) {
	if ( $view=="reupload" ) {

		echo $this->element('eventList', 
			array('authUser' => $authUser, 'authUsername' => $authUsername,
				  'verb' => 'upload',
				  'showPatientInfo' => true,
				  'heading' => 'Re-upload Existing Packets',
				  'instructions' => 
					  'Use this page to find an event which has already had a packet uploaded. Used for making corrections by uploading a revised packet.',
				  'eventlistinfo' => 'Events with Packets That Can Be Re-uploaded:',
                                  'prefix' => $prefix,
                                  'prefix2' => $prefix2,
				  'events' => $reuploadEvents));
		
	}
	if ( $view=="upload" OR $view=="none" ) {
		
		echo $this->element('eventList', 
			array('authUser' => $authUser, 'authUsername' => $authUsername,
				  'verb' => 'upload',
				  'showPatientInfo' => true,
				  'heading' => 'Upload New Packets',
				  'instructions' => 
					  'Use this page to find an event and upload its packet. Please note the instructions on the right about how to properly assemble a review packet.',
				  'eventlistinfo' => 'Events That Need Packets:',
                                  'prefix' => $prefix,
                                  'prefix2' => $prefix2,
				  'events' => $uploadEvents));
	}


}

if ($reviewer AND ($view=="none" OR $view=="review")) {
    echo $this->element('eventList', 
        array('authUser' => $authUser, 'authUsername' => $authUsername,
              'verb' => 'review',
              'verbSuffixes' => $reviewNumbers,
              'showPatientInfo' => false,
              'heading' => 'Event Packets for Your Review',
              'instructions' => 'Use this page to select an event packet to review.',
			  'eventlistinfo' => 'Events to Review:',
              'prefix' => $prefix,
              'prefix2' => $prefix2,
              'events' => $reviewEvents));
}

else {

}

?>
