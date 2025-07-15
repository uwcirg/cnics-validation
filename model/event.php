<?php
  /** 
   * Event class
   *
   * @author Greg Barnes
   * @version 0.1
   */
class Event extends AppModel
{
    var $name = 'Event';
    var $belongsTo = array('Patient', 
                           'Creator' => array('className' => 'User',
                                              'foreignKey' => 'creator_id'),
                           'Uploader' => array('className' => 'User',
                                               'foreignKey' => 'uploader_id'),
                           'Marker' => array('className' => 'User',
                                               'foreignKey' => 'marker_id'),
                           'Scrubber' => array('className' => 'User',
                                               'foreignKey' => 'scrubber_id'),
                           'Screener' => array('className' => 'User',
                                               'foreignKey' => 'screener_id'),
                           'Sender' => array('className' => 'User',
                                               'foreignKey' => 'sender_id'),
                           'Assigner' => array('className' => 'User',
                                               'foreignKey' => 'assigner_id'),
                           'Sender' => array('className' => 'User',
                                             'foreignKey' => 'sender_id'),
                           'Reviewer1' => array('className' => 'User',
                                                'foreignKey' => 'reviewer1_id'),
                           'Reviewer2' => array('className' => 'User',
                                                'foreignKey' => 'reviewer2_id'),
                           'Assigner3rd' => array('className' => 'User',
                                                  'foreignKey' => 
                                                      'assigner3rd_id'),
                           'Reviewer3' => array('className' => 'User',
                                                'foreignKey' => 'reviewer3_id')
                          );
    var $hasOne = array('EventDerivedData');
    var $hasMany = array('Review', 
                         'Criteria',
                         'Solicitation' => 
                              array('order' => 'Solicitation.date'));
    var $uses = 'User';

    /* annoying that I can't figure out a good way to access the static
       statusNames array from the behavior */
    public $actsAs = array('CodedItem', 'Emailer');

    /** Value for status field that indicates event has been created */
    const CREATED = 'created';

    /** Value for status field that indicates event has been uploaded */
    const UPLOADED = 'uploaded';

    /** Value for status field that indicates event has been scrubbed */
    const SCRUBBED = 'scrubbed';

    /** Value for status field that indicates event has been screened */
    const SCREENED = 'screened';

    /** Value for status field that indicates event has been assigned to
        reviewers */
    const ASSIGNED = 'assigned';

    /** Value for status field that indicates event packets have been sent to
        reviewers */
    const SENT = 'sent';

    /** Value for status field that indicates the first reviewer has reviewed
        the event (but not the second) */
    const REVIEWER1_DONE = 'reviewer1_done';

    /** Value for status field that indicates the second reviewer has reviewed
        the event (but not the first) */
    const REVIEWER2_DONE = 'reviewer2_done';

    /** Value for status field that indicates a 3rd review is needed */
    const THIRD_REVIEW_NEEDED = 'third_review_needed';

    /** Value for status field that indicates a 3rd review is needed */
    const THIRD_REVIEW_ASSIGNED = 'third_review_assigned';

    /** Value for status field that indicates all reviews are finished */
    const DONE = 'done';

    /** Value for status field that indicates event did not pass screening*/
    const REJECTED = 'rejected';

    /** Value for status field that indicates event will not have a packet */
    const NO_PACKET_AVAILABLE = 'no_packet_available';

    /** Name of a file with instructions for reviewers */
    const REVIEW_INSTRUCTIONS = ' MI reviewer instructions.doc';
	
    /** Name of a file with instructions for reviewers */
    const REVIEW_INSTRUCTIONS_PDF = ' MI reviewer instructions.pdf';

    /** Name of a file with instructions for uploaders */
    const UPLOAD_INSTRUCTIONS = 
      ' Review packet assembly instructions.doc';
	  
    /** Name of a file with instructions for uploaders (pdf version) */
    const UPLOAD_INSTRUCTIONS_PDF = 
      ' Review packet assembly instructions.pdf';

    /** Name of a file with instructions for scrubbers */
    const SCRUB_INSTRUCTIONS = 
      'CNICS MI event scrubbing protocol.doc';
	  
    /** Name of a file with instructions for scrubbers */
    const SCRUB_INSTRUCTIONS_PDF = 
      'CNICS MI event scrubbing protocol.pdf';

    /** Value for no_packet_reason field */
    const OUTSIDE_HOSPITAL = 'Outside hospital';

    /** Value for no_packet_reason field */
    const ASCERTAINMENT_DIAGNOSIS_ERROR = 'Ascertainment diagnosis error';

    /** Value for no_packet_reason field */
    const ASCERTAINMENT_PRIOR_EVENT = 
      'Ascertainment diagnosis referred to a prior event';

    /** Value for no_packet_reason field */
    const OTHER = 'Other';

    /** 
     * the allowed values for the status field.
     */
    private static $statusNames = array(
        self::CREATED, self::UPLOADED, self::SCRUBBED, self::SCREENED,
        self::ASSIGNED,
        self::SENT, self::REVIEWER1_DONE, self::REVIEWER2_DONE,
        self::THIRD_REVIEW_NEEDED, self::THIRD_REVIEW_ASSIGNED,
        self::DONE, self::REJECTED, self::NO_PACKET_AVAILABLE);

    /* annoying that I have to define this, but I can't figure out how to
       access the array (above) from the behavior otherwise 
       (even if it's public) */
    public static function getStatusNames() {
        return self::$statusNames;
    }

    public static function getNoPacketReasons() {
        return array(self::OUTSIDE_HOSPITAL => self::OUTSIDE_HOSPITAL, 
                     self::ASCERTAINMENT_DIAGNOSIS_ERROR => 
                         self::ASCERTAINMENT_DIAGNOSIS_ERROR,
                     self::ASCERTAINMENT_PRIOR_EVENT =>
                         self::ASCERTAINMENT_PRIOR_EVENT, 
                     self::OTHER => self::OTHER);
    }

    /** The status fields as instances of Status */
    private static $statuses;

