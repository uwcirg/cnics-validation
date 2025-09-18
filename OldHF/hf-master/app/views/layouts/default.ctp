<?php
if ( isset($authUser) ) {
	$validuser = 'y';
	$admin = $authUser['User']['admin_flag'] ? 1 : 0;
	$reviewer = $authUser['User']['reviewer_flag'] ? 1 : 0;
	$uploader = $authUser['User']['uploader_flag'] ? 1 : 0;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Strict//EN">
<html>
<head>
<title>
<?php echo LONG_PROJECT_NAME . " - $title_for_layout"; ?>  
</title>

<META HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE">
<?php
echo $html->charset() . "\n";

if (strpos(Router::url('/', true), 'cnics') != true) {
    echo $html->css('main-naaccord') . "\n";
} else {
    echo $html->css('main-cnics') . "\n";
}

echo $javascript->link('jquery-1.3.2.min.js') . "\n";
echo $javascript->link('jquery.quicksearch.js') . "\n";
?>
<!-- Options for QuickSearch -->
<script type="text/javascript">
$(document).ready(function () {
	$('input#id_search').quicksearch("table.table_list tbody#uploader tr",{
			noResults: '#noresults',
			stripeRows: ['odd', 'even'],
			loader: 'span.loading',
			delay: 300
	});
	$('input#id_search_review').quicksearch("table.table_list tbody#reviewer tr",{
			noResults: '#noresultsreviewer',
			stripeRows: ['odd', 'even'],
			loader: 'span.loading',
			delay: 300
	});
});
</script>   
</head>

<body>

<div class="row">
	<div class="column grid_12" id="header">
    	<div id="title">
<?php 
    echo $html->link(LONG_PROJECT_NAME, '/'); 
?>
		</div>
	</div>
</div>

<div class="row">
	<div class="column grid_12" id="login">
    	<div>
<?php
if (!empty($authUser) && $authUser['User']['uploader_flag']) {
    echo 'Welcome! ';
}

if (!empty($authUser)) {
    if ($authUsername != $authUser['User']['username']) {
    // different login and user name
        echo 'You are logged in as: ' . $authUsername . 
             " ({$authUser['User']['username']})";
    } else {
        echo 'You are logged in as: ' . $authUsername . '';
    }

    echo " | "; 
    echo $html->link("Log Out", "/users/logout", array('title'=>"Log Out"));
}
?>  
		</div>      
    </div>
</div>

<div class="row">
	<div class="column grid_12">

    	<div id="content">  
<?php
	
	if (!empty($authUser)) {
		echo $this->element('menuBar', array('admin' => $admin, 
										 'uploader' => $uploader, 
										 'reviewer' => $reviewer));
	}

	if ($session->check('Message.flash')) {
		$session->flash(); 
	}
	
	if ($session->check('Message.success')) {
		$session->flash('success'); 
	}
									 
    echo $content_for_layout; 

    echo '<div id="footer">';
    echo $html->link('Home page', '/');
	echo '</div>';

?>

		</div>
    </div>
</div>

</body>
</html>
