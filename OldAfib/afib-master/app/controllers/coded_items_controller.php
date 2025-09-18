<?php
/**
 * @version 0.1
 */
class CodedItemsController extends AppController
{
    var $uses = array('User');
    var $components = array('DhairAuth'); //, 'DhairDateTime');

    /** Name of the underlying model */
    protected $model;

    /** Instance of the underlying model */
    protected $modelInstance;

    /** Array of action aliases */
    protected $aliases;

    /** Name of the current action */
    protected $actionName;

    /** Id of the current item being acted upon */
    protected $id;

    /** current item being acted upon */
    protected $item;

    /** User-viewable name for the type of coded item */
    protected $displayString;

    /** If true, action succeeded */
    protected $actionSucceeded;

    public function __construct($model, $displayString) {
        parent::__construct();
        $this->model = $model;
        $this->displayString = $displayString;
    }

    /**
     * Try to find the id of the coded item being acted upon
     * @return The id, if we can find it, null if we can't
     */
    private function getId() {
        if (!empty($this->data)) {
            if (empty($this->data[$this->model]) || 
                empty($this->data[$this->model]['id']))
            {
                return null;
            } else {
                return intval($this->data[$this->model]['id']);
            }
        } else if (!empty($this->params['pass'])) {
        // should be last parameter passed
            $pass = $this->params['pass'];
            return intval($pass[count($pass) - 1]);
        } else {
            return null;
        }
    }

    /**
     * Do our best to find the id and hence, the item associated with the
     * current action
     */
    protected function getIdAndItem() {
        if (empty($id)) {
            $this->id = $this->getId();
           
            if (!empty($this->id)) {
                $this->item = $this->modelInstance->findById($this->id);
            } else {
                $this->item = null;
            }
        }
    }
        

    /**
     * Check that a particular action can be performed
     *  1. Can the action be performed on the item in its current state?
     *  2. Can the action be performed by the authorized user?
     * @param actionName name of the action
     * @return true if the action can be performed, false otherwise.  If false,
     *    Session->setFlash has already been called
     */
    protected function checkPreAction($actionName) {
        $this->getIdAndItem();

        if (!$this->modelInstance->canPerformAction($actionName, 
                                                    $this->authUser, 
                                                    $this->item)) 
        {
            if (empty($this->id)) {
                $this->Session->setFlash( "You cannot perform $actionName on {$this->displayString}s.");
            } else {
                $this->Session->setFlash( "You cannot perform $actionName on this {$this->displayString}.");
            }

            return false;
        }

        if (empty($this->item)) {
        // no item only works for creation actions
            if (!$this->modelInstance->isCreationAction($actionName)) {
                $this->Session->setFlash(
                    "No {$this->displayString} specified.");
                return false;
            } 
        } else {
            if (!$this->modelInstance->canBePerformed($actionName, 
                                                      $this->item)) 
            {
                $this->Session->setFlash( "$actionName cannot be performed on this {$this->displayString}.");
                return false;
            }
        }

        return true;
    }

    /**
     * @param modelInstance Instance of the model (which should be a coded item)
     * @param aliases array of actions that should be considered as aliases
     *   for other (coded item) actions
     */
    function beforeFilter($modelInstance, $aliases) {
        parent::beforeFilter();
        $this->modelInstance = $modelInstance;
        $this->aliases = $aliases;
        $this->actionName = $this->params['action'];
        $this->actionSucceeded = false;
      
        if (!empty($this->aliases[$this->actionName])) {
            $this->actionName = $this->aliases[$this->actionName];
        }
        
        $this->modelInstance->setVars($this->actionName, 
                                      $this->authUser['User']['id']);

        /* if this is an action that changes status, check that we can do it */
        if ($this->modelInstance->actionChangesStatus($this->actionName)) {
            if (!$this->checkPreAction($this->actionName)) {
            // failure
                $this->redirect('index');
            }
        }
    }

    /**
     * update the status, etc., if it hasn't been done already
     */
    function updateStatus() {
        if ($this->modelInstance->actionChangesStatus($this->actionName)) 
        {
            $this->modelInstance->insureSave($this->item);
        }
    }
}
?>