    /** The possible actions that change an Event's status */
    private static $actions;

    /** isAdmin callback */
    private static $isAdmin = array('User', 'isAdmin');

    static function initialize() {
        self::$statuses = Status::statusArray(self::$statusNames);
        self::$actions = Action::actionArray('Event', array(
            array('add', 'creator', Action::CREATION, self::$isAdmin, 
                  self::$statuses[self::CREATED], true),
            array('upload', 'uploader', 
                  array(self::CREATED, self::UPLOADED), 
                  'isUploaderForSite', self::$statuses[self::UPLOADED], true),
            array('markNoPacket', 'marker', 
                  array(self::CREATED, self::UPLOADED), 
                  'isUploaderForSite', 
                  self::$statuses[self::NO_PACKET_AVAILABLE], true),
            array('scrub', 'scrubber', array(self::UPLOADED, self::SCRUBBED), 
                  self::$isAdmin, self::$statuses[self::SCRUBBED], true),
            array('screen', 'screener', array(self::SCRUBBED), self::$isAdmin, 
                  null, true),
            array('assign', 'assigner', array(self::SCREENED), 
                  self::$isAdmin, Action::DV, true),
            array('send', 'sender', array(self::ASSIGNED), 
                  self::$isAdmin, Action::DV, true),
            array('review1', 'reviewer1', 
                  array(self::SENT, self::REVIEWER2_DONE), Action::DV, 
                  null, false),
            array('review2', 'reviewer2', 
                  array(self::SENT, self::REVIEWER1_DONE), Action::DV, 
                  null, false),
            array('assign3rd', 'assigner3rd', array(self::THIRD_REVIEW_NEEDED), 
                  self::$isAdmin, Action::DV, true),
            array('review3', 'reviewer3', array(self::THIRD_REVIEW_ASSIGNED), 
                  Action::DV, Action::DV, false)));
    }

    /* See note above for getStatusNames */
    public static function getActions() {
        return self::$actions;
    }

    /**
     * Update an event's status after a review by a user
     * @param reviewNumber The number of the review (1, 2, 3)
     * @param event The event, as seen when review was submitted
     * @param user The reviewer
     */
    /* Note that there are two possible complications:  (a) two reviewers 
       could be reviewing at once; thus creating a race condition where
       one updates the status between the time the event is read and the
       review written.  (b) If the two reviews don't agree, we must set
       the special '3rd review needed' status.
     */
    function updateAfterReview($reviewNumber, $event, $reviewer) {
        $status = $event['Event']['status'];
        $id = $event['Event']['id'];
        // Ensure any calls to save or saveField will be on this id
        $this->id = $id;	
        $today = date('Y-m-d');

        // easy case:  we are the 3rd reviewer
        if ($reviewNumber == 3) {
            $event['Event']['status'] = self::DONE;
            $event['Event']['review3_date'] = $today;
            $this->save($event, array('fieldList' => 
                                      array('status', 'review3_date')));
            $this->EventDerivedData->addAfterThree($event);
            return;
        }

        // Set (possible) new status and which date field to update
        if ($reviewNumber == 1) {
            $newStatus = self::REVIEWER1_DONE;
            $dateField = 'review1_date';
        } else {
            $newStatus = self::REVIEWER2_DONE;
            $dateField = 'review2_date';
        }

        /* If no reviews done yet (that we know of), just try to update */
        if ($status == self::SENT) {
            $this->updateAll(array('Event.status' => "'$newStatus'",
                                   "Event.$dateField" => "'$today'"),
                             array('Event.id' => $id, 
                                   'Event.status' => self::SENT));

            $event = $this->findById($id);
            $status = $event['Event']['status'];

            if ($status == $newStatus || $status == self::DONE ||
                $status == self::THIRD_REVIEW_NEEDED) 
            {   // update succeeded, we are done
                return;
            }
        }

        /* If we're here, then we are the second reviewer, so we must
           check for agreement */
        $reviews = $this->Review->findAllByEventId($id);
        $review1 = $reviews[0]['Review'];
        $review2 = $reviews[1]['Review'];
        $saveEdd = false;

        if ($review1['mci'] == $review2['mci'] && 
            $review1['ci'] == $review2['ci'] && 
            $review1['type'] == $review2['type'] &&
            $review1['false_positive_flag'] == 
                $review2['false_positive_flag'] &&
            $review1['false_positive_reason'] == 
                $review2['false_positive_reason']) 
        {  // agreement
            $event['Event']['status'] = self::DONE;
            $saveEdd = true;   /* can't save here, as putting one save inside
                                  another confuses Cake */
        } else {
	    $event['Event']['status'] = self::THIRD_REVIEW_NEEDED;
        }

        $event['Event'][$dateField] = $today;
        $this->save($event, array('fieldList' => array('status', $dateField)));

        if ($saveEdd) {
            $this->EventDerivedData->addAfterTwo($review1, $review2);
        }
    }
    

    /**
     * Find all the events that are awaiting review by a particular reviewer
     * @param reviewer The reviewer
     * @return All events that are waiting for the reviewer
     */
    function awaitingReview($reviewer) {
        $reviewerId = $reviewer['User']['id'];

        return $this->find('all', array('order' => 'Event.id',
            'conditions' => array('or' => array(
                array('Event.reviewer1_id' => $reviewerId,
                      'Event.status' => array(self::SENT, 
                                              self::REVIEWER2_DONE)),
                array('Event.reviewer2_id' => $reviewerId,
                      'Event.status' => array(self::SENT,
                                              self::REVIEWER1_DONE)),
                array('Event.reviewer3_id' => $reviewerId,
                      'Event.status' => self::THIRD_REVIEW_ASSIGNED)
             ))));
    }

    /**
     * Find all the events that are awaiting upload by a particular uploader
     * @param uploader The uploader
     * @return All events that are waiting for the uploader
     */
    function awaitingUpload($uploader) {
        $uploaderSite = $uploader['User']['site'];

        return $this->find('all', array('order' => 'Event.id',
            'conditions' => array('Patient.site' => $uploaderSite,
                                  'Event.status' => self::CREATED)));
    }

