<?php

namespace MgGoGetSsl\Facade;

final class Settings
{

    private function __clone() {}
    private function __wakeup() {}
    private function __construct() {}

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function setting($key, $default = null)
    {
        return (new \MgGoGetSsl\Util\Settings())
            ->setting($key, $default);
    }

    /**
     * @return array
     */
    public static function settings()
    {
        return (new \MgGoGetSsl\Util\Settings())
            ->settings();
    }

}
