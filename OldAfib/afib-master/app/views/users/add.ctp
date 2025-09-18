<h1>Add a new user</h1>
<?php
    echo $form->create(null, array('controller' => 'users',
                                   'action' => 'add'));
    echo $form->hidden(AppController::CAKEID_KEY,
                       array(
                           'value' => $session->read(AppController::ID_KEY)
                      ));
?>
<table class="formTable">
<tr>
  <th>Username</th>
  <td>
  <?php 
    echo $form->input('User.username', array('label' => ''));
  ?>
  </td>
  <td class="explain">should be a valid e-mail address</td>
</tr>
<tr>
  <th>Login</th>
  <td>
  <?php 
    echo $form->input('User.login', array('label' => ''));
  ?>
  </td>
  <td class="explain">used to log in to the application; leave blank if the same as username</td>
</tr>
<tr>
  <th>First name</th>
  <td>
  <?php 
    echo $form->input('User.first_name', array('label' => ''));
  ?>
  </td>
</tr>
<tr>
  <th>Last name</th>
  <td>
  <?php 
    echo $form->input('User.last_name', array('label' => ''));
  ?>
  </td>
</tr>
<tr>
  <th><?php echo "<label for = \"User.site\">Site</label>";?></th>
  <td>
  <?php
    echo $form->select("User.site", $sites);
  ?>
  </td>
</tr>
<tr>
  <th>Upload packets?</th>
  <td>
  <?php
    echo $form->input('User.uploader_flag',
                      array('type' => 'checkbox',
                            'label' => ''));
  ?>
  </td>
</tr>
<tr>
  <th>Reviewer?</th>
  <td>
  <?php
    echo $form->input('User.reviewer_flag',
                      array('type' => 'checkbox',
                            'label' => ''));
  ?>
  </td>
</tr>
<tr>
  <th>Possible 3rd Reviewer?</th>
  <td>
  <?php
    echo $form->input('User.third_reviewer_flag',
                      array('type' => 'checkbox',
                            'label' => ''));
  ?>
  </td>
</tr>
<tr>
  <th>Admin?</th>
  <td>
  <?php
    echo $form->input('User.admin_flag',
                      array('type' => 'checkbox',
                            'label' => ''));
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
