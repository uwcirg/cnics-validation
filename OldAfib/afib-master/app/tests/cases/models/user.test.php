<?php

require_once "my_test_case.php";

App::import('User');

class UserTestCase extends MyTestCase {
    var $fixtures = array('app.event', 'app.user', 'app.review', 'app.log', 
                          'app.patient', 'app.solicitation', 'app.criteria');

    function start() {
        parent::start();
        $this->User =& ClassRegistry::init('User');
    }

    function testSameSite() {
        $user = array('User' => array('site' => 'UW'));
        $this->assertEqual($this->User->sameSite($user, $user['User']), true,
                           "User is not the same site as itself");

        $patient = array('site' => 'UNC');
        $this->assertEqual($this->User->sameSite($user, $patient), false, 
                           "UW == UNC");
  
        $nullUser = array('User' => array('site' => null));
        $this->assertEqual($this->User->sameSite($user, $nullUser['User']), 
                           false, "UW == null");
  
        $this->assertEqual($this->User->sameSite($nullUser, $user['User']), 
                           false, "null == UW");
 
        $this->assertEqual($this->User->sameSite($nullUser, $nullUser['User']),
                           true, "null not the same as null");

        $this->assertEqual($this->User->sameSite($user, null), false,
                           "object null");
    }
    
    /**
     * Verify a function that checks a flag field
     * @param fieldName name of the flag field
     * @param functionName name of the function
     */
    function verifyFlagFunction($fieldName, $functionName) {
        $user1 = array('User' => null);
        $user2 = array('User' => array());
        $user3 = array('User' => array('id' => 5));
        $user4 = array('User' => array($fieldName => null));
        $user5 = array('User' => array($fieldName => 0));
        $user6 = array('User' => array($fieldName => 1));
        $callback = array('User', $functionName);
    
        $this->assertEqual(call_user_func($callback, null), false,
                           "$functionName true for null user");
        $this->assertEqual(call_user_func($callback, $user1), false,
                           "$functionName true for bogus user1");
        $this->assertEqual(call_user_func($callback, $user2), false,
                           "$functionName true for bogus user2");
        $this->assertEqual(call_user_func($callback, $user3), false,
                           "$functionName true for bogus user3");
        $this->assertEqual(call_user_func($callback, $user4), false,
                           "$functionName true for bogus user4");
        $this->assertEqual(call_user_func($callback, $user5), false,
                           "$functionName true for $fieldName = 0");
        $this->assertEqual(call_user_func($callback, $user6), true,
                           "$functionName false for $fieldName = 1");
    }

    function testIsUploader() {
        $this->verifyFlagFunction('uploader_flag', 'isUploader');
    }

    function testIsReviewer() {
        $this->verifyFlagFunction('reviewer_flag', 'isReviewer');
    }

    function testIsAdmin() {
        $this->verifyFlagFunction('admin_flag', 'isAdmin');
    }

    function testGetUserId() {
        $answer = 'whatever';
        $this->assertEqual($this->User->getUserId(
             array('User' => array('id' => $answer))), $answer);
    }

    function testGetUsername() {
        $answer = 'whatever';
        $this->assertEqual($this->User->getUsername(
             array('User' => array('username' => $answer, 'id' => 5))), 
             "$answer (5)");
    }

    function testGetLogin() {
        $answer = 'whatever';
        $this->assertEqual($this->User->getLogin(
             array('User' => array('login' => $answer))), $answer);
    }

    function testGetReviewers() {
        $this->checkArrayDiff($this->User->getReviewers(false),
             array(1 => 'gsbarnes@washington.edu (1)',
                   2 => 'user3@example.com (2)', 
                   4 => 'someone@ (4)',
                   5 => 'user@com. (5)',
                   6 => 'user2@example.com (6)'),
             "Reviewer arrays don't match");
    }

    function testGet3rdReviewers() {
        $this->checkArrayDiff($this->User->getReviewers(true),
             array(1 => 'gsbarnes@washington.edu (1)',
                   3 => 'user@example.com (3)',
                   7 => 'user2@example. (7)'),
             "Third reviewer arrays don't match");
    }
}

?>