    /**
     * Find all the events that can be re-uploaded by a particular uploader
     * @param uploader The uploader
     * @return All events that can be re-uploaded
     */
    function possibleReupload($uploader) {
        $uploaderSite = $uploader['User']['site'];

        return $this->find('all', array('order' => 'Event.id',
            'conditions' => array('Patient.site' => $uploaderSite,
                                  'Event.status' => self::UPLOADED)));
    }

    /**
     * Get a list of relevant events in a particular state/status
     * @param status Current status of the event (but not inclusive, as follows:
     *   SENT implies SENT, REVIEWER1_DONE or REVIEWER2_DONE)
     * @return a list of events with the given status
     */
    function getAll($status) {
        $statusValue = $status;

        if ($status == self::SENT) {
            $statusValue = array($statusValue, self::REVIEWER1_DONE, 
                                 self::REVIEWER2_DONE);
        }

        return $this->find('all', array('order' => 'Event.id', 
            'recursive' => 0,
            'conditions' => array('Event.status' => $statusValue)));
    }

    /** Directory where uploads are kept */
    const UPLOAD_DIR = 'chartUploads';

    /** MIME type of an PDF file */
    const PDF_MIME = 'application/pdf';

    /** MIME types of a Zip file */
    const ZIP_MIME_DEFAULT = 'application/zip';
    private static $zip_mimes =
        array(self::ZIP_MIME_DEFAULT, 'application/x-zip-compressed',
              'multipart/x-zip', 'application/x-compressed', 
              'application/octetstream');

    /** MIME type of an Gzip file */
    const GZIP_MIME1 = 'application/x-gzip';

    /** MIME type of an Gzip file */
    const GZIP_MIME2 = 'application/gzip';

    /**
     * Get the directory where files reside, include the final directory 
     * separator
     */
    function pathPrefix() {
        return APP . self::UPLOAD_DIR . DS;
    }

    /** Prefix for raw files */
    const RAW_PREFIX = 'orig';

    /** Prefix for scrubbed files */
    const SCRUBBED_PREFIX = 'clean';

    /**
     * Get a base chart file name
     * @param prefix Prefix for the file ('orig', or 'clean')
     * @param eventId event id
     * @param fileNumber Random number for the event
     * @return The file name
     */
    private function fileName($prefix, $eventId, $fileNumber) {
        return "${prefix}_{$eventId}_$fileNumber";
    }

    /**
     * Get a base raw chart file name
     * @param eventId event id
     * @param fileNumber Random number for the event
     * @return The file name
     */
    function rawFileName($eventId, $fileNumber) {
        return $this->fileName(self::RAW_PREFIX, $eventId, $fileNumber);
    }

    /**
     * Get a base scrubbed chart file name
     * @param eventId event id
     * @param fileNumber Random number for the event
     * @return The file name
     */
    function scrubbedFileName($eventId, $fileNumber) {
        return $this->fileName(self::SCRUBBED_PREFIX, $eventId, 
                               $fileNumber);
    }

    /**
     * find the suffix for the file to download (pdf, zip, or gz)
     * @param fileName Start of the name of the file to download
     * @return The proper suffix 
     */
    function findSuffix($fileName) {
        $pathPrefix = $this->pathPrefix();
        $suffixes = array('pdf', 'zip', 'gz');

        foreach ($suffixes as $suffix) {
            if (file_exists("$pathPrefix$fileName.$suffix")) {
                return $suffix;
            }
        }

        return null;
    }

    /**
     * Get the proper file suffix for a type of file
     * @param type The File Type
     * @return The suffix, or null for invalid types
     */
    function getSuffix($type) {
        if ($type == self::PDF_MIME) {
            return '.pdf';
        } else if (in_array($type, self::$zip_mimes)) {
            return '.zip';
        } else if ($type == self::GZIP_MIME1 || $type == self::GZIP_MIME2) {
            return '.gz';
        } else {
            return null;
        }
    }

    /**
     * Is a user an uploader for the same site as an event?
     * @param user The user
     * @param event The event
     * @return true if the user is an uploader for the same site as the event
     */
    static function isUploaderForSite($user, $event) {
        return User::isUploader($user) && !empty($event) && 
               !empty($event['Patient']) &&
               User::sameSite($user, $event['Patient']);
    }

    /**
     * Is a user a particular reviewer for an event?
     * @param user The user
     * @param event The event
     * @param reviewerNumber The reviewer number: 1, 2, or 3
     * @return true if the user is the corresponding reviewer for the event
     */
    static function is_reviewer($user, $event, $reviewerNumber) {
        return User::isReviewer($user) && !empty($event) && 
            !empty($event['Event']) &&
            CodedItemBehavior::idFieldMatches(
                $event['Event'], "reviewer{$reviewerNumber}_id", $user);
    }

    /**
     * Is a user the first reviewer for an event?
     * @param user The user
     * @param event The event
     * @return true if the user is the first reviewer for the event
     */
    static function is_reviewer1($user, $event) {
        return self::is_reviewer($user, $event, 1);
    }

    /**
     * Is a user the second reviewer for an event?
     * @param user The user
     * @param event The event
     * @return true if the user is the second reviewer for the event
     */
    static function is_reviewer2($user, $event) {
        return self::is_reviewer($user, $event, 2);
    }

    /**
     * Is a user the third reviewer for an event?
     * @param user The user
     * @param event The event
     * @return true if the user is the third reviewer for the event
     */
    static function is_reviewer3($user, $event) {
        return self::is_reviewer($user, $event, 3);
    }

    /**
     * Add a single new event
     * @param patientId Id of the patient
     * @param event event Data
     * @param criteria criteria data
     */
    function addEvent($patientId, $event, $criteria) {
        $newEvent['Event']['patient_id'] = $patientId;
        $newEvent['Event']['event_date'] = $event['event_date'];

        $i = 0;

        foreach ($criteria as $crit) {
            $newEvent['Criteria'][$i]['name'] = $crit['name'];
            $newEvent['Criteria'][$i]['value'] = $crit['value'];
            $i++;
        }

        $this->create();
        $this->saveAll($newEvent);
//$this->EventDerivedData->catchup();
    }

