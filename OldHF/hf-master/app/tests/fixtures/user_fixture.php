<?php

class UserFixture extends CakeTestFixture {
    var $name = 'User';
    var $import = 'User';

    var $records = array(
        array('id' => 1, 'first_name' => 'Greg', 'last_name' => 'Barnes', 'username' => 'gsbarnes@washington.edu', 'login' => 'gsbarnes@washington.edu', 'site' => 'UW', 'uploader_flag' => 1, 'reviewer_flag' => 1, 'third_reviewer_flag' => 1, 'admin_flag' => 1),
        array('id' => 2, 'first_name' => 'Justin', 'last_name' => 'McReynolds', 'username' => 'user3@example.com', 'login' => 'user3@example.com', 'site' => 'UW', 'uploader_flag' => 1, 'reviewer_flag' => 1, 'third_reviewer_flag' => 0, 'admin_flag' => 1),
        array('id' => 3, 'first_name' => 'Generic', 'last_name' => 'User', 'username' => 'user@example.com', 'login' => 'different@example.com', 'site' => 'UNC', 'uploader_flag' => 1, 'reviewer_flag' => 0, 'third_reviewer_flag' => 1, 'admin_flag' => 0),
        // bad e-mail address
        array('id' => 4, 'first_name' => 'Some', 'last_name' => 'Body', 'username' => 'someone@', 'login' => 'someone@', 'site' => 'UNC', 'uploader_flag' => 1, 'reviewer_flag' => 1, 'third_reviewer_flag' => 0, 'admin_flag' => 0),
        // another bad e-mail address
        array('id' => 5, 'first_name' => 'Bad', 'last_name' => 'Address', 'username' => 'user@com.', 'login' => 'user@com.', 'site' => 'UNC', 'uploader_flag' => 1, 'reviewer_flag' => 1, 'third_reviewer_flag' => 0, 'admin_flag' => 0),
        array('id' => 6, 'first_name' => 'Sample', 'last_name' => 'Address', 'username' => 'user2@example.com', 'login' => 'user2@example.com', 'site' => 'UNC', 'uploader_flag' => 1, 'reviewer_flag' => 1, 'third_reviewer_flag' => 0, 'admin_flag' => 0),
        array('id' => 7, 'first_name' => 'Also', 'last_name' => 'Bad', 'username' => 'user2@example.', 'login' => 'user2@example.', 'site' => 'UNC', 'uploader_flag' => 0, 'reviewer_flag' => 0, 'third_reviewer_flag' => 1, 'admin_flag' => 0)
    );
}

?>
