<?php

namespace MgGoGetSsl\Processor;

use MgGoGetSsl\Facade\Log;
use MgGoGetSsl\Facade\Lang;
use MgGoGetSsl\Facade\Config;
use MgGoGetSsl\API\GoGetSSLApi;
use MgGoGetSsl\Util\HttpHelper;
use MgGoGetSsl\Service\LogService;
use MgGoGetSsl\Facade\FlashMessage;
use MgGoGetSsl\Application\Runtime;
use MgGoGetSsl\Service\BlestaService;
use MgGoGetSsl\Service\GoGetSslService;

class ClientProcessor
{

    /** @var \Module */
    protected $module;

    /** @var  */
    protected $view;

    /** @var \stdClass|null */
    protected $service;

    /** @var \stdClass|null */
    protected $package;

    /**
     * ClientProcessor constructor
     *
     * @param \Module $module
     */
    public function __construct(\Module $module)
    {
        $this->module = $module;
    }

    /**
     * @throws \Exception
     * @param \stdClass $package
     * @return array
     */
    public function getClientTabs($package)
    {
        if ($package->module_id != (new GoGetSslService())
            ->getGoGetSslModuleId()
        ) {
            return [];
        }

        $tabs = [];

        $url = Runtime::request()->url(true);
        $matches = [];

        if (substr($url, -1, 1) != '/') {
            $url .= '/';
        }

        preg_match("/services\/manage\/(.*?)\//s", $url, $matches);

        if (!isset($matches[1]) || !is_numeric($matches[1])) {
            return [];
        }

        $serviceId = $matches[1];
        $service = (new BlestaService())
            ->getService($serviceId);

        if (!$service || $service->status != 'active') {
            return [];
        }

        $clientCertificate = (new GoGetSslService())
            ->getClientCertificateData($serviceId);

        if (empty($clientCertificate)) {
            $tabs['clientGenerateCert'] = [
                'name' => Lang::translate('generate_certificate'),
                'icon' => 'fa fa-cogs',
            ];
        } else {
            $orderId = $clientCertificate->order_id;
            //$orderStatus = $this->api->getOrderStatus($orderId);

            $tabs['clientDetailsCert'] = [
                'name' => Lang::translate('certificate_details'),
                'icon' => 'fa fa-file-text-o',
            ];
            $tabs['clientReissueCert'] = [
                'name' => Lang::translate('reissue_certificate'),
                'icon' => 'fa fa-refresh',
            ];
            $tabs['clientContactDetailsCert'] = [
                'name' => Lang::translate('contact_management_certificate'),
                'icon' => 'fa fa-user',
            ];
        }

        return $tabs;
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @return string
     */
    public function clientGenerateCertificate($package, $service)
    {
        $this->service = $service;
        $this->package = $package;

        $error = false;

        try {
            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);

            $certificateProcessor = (new CertificateProcessor($api, $package, $service, $config))
                ->processCertificateGenerate();

            $step = $certificateProcessor->getCurrentStep();
            $this->initView('generate_cert_step' . $step);

            foreach ($certificateProcessor->variables() as $key => $value) {
                $this->view->set($key, $value);
            }
        } catch (\RuntimeException $e) {
            $error = true;
            FlashMessage::error($e->getMessage());
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_GENERATE, $e->getTraceAsString());
        } catch (\Exception $e) {
            $error = true;
            FlashMessage::error(Lang::translate('general_error'));
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_GENERATE, $e->getTraceAsString());
        }

        if ($error) {
            return $this->errors();
        }

        $this->view->set('messages', FlashMessage::messages());
        return $this->view->fetch();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @return string
     */
    public function clientContactDetailsCertificate($package, $service)
    {
        $this->service = $service;
        $this->package = $package;

        $error = false;

        try {
            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);

            $certificateProcessor = (new CertificateProcessor($api, $package, $service, $config))
                ->processCertificateContactDetails();

            $this->initView('contact_details_cert');

            foreach ($certificateProcessor->variables() as $key => $value) {
                $this->view->set($key, $value);
            }
        } catch (\RuntimeException $e) {
            $error = true;
            FlashMessage::error($e->getMessage());
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_CONTACTS, $e->getTraceAsString());
        } catch (\Exception $e) {
            $error = true;
            FlashMessage::error(Lang::translate('general_error'));
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_CONTACTS, $e->getTraceAsString());
        }

        if ($error) {
            return $this->errors();
        }

        $this->view->set('messages', FlashMessage::messages());
        return $this->view->fetch();
    }
    
    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @return string
     */
    public function clientDetailsCertificate($package, $service)
    {
        $this->service = $service;
        $this->package = $package;

        $error = false;

        try {
            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);

            $certificateProcessor = (new CertificateProcessor($api, $package, $service, $config))
                ->processCertificateDetails();

            $this->initView('details_cert');

            foreach ($certificateProcessor->variables() as $key => $value) {
                $this->view->set($key, $value);
            }
        } catch (\RuntimeException $e) {
            $error = true;
            FlashMessage::error($e->getMessage());
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_DETAILS, $e->getTraceAsString());
        } catch (\Exception $e) {
            $error = true;
            FlashMessage::error(Lang::translate('general_error'));
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_DETAILS, $e->getTraceAsString());
        }

        if ($error) {
            return $this->errors();
        }

        $this->view->set('messages', FlashMessage::messages());
        return $this->view->fetch();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @return string
     */
    public function clientRenewCertificate($package, $service)
    {
        $this->service = $service;
        $this->package = $package;

        $error = false;

        try {
            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);

            $certificateProcessor = (new CertificateProcessor($api, $package, $service, $config))
                ->processCertificateRenew();

            $this->initView('renew_cert');

            foreach ($certificateProcessor->variables() as $key => $value) {
                $this->view->set($key, $value);
            }
        } catch (\RuntimeException $e) {
            $error = true;
            FlashMessage::error($e->getMessage());
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_RENEW, $e->getTraceAsString());
        } catch (\Exception $e) {
            $error = true;
            FlashMessage::error(Lang::translate('general_error'));
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_RENEW, $e->getTraceAsString());
        }

        if ($error) {
            HttpHelper::redirect($this->getClientUrl(''));
        }

        $this->view->set('messages', FlashMessage::messages());
        return $this->view->fetch();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param \stdClass $package
     * @param \stdClass $service
     * @return string
     */
    public function clientReissueCertificate($package, $service)
    {
        $this->service = $service;
        $this->package = $package;

        $error = false;
        
        try {
            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);

            $certificateProcessor = (new CertificateProcessor($api, $package, $service, $config))
                ->processCertificateReissue();

            $step = $certificateProcessor->getCurrentStep();
            $this->initView('reissue_cert_step' . $step);

            foreach ($certificateProcessor->variables() as $key => $value) {
                $this->view->set($key, $value);
            }
        } catch (\RuntimeException $e) {
            $error = true;
            FlashMessage::error($e->getMessage());
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_REISSUE, $e->getTraceAsString());
        } catch (\Exception $e) {
            $error = true;
            FlashMessage::error(Lang::translate('general_error'));
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_REISSUE, $e->getTraceAsString());
        }

        if ($error) {
            return $this->errors();
            HttpHelper::redirect($this->getClientUrl('clientDetailsCert'));
        }

        $this->view->set('messages', FlashMessage::messages());
        return $this->view->fetch();
    }

    /**
     * @param string      $method
     * @param string|null $action
     * @param array       $queryParams
     * @param array       $pathParams
     * @return string
     */
    public function getClientUrl($method, $action = null, array $queryParams = [], array $pathParams = [])
    {
        $url = sprintf('%sservices/manage/%s/%s',
            $this->module->base_uri, $this->service->id, $method
        );

        if (!empty($pathParams)) {
            $url .= '/' . implode('/', $pathParams);
        }

        $params = [];

        if ($action) {
            $params['action'] =  $action;
        }

        $params = array_merge($params, $queryParams);

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;

    }

    /**
     * @throws \RuntimeException
     * @return array
     */
    private function getModuleConfiguration()
    {
        $moduleRows = $this->module->getModuleRows();

        foreach ($moduleRows as $row) {
            if (isset($row->meta->api_username)) {
                $row->meta->base_uri = $this->module->base_uri;
                return $row->meta;
            }
        }

        throw new \RuntimeException(Lang::translate('credential_already_not_exists'));
    }

    /**
     * @throws \RuntimeException
     * @return array
     */
    private function getApiDetails()
    {
        $moduleRows = $this->module->getModuleRows();

        foreach ($moduleRows as $row) {
            if (isset($row->meta->api_username)) {
                return [
                    'username' => $row->meta->api_username,
                    'password' => $row->meta->api_password
                ];
            }
        }

        throw new \RuntimeException(Lang::translate('credential_already_not_exists'));
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param string $username
     * @param string $password
     * @return GoGetSSLApi
     */
    private function getAPI($username, $password)
    {
        return (new GoGetSSLApi($username, $password, Config::configKey('api.url')))
            ->auth();
    }

    /**
     * @param string $template
     * @param string $area
     */
    private function initView($template, $area = 'client')
    {
        $this->view = new \View($template, $area);
        $this->view->baseUri = $this->module->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . Config::configKey('module.system_name') . DS);

        \Loader::loadHelpers($this->view, ['Form', 'Html', 'Widget']);
    }

    /**
     * @return string
     */
    private function errors()
    {
        $this->initView('errors');
        $this->view->set('messages', FlashMessage::messages());

        return $this->view->fetch();

        $this->initView('details_cert');

        $url = $this->module->base_uri . $this->view->view_path;

        $assets = <<< EOT
            <script type='text/javascript' src='{$url}views/client/assets/js/scripts.js'></script>
            <link href='{$url}views/client/assets/css/style.css' rel='stylesheet' type='text/css'/>
EOT;

        return $assets . FlashMessage::renderMessages();
    }

}
