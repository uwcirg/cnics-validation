<?php
class CriteriasController extends AppController {
    var $name = 'Criterias';
    var $uses = array('Criteria', 'Event', 'User');
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
        $data['Criteria']['name'] = strip_tags($data['Criteria']['name']);
        $data['Criteria']['value'] = strip_tags($data['Criteria']['value']);
        return array(true);
    }

    /**
     * Add a criteria
     */
    function add() {
        if (!empty($this->data)) {
            $eventId = $this->data['Criteria']['event_id'];
            $event = $this->Event->findById($eventId);
    
            if (empty($event)) {
                $this->Session->setFlash("No such event $eventId");
                $this->redirect('/events/index');
            } else {
                $result = $this->checkData($this->data);

                // first array item returned indicates success
                if (!$result[0]) {
                    // for failure, 2nd array item holds a message
                    $this->Session->setFlash($result[1]);
                } else {
                    $this->Criteria->save($this->data, array('fieldList' => 
                        array('event_id', 'name', 'value')));
                    $this->setSuccessMessage("Saved new criterion");
                    $this->redirect("/events/edit/$eventId");
                }
            }
        } else {
            $this->Session->setFlash('No criterion data!');
        }

        $this->redirect('/events/index');
    }

    /**
     * Delete a criterion
     * @param id Which criterion?
     * @param eventId Event whose page we should go to when we are done
     */
    function delete($id, $eventId) {
        $id = intval($id);
        $eventId = intval($eventId);

        $criterion = $this->Criteria->findById($id);

        if (empty($criterion)) {
            $this->Session->setFlash("No such criterion: $id");
        } else {
            $this->Criteria->delete($id);
            $this->setSuccessMessage('Criterion deleted');
        }
 
        $this->redirect("/events/edit/$eventId");
    }
}
?>
