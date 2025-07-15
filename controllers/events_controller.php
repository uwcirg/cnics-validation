<?php
require_once('coded_items_controller.php');

/**
 * @version 1.0
 */
class EventsController extends CodedItemsController
{
    var $name = 'Events';
    var $helpers = array('Html', 'Form', 'Javascript', 'Csv');

    public function __construct() {
        parent::__construct('Event', 'event');
        $this->uses = array_merge($this->uses, array('Event', 'Review', 
                                                     'Criteria', 'Patient'));
    }

    /** 
     * An array of actions that should be considered as another coded item
     * action (by the coded items controller and behavior)
     */
    private static $actionAliases = array('addMany' => 'add');

    /** 
     * Same thing, but with an addition that allows us to test the aliases
     */
    private static $testActionAliases = array('addMany' => 'add',
                                              'testAlias' => 'upload');

    function beforeFilter() {
        if (!defined('CAKEPHP_UNIT_TEST_EXECUTION')) {
            parent::beforeFilter($this->Event, self::$actionAliases);
        } else {
            parent::beforeFilter($this->Event, self::$testActionAliases);
        }
    }

    /**
     * Get the prefix for instructions (based on the URL)
     */
    private function getInstructionPrefix() {
        return (strpos(Router::url('/', true), 'cnics') != true) ? 
            'NA-ACCORD' : 'CNICS';
    }

    // The default page for the application
    function index() {
        if ($this->authUser['User']['uploader_flag']) {
            $this->set('uploadEvents', 
                       $this->Event->awaitingUpload($this->authUser));
            $this->set('reuploadEvents', 
                       $this->Event->possibleReupload($this->authUser));
        }

        if ($this->authUser['User']['reviewer_flag']) {
            $reviewEvents = $this->Event->awaitingReview($this->authUser);
            $reviewNumbers = array();
            $userid = $this->authUser['User']['id'];

            foreach ($reviewEvents as $event) {
                if ($event['Event']['reviewer1_id'] == $userid) {
                    $reviewNumbers[] = 1;
                } else if ($event['Event']['reviewer2_id'] == $userid) {
                    $reviewNumbers[] = 2;
                } else {
                    $reviewNumbers[] = 3;
                }
            }

            $this->set('reviewEvents', $reviewEvents);
            $this->set('reviewNumbers', $reviewNumbers);
        }

        $this->set('prefix', $this->getInstructionPrefix());
    }

    /**
     * Add an event
     */
    function add() {
        if (!empty($this->data)) {
            $result = $this->Event->checkAddData($this->data);

            // first array item returned indicates success
            if (!$result[0]) {
                // for failure, 2nd array item holds a message
                $this->Session->setFlash($result[1]);
            } else {
                $this->Event->addEvent($result[1], $this->data['Event'], 
                                $this->data['Criteria']);

		if (PROJECT_NAME == 'MI') {
                    $this->setSuccessMessage('Recorded new event MI ' . 
                                             (1000 + $this->Event->id));
                } else {
                    $this->setSuccessMessage('Recorded new event ' . 
                                             $this->Event->id);
                }

                $this->redirect('/events/index');
            }
        }

        $this->set('sites', $this->Patient->getSiteArray());
    }

    /** MIME type of an CSV file */
    const CSV_MIME = 'text/csv';

    /** another possible MIME type of an CSV file */
    const CSV_MIME2 = 'application/csv';

    /** another possible MIME type of an CSV file */
    const CSV_MIME3 = 'application/vnd.ms-excel';

    /** another MIME type of an CSV file */
    const CSV_MIME4 = 'text/comma-separated-values';

