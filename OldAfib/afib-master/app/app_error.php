<?php
class AppError extends ErrorHandler {
    /**
     * Handle an unknown user error
     */
    function unknownUser($params) {
        $this->controller->set('authUsername', $params['authUsername']);
        $this->_outputMessage('unknown_user');
    }

    /**
     * Handle an error caused by a user accessing a page they should not
     */
    function notAuthorized($params) {
        $this->controller->set('authUsername', $params['authUsername']);
        $this->controller->set('params', $params['params']);
        $this->_outputMessage('not_authorized');
    }
}
?>

