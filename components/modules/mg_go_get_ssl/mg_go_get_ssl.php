<?php

class MgGoGetSsl extends Module
{

    /** @var array */
    private $events = [
        'Services.add'  => \MgGoGetSsl\Event\ServiceAdd::class,
        'Services.edit' => \MgGoGetSsl\Event\ServiceEdit::class,
//        'Appcontroller.preAction' => \MgGoGetSsl\Event\AppControllerPreAction::class,
    ];

    /**
     * MgGoGetSsl constructor
     */
    public function __construct()
    {
        require_once 'autoloader.php';

        Language::loadLang(self::class, null, dirname(__FILE__) . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR);

        $this->loadConfig(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.json');

        if (!defined('GoGetSSL_Module_DIR')) {
            define('GoGetSSL_Module_DIR', __DIR__);
        }

        if ((new \MgGoGetSsl\Service\BlestaService())->isBlesta36()) {
            $compontents = ['Input', 'Events'];
        } else {
            $compontents = ['Input', 'Emails', 'EmailGroups', 'Events'];
        }

        Loader::loadComponents($this, $compontents);

        foreach ($this->events as $event => $class) {
            $classObject = new $class;

            if (is_callable($callback = [$classObject, 'handle'])) {
                $this->Events->register($event, call_user_func($callback));
            }
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        return \MgGoGetSsl\Facade\Lang::translate('module_name');
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return \MgGoGetSsl\Facade\Config::configKey('module.version', '1.0.0');
    }

    /**
     * @return array
     */
    public function getAuthors()
    {
        return \MgGoGetSsl\Facade\Config::configKey('module.authors', []);
    }

    /**
     * @param string $service
     * @return string
     */
    public function getServiceName($service)
    {
        return $service;
    }

    /**
     * @return string
     */
    public function moduleRowName()
    {
        return \MgGoGetSsl\Facade\Config::configKey('module.row_name', '');
    }

    /**
     * @return string
     */
    public function moduleRowNamePlural()
    {
        return \MgGoGetSsl\Facade\Config::configKey('module.row_name_plural', 's');
    }

    /**
     * @return string
     */
    public function moduleGroupName()
    {
        return \MgGoGetSsl\Facade\Config::configKey('module.group_name', '');
    }

    /**
     * @return string
     */
    public function moduleRowMetaKey()
    {
        return \MgGoGetSsl\Facade\Config::configKey('module.row_meta_key', '');
    }

    /**
     * @param stdClass|null $vars
     * @return \MgGoGetSsl\Processor\ModuleFields
     */
    public function getPackageFields($vars = null)
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this, null, $vars))
            ->getPackageFields();
    }

    /**
     * @throws \Exception
     * @param \stdClass $module
     * @param array     $vars
     * @return string
     */
    public function manageModule($module, array &$vars)
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this, $module, $vars))
            ->manageModule();
    }

    /**
     * @throws \Exception
     * @param array $vars
     * @return string
     */
    public function manageAddRow(array &$vars)
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this, null, $vars))
            ->manageAddRow();
    }

    /**
     * @param \stdClass $moduleRow
     * @param array     $vars
     * @return string
     */
    public function manageEditRow($moduleRow, array &$vars)
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this, null, $moduleRow))
            ->manageEditRow();
    }

    /**
     * @throws \Exception
     * @param array $vars
     * @return array|bool
     */
    public function addModuleRow(array &$vars)
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this, null, $vars))
            ->addModuleRow();
    }

    /**
     * @throws \Exception
     * @param       $moduleRow
     * @param array $vars
     * @return array
     */
    public function editModuleRow($moduleRow, array &$vars)
    {
        return $this->addModuleRow($vars);
    }

    /**
     * @throws \Exception
     * @param \stdClass $package
     * @return array
     */
    public function getClientTabs($package)
    {
        return (new \MgGoGetSsl\Processor\ClientProcessor($this))
            ->getClientTabs($package);
    }

    /**
     * @throws Exception
     * @param \stdClass $package
     * @return array
     */
    public function getAdminTabs($package)
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this, null, null))
            ->getAdminTabs($package);
    }

    /**
     * @throws \Exception
     * @throws \MgGoGetSsl\Processor\GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @param array     $get
     * @param array     $post
     * @param array     $files
     * @return string
     */
    public function adminDetailsCert($package, $service, array $get = [], array $post = [], array $files = [])
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this))
            ->adminDetailsCertificate($package, $service);
    }

    /**
     * @throws \Exception
     * @throws \MgGoGetSsl\Processor\GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @param array     $get
     * @param array     $post
     * @param array     $files
     * @return string
     */
    public function adminContactDetailsCert($package, $service, array $get = [], array $post = [], array $files = [])
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this))
            ->clientContactDetailsCertificate($package, $service);
    }

    /**
     * @throws \Exception
     * @throws \MgGoGetSsl\Processor\GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @param array     $get
     * @param array     $post
     * @param array     $files
     * @return string
     */
    public function adminReissueCert($package, $service, array $get = [], array $post = [], array $files = [])
    {
        if (is_callable([$this, 'setMessage'])) {
            $this->setMessage('co za guano', 'z tej blesty');
        }

        return (new \MgGoGetSsl\Processor\AdminProcessor($this))
            ->clientReissueCertificate($package, $service);
    }

    /**
     * @throws \Exception
     * @throws \MgGoGetSsl\Processor\GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @param array     $get
     * @param array     $post
     * @param array     $files
     * @return string
     */
    public function clientGenerateCert($package, $service, array $get = [], array $post = [], array $files = [])
    {
        if (is_callable([$this, 'setMessage'])) {
            $this->setMessage('co za guano', 'z tej blesty');
        }

        return (new \MgGoGetSsl\Processor\ClientProcessor($this))
            ->clientGenerateCertificate($package, $service);
    }

    /**
     * @throws \Exception
     * @throws \MgGoGetSsl\Processor\GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @param array     $get
     * @param array     $post
     * @param array     $files
     * @return string
     */
    public function clientReissueCert($package, $service, array $get = [], array $post = [], array $files = [])
    {
        if (is_callable([$this, 'setMessage'])) {
            $this->setMessage('co za guano', 'z tej blesty');
        }

        return (new \MgGoGetSsl\Processor\ClientProcessor($this))
            ->clientReissueCertificate($package, $service);
    }

    /**
     * @throws \Exception
     * @throws \MgGoGetSsl\Processor\GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @param array     $get
     * @param array     $post
     * @param array     $files
     * @return string
     */
    public function clientRenewCert($package, $service, array $get = [], array $post = [], array $files = [])
    {
        if (is_callable([$this, 'setMessage'])) {
            $this->setMessage('co za guano', 'z tej blesty');
        }

        return (new \MgGoGetSsl\Processor\ClientProcessor($this))
            ->clientRenewCertificate($package, $service);
    }

    /**
     * @throws \Exception
     * @throws \MgGoGetSsl\Processor\GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @param array     $get
     * @param array     $post
     * @param array     $files
     * @return string
     */
    public function clientDetailsCert($package, $service, array $get = [], array $post = [], array $files = [])
    {
        return (new \MgGoGetSsl\Processor\ClientProcessor($this))
            ->clientDetailsCertificate($package, $service);
    }

    /**
     * @throws \Exception
     * @throws \MgGoGetSsl\Processor\GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @param array     $get
     * @param array     $post
     * @param array     $files
     * @return string
     */
    public function clientContactDetailsCert($package, $service, array $get = [], array $post = [], array $files = [])
    {
        return (new \MgGoGetSsl\Processor\ClientProcessor($this))
            ->clientContactDetailsCertificate($package, $service);
    }

    /**
     * @param \stdClass $service
     * @param \stdClass $package
     * @return string
     */
    public function getClientServiceInfo($service, $package)
    {

    }

    /**
     * @throws \Exception
     * @param \stdClass $service
     * @param \stdClass $package
     * @return string
     */
    public function getAdminServiceInfo($service, $package)
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this, null, null))
            ->adminServiceInfo($service, $package);
    }

    /**
     * @param \stdClass      $package
     * @param \stdClass      $service
     * @param \stdClass|null $parentPackage
     * @param \stdClass|null $parentService
     * @return string|null
     */
    public function cancelService($package, $service, $parentPackage = null, $parentService = null)
    {
        if (($row = $this->getModuleRow())) {
            return (new \MgGoGetSsl\Processor\AdminProcessor($this, null, null))
                ->cancelService($service, $package);
        }

        return null;
    }

    /**
     * @param \stdClass  $package
     * @param array|null $vars
     * @return array
     */
    public function editPackage($package, array $vars = null)
    {
        return (new \MgGoGetSsl\Processor\AdminProcessor($this,null, $vars))
            ->editPackageValidate($package);
    }

    /**
     * @return void
     */
    public function install()
    {
        (new \MgGoGetSsl\Service\GoGetSslService())
            ->installModuleCommand($this);
    }

    /**
     * @return void
     */
    public function uninstall($moduleId, $lastInstance)
    {
        (new \MgGoGetSsl\Service\GoGetSslService())
            ->uninstalModuleCommands();
    }

    /**
     * @return void
     */
    public function upgrade($currentVersion)
    {

    }

}
