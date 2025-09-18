<div class="tabnav">
	<ul>
<?php
    if ($admin) {
		echo "<li>";
	    echo $html->link('Admin Tools', '/?view=admin');
		echo "</li>";
    }
    if ($uploader) {
		echo "<li>";
	    echo $html->link('Upload New Packets', '/?view=upload');
		echo "</li>";
		echo "<li>";
	    echo $html->link('Re-upload Existing Packets', '/?view=reupload');
		echo "</li>";
    }
    if ($reviewer) {
		echo "<li>";
	    echo $html->link('Review Events', '/?view=review');
		echo "</li>";
    }
?>
	</ul>
</div>