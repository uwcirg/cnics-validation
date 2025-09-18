<?php
  /** 
   * Log class
   *
   * @author Greg Barnes
   * @version 0.1
   */
class Log extends AppModel
{
    var $name = 'Log';
    var $belongsTo = array('User');
}