    /**
     * Edit an event
     * @param patientId Id of the patient
     * @param event event Data
     */
    function editEvent($patientId, $event) {
        $newEvent['Event']['patient_id'] = $patientId;
        $newEvent['Event']['event_date'] = $event['event_date'];
        $this->id = $event['id'];
        $this->save($newEvent, array('fieldList' => 
                                     array('patient_id', 'event_date')));
    }

    /**
     * verify the file upload worked
     * @param file Array representing the uploaded file
     * @return true if the upload worked
     */
    function verifyUpload($file) {
        if ((isset($file['error']) && $file['error'] == 0) &&
            (!empty($file['tmp_name']) && $file['tmp_name'] != 'none'))
        {
            if (!defined('CAKEPHP_UNIT_TEST_EXECUTION')) {
                return is_uploaded_file($file['tmp_name']);
            } else {
                /* for unit tests, uploaded file should be said to be in one
                   of these places */
                return (strpos($file['tmp_name'], '/tmp') === 0) ||
                       (strpos($file['tmp_name'], '/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/controllers') === 0);
            }
        } else {
            return false;
        }
    } 

    /**
     * If an upload worked, save the file
     * @param file Array representing the uploaded file
     * @param fileName name of the file to save
     * @return status array:  (success => true/false; message => ...)
     */
    function saveFile($file, $fileName) {
        if ($this->verifyUpload($file)) {
	    $type = $file['type'];
            $suffix = $this->getSuffix($type);

            if (empty($suffix)) {
                return array('success' => false, 
                             'message' => "Invalid file type {$file['type']}");
            }

            $fileName = $this->pathPrefix() . "$fileName$suffix";

            if (!defined('CAKEPHP_UNIT_TEST_EXECUTION')) {
                if (!move_uploaded_file($file['tmp_name'], $fileName)) {
                    return array('success' => false, 
                                 'message' => 'Error saving file');
                }
            } else { // make it possible to test
                if (!is_writable(dirname($fileName))) {
                    return array('success' => false, 
                                 'message' => "Cannot write file");
                }
            }

            return array('success' => true);
        } else {
            $this->log('Upload failed ' .  print_r($file, true));
            return array('success' => false, 
                         'message' => 'Upload failed.');
        }
    }

    /**
     * Get the flash message to display after a successful file upload
     * @param baseMessage Basic message (e.g., 'File uploaded.')
     * @param size Size of the file, in bytes
     * @return The message
     */
    function getUploadMessage($baseMessage, $size) {
        return "$baseMessage.  Size = $size bytes.";
    }

    /** 
     * upload charts for an event, either the initial upload or the 
     *   scrubbed version
     * @param event The event (from the database)
     * @param file uploaded file
     * @param scrub If true, we are uploading a scrubbed file
     * @return status array:  (success => true/false; message => ...)
     */
    function uploadCharts($event, $file, $scrub) {
        $size = $file['size'];

        if (!$scrub) {
            $fileNumber = rand(1, 1000000000);
            $originalName = $file['name'];
            $fileName = $this->rawFileName($event['Event']['id'], 
                                           $fileNumber);
        } else {
            $fileNumber = $event['Event']['file_number'];
            $fileName = $this->scrubbedFileName($event['Event']['id'], 
                                                $fileNumber);
        }

        $status = $this->saveFile($file, $fileName);

        if ($status['success']) {
            if (!$scrub) {
                $event['Event']['file_number'] = $fileNumber;
                $event['Event']['original_name'] = $originalName;
                $this->id = $event['Event']['id'];
    
                $this->save($event, true, 
                            array('file_number', 'original_name'));
            } 

            $filetype = $scrub ? 'Scrubbed' : 'Charts';

            $status['message'] = 
                $this->getUploadMessage("$filetype file uploaded", $size);
        }

        return $status;
    } 

    const MISSING_PATIENT_DATA = "Missing patient identifiers";
    const MISSING_EVENT_DATE = "Missing event date";
    const EVENT_DATE_TOO_EARLY = "Event date too early";
    const CRITERIA_PROBLEM = "Bad format for criteria";

    /** List of likely criteria names */
    private $criteriaNames = array('mi_dx', 'mi dx', 'diagnosis', 'dx', 'ckmb',
                                   'troponin', 'troponin t', 'troponin i', 
 				   'trop_i', 'trop_t', 'troponin i (tni)', 
                                   'troponin t (tnt)', 'ckmb_q',
                                   'creatine kinase mb quotient', 'ckmb_m',
                                   'creatine kinase mb mass');

    /**
     * Check that data to add an event is okay
     * @param data The data
     * @return an array:  (true, patientId) if all is well,
     *                    (false, message) if there was a problem
     */
    function checkAddData($data) {
        $sitePatientId = $data['Patient']['site_patient_id'];
        $site = $data['Patient']['site'];

        if (empty($sitePatientId) || empty($site)) {
            return array(false, self::MISSING_PATIENT_DATA);
        }

        if (empty($data['Event']) || empty($data['Event']['event_date'])) {
            return array(false, self::MISSING_EVENT_DATE);
        }

        if ($data['Event']['event_date'] < '1970-01-01') {
            return array(false, self::EVENT_DATE_TOO_EARLY);
        }

        if (!empty($data['Criteria'])) {
            foreach ($data['Criteria'] as $criteria) {
                if (in_array(strtolower($criteria['value']), 
                             $this->criteriaNames)) 
                {
                    return array(false, self::CRITERIA_PROBLEM);
                }
            }
        }

        $patient = $this->Patient->find('first', array('conditions' =>
            array('Patient.site_patient_id' => $sitePatientId,
                  'Patient.site' => $site)));

        if (empty($patient)) {
            return array(false, 
                "No patient found with id $sitePatientId at site $site");
        }

        return array(true, $patient['Patient']['id']);
    }

