<?php

namespace MgGoGetSsl\Facade;

use MgGoGetSsl\Service\LogService;

final class Log
{

    private function __clone() {}
    private function __wakeup() {}
    private function __construct() {}

    /**
     * @param string      $type
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public static function log($type, $title, $namespace = null, $data = null, $function = null)
    {
        (new LogService())->log($type, $title, $namespace, $data, $function);
    }

    /**
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public static function logError($title, $namespace = null, $data = null, $function = null)
    {
        self::log(LogService::ERROR, $title, $namespace, $data, $function);
    }

    /**
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public static function logSuccess($title, $namespace = null, $data = null, $function = null)
    {
        self::log(LogService::SUCCESS, $title, $namespace, $data, $function);
    }

    /**
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public static function logInfo($title, $namespace = null, $data = null, $function = null)
    {
        self::log(LogService::INFO, $title, $namespace, $data, $function);
    }

    /**
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public static function logWarning($title, $namespace = null, $data = null, $function = null)
    {
        self::log(LogService::WARN, $title, $namespace, $data, $function);
    }

}
