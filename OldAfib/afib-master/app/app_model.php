<?php

/**
  Status for a coded item
 */
class Status {
    public $name;

    function __construct($name) {
        $this->name = $name;
    }

    function getName() {
        return $this->name;
    }

    /**
     * Turn an array of names into an array of statuses, keyed by the names 
     * @param names array of names
     */
    static function statusArray($names) {
        $a = array();

        foreach($names as $name) {
            $a[$name] = new Status($name);
        }       
 
        return $a;
    }
}

/**
 * Action for a coded item
 */
class Action {
    /** String to indicate a default value */
    const DV = '__default';

    /** Suffix for default canBePerformed function name */
    const CBP_SUFFIX = 'CanBePerformed';

    /** Prefix for default canPerform function name */
    const CP_PREFIX = 'is_';

    /** Name of a function that checks for a particular status */
    static $STATUS_FUNC = array('CodedItemBehavior', 'statusMatches');

    /** Name of the action */
    public $name;

    /** String describing the person performing the action 
     *  (adding 'er' to the name doesn't always work) 
     */
    public $actor;

    /** A condition to test before the action can be performed.  
     *  Either a status (indicating the coded item must have that status)
     *  or a pointer to a boolean function (which must return true when 
     *  passed the coded item)
     */
    public $canBePerformed;

    /** A condition to test whether a particular actor can perform the action
     *  Should be a pointer to a boolean function (which must return true when 
     *  passed the coded item and the actor 
     */
    public $canPerform;

    /** The new status after the action is performed.  Either a status,
     *  or the null pointer (indicating we can't automatically update the
     *  status)
     */
    public $newStatus;

    /**
     * Whether the {actor}_id field should be set when the action is performed
     */
    public $setActor;

    /** The classname of the item; used for some callbacks */
    public $className;

    function __construct($name, $actor, $canBePerformed, $canPerform, 
                         $newStatus, $setActor, $className) 
    {
        $this->name = $name;
        $this->actor = $actor;
        $this->canBePerformed = $canBePerformed;
        $this->canPerform = $canPerform;
        $this->newStatus = $newStatus;
        $this->setActor = $setActor;
        $this->className = $className;
    }

    /**
     * Turn an array of names into an array of statuses, keyed by the names 
     * @param names array of names
     */
    static function actionArray($className, $actions) {
        $a = array();

        foreach($actions as $action) {
            // change function names to callback
            $name = $action[0];
            $actor = $action[1];
            $canBePerformed = $action[2];
            $canPerform = $action[3];
            $newStatus = $action[4];
            $setActor = $action[5];

            if ($canBePerformed == self::DV) {
                $canBePerformed = array($className, $name . self::CBP_SUFFIX);
            } else if (!is_array($canBePerformed) &&
                       $canBePerformed != self::CREATION) 
            {
                $canBePerformed = array($className, $canBePerformed);
            }

            if ($canPerform == self::DV) {
                $canPerform = array($className, self::CP_PREFIX . $actor);
            } else if (!is_array($canPerform)) {	
            // change to callback if it isn't already
                $canPerform = array($className, $canPerform);
            }

            $a[$name] = new Action($name, $actor, $canBePerformed, 
                                   $canPerform, $newStatus, $setActor, 
                                   $className);
        }       
 
        return $a;
    }

    const CREATION = '__creation'; 

    /**
     * Does the action create an item?
     * @return true if the action is a creation action
     */
    public function isCreationAction() {
        return $this->canBePerformed == self::CREATION;
    }

    /**
     * Can the action be performed on an item?  
     * @param item the item
     * @return true if the action can be performed on the item
     */
    public function canBePerformed($item) {
        if (is_array($this->canBePerformed)) {
            return call_user_func(self::$STATUS_FUNC, $this->className, 
                                  $item, $this->canBePerformed);
        } else {
            return call_user_func($this->canBePerformed, $item);
        }
    }

    /**
     * Can the action be performed by a user on an item?  
     * @param user User
     * @param item the item
     * @return true if the user can perform the action on the item
     */
    public function canPerformAction($user, $item) {
        return call_user_func($this->canPerform, $user, $item);
    }
}

/**
 * Superclass of all models
 */
class AppModel extends Model {
}
?>
