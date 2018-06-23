<?php

final class CustomerQuestionnaireAutoloader
{

    /** @var array */
    private static $paths;

    /** @var string */
    private static $modulePath;

    /** @var string */
    private static $baseNamespace = 'MgGoGetSsl';
    
    /** @var bool */
    private static $throwExceptions = false;

    /**
     * Init Autoloader
     */
    public static function init()
    {
        spl_autoload_register(sprintf('%s::loadClass', self::class));
    }

    /**
     * Load given class
     *
     * @throws \Exception
     * @param string $class
     * @return void
     */
    protected static function loadClass($class)
    {
        if (strpos($class, self::$baseNamespace) === false) {
            return;
        }

        if (($pos = strpos($class, self::$baseNamespace . '\\')) !== false) {
            $class = substr($class, $pos + strlen(self::$baseNamespace) + 1);
        }
        
        $relativeFilePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $class) . '.php';
        $path = __DIR__;
        $filePath = realpath($path . DIRECTORY_SEPARATOR . $relativeFilePath);
        
        if (file_exists($filePath)) {
            require_once $filePath;
            return;
        }

        if (self::$throwExceptions) {
            throw new \Exception(sprintf('Could not find %s class', $class));
        }
    }

}

CustomerQuestionnaireAutoloader::init();