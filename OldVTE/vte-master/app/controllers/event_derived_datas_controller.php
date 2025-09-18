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
        // have to treat checkboxes special to distinguish between null and 0
        $pe_flag = $data['EventDerivedData']['pe_flag'];
        $dvt_flag = $data['EventDerivedData']['dvt_flag'];
        $cat_flag = $data['EventDerivedData']['cat_flag'];
        $no_vte_flag = $data['EventDerivedData']['no_vte_flag'];

        $cat_subtype = $data['EventDerivedData']['cat_subtype'];
        $pe_dp = $data['EventDerivedData']['pe_dp'];
        $dvt_dp = $data['EventDerivedData']['dvt_dp'];
        $cat_dp = $data['EventDerivedData']['cat_dp'];
        $pe_type = $data['EventDerivedData']['pe_type'];
        $dvt_type = $data['EventDerivedData']['dvt_type'];
        $cat_type = $data['EventDerivedData']['cat_type'];
        $pe_location = $data['EventDerivedData']['pe_location'];
        $dvt_location = $data['EventDerivedData']['dvt_location'];
        $dvt_location = $data['EventDerivedData']['dvt_location'];
        $dvt_le_location = $data['EventDerivedData']['dvt_le_location'];
        $dvt_other_location = $data['EventDerivedData']['dvt_other_location'];
        $dvt_other_neck_location = $data['EventDerivedData']['dvt_other_neck_location'];
        $dvt_other_ap_location = $data['EventDerivedData']['dvt_other_ap_location'];
        $dvt_other_ps_location = $data['EventDerivedData']['dvt_other_ps_location'];
        $dvt_other_ic_location = $data['EventDerivedData']['dvt_other_ic_location'];

        if (empty($pe_flag) && $pe_flag !== '0') {
            $data['EventDerivedData']['pe_flag'] = null;
        }

        if (empty($dvt_flag) && $dvt_flag !== '0') {
            $data['EventDerivedData']['dvt_flag'] = null;
        }

        if (empty($cat_flag) && $cat_flag !== '0') {
            $data['EventDerivedData']['cat_flag'] = null;
        }

        if (empty($no_vte_flag) && $no_vte_flag !== '0') {
            $data['EventDerivedData']['no_vte_flag'] = null;
        }

        if ($cat_subtype === null || $cat_subtype == '') {
            $data['EventDerivedData']['cat_subtype'] = null;
        } else {
            $data['EventDerivedData']['cat_subtype'] = 
              strip_tags($data['EventDerivedData']['cat_subtype']);
        }

        if ($pe_dp === null || $pe_dp == '') {
            $data['EventDerivedData']['pe_dp'] = null;
        } else {
            $data['EventDerivedData']['pe_dp'] = 
              strip_tags($data['EventDerivedData']['pe_dp']);
        }

        if ($dvt_dp === null || $dvt_dp == '') {
            $data['EventDerivedData']['dvt_dp'] = null;
        } else {
            $data['EventDerivedData']['dvt_dp'] = 
              strip_tags($data['EventDerivedData']['dvt_dp']);
        }

        if ($cat_dp === null || $cat_dp == '') {
            $data['EventDerivedData']['cat_dp'] = null;
        } else {
            $data['EventDerivedData']['cat_dp'] = 
              strip_tags($data['EventDerivedData']['cat_dp']);
        }

        if ($pe_type === null || $pe_type == '') {
            $data['EventDerivedData']['pe_type'] = null;
        } else {
            $data['EventDerivedData']['pe_type'] = 
              strip_tags($data['EventDerivedData']['pe_type']);
        }

        if ($dvt_type === null || $dvt_type == '') {
            $data['EventDerivedData']['dvt_type'] = null;
        } else {
            $data['EventDerivedData']['dvt_type'] = 
              strip_tags($data['EventDerivedData']['dvt_type']);
        }

        if ($cat_type === null || $cat_type == '') {
            $data['EventDerivedData']['cat_type'] = null;
        } else {
            $data['EventDerivedData']['cat_type'] = 
              strip_tags($data['EventDerivedData']['cat_type']);
        }

        if ($pe_location === null || $pe_location == '') {
            $data['EventDerivedData']['pe_location'] = null;
        } else {
            $data['EventDerivedData']['pe_location'] = 
              strip_tags($data['EventDerivedData']['pe_location']);
        }

        if ($dvt_location === null || $dvt_location == '') {
            $data['EventDerivedData']['dvt_location'] = null;
        } else {
            $data['EventDerivedData']['dvt_location'] = 
              strip_tags($data['EventDerivedData']['dvt_location']);
        }

        if ($dvt_le_location === null || $dvt_le_location == '') {
            $data['EventDerivedData']['dvt_le_location'] = null;
        } else {
            $data['EventDerivedData']['dvt_le_location'] = 
              strip_tags($data['EventDerivedData']['dvt_le_location']);
        }

        if ($dvt_other_location === null || $dvt_other_location == '') {
            $data['EventDerivedData']['dvt_other_location'] = null;
        } else {
            $data['EventDerivedData']['dvt_other_location'] = 
              strip_tags($data['EventDerivedData']['dvt_other_location']);
        }

        if ($dvt_other_neck_location === null || $dvt_other_neck_location == '') {
            $data['EventDerivedData']['dvt_other_neck_location'] = null;
        } else {
            $data['EventDerivedData']['dvt_other_neck_location'] = 
              strip_tags($data['EventDerivedData']['dvt_other_neck_location']);
        }

        if ($dvt_other_ap_location === null || $dvt_other_ap_location == '') {
            $data['EventDerivedData']['dvt_other_ap_location'] = null;
        } else {
            $data['EventDerivedData']['dvt_other_ap_location'] = 
              strip_tags($data['EventDerivedData']['dvt_other_ap_location']);
        }

        if ($dvt_other_ps_location === null || $dvt_other_ps_location == '') {
            $data['EventDerivedData']['dvt_other_ps_location'] = null;
        } else {
            $data['EventDerivedData']['dvt_other_ps_location'] = 
              strip_tags($data['EventDerivedData']['dvt_other_ps_location']);
        }

        if ($dvt_other_ic_location === null || $dvt_other_ic_location == '') {
            $data['EventDerivedData']['dvt_other_ic_location'] = null;
        } else {
            $data['EventDerivedData']['dvt_other_ic_location'] = 
              strip_tags($data['EventDerivedData']['dvt_other_ic_location']);
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
			     array('event_id', 'pe_flag', 'dvt_flag', 
                                   'cat_flag', 'no_vte_flag', 'cat_subtype',
				   'pe_dp', 'dvt_dp', 'cat_dp',
				   'pe_type', 'dvt_type', 'cat_type',
				   'pe_location', 'dvt_location',
				   'dvt_le_location', 'dvt_other_location',
				   'dvt_other_neck_location',
				   'dvt_other_ap_location',
				   'dvt_other_ps_location',
				   'dvt_other_ic_location')));
 
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
