<?php
class UsersController extends AppController {
    var $name = 'Users';
    var $uses = array('User', 'Patient');
    var $helpers = array('Html', 'Form', 'Javascript');

    /**
     * Check that data to add a user is okay; sanitize any dubious strings
     * @param data The data
     * @return an array:  (true) if all is well,
     *                    (false, message) if there was a problem
     */
    private function checkData(&$data) {
        $data['User']['username'] = strip_tags($data['User']['username']);
        $data['User']['login'] = strip_tags($data['User']['login']);
        $data['User']['first_name'] = strip_tags($data['User']['first_name']);
        $data['User']['last_name'] = strip_tags($data['User']['last_name']);
        $data['User']['site'] = strip_tags($data['User']['site']);

        if (empty($data['User']['login'])) {
            $data['User']['login'] = $data['User']['username'];
        }

        return array(true);
    }

    /**
     * Add a user
     */
    function add() {
        if (!empty($this->data)) {
            $result = $this->checkData($this->data);

            // first array item returned indicates success
            if (!$result[0]) {
                // for failure, 2nd array item holds a message
                $this->Session->setFlash($result[1]);
            } else {
                $this->User->save($this->data, array('fieldList' => 
                    array('username', 'login', 'first_name', 'last_name', 
                          'site',
                          'reviewer_flag', 'third_reviewer_flag', 
                          'uploader_flag', 
                          'admin_flag')));
                $this->setSuccessMessage("Created new user 
                   {$this->data['User']['username']} ({$this->data['User']['login']}, {$this->data['User']['first_name']} {$this->data['User']['last_name']}, {$this->data['User']['site']})");
                $this->redirect('/users/viewAll');
            }
        }

        $this->set('sites', $this->Patient->getSiteArray());
    }

function logout() {
    $this->Session->destroy();
    $this->layout = false;
}
    /**
     * Show all users
     */
    function viewAll() {
        $this->set('users', $this->User->find('all', array(
            'order' => array('User.username'))));
    }


    /**
     * Delete a user
     * @param id user id
     */
    function delete($id) {
        $id = intval($id);
        $user = $this->User->findById($id);

        if (empty($user)) {
            $this->Session->setFlash("No such user: $id.");
        } else {
            $this->User->del($id);
            $this->setSuccessMessage("User $id deleted.");
        }

        $this->redirect(array('action' => 'viewAll'));
    }

    /**
     * Edit a user
     * @param id user id
     */
    function edit($id) {
        $id = intval($id);

        if (!empty($this->data)) {
            $id = intval($this->data['User']['id']);
        }

        $oldUser = $this->User->findById($id);

        if (empty($oldUser)) {
            $this->Session->setFlash("No such user: $id.");
            $this->redirect(array('action' => 'viewAll'));
        }

        if (!empty($this->data)) {
            $result = $this->checkData($this->data);

            // first array item returned indicates success
            if (!$result[0]) {
                // for failure, 2nd array item holds a message
                $this->Session->setFlash($result[1]);
            } else {
                $this->User->id = $id;
                $this->User->save($this->data, array('fieldList' => 
                    array('username', 'login', 'first_name', 'last_name', 
                          'site',
                          'reviewer_flag', 'third_reviewer_flag', 
                          'uploader_flag', 
                          'admin_flag')));
                $this->setSuccessMessage('User record updated');
                $this->redirect('/users/viewAll');
            }
        } else {
            $this->data = $oldUser;
        }

        $this->set('sites', $this->Patient->getSiteArray());
    }
}
