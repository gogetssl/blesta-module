<?php

namespace MgGoGetSsl\Util;

final class Config
{
    
    /**
     * @var array
     */
    private $config;
    
    private function __clone() {}
    private function __wakeup() {}

    /**
     * Config constructor
     *
     * @throws \Exception
     * @param Application $application
     */
    public function __construct()
    {
        $this->loadConfig();
    }
    
    /**
     * Load config file
     *
     * @throws \Exception
     * @return $this
     */
    private function loadConfig()
    {
        $configFile = sprintf('%s/resources/config/app.php', GoGetSSL_Module_DIR);
        
        if (!file_exists($configFile)) {
            $this->config = [];

            return $this;
        }
        
        $this->config = require $configFile;
        
        return $this;
    }

    /**
     * Get config by key
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function configKey($key, $default = null)
    {
        $keys = strpos($key, '.') !== false ? explode('.', $key) : [$key];

        $index = 0;
        $found = true;
        $currentConfig = $this->config;
        do {
            $key = $keys[$index++];
        
            if (is_array($currentConfig) && isset($currentConfig[$key])) {
                $currentConfig = $currentConfig[$key];
            } else {
                $found = false;
                break;
            }
        
        } while ($index < count($keys));
    
        return $found ? $currentConfig : $default;
    }
    
}
