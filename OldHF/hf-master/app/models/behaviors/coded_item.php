<?php
/**
 * An item that can be coded/adjudicated/reviewed/etc.
 */
class CodedItemBehavior extends ModelBehavior {
    /** Name of the model using this behavior */
    private $name;
    /** Name of the table the model uses */
    private $useTable;
    /** Current action name */
    private $actionName;
    /** Current authorized user id */
    private $authUserId;
    /** Has beforeSave been called yet? */
    private $beforeSaveCalled;

    function setup(&$model, $config = array()) {
        parent::setup($model, $config);
        $this->name = $model->name;
        $this->useTable = $model->useTable;
    }

    /**
     * Save info about the current action name and authorized user
     * @param actionName Name of the current action
     * @param authUserId id of the authorized user
     */
    public function setVars(&$model, $actionName, $authUserId) {
        $this->actionName = $actionName;
        $this->authUserId = $authUserId;
        $this->beforeSaveCalled = false;
    }

    /**
     * Callback function to get a status name from a row of a db query
     * @param row Array containing the row
     * @return The status field from the row
     */
    private function getStatusName($row) {
        return $row[$this->name]['status'];
    }

    /**
     * Callback function to get a status count from a row of a db query
     * @param row Array containing the row
     * @return The count field from the row
     */
    private function getStatusCount($row) {
        return $row[0]['count'];
    }

    /**
     * Create an array of status/status counts pairs 
     * @return array of status/status counts pairs 
     */
    function countStatuses(&$model) {
        $counts = $model->find('all',
           array('fields' => array("{$this->name}.status",
                                   "COUNT({$this->name}.id) as count"),
                 'group' => "{$this->name}.status"));
        
        if (empty($counts)) {
            $result = array();
        } else {
            $result = array_combine(
                array_map(array('CodedItemBehavior', 'getStatusName'), $counts),
                array_map(array('CodedItemBehavior', 'getStatusCount'),                                   $counts));
        }
        
        // fill in the blank statuses with zeroes
        foreach ($model->getStatusNames() as $status) {
            if (empty($result[$status])) {
                $result[$status] = 0;
            }
        }

        // remove any null statuses 
        unset($result[null]);

        return $result;
    }

    /**
     * Does a particular action change the item's status?
     * @param actionName name of the action
     * @return true if the action can change the item's status?
     */
    public function actionChangesStatus(&$model, $actionName) {
        $actions = $model->getActions();
        return !empty($actions[$actionName]);
    }

    /**
     * Does a particular action create an instance of the item?
     * @param actionName name of the action
     * @return true if the action is a creation action
     */
    public function isCreationAction(&$model, $actionName) {
        $actions = $model->getActions();
        
        if (!array_key_exists($actionName, $actions)) { 
            return false;
        } else {
            $action = $actions[$actionName];;
            return $action->isCreationAction();
        }
    }

    /**
     * Should status, actor and action date be updated on a save call for
     * the current action?
     * @return true if these fields should be updated
     */
    function shouldUpdateStatus($model) {
        $actions = $model->getActions();

        if (array_key_exists($this->actionName, $actions)) { 
            $action = $actions[$this->actionName];
            $newStatus = $action->newStatus;

            // if $newStatus is null, can't do this automatically
            return $newStatus !== null;
        } else {
            // not an action that changes status
            return false;
        }
    }

    /**
     * Intercept model save calls and update status, actor and action date 
     * per the current action
     * @param $options Options passed to save
     */
    public function beforeSave(&$model, $options) {
        $this->beforeSaveCalled = true;

        if (!empty($model->data['Event'])) {
            if (!$this->shouldUpdateStatus($model)) {
                return true;
            } else {
                $actions = $model->getActions();
                $action = $actions[$this->actionName];
                $actor = $action->actor;
                $newStatus = $action->newStatus;
                assert(!empty($newStatus));

                if ($newStatus instanceof Status) {
                    $newStatusName = $newStatus->getName();
                } else {
                    if ($newStatus != Action::DV) {
                        $this->log("bad newStatus " . 
                                   print_r($newStatus, true));
                        return false;
                    }

                    if (empty($model->data['Event']['id'])) {
                        $this->log('Cannot update status without knowing current status');
                        return false;
                    } else {
                        $oldEvent = 
                            $model->findById($model->data['Event']['id']);
                        $oldStatus = $oldEvent['Event']['status'];
                        $statusNames = $model->getStatusNames();

                        foreach ($statusNames as $key => $status) {
                            if ($status == $oldStatus) {
                                $newStatusName = $statusNames[$key + 1];
                                break;
                            }
                        }

                        if (empty($newStatusName)) {
                            $this->log(
                                "Couldn't figure out new status name for "
                                . print_r($oldEvent, true));
                            return false;
                        }
                    }
                }

                // set status, actor id, and action date
                $model->data['Event']['status'] = $newStatusName;
                $model->data['Event']["{$this->actionName}_date"] = 
                    date('Y-m-d');

                if ($action->setActor) {
                    $model->data['Event']["{$actor}_id"] = $this->authUserId;
                }

                // add these fields to the whitelist (fieldlist) if it exists
                if (!empty($model->whitelist)) {
                    $model->whitelist[] = 'status';
                    $model->whitelist[] = "{$this->actionName}_date";

                    if ($action->setActor) {
                        $model->whitelist[] = "{$actor}_id";
                    }
                }
            }
        }

        return true;
    }

    /**
     * Insure beforeSave is called (so that status, actor, and action_date are
     * written)
     */
    public function insureSave(&$model, $item) {
        if (!$this->beforeSaveCalled && $this->shouldUpdateStatus($model)) {
            $model->save($item, array('fieldlist' => array()));
        }
    }

    /**
     * Can an action be performed on an item?
     * @param actionName name of the action
     * @param item the item
     * @return true if the action can be performed on the item
     */
    public function canBePerformed(&$model, $actionName, $item) {
        $actions = $model->getActions();

        if (!array_key_exists($actionName, $actions)) { 
            return false;
        } else {
            $action = $actions[$actionName];;
            return $action->canBePerformed($item);
        }
    }

    /**
     * Can a user perform an action on an item?
     * @param actionName name of the action
     * @param user User
     * @param item the item
     * @return true if the user can perform the action on the item.  False if
     *    the action is not in our array of status-changing actions
     */
    public function canPerformAction(&$model, $actionName, $user, $item) {
        $actions = $model->getActions();

        if (!array_key_exists($actionName, $actions)) { 
            return false;
        } else {
            $action = $actions[$actionName];;
            return $action->canPerformAction($user, $item);
        }
    }

    /**
     * Is an item's status in a particular array of statuses?
     * @param className name of the item's class
     * @param item The item
     * @param statuses The array of statuses
     */
    public static function statusMatches($className, $item, $statuses) {
        return !empty($item) && 
               in_array($item[$className]['status'], $statuses);
    }

    /**
     * Does a particular id field for an item match a user's id?
     * @param item The item
     * @param fieldName Name of the id field
     * @param user The user
     */
    public static function idFieldMatches($item, $fieldName, $user) {
        return !empty($item) && !empty($user) && !empty($item[$fieldName]) &&
               !empty($user['User']) && !empty($user['User']['id']) &&
               $item[$fieldName] == $user['User']['id'];
    }
}
?>
