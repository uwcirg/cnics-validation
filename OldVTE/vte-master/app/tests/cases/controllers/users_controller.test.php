<?php

require_once "my_controller_test_case.php";

class UsersControllerTest extends MyControllerTestCase {
    var $fixtures = array('app.event', 'app.review', 'app.log', 
                          'app.patient', 'app.user', 'app.solicitation', 
                          'app.criteria');

    function startCase() {
        parent::startCase();
        $this->User =& ClassRegistry::init('User');
        $this->Patient =& ClassRegistry::init('Patient');
    }

    /**
     * Check an add action
     * @param data Data to pass in
     * @param expected Data expected (can be different due to strip tags, blank
     *     login
     */
    function checkAddData($data, $expected) {
        $result = $this->testAction('/users/add',
            array('data' => $data, 'method' => 'post', 'return' => 'contents'));
        $expectedPattern = "/Created new user\s+{$expected['username']} \({$expected['login']}, {$expected['first_name']} {$expected['last_name']}, {$expected['site']}\)/sm";
        $this->assertTrue(preg_match($expectedPattern, $result) == 1, 
            "Pattern ($expectedPattern) does not appear in result"); 
        $this->noErrors($result);

        // get last user added
        $user = $this->User->find('first', array('order' => 'User.id DESC'));

        $this->assertContainsFields($expected, $user['User'],
                                    "User fields don't match");
    }

    /**
     * Get vars for an add or edit
     * @param emptyLogin whether to use the default value for login
     * @param tags Whether to add tags to submitted data (which should 
     *    be stripped out)
     * @param id Id of the user (null if we're adding)
     * @return An array('data' => data to submit, 
     *                  'expected' => data that should be saved
     */
    function getAddEditVars($emptyLogin, $tags, $id = null) {
        $random = mt_rand();
        $username = "test$random";
        $login = $emptyLogin ? '' : "login$random";
        $firstname = "Joe$random";
        $lastname = "Test$random";
        $sitename = "UNC$random";
        $prefix = $tags ? '<b>' : '';
        $loginPrefix = $emptyLogin ? '' : $prefix;

        $data['User'] = array('username' => $prefix . $username,
                              'login' => $loginPrefix . $login,
                              'first_name' => $prefix . $firstname,
                              'last_name' => $prefix . $lastname,
                              'site' => $prefix . $sitename,
                              'reviewer_flag' => true,
                              'third_reviewer_flag' => true,
                              'uploader_flag' => true,
                              'admin_flag' => true);

        if (!empty($id)) {
            $data['User']['id'] = $id;
            $expected['id'] = $id;
        }

        $expected['username'] = $username;
        $expected['login'] = $emptyLogin ? $username : $login;
        $expected['first_name'] = $firstname;
        $expected['last_name'] = $lastname;
        $expected['site'] = $sitename;
        $expected['uploader_flag'] = true;
        $expected['reviewer_flag'] = true;
        $expected['third_reviewer_flag'] = true;
        $expected['admin_flag'] = true;
        
        return array('data' => $data, 'expected' => $expected);
    }
        
    /**
     * Check an add action
     * @param emptyLogin whether to use the default value login (username)
     * @param tags whether to add tags to the submitted data
     */
    function checkAdd($emptyLogin, $tags) {
        $vars = $this->getAddEditVars($emptyLogin, $tags, false);
        $this->checkAddData($vars['data'], $vars['expected']);
    }

    function testAdd() {
        $this->checkAdd(false, false);
    }

    function testAddEmptyLogin() {
        $this->checkAdd(true, false);
    }

    function testStripTags() {
        $this->checkAdd(true, true);
    }

    const USERNAME = 'gsbarnes@washington.edu';
    const DIFF_USERNAME = 'George';

