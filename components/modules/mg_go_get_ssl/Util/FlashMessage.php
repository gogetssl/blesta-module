<?php

namespace MgGoGetSsl\Util;

class FlashMessage
{

    const SUCCESS = 'success';
    const ERROR = 'error';
    const DANGER = 'danger';
    const INFO = 'info';
    const WARN = 'warning';

    private $messageFormat = '<div class="alert alert-%s">%s</div>';

    /**
     * Add message
     *
     * @param string $type
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     * @return bool
     */
    public function addMessage($type, $message, $checkIfAlreadyExists = false)
    {
        if (!in_array($type, [self::SUCCESS, self::ERROR, self::INFO, self::WARN])) {
            return false;
        }

        if ($checkIfAlreadyExists) {
            $messages = isset($_SESSION['messanger_messages'][$type]) ? $_SESSION['messanger_messages'][$type] : [];
            if (is_array($messages) && in_array($message, $messages)) {
                return false;
            }
        }

        $_SESSION['messanger_messages'][$type][] = $message;

        return true;
    }

    /**
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public function addSuccessMessage($message, $checkIfAlreadyExists = true)
    {
        self::addMessage(self::SUCCESS, $message, $checkIfAlreadyExists);
    }

    /**
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public function addErrorMessage($message, $checkIfAlreadyExists = true)
    {
        self::addMessage(self::ERROR, $message, $checkIfAlreadyExists);
    }

    /**
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public function addInfoMessage($message, $checkIfAlreadyExists = true)
    {
        self::addMessage(self::INFO, $message, $checkIfAlreadyExists);
    }

    /**
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public function addWarningMessage($message, $checkIfAlreadyExists = true)
    {
        self::addMessage(self::WARN, $message, $checkIfAlreadyExists);
    }

    /**
     * Get messages
     *
     * @param string $type
     * @param bool   $clear
     * @return array|false
     */
    public function getMessages($type = null, $clear = true)
    {
        if (empty($type)) {
            $messages = isset($_SESSION['messanger_messages']) ? $_SESSION['messanger_messages'] : [];

            if ($clear) {
                unset($_SESSION['messanger_messages']);
            }

            return is_array($messages) ? $messages : [];
        }

        if (!in_array($type, [self::SUCCESS, self::ERROR, self::INFO, self::WARN])) {
            return false;
        }

        $messages = isset($_SESSION['messanger_messages'][$type]) ? $_SESSION['messanger_messages'][$type] : [];

        if ($clear) {
            unset($_SESSION['messanger_messages'][$type]);
        }

        return is_array($messages) ? $messages : [];
    }

    /**
     * @param string|null $type
     * @param bool        $clear
     * @return string
     */
    public function renderMessages($type = null, $clear = true)
    {
        $html = '';
        $messages = $this->getMessages($type, $clear);

        foreach ($messages as $type => $messagesByType) {
            foreach ($messagesByType as $message) {
                $html .= $this->staticMessage($type, $message);
            }
        }

        return $html;
    }

    /**
     * @param string $type
     * @param string $message
     * @return string
     */
    public function staticMessage($type = null, $message)
    {
        return sprintf($this->messageFormat, $type == 'error' ? 'danger' : $type, $message);
    }
    
}
