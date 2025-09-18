<?php
  /** 
   * Patient class
   *
   * @author Greg Barnes
   * @version 0.1
   */
class Patient extends AppModel
{
    var $name = 'Patient';
    var $hasMany = array('Event');

    /**
     * Get an array of all the possible patient sites 
     */
    function getSites() {
        $query = "SELECT DISTINCT site from patients ORDER BY site";
        return $this->query($query);
    }

    /**
     * Callback function to get a site name from a row of a db query
     * @param row Array containing the row
     * @param return The site name field from the row
     */
    private function getSiteName($row) {
        return $row['patients']['site'];
    }

    /**
     * Create an array of the possible patient sites
     * @return array of patient sites for Cake select menu
     */
    function getSiteArray() {
        $sites = $this->getSites();
        $siteArray = array_combine(
            array_map(array('Patient', 'getSiteName'), $sites),
            array_map(array('Patient', 'getSiteName'), $sites));
        $siteArray['Other'] = 'Other';
        return $siteArray;
    }
}