    /**
     * Upload charts for an event, or mark it as having no available packet 
     *    (both are on the same page)
     * @param id Id of the event
     */
    private function uploadOrNot($verb, $id) {
        if (!empty($this->data)) {
            $id = intval($this->data['Event']['id']);
        } else {
            $id = intval($id);
        }

        $event = $this->Event->findById($id);

        if (!empty($this->data)) {
            if ($verb == 'upload' && 
                !empty($this->data['Event']['chartFile'])) 
            {
            // uploading a packet
                if ($event['Event']['status'] != Event::CREATED &&
                    empty($this->data['Event']['confirmUpload'])) 
                {
                    $this->Session->setFlash(
                        'You must select the confirm checkbox to re-upload');
                } else {
                    $status = $this->Event->uploadCharts($event, 
                        $this->data['Event']['chartFile'], false);
    
                    if ($status['success']) {
                        $this->setSuccessMessage($status['message']);
                        $this->redirect('index');
                    } else {
                        $this->Session->setFlash($status['message']);
                    }
                }
            } else if ($verb = 'markNoPacket' &&
                       !empty($this->data['Event']['no_packet_reason'])) 
            {
            // no packet to upload
                $status = $this->Event->markNoPacketAvailable($event, 
                    $this->data, $this->authUser['User']['id']);

                if ($status['success']) {
                    $this->setSuccessMessage($status['message']);
                    $this->redirect('index');
                } else {
                    $this->Session->setFlash($status['message']);
                }
            }
        }

        $this->set('prefix', $this->getInstructionPrefix());
        $this->set('alreadyUploaded', 
                   $event['Event']['status'] != Event::CREATED);
        $this->set('event', $event);
        $this->set('noPacketReasons', $this->Event->getNoPacketReasons());
        $this->render('upload');
    }

    /**
     * Upload charts for an event
     * @param id Id of the event
     */
    function upload($id = null) {
        $this->uploadOrNot('upload', $id);
    }

    /**
     * Mark an event as having no packet available
     * @param id Id of the event
     */
    function markNoPacket($id = null) {
        $this->uploadOrNot('markNoPacket', $id);
    }

    /**
     * Upload scrubbed charts for an events
     * @param id Id of the event
     */
    function scrub($id = null) {
        if (!empty($this->data)) {
            $id = intval($this->data['Event']['id']);
        } else {
            $id = intval($id);
        }

        $event = $this->Event->findById($id);

        if (!empty($this->data) && !empty($this->data['Event']['chartFile']))
        {
            $status = $this->Event->uploadCharts($event, 
                $this->data['Event']['chartFile'], true);

            if ($status['success']) {
                $this->updateStatus();
                $this->setSuccessMessage($status['message']);
                $this->redirect('viewAll');
            } else {
                $this->Session->setFlash($status['message']);
            }
        }

        $this->set('prefix', $this->getInstructionPrefix());
        $this->set('event', $event);
    }

    /**
     * Screen charts for an events
     * @param id Id of the event
     */
    function screen($id = null) {
        if (!empty($this->data)) {
            $id = intval($this->data['Event']['id']);
        } else {
            $id = intval($id);
        }

        $event = $this->Event->findById($id);

        if (!empty($this->data) && !empty($this->data['Event'])) {
            $status = $this->Event->screen($event, $this->data['Event'],
                                           $this->authUser['User']['id']);

            if ($status['success']) {
                $this->setSuccessMessage($status['message']);
                $this->redirect('viewAll');
            } else {
                $this->Session->setFlash($status['message']);
            }
        }

        $this->set('event', $event);
    }

    /**
     * Add many events from a open CSV stream
     * @param handle Handle to the stream
     */
    private function addFromCsv($handle) {
        $returnArray = array();
        $returnArray['saved'] = 0;
        $returnArray['notFound'] = 0;
        $returnArray['missingData'] = 0;
        $returnArray['criteriaProblem'] = 0;
        $returnArray['notFoundList'] = '';
        $returnArray['missingDataList'] = '';
        $returnArray['criteriaProblemList'] = '';
        $lineNumber = 0;

        while ($line = fgetcsv($handle)) {
            $lineNumber++;

            if (empty($line) || empty($line[0]) || empty($line[1]) || 
                empty($line[2]))
            {
                $returnArray['missingData']++;
                $returnArray['missingDataList'] .= " $lineNumber";
                continue;
            } 

            $line[0] = trim(strip_tags($line[0]));
            $line[1] = trim(strip_tags($line[1]));
            $line[2] = trim(strip_tags($line[2]));
            $csv['Patient']['site_patient_id'] = $line[0];
            $csv['Patient']['site'] = $line[1];
            $csv['Event']['event_date'] = date('Y-m-d', strtotime($line[2]));
            $csv['Criteria'] = 
                $this->Criteria->extractCriteriaFromArray($line, 3);

            if (!empty($csv['Criteria']['error'])) {
                $returnArray['criteriaProblem']++;
                $returnArray['criteriaProblemList'] .= " $lineNumber";
            } else {
                $result = $this->Event->checkAddData($csv);
    
                // first array item returned indicates success
                if (!$result[0]) {
                    // for failure, 2nd array item holds a message
                    if (in_array($result[1], array(Event::MISSING_PATIENT_DATA,
                                                   Event::MISSING_EVENT_DATE,
                                                   Event::EVENT_DATE_TOO_EARLY))) 
                    {
                        $returnArray['missingData']++;
                        $returnArray['missingDataList'] .= " $lineNumber";
                    } else if ($result[1] === Event::CRITERIA_PROBLEM) {
                        $returnArray['criteriaProblem']++;
                        $returnArray['criteriaProblemList'] .= " $lineNumber";
                    } else {
                        $returnArray['notFound']++;
                        $returnArray['notFoundList'] .= 
                            "<br/> Line $lineNumber: {$line[0]}, {$line[1]}.";
                    }
                } else {
                    $returnArray['saved']++;
                    $this->Event->addEvent($result[1], $csv['Event'], 
                                           $csv['Criteria']);
                }
            }
        }
 
        return $returnArray;
    }

