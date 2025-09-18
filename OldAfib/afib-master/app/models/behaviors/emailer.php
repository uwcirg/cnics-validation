<?php
/**
 * Email 
 *
 * @author Greg Barnes
 */
class EmailerBehavior extends ModelBehavior {
    /**
     * Generate the 'From:' headers for a given email address
     * @param fromAddress email Address
     * @return Appropriate 'From:' headers
     */
    function fromHeaders($fromAddress) {
        return "From: " . $fromAddress . "\n" .
               "Reply-To: " . $fromAddress . "\n" .
               "X-Mailer: PHP/" . phpversion();
    }

    /**
     * Generate attachment headers
     * @param boundary Boundary string
     * @return The attachment headers
     */
    function attachHeaders($boundary) {
        return "MIME-Version: 1.0\n" .
               "Content-Type: multipart/mixed; boundary=\"$boundary\""; 
    }

    /**
     * Generate headers for the attachment part of a multipart message
     * @param filename Name of the attachment file
     * @param mimetype MIME type of the attachment
     * @return The headers that go inside the message
     */
    function multipartHeaders($filename, $mimetype) {
        return "Content-Type: $mimetype; name=\"$filename\"\n" .
               "Content-Transfer-Encoding: base64\n" .
               "Content-Disposition: attachment; filename=\"$filename\"\n\n"; 
    }

    /**
     * Generate an actual attachment (the scrubbed packet for an event)
     * @param model Event model
     * @param event The event
     * @param boundary Boundary for the e-mail message
     * @return the headers + the attachment
     */
    function attachment($model, $event, $boundary) {
        $id = $event['Event']['id'];
        $scrubbedFilename = 
            $model->scrubbedFilename($id, $event['Event']['file_number']);
        $suffix = $model->findSuffix($scrubbedFilename);
        $attachInfo = $model->getDownloadInfo($scrubbedFilename, $suffix, 
                                              EVENT::SCRUBBED_PREFIX, $id);
        $destfile = $attachInfo['destfile'];
        $attachment = chunk_split(base64_encode(
             file_get_contents($attachInfo['sourcefile']))); 
        return "\n--$boundary\n" . 
               $this->multipartHeaders($destfile, $attachInfo['contentType']) .
               $attachment . "\n";
    }

    /** First part of the plaintext MIME start */
    const MIMESTART1 = "This is a multi-part message in MIME format.\n--";

    /** Second part of the plaintext MIME start */
    const MIMESTART2 = 
        "\nContent-Type: text/plain; charset=ISO-8859-1\nContent-Transfer-Encoding: 7bit\n\n";

    /**
     * Generate the start of a MIME message
     * @param boundary The boundary
     * @return The start of the MIME message (everything before the plain text)
     */
    function mimeStart($boundary) {
        return self::MIMESTART1 . $boundary . self::MIMESTART2;
    }

    /**
     * Email a packet to a reviewer
     * @param reviewer The reviewer
     * @param reviewNum The reviewer's number (1, 2, or 3)
     * @param event The packet's event
     * @param testing set to true to return what would be mailed instead
     *    of actually doing the mail
     * @param includeAttachment include the packet as an attachment; 
     *    currently set to false because big attachments cause problems
     */
    function emailPacket(&$model, $reviewer, $reviewNum, $event, 
                         $testing = false, $includeAttachment = false) 
    {
        $applicationUC = strpos(Router::url('/', true), 'cnics') != true ?
                'NA-ACCORD' : 'CNICS';
        $applicationLC = $applicationUC == 'CNICS' ? 'cnics' : 'naaccord';

        /* Help email address */
        $help = $applicationLC . '@cirg.washington.edu';

        /* Subject for a packet email */
        $packetSubject = "$applicationUC " . LONG_NAME . " event ready for review";
        
        /* Packet email body, part 1, when attachment included */
        $packetBody1Attachment = 
                "You have been assigned to review an event for the $applicationUC " . LONG_NAME . " Project.  The packet associated with the event is attached to this e-mail message.  You can also download the packet from the project website at this URL:.";
        	
        /* Packet email body, part 1, no attachment */
        $packetBody1NoAttachment = 
                "You have been assigned to review an event for the $applicationUC " . LONG_NAME . " Project.  You can download the packet associated with the event from the project website at this URL:.";
        	
        /* Packet email body, part 2 */
        $packetBody2 = "To review the event, please visit the following URL:";
        
        /* Packet email body, part 3 */
        $packetBody3 = "A list of your outstanding reviews can be found here:";
        
        /* Packet sig */
        $packetSig = "\r\nThank you so much for completing reviews!\r\n\r\nSincerely,\r\nThe $applicationUC " . LONG_NAME . " team";

        $address = $reviewer['username'];

        $downloadUrl = $model->downloadUrl($event);
        $reviewUrl = $model->reviewUrl($event, $reviewNum);
        $indexUrl = $model->indexUrl();

        $boundary = '-----=' . md5(uniqid(rand())); 

	$emailBody = ($includeAttachment ? $this->mimeStart($boundary) : '') .
                     "Dear {$reviewer['first_name']} " .
	             "{$reviewer['last_name']}, \r\n\r\n" .
		     ($includeAttachment ? $packetBody1Attachment :
                                           $packetBody1NoAttachment) . 
                     "\r\n\r\n$downloadUrl\r\n\r\n" .
		     $packetBody2 . "\r\n\r\n$reviewUrl\r\n\r\n" .
                     $packetBody3 . "\r\n\r\n$indexUrl\r\n\r\n" .
                     $packetSig .  "\r\n" .
                     ($includeAttachment ? 
                          $this->attachment($model, $event, $boundary) : '');


        if ($testing) {
            return array($address, $packetSubject, $emailBody, 
	                 $this->fromHeaders($help) . 
                         ($includeAttachment ? 
                             "\n" .  $this->attachHeaders($boundary) : '')); 
        }

        if (mail($address, $packetSubject, $emailBody, 
	         $this->fromHeaders($help) . 
                 ($includeAttachment ? 
                     "\n" .  $this->attachHeaders($boundary) : ''))) 
        {
	    return null;
        } else {
	    return 'Email failed';
        }
    }
}
?>
