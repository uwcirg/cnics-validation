<?php
/** 
 *  Handles much of the authorization/authentication 
 *
 *  @author Justin McReynolds, Greg Barnes
 *  @version 0.2
*/


class DhairAuthComponent extends Object
{
    var $components = array('RequestHandler', 'Session');

    //called before Controller::beforeFilter()
    function initialize(&$controller) {
        // saving the controller reference for later use
        $this->controller =& $controller;
    }

    /**
      Was a bad id field passed into form (and similar) requests?
      @param vars Array of vars that should contain the id
      @return true if the id field was not present or doesn't match;
              false if it was present and matches
     */
    function badId($vars)
    {
        if (!$this->Session->check(AppController::ID_KEY)) {
	    return true;
        }

        if (empty($vars) || empty($vars[AppController::ID_KEY]) ||
            $vars[AppController::ID_KEY] != 
	        $this->Session->read(AppController::ID_KEY))
        {
            // dump a bunch of variables and abort
            $this->log('bad session id ');
            $this->log('_id: ' . $this->Session->read(AppController::ID_KEY));

            if (empty($vars)) {
                $this->log('vars empty');
            } else if (empty($vars[AppController::ID_KEY])) {
                $this->log('vars[ID_KEY] empty');
            } else {
                $this->log($vars[AppController::ID_KEY]);
            }

            if ($this->RequestHandler->isPost()) {
                $this->log('isPost');
            } else if ($this->RequestHandler->isGet()) {
                $this->log('isGet');
            }

            // don't log secret fields
	    if (!empty($this->controller->params['data']) &&
	        !empty($this->controller->params['data']['User'])) 
            {
	        unset($this->controller->params['data']['User']['password']);
	        unset($this->controller->params['data']['User']['password_confirm']);
            }

            // unset sensitive fields
	    if (!empty($this->controller->params['data']) &&
	        !empty($this->controller->params['data']['Patient'])) 
            {
	        unset($this->controller->params['data']['Patient']);
            }

            $this->log('params: ' . print_r($this->controller->params, true));

            if (!empty($this->passedArgs)) {
                $this->log('passedArgs: ' . print_r($this->passedArgs, true));
            }
	    
	    return true;
        } else {
//            $this->log('session id matches');
	    return false;
        }
    }

    /** Actions that can or should be accessed only as POST.
     * Key = controller.action,
     * value = whether the action can be accessed as both a GET and POST
     */
    private static $postActions = 
        array('criterias.add' => false,
              'event_derived_datas.edit' => false,
              'events.add' => true,
              'events.addMany' => true,
              'events.assign3rdMany' => true,
              'events.assignMany' => true,
              'events.edit' => true,
              'events.markNoPacket' => true,
              'events.review1' => true,
              'events.review2' => true,
              'events.review3' => true,
              'events.screen' => true,
              'events.scrub' => true,
              'events.sendMany' => true,
              'events.upload' => true,
              'solicitations.add' => false,
              'users.add' => true,
              'users.edit' => true);

    /**
     * Does this HTTP request use the proper method?
     * @return false if they perform a GET when they should have performed a 
     *         POST
     */
    private function properMethod() {
        $arrayKey = $this->controller->params['controller'] . '.' . 
	            $this->controller->params['action'];
	$isPost = $this->RequestHandler->isPost();

        if ($isPost || !array_key_exists($arrayKey, self::$postActions)) {
	    // Don't need to check, but...

	    if ($isPost && !array_key_exists($arrayKey, self::$postActions)) {
	        // this is somewhat suspicious
	        $this->log("Action $arrayKey accessed via POST method, but it is not in the list of postActions in dhair_auth.");
            }

	    return true;
        } else {   
	    /* We know it's a GET on an action that sometimes/always uses POST
	       These are only okay if you can sometimes use GET, and you
	       don't pass any data */
	    $getSometimesOkay = self::$postActions[$arrayKey];
	    $noData = empty($this->controller->data);
	    return $noData && $getSometimesOkay;
        }
    }

    /** Does the request look like a potential cross-site request forgery?
      * @return Whether the request looks like a cross-site request forgery
      */
    /* All POST requests must be checked unless there is no authorized user
       We assume the only GET requests 
       that must be checked are those that change the database state,
       or the special kiosk mode changes.  These actions are listed
       below (kiosk only performs an action if a parameter is passed).  

       Any future exceptions will have to be added
       to the else clause below
     */
    function possibleXsrf() {
        if (!$this->properMethod()) {
	    $this->log("Improper GET request on: " . 
	               $this->controller->params['controller'] . '.' . 
		       $this->controller->params['action'] . ' ' .
		       print_r($this->controller->data, true));
	    return true;
        }

	if ($this->RequestHandler->isPost()) {
            if (!empty($this->controller->data['AppController'])) {
	        return $this->badId($this->controller->data['AppController']);
	    } else {
	        return $this->badId(null);	// sure to fail
	    }
        } else if ($this->RequestHandler->isGet() && 
	           ($this->controller->params['action'] == 'delete' ))
        {
	    if (!empty($this->controller->params) && 
	        !empty($this->controller->params['url'])) 
            {
                return $this->badId($this->controller->params['url']);
	    } else {
	        return $this->badId(null);	// sure to fail
	    }
	} else {
	    return false;
        }
    }

    /** Actions that should only be accessed by reviewers */
    private static $reviewerActions = 
        array('events.review1', 'events.review2', 'events.review3');

    /** Actions that should only be accessed by uploaders */
    private static $uploaderActions = 
        array('events.upload', 'events.markNoPacket');

    /** Actions that should only be accessed by administrators */
    private static $adminActions = 
        array('criterias.add', 'criterias.delete', 
              'event_derived_datas.edit', 
              'events.add', 
              'events.addMany', 'events.assign3rdMany', 'events.assignMany', 
              'events.edit', 'events.getCsv',
              'events.screen', 'events.scrub',
              'events.sendMany',
              'events.viewAll', 
              'solicitations.add', 'solicitations.delete',
              'users.add', 'users.delete', 'users.edit', 'users.viewAll');

    /** Actions that can be accessed by anyone */
    private static $openActions = 
        array('events.index', 'events.download');
        /* download credentials checked in function, as different users
           can download at different times */

    /**
     * Is a user authorized to access a particular action
     * @param authUser User
     * @return true if the user is authorized to access this controller/action
     */
    function authorized($authUser) {
        // create canonical string version of action
        $action= $this->controller->params['controller'] . '.' . 
	         $this->controller->params['action'];

        // sanity check:  make sure this action is in one of our arrays
        if (!(in_array($action, self::$reviewerActions)) && 
            !(in_array($action, self::$uploaderActions)) && 
            !(in_array($action, self::$adminActions)) && 
            !(in_array($action, self::$openActions)))
        {
            $this->log("Action $action is not listed in the authorization 
                        arrays.");
        }

        if (in_array($action, self::$reviewerActions) && 
                     !$this->controller->User->isReviewer($authUser))
        {
            return false;
        } else if (in_array($action, self::$uploaderActions) && 
                   !$this->controller->User->isUploader($authUser))
        {
            return false;
        } else if (in_array($action, self::$adminActions) && 
                   !$this->controller->User->isAdmin($authUser))
        {
            return false;
        } else {
            return true;
        }
    }
}