    function testLoginNotUsername() {
        $this->User->id = 1;
        $this->User->saveField('username', self::DIFF_USERNAME);

        $result = $this->testAction('/users/add',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "/logged in as: " . self::USERNAME . "\s+\(" . 
                    self::DIFF_USERNAME .  "\)/sm";
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern ($expected) does not appear in result"); 
        $this->noErrors($result);

        $this->User->id = 1;
        $this->User->saveField('username', self::USERNAME);
    }

    function testLoginIsUsername() {
        $result = $this->testAction('/users/add',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "/logged in as: " . self::USERNAME . "\s+<\/div>/sm";
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern ($expected) does not appear in result"); 
        $this->noErrors($result);
    }

    function testViewAll() {
        $result = $this->testAction('/users/viewAll',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "/Users.*?Greg Barnes.*?gsbarnes@washington.edu.*?gsbarnes@washington.edu.*?UW.*?yes.*?yes.*?yes.*?yes.*?users\/edit.*?Edit.*?users\/delete.*?Delete/sm";
        $expected2 = "/gsbarnes@washington.edu.*?someone@.*?user2@example\..*?user2@example.com.*?user3@example.com.*?user@com.*?Generic User.*?user@example.com.*?different@example.com.*?UNC.*?yes.*?no.*?yes.*?no.*?users\/add.*?Add a user/sm";
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern ($expected) does not appear in result"); 
        $this->assertTrue(preg_match($expected2, $result) == 1, 
                          "Pattern ($expected2) does not appear in result"); 
        $this->noErrors($result);
    }

    function testDelete() {
        $result = $this->testAction('/users/delete/3',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $this->assertTrue(strpos($result, 'User 3 deleted') !== false);
        $this->noErrors($result);

        $result = $this->User->findById(3);
        // get the last criterion added
        $this->assertEqual($result, null, 
           'Found supposedly deleted user');

        $badId = 15;
        $result = $this->testAction("/users/delete/$badId",
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "No such user: $badId.";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);

        $badId = '15hey';
        $result = $this->testAction("/users/delete/$badId",
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "No such user: 15.";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);
    }

    function testEditNoSuchUser() {
        $badId = 15;
        $result = $this->testAction("/users/edit/$badId",
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = "No such user: $badId.";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);

        // again with post
        $result = $this->testAction("/users/edit",
            array('data' => array('User' => array('id' => $badId)), 
                  'method' => 'post', 'return' => 'contents'));
        $expected = "No such user: $badId.";
        $this->assertTrue(strpos($result, $expected) !== false);
        $this->noCakeErrors($result);
    }

    function testEditView() {
        $result = $this->testAction('/users/edit/3',
            array('data' => null, 'method' => 'get', 'return' => 'contents'));
        $expected = '/Edit user.*?Username.*?user@example.com.*?Login.*?different@example.com.*?First name.*?Generic.*?Last name.*?User.*?Site.*?selected="selected">UNC.*?Upload packets.*?checked="checked".*?Reviewer.*?Flag" \/>.*?Possible 3rd Reviewer.*?checked="checked".*?Admin.*?Flag" \/>.*?Submit/sm';
        $this->assertTrue(preg_match($expected, $result) == 1, 
                          "Pattern ($expected) does not appear in result"); 
        $this->noErrors($result);

        $result = $this->testAction('/users/edit/3',
            array('data' => null, 'method' => 'get', 'return' => 'vars'));
        $sites = $this->Patient->getSiteArray();
        $this->checkArrayDiff($sites, $result['sites'], 
                              'Sites array does not match');
    }

    /* 
     * Check an edit submission
     * @param emptyLogin True to use the default login value (username)
     * @param tags True to add tags to data (should be stripped out)
     * @param bogusId True to add a bogus id to the URL (id should be picked up
     *    from $data
     */
    function checkEdit($emptyLogin, $tags, $bogusId) {
        $vars = $this->getAddEditVars($emptyLogin, $tags, 3);
        $expected = $vars['expected'];

        $urlSuffix = $bogusId ? '5' : '';

        $result = $this->testAction("/users/edit/$urlSuffix",
            array('data' => $vars['data'], 'method' => 'post', 
                  'return' => 'contents'));
        $this->assertTrue(strpos($result, 'User record updated') !== false);
        $this->noErrors($result);

        $user = $this->User->findById(3);
        $this->checkArrayDiff($expected, $user['User'],
                              "User fields don't match");
    }

    function testEditEmptyLogin() {
        $this->checkEdit(true, false, true);
    }

    function testEditDiffLogin() {
        $this->checkEdit(false, false, false);
    }

    function testEditTags() {
        $this->checkEdit(false, true, false);
    }
}

?>
