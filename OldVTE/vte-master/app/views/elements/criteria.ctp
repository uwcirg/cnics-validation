<?php
echo "{$criteria['name']}: {$criteria['value']}";

if ($showDeleteLink) {
    echo "&nbsp;" . $html->link('Delete', 
        "/criterias/delete/{$criteria['id']}/{$criteria['event_id']}?" .
            AppController::ID_KEY . '=' .  
            $session->read(AppController::ID_KEY));
}

echo $separator;
?>