    /**
     * Add many events from a CSV file
     */
    function addMany() {
        if (!empty($this->data)) {
            $file = $this->data['Event']['newEventsFile'];
        
            if (!$this->Event->verifyUpload($file)) {
                $this->log('Upload failed ' .  print_r($file, true));
                $this->Session->setFlash('Upload failed.');
            } else {
	        $type = $file['type'];

                if (!in_array($type, array(self::CSV_MIME, self::CSV_MIME2, 
                                           self::CSV_MIME3, self::CSV_MIME4))) 
                {
                    $this->Session->setFlash("Bad file type $type");
                } else {
                    $tempFileName = $file['tmp_name'];
                    $handle = fopen($tempFileName, 'rb');

                    if (!$handle) {
                        $this->Session->setFlash(
                            "Failure: couldn't open temp file $tempFileName");
                    } else {
                        $results = $this->addFromCsv($handle);
                        $status = $results['saved'] . ' new events added.';
                        
                        if ($results['missingData'] > 0) {
                            $status .= "<br/> {$results['missingData']} lines missing patient data: {$results['missingDataList']}"; 
                        }

                        if ($results['notFound'] > 0) {
                            $status .= "<br/> {$results['notFound']} patients not found: {$results['notFoundList']}";
                        }

                        if ($results['criteriaProblem'] > 0) {
                            $status .= "<br/> {$results['criteriaProblem']} lines with criteria problems: {$results['criteriaProblemList']}"; 
                        }

                        $this->setSuccessMessage($status);
                        $this->redirect('index');
                    }
                }
            }
        }
    }
                    
    /**
     * Review an event
     * @param reviewerNumber Which reviewer (1, 2, or 3)?
     * @param eventId Id of the event to review
     */
    private function review($reviewerNumber, $eventId) {
        if (empty($eventId)) {
            $eventId = intval($this->data['Event']['id']);
        } else {
            $eventId = intval($eventId);
        }

        $event = $this->Event->findById($eventId);

        if (!empty($this->data)) {
            $result = $this->Event->checkReviewData($this->data);

            // first array item returned indicates success
            if (!$result[0]) {
                // for failure, 2nd array item holds a message
                $this->Session->setFlash($result[1]);
            } else {
                $this->data['Review']['event_id'] = $eventId;
                $this->data['Review']['reviewer_id'] = 
                    $this->authUser['User']['id'];
                $this->Review->save($this->data, array('fieldList' => 
                    array('event_id', 'reviewer_id', 'mci', 
                          'abnormal_ce_values_flag', 'ce_criteria',
                          'chest_pain_flag', 'ecg_changes_flag',
                          'lvm_by_imaging_flag', 'ci',
                          'type', 'secondary_cause', 'other_cause',
                          'false_positive_flag', 'false_positive_reason',
                          'false_positive_other_cause', 
                          'past_tobacco_use_flag', 'current_tobacco_use_flag',
                          'cocaine_use_flag', 'family_history_flag',
                          'ci_type', 'cardiac_cath', 'ecg_type')));
		$this->Event->updateAfterReview($reviewerNumber, $event, 
                                                $this->authUser);
                $this->setSuccessMessage('Review saved.');
                $this->redirect('/events/index');
            }
        }

        $this->set('prefix', $this->getInstructionPrefix());
        $this->set('event', $event);
        $this->set('eventId', $eventId);
        $this->set('mcis', $this->Review->getMcis());
        $this->set('ceCriterias', $this->Review->getCriterias());
        $this->set('types', $this->Review->getTypes());
        $this->set('secondaryCauses', $this->Review->getSecondaryCauses());
        $this->set('falsePositiveReasons', 
                   $this->Review->getFalsePositiveReasons());
        $this->set('ciTypes', $this->Review->getCiTypes());
        $this->set('ecgTypes', $this->Review->getEcgTypes());
        $this->set('reviewerNumber', $reviewerNumber);
        $this->render('review');
    }

