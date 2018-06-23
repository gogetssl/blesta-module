<?php

namespace MgGoGetSsl\Facade;

use MgGoGetSsl\Util\FlashMessage as FlassMessageUtil;

final class FlashMessage
{

    const SUCCESS = 'success';
    const ERROR = 'danger';
    const DANGER = 'danger';
    const INFO = 'info';
    const WARN = 'warning';

    private function __clone() {}
    private function __wakeup() {}
    private function __construct() {}

    /**
     * @param string $type
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public static function message($type, $message, $checkIfAlreadyExists = true)
    {
        (new FlassMessageUtil())->addMessage($type, $message, $checkIfAlreadyExists);
    }

    /**
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public static function success($message, $checkIfAlreadyExists = true)
    {
        (new FlassMessageUtil())->addSuccessMessage($message, $checkIfAlreadyExists);
    }

    /**
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public static function info($message, $checkIfAlreadyExists = true)
    {
        (new FlassMessageUtil())->addInfoMessage($message, $checkIfAlreadyExists);
    }

    /**
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public static function error($message, $checkIfAlreadyExists = true)
    {
        (new FlassMessageUtil())->addErrorMessage($message, $checkIfAlreadyExists);
    }

    /**
     * @param string $message
     * @param bool   $checkIfAlreadyExists
     */
    public static function warning($message, $checkIfAlreadyExists = true)
    {
        (new FlassMessageUtil())->addWarningMessage($message, $checkIfAlreadyExists);
    }

    /**
     * @param string|null $type
     * @param bool        $clear
     * @return array|false
     */
    public static function messages($type = null, $clear = true)
    {
        return (new FlassMessageUtil())->getMessages($type, $clear);
    }

    /**
     * @param string|null $type
     * @param bool        $clear
     * @return string
     */
    public static function renderMessages($type = null, $clear = true)
    {
        return (new FlassMessageUtil())->renderMessages($type, $clear);
    }

    /**
     * @param string $type
     * @param string $message
     * @return string
     */
    public static function staticMessage($type, $message)
    {
        return (new FlassMessageUtil())->staticMessage($type, $message);
    }

}