    const MCI_BLANK = 'Myocardial infarction field cannot be blank.';
    const NO_CRITERIA = 'No criteria identified.';
    const NO_CE_CRITERIA = 'No cardiac enzyme criteria selected.';
    const CI_BLANK = 'Cardiac intervention field cannot be blank.';
    const TYPE_BLANK = 'Primary/Secondary field cannot be blank.';
    const SC_BLANK = 'Secondary cause cannot be blank.';
    const OC_BLANK = 'Other cause cannot be blank.';
    const FALSE_POSITIVE_REASON_BLANK = 
        'False positive reason cannot be blank.';
    const FPOC_BLANK = 'False positive Other cause cannot be blank.';
    const CURRENT_TOBACCO_USE_BLANK = 'Current tobacco use cannot be blank.';
    const PAST_TOBACCO_USE_BLANK = 'Past tobacco use cannot be blank.';
    const COCAINE_USE_BLANK = 'Cocaine use cannot be blank.';
    const FAMILY_HISTORY_BLANK = 'Family history cannot be blank.';
    const CI_TYPE_BLANK = 'CI type cannot be blank.';
    const ECG_TYPE_BLANK = 'ECG based type cannot be blank.';
    const CARDIAC_CATH_BLANK = 'Cardiac cath cannot be blank.';
    
    /**
     * Check that review data is okay; sanitize any dubious strings
     * @param data The data
     * @return an array:  (true) if all is well,
     *                    (false, message) if there was a problem
     */
    function checkReviewData(&$data) {
        $mci = $data['Review']['mci'];
        $abnormalCeValues = $data['Review']['abnormal_ce_values_flag'];
        $chestPain = $data['Review']['chest_pain_flag'];
        $ceCriteria = $data['Review']['ce_criteria'];
        $ecgChanges = $data['Review']['ecg_changes_flag'];
        $lvmByImaging = $data['Review']['lvm_by_imaging_flag'];
        $ci = $data['Review']['ci'];
        $type = $data['Review']['type'];
        $secondaryCause = $data['Review']['secondary_cause'];
        $falsePositive = $data['Review']['false_positive_flag'];
        $falsePositiveReason = $data['Review']['false_positive_reason'];
        $falsePositiveOtherCause = 
            $data['Review']['false_positive_other_cause'];
        $currentTobaccoUse = $data['Review']['current_tobacco_use_flag'];
        $pastTobaccoUse = $data['Review']['past_tobacco_use_flag'];
        $cocaineUse = $data['Review']['cocaine_use_flag'];
        $familyHistory = $data['Review']['family_history_flag'];
        $ciType = $data['Review']['ci_type'];
        $cardiacCath = $data['Review']['cardiac_cath'];
        $ecgType = $data['Review']['ecg_type'];

        /* ensure unchosen radio buttons aren't set to 'No' if 
           there's an error and they have to redo the form */
        if ($ci === '') {
            unset($data['Review']['ci']);
        }

        if ($currentTobaccoUse === '') {
            unset($data['Review']['current_tobacco_use_flag']);
        }

        if ($pastTobaccoUse === '') {
            unset($data['Review']['past_tobacco_use_flag']);
        }

        if ($cocaineUse === '') {
            unset($data['Review']['cocaine_use_flag']);
        }

        if ($familyHistory === '') {
            unset($data['Review']['family_history_flag']);
        }

        if ($cardiacCath === '') {
            unset($data['Review']['cardiac_cath']);
        }

        if (empty($mci)) {
            return array(false, self::MCI_BLANK);
        } else if ($mci == Review::NOT || $mci == Review::RCA) {
            unset($data['Review']['abnormal_ce_values_flag']);
            unset($data['Review']['ce_criteria']);
            unset($data['Review']['chest_pain_flag']);
            unset($data['Review']['ecg_changes_flag']);
            unset($data['Review']['lvm_by_imaging_flag']);
            unset($data['Review']['type']);
            unset($data['Review']['secondary_cause']);
            unset($data['Review']['other_cause']);
            unset($data['Review']['false_positive_flag']);
            unset($data['Review']['false_positive_reason']);
            unset($data['Review']['false_positive_other_cause']);
            unset($data['Review']['current_tobacco_use_flag']);
            unset($data['Review']['past_tobacco_use_flag']);
            unset($data['Review']['cocaine_use_flag']);
            unset($data['Review']['family_history_flag']);
            unset($data['Review']['ecg_type']);

            if ($ci === '') {
                return array(false, self::CI_BLANK);
            } else if (empty($ciType) && $ci) {
                return array(false, self::CI_TYPE_BLANK);
            } 

            if (!$ci) {
                unset($data['Review']['ci_type']);
            }
        } else {
            unset($data['Review']['ci']);
            unset($data['Review']['ci_type']);
                
            if (!$abnormalCeValues) {
                unset($data['Review']['ce_criteria']);
            } else if (empty($ceCriteria)) {
                return array(false, self::NO_CE_CRITERIA);
            }

            if (!$abnormalCeValues && !$chestPain && !$ecgChanges && 
                !$lvmByImaging) 
            {
                return array(false, self::NO_CRITERIA);
            } else if (empty($type)) {
                return array(false, self::TYPE_BLANK);
            } else if ($type != Review::SECONDARY) {
                unset($data['Review']['secondary_cause']);
                unset($data['Review']['other_cause']);
            } else if (empty($secondaryCause)) {
                return array(false, self::SC_BLANK);
            } else if ($secondaryCause != Review::OTHER) {
                unset($data['Review']['other_cause']);
            } else {
                $data['Review']['other_cause'] = 
                    strip_tags($data['Review']['other_cause']);
    
                if (empty($data['Review']['other_cause'])) {
                    return array(false, self::OC_BLANK);
                }
            }

            if (empty($ecgType)) {
                return array(false, self::ECG_TYPE_BLANK);
            }

            if (!$falsePositive) {
                unset($data['Review']['false_positive_reason']);
                unset($data['Review']['false_positive_other_cause']);
            } else if (empty($falsePositiveReason)) {
                return array(false, self::FALSE_POSITIVE_REASON_BLANK);
            } else if ($falsePositiveReason != Review::OTHER) {
                unset($data['Review']['false_positive_other_cause']);
            } else {
                $data['Review']['false_positive_other_cause'] = 
                    strip_tags($data['Review']['false_positive_other_cause']);
    
                if (empty($data['Review']['false_positive_other_cause'])) {
                    return array(false, self::FPOC_BLANK);
                }
            }

            if ($currentTobaccoUse === '') {
                return array(false, self::CURRENT_TOBACCO_USE_BLANK);
            } else if ($currentTobaccoUse === '0' && $pastTobaccoUse === '') {
                return array(false, self::PAST_TOBACCO_USE_BLANK);
            } else if ($cocaineUse === '') {
                return array(false, self::COCAINE_USE_BLANK);
            } else if ($familyHistory === '') {
                return array(false, self::FAMILY_HISTORY_BLANK);
            }

            if ($currentTobaccoUse === '1') {
                unset($data['Review']['past_tobacco_use_flag']);
            }
        }

        if ($cardiacCath === '') {
            return array(false, self::CARDIAC_CATH_BLANK);
        }
                
        return array(true);
    }

