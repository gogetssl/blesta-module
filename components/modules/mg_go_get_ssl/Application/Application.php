<?php

namespace MgGoGetSsl\Application;


final class Application
{

    /** @var bool */
    private static $initiated = false;

    /** @var $this */
    private static $self;

    /** @var array */
    private static $inputArgs = [];

    private function __clone() {}
    private function __wakeup() {}

    /**
     * Application constructor
     */
    private function __construct()
    {
        self::$initiated = true;
    }

    /**
     * Initiate the whole App
     *
     * @param array $inputArgs
     * @throws \Exception
     */
    public static function init(array $inputArgs = [])
    {

    }

}
