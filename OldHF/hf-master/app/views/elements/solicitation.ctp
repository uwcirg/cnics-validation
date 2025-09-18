<?php
echo "<br/>{$solicitation['date']} ({$solicitation['contact']})";
echo "&nbsp;" . $html->link('Delete', 
    "/solicitations/delete/{$solicitation['id']}/{$solicitation['event_id']}?" .
        AppController::ID_KEY . '=' .  $session->read(AppController::ID_KEY));
?>