    const NO_PACKET_SUCCESS = 'Event marked as having no packet';
    const TWO_ATTEMPTS_BLANK = '2 attempts field cannot be blank.';
    const PRIOR_EVENT_DATE_KNOWN_BLANK = 
      'Prior event date known field cannot be blank.';
    const PRIOR_EVENT_ONSITE_BLANK = 
      'Prior event onsite field cannot be blank.';

    /**
     * Mark an event as having no packet
     * @param event The event
     * @param data The data
     * @return status array:  (success => true/false; message => ...)
     */
    function markNoPacketAvailable($event, &$data, $authUserId) {
        $noPacketReason = $data['Event']['no_packet_reason'];
        $twoAttemptsFlag = $data['Event']['two_attempts_flag'];
        $priorEventDateKnown = $data['Event']['prior_event_date_known'];
        $priorEventYear = intval($data['Event']['priorDateYear']);
        $priorEventMonth = intval($data['Event']['priorDateMonth']['month']);
        $priorEventOnsiteFlag = $data['Event']['prior_event_onsite_flag'];
        $otherCause = $data['Event']['other_cause'];

        unset($data['Event']['two_attempts_flag']);
        unset($data['Event']['prior_event_date_known']);
        unset($data['Event']['prior_event_date']);
        unset($data['Event']['prior_event_onsite_flag']);
        unset($data['Event']['other_cause']);

        if ($noPacketReason == Event::OUTSIDE_HOSPITAL) {
            if ($twoAttemptsFlag === '') {
                return array('success' => false, 
                             'message' => self::TWO_ATTEMPTS_BLANK);
            }

            $data['Event']['two_attempts_flag'] = $twoAttemptsFlag;
        } else if ($noPacketReason == Event::OTHER) {
            $data['Event']['other_cause'] = strip_tags($otherCause);

            if (empty($data['Event']['other_cause'])) {
                return array('success' => false, 'message' => self::OC_BLANK);
            }
        } else if ($noPacketReason == Event::ASCERTAINMENT_PRIOR_EVENT) {
            if ($priorEventDateKnown === '') {
                return array('success' => false, 
                             'message' => self::PRIOR_EVENT_DATE_KNOWN_BLANK);
            }

            if ($priorEventDateKnown) {
                if (empty($priorEventYear) || $priorEventYear > 9999 || 
                    $priorEventYear < 1000) 
                {
                    $priorEventYear = '0000';
                }

                if (empty($priorEventMonth) || $priorEventMonth > 12 || 
                    $priorEventMonth < 1) 
                {
                    $priorEventMonth = '00';
                } else if ($priorEventMonth < 10) {
                    $priorEventMonth = '0' . $priorEventMonth;
                }

                $data['Event']['prior_event_date'] = $priorEventMonth . '-' . 
                    $priorEventYear;
            }

            if ($priorEventOnsiteFlag === '') {
                return array('success' => false, 
                             'message' => self::PRIOR_EVENT_ONSITE_BLANK);
            }

            $data['Event']['prior_event_onsite_flag'] = $priorEventOnsiteFlag;
        }
                
        $fieldList = array('no_packet_reason', 'two_attempts_flag',
                           'prior_event_date', 'prior_event_onsite_flag',
                           'other_cause');
        $this->id = $event['Event']['id'];
        $this->save($data, true, $fieldList);
        return array('success' => true, 
                     'message' => self::NO_PACKET_SUCCESS);
    }
    

    /**
     * Can a particular user screen a particular event?
     * @param event The event
     * @param user the user
     */
    function toBeScreened($event, $user) {
        return $this->canBePerformed('screen', $event) &&
               $this->canPerformAction('screen', $user, $event);
    }

    /**
     * Can a particular user scrub a particular event?
     * @param event The event
     * @param user the user
     */
    function canBeScrubbed($event, $user) {
        return $this->canBePerformed('scrub', $event) &&
               $this->canPerformAction('scrub', $user, $event);
    }

    /**
     * Can a particular user review a particular event?
     * @param event The event
     * @param user the user
     */
    function toBeReviewed($event, $user) {
        return ($this->canBePerformed('review1', $event) &&
                $this->canPerformAction('review1', $user, $event)) ||
               ($this->canBePerformed('review2', $event) &&
                $this->canPerformAction('review2', $user, $event)) ||
               ($this->canBePerformed('review3', $event) &&
                $this->canPerformAction('review3', $user, $event));
    }

    /**
     * Does an event have a chart?
     * @param event The event
     */
    function hasChart($event) {
        return $event['Event']['status'] != self::CREATED;
    }

    /** Value indicating event/chart accepted */
    const ACCEPT = 'Accept';

    /** Value indicating event/chart rejected */
    const REJECT = 'Reject';

    /** Value indicating event/chart needs rescrubbing */
    const RESCRUB = 'Needs Rescrubbing';

