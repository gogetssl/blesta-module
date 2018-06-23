<?php

namespace MgGoGetSsl\Application;

use MgGoGetSsl\Util\Config;

final class Runtime
{

    /**
     * @return Config
     */
    public static function config()
    {
        return new Config();
    }

    /**
     * @return bool
     */
    public static function isCommandLineInterface()
    {
        return self::sapiName() === 'cli';
    }

    /**
     * @return string
     */
    public static function sapiName()
    {
        return strtolower(php_sapi_name());
    }

    /**
     * @return bool
     */
    public static function isWindows()
    {
        return strpos(self::os(), 'win') !== false;
    }

    /**
     * @return bool
     */
    public static function isLinux()
    {
        return strpos(self::os(), 'linux') !== false;
    }

    /**
     * @return string
     */
    public static function os()
    {
        return strtolower(PHP_OS);
    }

    /**
     * @return int
     */
    public static function maxUploadFileSize()
    {
        return max((int) ini_get('post_max_size'), (int) ini_get('upload_max_filesize'));
    }

    /**
     * @throws \Exception
     * @return Request
     */
    public static function request()
    {
        return App::make(Request::class);
    }

}
