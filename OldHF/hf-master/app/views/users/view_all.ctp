<h1>Users</h1>

<?php 
if (empty($users)) {
?>
<p>None (?!?)</p>

<?php
} else {
?>
<!-- Make the User Name the only th? Then list the other variable with their values within the tds? -->
<table class="userTable">
    <tr>
        <th><?php echo 'User'; ?></th>
        <th><?php echo 'Site'; ?></th>
        <th><?php echo 'Uploader?'; ?></th>
        <th><?php echo 'Reviewer?'; ?></th>
        <th><?php echo '3rd Reviewer?'; ?></th>
        <th><?php echo 'Admin?'; ?></th>
        <th>&nbsp;</th>
    </tr>

<?php
    foreach($users as $user) {
        $userId = $user['User']['id'];
        $uploader = $user['User']['uploader_flag'] ? 'yes' : 'no';
        $reviewer = $user['User']['reviewer_flag'] ? 'yes' : 'no';
        $thirdReviewer = $user['User']['third_reviewer_flag'] ? 'yes' : 'no';
        $admin = $user['User']['admin_flag'] ? 'yes' : 'no';
?>

    <tr>
        <td><em>Name</em>: <?php echo $user['User']['first_name'] . ' ' .  $user['User']['last_name']; ?><br />
        <em>Username</em>: <?php echo $user['User']['username']; ?><br />
		<em>Login</em>: <?php echo $user['User']['login']; ?></td>
        <td><?php echo $user['User']['site']; ?></td>
        <td><?php echo $uploader; ?></td>
        <td><?php echo $reviewer; ?></td>
        <td><?php echo $thirdReviewer; ?></td>
        <td><?php echo $admin; ?></td>
        <td>
        <?php echo $html->link('Edit', "/users/edit/$userId") . ' | ' .
                   $html->link('Delete', "/users/delete/{$userId}?" .
                                   AppController::ID_KEY . "=" .
                                   $session->read(AppController::ID_KEY),
                               array(),                                                                        "Are you sure you want to delete this user?"); ?>

        </td>
    </tr>
<?php
    }
?>
</table>
<?php
}
?>

<p>
<?php
    echo $html->link('Add a user', '/users/add');
?>
</p>
