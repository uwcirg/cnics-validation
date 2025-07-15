<?php
  /** 
   * Solicitation class
   *
   * @author Greg Barnes
   * @version 0.1
   */
class Solicitation extends AppModel
{
    var $name = 'Solicitation';
    var $belongsTo = array('Event');
    var $order = 'Solicitation.date';
}
