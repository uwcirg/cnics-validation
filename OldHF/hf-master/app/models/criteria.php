<?php
  /** 
   * Criteria class
   *
   * @author Greg Barnes
   * @version 0.1
   */
// yes, I know criteria is a plural noun.  But Cake likes it this way
class Criteria extends AppModel {
    var $name = 'Criteria';
    var $belongsTo = array('Event');

    /**
     * Extract criteria name/value pairs from an array of strings
     * @param a The array
     * @param start Index in the array where the pairs start
     * @return If there is a value for every name, the name value pairs in 
     *    Cake form:  
     *    [0] => array('name' => name, 'value' => value),
     *    [1] => array('name' => name, 'value' => value), etc.
     *
     *    If there are an odd number of elements, return an error result
     *    array('error' => 1);
     */
    function extractCriteriaFromArray($a, $start) {
        $criteria = array();
        $cIndex = 0;	// index into criteria array

        for ($i = $start; $i < count($a) - 1; $i += 2) {
            $criteria[$cIndex] = array('name' => trim(strip_tags($a[$i])), 
                                       'value' => trim(strip_tags($a[$i+1])));
            $cIndex++;
        }

        /* if we're not at the end, they're trying to pair up an odd number
           of elements */
        if ($i != count($a)) {   
            return array('error' => 1);
        } else {
            return $criteria;
        }
    }
}
