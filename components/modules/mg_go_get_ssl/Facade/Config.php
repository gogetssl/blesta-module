<?php

namespace MgGoGetSsl\Facade;

use MgGoGetSsl\Application\Runtime;

final class Config
{

    private function __clone() {}
    private function __wakeup() {}
    private function __construct() {}

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function configKey($key, $default = null)
    {
        return Runtime::config()
            ->configKey($key, $default);
    }

}