    /**
     * Review an event as the first reviewer
     */
    function review1($eventId = null) {
        $this->review(1, $eventId);
    }

    /**
     * Review an event as the second reviewer
     */
    function review2($eventId = null) {
        $this->review(2, $eventId);
    }

    /**
     * Review an event as the third reviewer
     */
    function review3($eventId = null) {
        $this->review(3, $eventId);
    }

    /**
     * View all events
     */
    function viewAll() {
        $summary = $this->Event->countStatuses();
        $summary['total'] = array_sum($summary);
        
        $this->set('reviewers', $this->User->getReviewers(false));
        $this->set('thirdReviewers', $this->User->getReviewers(true));
        $this->set('summary', $summary);
        $this->set('toBeUploaded', $this->Event->getAll(Event::CREATED));
        $this->set('toBeScrubbed', $this->Event->getAll(Event::UPLOADED));
        $this->set('toBeScreened', $this->Event->getAll(Event::SCRUBBED));
        $this->set('toBeAssigned', $this->Event->getAll(Event::SCREENED));
        $this->set('toBeSent', $this->Event->getAll(Event::ASSIGNED));
        $this->set('toBeReviewed', $this->Event->getAll(Event::SENT));
        $this->set('thirdReviewNeeded', 
                   $this->Event->getAll(Event::THIRD_REVIEW_NEEDED));
        $this->set('thirdReviewerAssigned', 
                   $this->Event->getAll(Event::THIRD_REVIEW_ASSIGNED));
        $this->set('allDone', $this->Event->getAll(Event::DONE));
        $this->set('noPacketAvailable', $this->Event->getAll(Event::NO_PACKET_AVAILABLE));
        $this->set('rejected', $this->Event->getAll(Event::REJECTED));
    }

    /**
     * get events as a CSV file
     */
    function getCsv() {
        $events = $this->Event->find('all', array('order' => 'Event.id'));

        foreach ($events as $key => $event) {
            $reviews = $this->extractReviews($event);
            $events[$key]['Review1'] = $reviews['review1'];
            $events[$key]['Review2'] = $reviews['review2'];
            $events[$key]['Review3'] = $reviews['review3'];
        }

        $this->set('events', $events);

        // suppress debugging messages in output
        Configure::write('debug', 0);
        $this->layout = 'ajax';
        $this->autoLayout = false;
        $this->render('eventCsv');
    }

    /**
     * Extract the 3 reviews from $this->data (for an event)
     * @param data $this->data
     * @return The reviews, as an array ('review1' => ..., etc)
     */
    private function extractReviews($data) {
        $reviews['review1'] = null;
        $reviews['review2'] = null;
        $reviews['review3'] = null;

        if (!empty($data['Review'])) {
            foreach ($data['Review'] as $review) {
                $reviewerId = $review['reviewer_id'];

                if ($reviewerId == $data['Event']['reviewer1_id']) {
                    $reviews['review1'] = $review;
                } else if ($reviewerId == $data['Event']['reviewer2_id']) {
                    $reviews['review2'] = $review;
                } else if ($reviewerId == $data['Event']['reviewer3_id']) {
                    $reviews['review3'] = $review;
                } else {
                    $this->log("review for event {$data['Event']['id']} 
                                doesn't match reviewers: "
                               . print_r($review, true));
                }
            }
        }
    
        return $reviews;
    }


