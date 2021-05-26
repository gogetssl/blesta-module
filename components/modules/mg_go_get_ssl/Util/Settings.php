<?php

namespace MgGoGetSsl\Util;

/**
 * @property $SettingsCollection
 */
final class Settings
{

    const DEFAULT_CURRENCY = 'default_currency';
    
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
        \Loader::loadComponents($this, ['SettingsCollection']);
    }

    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function setting($key, $default = null)
    {
        $settings = $this->SettingsCollection->fetchSettings();

        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    /**
     * @return array
     */
    public function settings()
    {
        return $this->SettingsCollection->fetchSettings();
    }
    
}