    /** Message to return for bad screenAccept values */
    const BAD_SCREEN_ACCEPT = 
        'You must either Accept, Reject, or return for Rescrubbing';

    /** 
     * screen charts for an event
     * @param event The event (from the database)
     * @param dataEvent $this->data['Event']
     * @return status array:  (success => true/false; message => ...)
     */
    function screen($event, $dataEvent, $authUserId) {
        if (empty($dataEvent['screenAccept']) ||
            !in_array($dataEvent['screenAccept'], 
                      array(self::ACCEPT, self::RESCRUB, self::REJECT))) 
        {
            return array('success' => false, 
                         'message' => self::BAD_SCREEN_ACCEPT);
        } else {
            $screenAccept = $dataEvent['screenAccept'];
            $event['Event']['screen_date'] = date('Y-m-d');
            $event['Event']['screener_id'] = $authUserId;
            $fieldList = array('screen_date', 'screener_id', 'status');

            if (!empty($dataEvent['message'])) {
                $message = strip_tags($dataEvent['message']);
            } else {
                $message = null;
            }

            if ($screenAccept == self::ACCEPT) {
                $event['Event']['status'] = self::SCREENED;
            } else if ($screenAccept == self::REJECT) {
                $fieldList[] = 'reject_message';
                $event['Event']['status'] = self::REJECTED;
                $event['Event']['reject_message'] = $message;
            } else {	// needs rescrubbing
                $fieldList[] = 'rescrub_message';
                $event['Event']['status'] = self::UPLOADED;
                $event['Event']['rescrub_message'] = $message;
            }

            $this->id = $event['Event']['id'];
            $this->save($event, true, $fieldList);
            $acceptMessage = 
                $screenAccept == self::RESCRUB ? $screenAccept :
                                                 $screenAccept . 'ed';
            $msg = PROJECT_NAME == 'MI' ? 
                     'Screened event MI ' . ($event['Event']['id'] + 1000) .
                     " ({$acceptMessage})" :
                     'Screened event ' . $event['Event']['id'] .
                     " ({$acceptMessage})";

            return array('success' => true, 'message' => $msg);
        }
    } 

    /**
     * Can a particular event be assigned?
     * @param event The event
     * @param thirdReview Are we assigning a 3rd reviewer?
     */
    function toBeAssigned($event, $thirdReview) {
        return $this->canBePerformed($thirdReview ? 'assign3rd' : 'assign', 
                                     $event);
    }

    /**
     * Can a particular user be assigned to review a particular event?
     * @param event The event
     * @param userid Id of the reviewer
     * @param thirdReview Is this the 3rd review?
     */
    function canBeAssigned($event, $userid, $thirdReview) {
        return $event['Event']['reviewer1_id'] != $userid &&
               (!$thirdReview || 
                $event['Event']['reviewer2_id'] != $userid);
    }

    /**
     * Can a particular user assign a particular event?
     * @param event The event
     * @param user the user
     */
    // note that a user can perform 'assign' iff they can perform 'assign3rd' 
    function canAssign($event, $user) {
        return $this->canPerformAction('assign', $user, $event);
    }

    const NO_REVIEWER = 'You must choose a reviewer';
    const BAD_REVIEWER = ' is not a reviewer';

    /**
     * Assign a set of events
     * @param data Containing the events and the id of the reviewer to assign
     *    them to
     * @param reviewers An array of valid reviewers
     * @param authUser User doing the assignment
     * @param thirdReview Are we assigning the 3rd review?
     */
    public function assignAll($data, $reviewers, $authUser, $thirdReview) {
        $returnArray = array();
        $returnArray['error'] = null;
        $returnArray['assigned'] = 0;
        $returnArray['notFound'] = 0;
        $returnArray['cannotAssign'] = 0;
        $returnArray['cannotAssignReviewer'] = 0;
        $returnArray['notFoundList'] = '';
        $returnArray['cannotAssignList'] = '';
        $returnArray['cannotAssignReviewerList'] = '';

        if ($thirdReview) {
            $returnArray['emailFailed'] = 0;
            $returnArray['emailFailedList'] = '';
        }

        if (empty($data['Assign']['reviewer_id'])) {
            $returnArray['error'] = self::NO_REVIEWER;
            return $returnArray;
        }

        $reviewerId = $data['Assign']['reviewer_id'];

        if (empty($reviewers[$reviewerId])) {
            $returnArray['error'] = "User $reviewerId" . self::BAD_REVIEWER;
            return $returnArray;
        }
 
        foreach ($data['Event'] as $key => $value) {
            if (substr($key, 0, 6) == 'assign' && $value) {
                $id = substr($key, 6);
                $event = $this->findById($id);

                if (empty($event)) {
                    $returnArray['notFound']++;
                    $returnArray['notFoundList'] .= " $id";
                } else if (!$this->toBeAssigned($event, $thirdReview)) {
                    $returnArray['cannotAssign']++;
                    $returnArray['cannotAssignList'] .= " $id";
                } else if (!$this->canBeAssigned($event, $reviewerId, 
                                                 $thirdReview)) 
                {
                    $returnArray['cannotAssignReviewer']++;
                    $returnArray['cannotAssignReviewerList'] .= " $id";
                } else if (!$this->canAssign($event, $authUser)) {
                    $returnArray['error'] = 'You cannot assign reviewers'; 
                    return $returnArray;
                } else {
                    if ($thirdReview) {
                        $this->User =& ClassRegistry::init('User');
                        $reviewer = $this->User->findById($reviewerId);
                        $emailResult = 
                            $this->emailPacket($reviewer['User'], 3, $event);
                    }

                    if (!$thirdReview || empty($emailResult)) {
                        $returnArray['assigned']++;
                        $this->assign($event, $reviewerId, 
                                      $authUser['User']['id'], $thirdReview);
                    } else {
                        $this->log("assignAll email failure: " . 
                                   $reviewer['User']['username']);
                        $returnArray['emailFailed']++;
                        $returnArray['emailFailedList'] .= " $id";
                    }
                }
            }
        }
 
        return $returnArray;
    }