    /**
     * Edit an event
     * @param id Id of the event
     */
    function edit($id) {
        $id = intval($id);

        if (!empty($this->data)) {
            $id = $this->data['Event']['id'];
        }

        $oldEvent = $this->Event->findById($id);

        if (empty($oldEvent)) {
            $this->Session->setFlash("No such event: $id.");
            $this->redirect(array('action' => 'viewAll'));
        }

        if (!empty($this->data)) {
            $result = $this->Event->checkAddData($this->data);

            // first array item returned indicates success
            if (!$result[0]) {
                // for failure, 2nd array item holds a message
                $this->Session->setFlash($result[1]);
            } else {
                $this->Event->editEvent($result[1], $this->data['Event']);

		if (PROJECT_NAME == 'MI') {
                    $this->setSuccessMessage('Changed event MI ' . 
                                             (1000 + $this->Event->id));
                } else {
                    $this->setSuccessMessage('Changed event ' . 
                                             $this->Event->id);
                }
            }
        } 
        
        $this->data = $this->Event->findById($id);
        $reviews = $this->extractReviews($this->data);
        $this->set('review1', $reviews['review1']);
        $this->set('review2', $reviews['review2']);
        $this->set('review3', $reviews['review3']);

        $this->set('sites', $this->Patient->getSiteArray());
        $this->set('canDownload', $this->Event->hasChart($this->data));

        $secondaryCauses = $this->Review->getSecondaryCauses();
        $secondaryCauses[EventDerivedData::NO_CONSENSUS] = 
            EventDerivedData::NO_CONSENSUS;
        $ciTypes = $this->Review->getCiTypes();
        $ciTypes[EventDerivedData::NO_CONSENSUS] = 
            EventDerivedData::NO_CONSENSUS;
        $ecgTypes = $this->Review->getEcgTypes();
        $ecgTypes[EventDerivedData::NO_CONSENSUS] = 
            EventDerivedData::NO_CONSENSUS;
        $flagChoices = array('No', 'Yes');
        $this->set('outcomes', $this->Review->getMcis());
        $this->set('types', $this->Review->getTypes());
        $this->set('secondaryCauses', $secondaryCauses);
        $this->set('falsePositiveReasons', 
                   $this->Review->getFalsePositiveReasons());
        $this->set('ciTypes', $ciTypes);
        $this->set('ecgTypes', $ecgTypes);
        $this->set('flagChoices', $flagChoices);
    }

    // for testing
    function testAlias() {
    }

    /**
     * Download a charts file for an event
     * @param id Id of the event
     */
    function download($id = null) {
        $id = intval($id);
        $event = $this->Event->findById($id);

        /* test suite disables redirects, so below set it up so that
           every $this->redirect falls through to the bottom part
           where we set up the output */
        if (empty($event)) {
            $this->Session->setFlash('Invalid event id');
            $this->redirect($this->referer());
        } else {
            $fileNumber = $event['Event']['file_number'];
 
            /* allow download for admin (if there is a chart),
               or if the event is to reviewed by the authUser */
            if (!($this->User->isAdmin($this->authUser) && 
                  $this->Event->hasChart($event)) &&
                !$this->Event->toBeReviewed($event, $this->authUser))
            {
                $this->Session->setFlash(
                    'You should not be downloading this file at this time');
                $this->redirect($this->referer());
            } else {
            /* seemingly screwy logic below, but it works.  An event can both
               be 'toBeScreened' and 'canBeScrubbed', so switching first else
               with if does not yield the same logic */
                if ($this->Event->toBeReviewed($event, $this->authUser) ||
                    $this->Event->toBeScreened($event, $this->authUser)) 
                {
                    $fileName = 
                        $this->Event->scrubbedFileName($id, $fileNumber);
                    $downloadPrefix = Event::SCRUBBED_PREFIX;
                } else if ($this->Event->canBeScrubbed($event, $this->authUser))
                {
                    $fileName = $this->Event->rawFileName($id, $fileNumber);
                    $downloadPrefix = Event::RAW_PREFIX;
                } else {
                    $fileName = 
                        $this->Event->scrubbedFileName($id, $fileNumber);
                    $downloadPrefix = Event::SCRUBBED_PREFIX;
                }

                $suffix = $this->Event->findSuffix($fileName);

                if (empty($suffix)) {
                    $this->Session->setFlash("Couldn't find file to download");
                    $this->redirect("index/$id");
                } 
            }
        }

        if (!defined('CAKEPHP_UNIT_TEST_EXECUTION')) {
            // don't write extra stuff
            Configure::write('debug', 0);
            $this->layout = 'ajax'; // view does all output
        }

        $downloadInfo = $this->Event->getDownloadInfo($fileName, $suffix, 
                                                      $downloadPrefix, $id);

        $this->set('sourcefile', $downloadInfo['sourcefile']);
        $this->set('destfile', $downloadInfo['destfile']);
        $this->set('contentType', $downloadInfo['contentType']);
    }

