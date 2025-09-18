<?php
  /** 
   * User class
   *
   * @author Greg Barnes
   * @version 0.1
   */
class User extends AppModel
{
    var $name = 'User';
    var $hasMany = array('Review' => array('foreignKey' => 'reviewer_id'));
    
    /**
     * Check whether a user is from the same site as some other entity
     * @param user The user
     * @param entity Something, presumably an array with a 'site' key
     * @return true if the user's site matches the entity's
     */
    public static function sameSite($user, $entity) {
        return (!empty($entity) && $user['User']['site'] == $entity['site']);
    }

    /**
     * Is a user an administrator?
     * @param user The user
     * @return true if the user is an administrator
     */
    public static function isAdmin($user) {
        return !empty($user) && !empty($user['User']) &&
               !empty($user['User']['admin_flag']) &&
               $user['User']['admin_flag'];
    }

    /**
     * Is a user a reviewer?
     * @param user The user
     * @return true if the user is a reviewer
     */
    public static function isReviewer($user) {
        return !empty($user) && !empty($user['User']) &&
               !empty($user['User']['reviewer_flag']) &&
               $user['User']['reviewer_flag'];
    }

    /**
     * Is a user an uploader?
     * @param user The user
     * @return true if the user is an uploader
     */
    public static function isUploader($user) {
        return !empty($user) && !empty($user['User']) &&
               !empty($user['User']['uploader_flag']) &&
               $user['User']['uploader_flag'];
    }

    /**
     * Callback function to get a user id from a row of a db query
     * @param row Array containing the row
     * @param return The id field from the row
     */
    function getUserId($row) {
        return $row['User']['id'];
    }

    /**
     * Callback function to get a username from a row of a db query
     * @param row Array containing the row
     * @param return The name field from the row
     */
    function getUsername($row) {
        return $row['User']['username'] . ' (' . $row['User']['id'] . ')';
    }

    /**
     * Callback function to get a login name from a row of a db query
     * @param row Array containing the row
     * @param return The login name field from the row
     */
    function getLogin($row) {
        return $row['User']['login'];
    }

    /**
     * Get all reviewers, as an array suitable for $form->select
     * @param thirdReviewer If true, get eligible 3rd reviewers
     * @return All reviewers
     */
    public function getReviewers($thirdReviewer) {
        $fieldName = $thirdReviewer ? 'third_reviewer_flag' : 'reviewer_flag';
        $reviewers = $this->find('all', array('conditions' => 
                                        array("User.$fieldName" => true)));
        return array_combine(
            array_map(array('User', 'getUserId'), $reviewers),
            array_map(array('User', 'getUsername'), $reviewers));
    }
}
