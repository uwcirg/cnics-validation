<?php
class EventDerivedDatasController extends AppController {
    var $name = 'EventDerivedDatas';
    var $uses = array('EventDerivedData', 'Event', 'Review', 'User');
    /* Note: User used by AppController; this actually matters when running 
       tests */
    var $helpers = array('Html', 'Form', 'Javascript');

    /**
     * sanitize any dubious strings
     * @param data The data
     * @return an array:  (true) if all is well,
     *                    (false, message) if there was a problem
     */
    private function checkData(&$data) {
        // have to treat these special to distinguish between null and 0
        $fpe = $data['EventDerivedData']['false_positive_event'];
        $ci = $data['EventDerivedData']['ci'];
        $sco = $data['EventDerivedData']['secondary_cause_other'];

        if (empty($data['EventDerivedData']['outcome'])) {
            $data['EventDerivedData']['outcome'] = null;
        }

        if (empty($data['EventDerivedData']['primary_secondary'])) {
            $data['EventDerivedData']['primary_secondary'] = null;
        }

        if (empty($fpe) && $fpe !== '0') {
            $data['EventDerivedData']['false_positive_event'] = null;
        }

        if (empty($data['EventDerivedData']['secondary_cause'])) {
            $data['EventDerivedData']['secondary_cause'] = null;
        }

        if ($sco === null || $sco == '') {
            $data['EventDerivedData']['secondary_cause_other'] = null;
        } else {
            $data['EventDerivedData']['secondary_cause_other'] = 
              strip_tags($data['EventDerivedData']['secondary_cause_other']);
        }

        if (empty($data['EventDerivedData']['false_positive_reason'])) {
            $data['EventDerivedData']['false_positive_reason'] = null;
        }

        if (empty($ci) && $ci !== '0') {
            $data['EventDerivedData']['ci'] = null;
        }

        if (empty($data['EventDerivedData']['ci_type'])) {
            $data['EventDerivedData']['ci_type'] = null;
        }

        if (empty($data['EventDerivedData']['ecg_type'])) {
            $data['EventDerivedData']['ecg_type'] = null;
        }

        return array(true);
    }

    /**
     * Edit or add an event_derived_data record
     */
    function edit() {
        if (!empty($this->data)) {
            $eventId = $this->data['EventDerivedData']['event_id'];
            $event = $this->Event->findById($eventId);
    
            if (empty($event)) {
                $this->Session->setFlash("No such event $eventId");
                $this->redirect('/events/index');
            } else if ($event['Event']['status'] != Event::DONE) {
                $this->Session->setFlash("Can't edit fields if reviews aren't 
                                          done!");
                $this->redirect("/events/edit/$eventId");
            } else {
                $result = $this->checkData($this->data);

                // first array item returned indicates success
                if (!$result[0]) {
                    // for failure, 2nd array item holds a message
                    $this->Session->setFlash($result[1]);
                } else {
                    $this->EventDerivedData->save($this->data, 
                        array('fieldList' => 
                            array('event_id', 'outcome', 
                                  'primary_secondary', 
                                  'false_positive_event', 'secondary_cause',                                      'secondary_cause_other', 
                                  'false_positive_reason', 'ci',
                                  'ci_type', 'ecg_type')));
 
                    $this->setSuccessMessage("Saved overall fields");
                    $this->redirect("/events/edit/$eventId");
                }
            }
        } else {
            $this->Session->setFlash('No overall fields data!');
        }

        $this->redirect('/events/index');
    }
}
?>
