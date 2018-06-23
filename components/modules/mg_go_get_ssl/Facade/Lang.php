<?php

namespace MgGoGetSsl\Facade;

final class Lang
{

    private function __clone() {}
    private function __wakeup() {}
    private function __construct() {}

    /**
     * @return string
     */
    public static function current()
    {
        return 'en_us';
    }

    /**
     * @param string $key
     * @param bool   $prefixed
     * @return string
     */
    public static function translate($key, $prefixed = false)
    {
        if ($prefixed) {
            $key = sprintf('%s.%s', Config::configKey('module.namespace'), $key);
        }

        return \Language::_($key, true);
    }

}