    const NOT_FOUND = ' event(s) not found:';
    const CANT_ASSIGN = ' event(s) cannot be assigned at this time:';
    const CANT_ASSIGN_REVIEWER = 
        ' event(s) cannot be assigned to the selected reviewer:';
    const EMAIL_FAILED = 
        ' event(s) email failed:';

    /**
     * Assign charts to reviewers
     * @param thirdReview If true, assign a 3rd review (else, assigning the
     *     first two reviews
     */
    private function doAssign($thirdReview) {
        $reviewers = $this->User->getReviewers(false);
      
        if ($thirdReview) {
            $thirdReviewers = $this->User->getReviewers(true);
            $this->set('thirdReviewers', $thirdReviewers);
        }

        if (!empty($this->data)) {
            $results = $this->Event->assignAll($this->data, 
                $thirdReview ? $thirdReviewers : $reviewers, 
                $this->authUser, $thirdReview);

            if (!empty($results['error'])) {
                $this->Session->setFlash($results['error']);
            } else {
                $status = $results['assigned'] . ' event(s) assigned.';
                        
                if ($results['notFound'] > 0) {
                    $status .= "<br/> {$results['notFound']}" . 
                                self::NOT_FOUND . 
                                "{$results['notFoundList']}"; 
                }

                if ($results['cannotAssign'] > 0) {
                    $status .= "<br/> {$results['cannotAssign']}" . 
                                self::CANT_ASSIGN . 
                                "{$results['cannotAssignList']}"; 
                }

                if ($results['cannotAssignReviewer'] > 0) {
                    $status .= "<br/> {$results['cannotAssignReviewer']}" .
                                self::CANT_ASSIGN_REVIEWER .
                                "{$results['cannotAssignReviewerList']}"; 
                }

                if (!empty($results['emailFailed']) && 
                    $results['emailFailed'] > 0) 
                {
                    $status .= "<br/> {$results['emailFailed']}" .
                                self::EMAIL_FAILED .
                                "{$results['emailFailedList']}"; 
                }

                $this->setSuccessMessage($status);
                $this->data = null;
            }
        }

        $toBeAssigned = $this->Event->getAll($thirdReview ? 
            Event::THIRD_REVIEW_NEEDED : Event::SCREENED);

        if (count($toBeAssigned) == 0) {
            $this->redirect("viewAll");
        }

        $this->set('reviewers', $reviewers);
        $this->set('toBeAssigned', $toBeAssigned);
        $this->set('thirdReview', $thirdReview);
        $this->render('assignMany');
    }

    /**
     * Assign charts to reviewers (first 2 reviews)
     */
    function assignMany() {
        $this->doAssign(false);
    }

    /**
     * Assign third reviewers
     */
    function assign3rdMany() {
        $this->doAssign(true);
    }

    const CANT_SEND = ' event(s) cannot be sent at this time:';
    const BAD_EMAIL = 
        ' event(s) had bad reviewer e-mail addresses (mail was sent to the reviewers with good addresses):';

    /**
     * Send charts to reviewers
     */
    function sendMany() {
        if (!empty($this->data)) {
            $results = $this->Event->sendAll($this->data, $this->authUser);

            if (!empty($results['error'])) {
                $this->Session->setFlash($results['error']);
            } else {
                $status = $results['sent'] . ' event(s) sent.';
                        
                if ($results['notFound'] > 0) {
                    $status .= "<br/> {$results['notFound']}" . 
                                self::NOT_FOUND . 
                                "{$results['notFoundList']}"; 
                }

                if ($results['cannotSend'] > 0) {
                    $status .= "<br/> {$results['cannotSend']}" . 
                                self::CANT_SEND . 
                                "{$results['cannotSendList']}"; 
                }

                if ($results['badEmail'] > 0) {
                    $status .= "<br/> {$results['badEmail']}" .
                                self::BAD_EMAIL .
                                "{$results['badEmailList']}"; 
                }

                $this->setSuccessMessage($status);
            }
        }

        $toBeSent = $this->Event->getAll(Event::ASSIGNED);

        if (count($toBeSent) == 0) {
            $this->redirect("viewAll");
        }

        $this->set('reviewers', $this->User->getReviewers(false));
        $this->set('toBeSent', $toBeSent);
    }
}
