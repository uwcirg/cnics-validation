<?php
class SolicitationsController extends AppController {
    var $name = 'Solicitations';
    var $uses = array('Solicitation', 'Event', 'User');
    /* Note: User used by AppController; this actually matters when running 
       tests */
    var $helpers = array('Html', 'Form', 'Javascript');

    /**
     * Check that data to add a solicitation is okay; sanitize any dubious 
     * strings
     * @param data The data
     * @return an array:  (true) if all is well,
     *                    (false, message) if there was a problem
     */
    private function checkData(&$data) {
        $data['Solicitation']['contact'] = 
            strip_tags($data['Solicitation']['contact']);
        return array(true);
    }

    /**
     * Add a solicitation
     */
    function add() {
        if (!empty($this->data)) {
            $eventId = $this->data['Solicitation']['event_id'];
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
                    $this->Solicitation->save($this->data, array('fieldList' => 
                        array('event_id', 'date', 'contact')));
                    $this->setSuccessMessage("Saved new solicitation");
                    $this->redirect("/events/edit/$eventId");
                }
            }
        } else {
            $this->Session->setFlash('No solicitation data!');
        }

        $this->redirect('/events/index');
    }

    /**
     * Delete a solicitation
     * @param id Which solicitation?
     * @param eventId Event whose page we should go to when we are done
     */
    function delete($id, $eventId) {
        $id = intval($id);
        $eventId = intval($eventId);

        $solicitation = $this->Solicitation->findById($id);

        if (empty($solicitation)) {
            $this->Session->setFlash("No such solicitation: $id");
        } else {
            $this->Solicitation->delete($id);
            $this->setSuccessMessage('Solicitation deleted');
        }
 
        $this->redirect("/events/edit/$eventId");
    }
}
?>