    /**
     * Assign an event to a reviewer
     * @param event The event
     * @param reviewerId Id of the reviewer
     * @param authUserId Id of the person assigning
     * @param thirdReview Are we assigning the third reviewer?
     */
    function assign($event, $reviewerId, $authUserId, $thirdReview) {
        if ($thirdReview) {
            $reviewerField = 'reviewer3_id';
            $event['Event']['assign3rd_date'] = date('Y-m-d');
            $event['Event']['assigner3rd_id'] = $authUserId;
            $event['Event']['status'] = self::THIRD_REVIEW_ASSIGNED;
            $fieldList = array('assign3rd_date', 'assigner3rd_id', 'status', 
                               'reviewer3_id');
        } else if (empty($event['Event']['reviewer1_id'])) {
            $reviewerField = 'reviewer1_id';
            $fieldList = array('reviewer1_id');
        } else {
            $reviewerField = 'reviewer2_id';
            $event['Event']['assign_date'] = date('Y-m-d');
            $event['Event']['assigner_id'] = $authUserId;
            $event['Event']['status'] = self::ASSIGNED;
            $fieldList = array('assign_date', 'assigner_id', 'status', 
                               'reviewer2_id');
        }

        $event['Event'][$reviewerField] = $reviewerId;
        $this->id = $event['Event']['id'];
        $this->save($event, true, $fieldList);
    }

    /**
     * Can a particular event be sent?
     * @param event The event
     */
    function toBeSent($event) {
        return $this->canBePerformed('send', $event);
    }

    /**
     * Can a particular user send a particular event?
     * @param event The event
     * @param user the user
     */
    function canSend($event, $user) {
        return $this->canPerformAction('send', $user, $event);
    }

    /**
     * Send a set of event packets
     * @param data Containing the events to send packets for
     * @param authUser User doing the sending
     */
    public function sendAll($data, $authUser) {
        $returnArray = array();
        $returnArray['error'] = null;
        $returnArray['sent'] = 0;
        $returnArray['notFound'] = 0;
        $returnArray['badEmail'] = 0;
        $returnArray['cannotSend'] = 0;
        $returnArray['notFoundList'] = '';
        $returnArray['cannotSendList'] = '';
        $returnArray['badEmailList'] = '';

        foreach ($data['Event'] as $key => $value) {
            if (substr($key, 0, 4) == 'send' && $value == true) {
                $id = substr($key, 4);
                $event = $this->findById($id);

                if (empty($event)) {
                    $returnArray['notFound']++;
                    $returnArray['notFoundList'] .= " $id";
                } else if (!$this->toBeSent($event)) {
                    $returnArray['cannotSend']++;
                    $returnArray['cannotSendList'] .= " $id";
                } else if (!$this->canSend($event, $authUser)) {
                    $returnArray['error'] = 'You cannot send packets'; 
                    return $returnArray;
                } else {
                    $result = $this->send($event, $authUser['User']['id']);

                    if ($result != null) {
                        $this->log("sendAll failure: " . $result);
                        $returnArray['badEmail']++;
                        $returnArray['badEmailList'] .= " $id";
                    } else {
                        $returnArray['sent']++;
                    }
                }
            }
        }
 
        return $returnArray;
    }

    /**
     * Send an event to its reviewers
     * @param event The event
     * @param authUserId Id of the person sending
     */
    function send($event, $authUserId) {
        $result = $this->emailPacket($event['Reviewer1'], 1, $event);
        $result2 = $this->emailPacket($event['Reviewer2'], 2, $event);

        $this->id = $event['Event']['id'];
        $event['Event']['send_date'] = date('Y-m-d');
        $event['Event']['sender_id'] = $authUserId;
        $event['Event']['status'] = self::SENT;
        $this->save($event, true, 
                    array('send_date', 'sender_id', 'status'));

        if (empty($result)) {
            if (empty($result2)) {
                return null;
            } else {
                return "Email to {$event['Reviewer2']['username']} failed.";
            }
        } else {
            if (empty($result2)) {
                return "Email to {$event['Reviewer1']['username']} failed.";
            } else {
                return "Email to {$event['Reviewer1']['username']} and {$event['Reviewer2']['username']} failed.";
            }
        }
    }

    /**
     * Return the URL to download the packet for an event
     * @param event The event
     * @return A URL to download the packet
     */
    function downloadUrl($event) {
        return Router::url("/events/download/{$event['Event']['id']}", true);
    }

    /**
     * Return the URL to review an event
     * @param event The event
     * @param reviewer Which reviewer (1, 2, or 3)
     * @return A URL to review the event
     */
    function reviewUrl($event, $reviewer) {
        return Router::url("/events/review{$reviewer}/{$event['Event']['id']}", 
                           true);
    }

    /**
     * Return the URL to the index page
     */
    function indexUrl() {
        return Router::url('/', true);
    }

    /**
     * Get info common to downloading files
     * @param fileName base name of the file on the server
     * @param suffix Suffix of the file
     * @param downloadPrefix Prefix of the filename to be presented to the user
     * @param id Id of the event the file corresponds to
     * @return an array: 'sourcefile' => {full server file name},
     *                   'destfile' => {file name to present to the user},
     *                   'contentType' => MIME type of the file
     */
    function getDownloadInfo($fileName, $suffix, $downloadPrefix, $id) {
        $contentType = array('pdf' => self::PDF_MIME,
                             'gz' => self::GZIP_MIME1,
                             'zip' => self::ZIP_MIME_DEFAULT);

        $server = strpos(Router::url('/', true), 'cnics') != true ? 
                  'na-accord' : 'cnics';

        return array('sourcefile' => $this->pathPrefix() . "$fileName.$suffix",
                     'destfile' => "${downloadPrefix}_{$server}_" . 
                                   PROJECT_NAME . '_' . 
                                   ($id + 1000) . ".$suffix",
                     'contentType' => $contentType[$suffix]);
    }
}

// as close as we get to a static block
Event::initialize();
?>
