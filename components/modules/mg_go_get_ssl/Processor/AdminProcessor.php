<?php

namespace MgGoGetSsl\Processor;

use MgGoGetSsl\Facade\Log;
use MgGoGetSsl\Facade\Lang;
use MgGoGetSsl\Facade\Config;
use MgGoGetSsl\Util\Inflector;
use MgGoGetSsl\API\GoGetSSLApi;
use MgGoGetSsl\Util\HttpHelper;
use MgGoGetSsl\Facade\Settings;
use MgGoGetSsl\Service\LogService;
use MgGoGetSsl\Application\Runtime;
use MgGoGetSsl\Facade\FlashMessage;
use MgGoGetSsl\Service\BlestaService;
use MgGoGetSsl\Service\PackageService;
use MgGoGetSsl\Service\CurrencyService;
use MgGoGetSsl\Service\GoGetSslService;
use MgGoGetSsl\Exception\GoGetSSLApiException;

class AdminProcessor
{

    /** @var  */
    protected $view;

    /** @var \Module */
    protected $module;

    /** @var \stdClass */
    protected $moduleData;

    /** @var mixed */
    protected $vars;

    /**
     * AdminProcessor constructor
     *
     * @param \Module        $module
     * @param \stdClass|null $moduleData
     * @param null $vars
     */
    public function __construct(\Module $module, \stdClass $moduleData = null, $vars = null)
    {
        $this->module = $module;
        $this->vars = $vars;

        if ($moduleData) {
            $this->moduleData = $moduleData;
            $this->module->setModule($moduleData);
        }
    }

    /**
     * @throws \Exception
     * @return string
     */
    public function manageAddRow()
    {
        if ($this->getAction() == 'test-connection') {
            $this->manageModuleTestConnection();
        }

        $this->initView('add-row', 'admin');
        $this->view->set('vars', (object) $this->vars);
        $this->view->set('countries', (new BlestaService())->countries());
        $this->view->set('moduleConfHref', $this->getAdminUrl('manage'));
        $this->view->set('testConnectionHref', $this->getAdminUrl('addrow', 'test-connection', [], [
            (new GoGetSslService())->getGoGetSslModuleId()
        ]));
        
        return $this->view->fetch();
    }

    /**
     * @return string
     */
    public function manageEditRow()
    {
        $this->initView('edit-row', 'admin');
        $this->view->set('vars', (object) $this->vars);
        $this->view->set('countries', (new BlestaService())->countries());
        $this->view->set('moduleConfHref', $this->getAdminUrl('manage'));
        $this->view->set('testConnectionHref', $this->getAdminUrl('manage', 'test-connection'));

        return $this->view->fetch();
    }

    /**
     * @throws \Exception
     * @return mixed
     */
    public function manageModule()
    {
        $action = $this->getAction();

        switch ($action) {
            case 'products-creator':
                $this->manageModuleProductsCreator();
                break;

            case 'products-configuration';
                $this->manageModuleProductsConfiguration();
                break;

            case 'change-package-status':
                $this->manageModuleChangePackageStatus();
                break;

            case 'test-connection':
                $this->manageModuleTestConnection();
                break;

            case 'ajax-action':
                $this->manageModuleAjaxActions();
                break;

            default:
                $this->manageModuleMainPage();
        }

        $this->view->set('moduleConfHref', $this->getAdminUrl('manage'));
        $this->view->set('messages', FlashMessage::messages());
        return $this->view->fetch();
    }

    /**
     * @param stdClass|null $vars
     * @return ModuleFields
     */
    public function getPackageFields()
    {
        \Loader::loadHelpers($this->module, ['Form', 'Html']);

        $row = null;
        $productsArray = [];
        $fields = new \ModuleFields();

        if (isset($this->vars->module_row) && $this->vars->module_row > 0) {
            $row = $this->module->getModuleRow($this->vars->module_row);
        } else {
            $rows = $this->module->getModuleRows();
            if (isset($rows[0])) {
                $row = $rows[0];
            }
            unset($rows);
        }

        if ($row && isset($row->meta->api_password)) {
            try {
                $api = $this->getApi($row->meta->api_username, $row->meta->api_password);
                $products = $api->getProducts();

                foreach ($products as $product) {
                    $productsArray[$product['id']] = $product['product'];
                }
            } catch (\Exception $e) {

            }
        }

        $fields->setField($fields->label(\MgGoGetSsl\Facade\Lang::translate('certificate_type'))
            ->attach($fields->fieldSelect(
                'meta[certificate_type]',
                $productsArray,
                $this->module->Html->ifSet($this->vars->meta['certificate_type']), [
                    'id' => 'MgGoGetSsl_certificate_type',
                ]
            ))
        );

        $fields->setField($fields->label(\MgGoGetSsl\Facade\Lang::translate('months'))
            ->attach($fields->fieldText(
                'meta[months]',
                $this->module->Html->ifSet($this->vars->meta['months']), [
                    'id' => 'MgGoGetSsl_months',
                ]
            ))
        );

        $fields->setField($fields->label(\MgGoGetSsl\Facade\Lang::translate('included_sans'))
            ->attach($fields->fieldText(
                'meta[included_sans]',
                $this->module->Html->ifSet($this->vars->meta['included_sans']), [
                    'id' => 'MgGoGetSsl_included_sans',
                ]
            ))
        );

        $fields->setField($fields->label(\MgGoGetSsl\Facade\Lang::translate('enable_sans'))
            ->attach($fields->fieldCheckbox(
                'meta[enable_sans]',
                1,
                isset($this->vars->meta['enable_sans']) && $this->vars->meta['enable_sans'], [
                    'id' => 'MgGoGetSsl_enable_sans',
                ]
            ))
        );

        return $fields;
    }

    /**
     * @throws \Exception
     * @return array|bool
     */
    public function addModuleRow()
    {
        $action = $this->getAction();

        if ($action == 'add-credentials') {
            $rows = $this->module->getModuleRows();

            if (count($rows) > 0) {
                foreach ($rows as $r) {
                    if (isset($r->meta->api_password)) {
                        $this->module->Input->setErrors([
                            'invalid_action' => [
                                'internal' => \Language::_('credential_already_exist', true)
                            ]
                        ]);

                        return false;
                    }
                }
            }
        }

        if ($action == 'add-credentials' || $action == 'edit-credentials') {
            $metaFields = [
                'api_username', 'api_password', 'use_admin_contact', 'tech_firstname', 'tech_lastname',
                'tech_organization', 'tech_addressline1', 'tech_phone', 'tech_title', 'tech_email', 'tech_city',
                'tech_country', 'tech_fax', 'tech_postalcode', 'tech_region'
            ];
            $encryptedFields = ['api_password'];
            
            $useAdminContact = Runtime::request()->post('use_admin_contact');
            
            if (!$useAdminContact) {
                foreach ($metaFields as $metaField) {
                    $metaFieldValue = Runtime::request()->post($metaField);

                    if (empty($metaFieldValue) && !in_array($metaField, ['api_username', 'api_password', 'use_admin_contact'])) {
                        FlashMessage::warning(Lang::translate(sprintf('field_%s_empty', $metaField)));
                    }
                }
            }

            $this->module->Input->setRules($this->getConfigurationRules($this->vars));

            if ($this->module->Input->validates($this->vars)) {
                $meta = [];
                $meta[] = [
                    'key'       => 'api_config_name',
                    'value'     => 'default',
                    'encrypted' => 0,
                ];

                foreach ($this->vars as $key => $value) {
                    if (in_array($key, $metaFields)) {
                        $meta[] = [
                            'key'       => $key,
                            'value'     => $value,
                            'encrypted' => (int) in_array($key, $encryptedFields),
                        ];
                    }
                }

                return $meta;
            }
        }
    }

    /**
     * @throws \Exception
     * @param \stdClass $package
     * @return array
     */
    public function getAdminTabs($package)
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

        preg_match("/clients\/editservice\/(.*?)\/(.*?)\//s", $url, $matches);
        
        if (!isset($matches[2]) || !is_numeric($matches[2])) {
            return [];
        }

        $serviceId = $matches[2];
        $service = (new BlestaService())
            ->getService($serviceId);

        if (!$service || $service->status != 'active') {
            return [];
        }

        $clientCertificate = (new GoGetSslService())
            ->getClientCertificateData($serviceId, $matches[1]);

        if (!empty($clientCertificate)) {
            $orderId = $clientCertificate->order_id;
            //$orderStatus = $this->api->getOrderStatus($orderId);

            $tabs['adminDetailsCert'] = Lang::translate('certificate_details');
            $tabs['adminReissueCert'] = Lang::translate('reissue_certificate');
            $tabs['adminContactDetailsCert'] = Lang::translate('contact_management_certificate');
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
    public function adminDetailsCertificate($package, $service)
    {
        $error = false;

        $url = Runtime::request()->url(true);
        $matches = [];

        if (substr($url, -1, 1) != '/') {
            $url .= '/';
        }

        preg_match("/clients\/servicetab\/(.*?)\/(.*?)\//s", $url, $matches);
        
        try {
            if (!isset($matches[1]) || empty($matches[1])) {
                throw new \RuntimeException(Lang::translate('client_not_found'));
            }

            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);
            
            $certificateProcessor = (new CertificateProcessor($api, $package, $service, $config, $matches[1]))
                ->processCertificateDetails();

            $this->initView('admin-details-cert');

            foreach ($certificateProcessor->variables() as $key => $value) {
                $this->view->set($key, $value);
            }

            $reissueHref = sprintf('%s/clients/servicetab/%s/%s/adminReissueCert',
                Inflector::trimLastChar($this->module->base_uri, '/'), $matches[1], $service->id
            );

            $this->view->set('reissueHref', $reissueHref);
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
    public function clientContactDetailsCertificate($package, $service)
    {
        $error = false;

        $url = Runtime::request()->url(true);
        $matches = [];

        if (substr($url, -1, 1) != '/') {
            $url .= '/';
        }

        preg_match("/clients\/servicetab\/(.*?)\/(.*?)\//s", $url, $matches);

        try {
            if (!isset($matches[1]) || empty($matches[1])) {
                throw new \RuntimeException(Lang::translate('client_not_found'));
            }

            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);

            $certificateProcessor = (new CertificateProcessor($api, $package, $service, $config, $matches[1]))
                ->processCertificateContactDetails();

            $this->initView('admin-contact-details-cert');

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
    public function clientReissueCertificate($package, $service)
    {
        $error = false;

        $url = Runtime::request()->url(true);
        $matches = [];

        if (substr($url, -1, 1) != '/') {
            $url .= '/';
        }

        preg_match("/clients\/servicetab\/(.*?)\/(.*?)\//s", $url, $matches);

        try {
            if (!isset($matches[1]) || empty($matches[1])) {
                throw new \RuntimeException(Lang::translate('client_not_found'));
            }

            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);

            $certificateProcessor = (new CertificateProcessor($api, $package, $service, $config, $matches[1]))
                ->processCertificateReissue();

            $step = $certificateProcessor->getCurrentStep();
            $this->initView('admin-reissue-cert-step' . $step);

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
        }

        $this->view->set('messages', FlashMessage::messages());
        return $this->view->fetch();
    }

    /**
     * @throws \Exception
     * @param \stdClass $service
     * @param \stdClass $package
     * @return string
     */
    public function adminServiceInfo($service, $package)
    {
        $this->initView('service-info', 'admin');

        $module = (new GoGetSslService())
            ->getGoGetSslModule();

        if ($module && ($package->module_id != $module->id)) {
            return '';
        }
        
        foreach ($this->module->getModuleRows() as $row) {
            if (isset($row->meta->api_username)) {
                $apiCredentials = [
                    'username' => $row->meta->api_username,
                    'password' => $row->meta->api_password,
                ];
                break;
            }
        }

        if (!isset($apiCredentials)) {
            return '';
        }

        $url = Runtime::request()->url(true);
        $matches = [];

        if (substr($url, -1, 1) != '/') {
            $url .= '/';
        }

        preg_match("/clients\/serviceinfo\/(.*?)\/(.*?)\//s", $url, $matches);

        if (!isset($matches[1]) || !is_numeric($matches[1]) || !$clientCertificateData = (new GoGetSslService())
            ->getClientCertificateData($service->id, $matches[1])
        ) {
            FlashMessage::error(Lang::translate('client_cert_data_not_available'));
            return $this->errors();
        }

        try {
            $api = $this->getApi($apiCredentials['username'], $apiCredentials['password']);
            
            $goGetSslProduct = (object) $api->getProduct($package->meta->certificate_type);
            $orderId = $clientCertificateData->order_id;
            $orderStatus = $api->getOrderStatus($orderId);

            $brand = $goGetSslProduct->brand;
            $brandsWithOnlyEmailValidation = Config::configKey('brands_with_only_email_validation');

            $methods = [
                'EMAIL' => 'EMAIL',
            ];

            if (!in_array($brand, $brandsWithOnlyEmailValidation)) {
                $methods['HTTP'] = 'HTTP';
                $methods['HTTPS'] = 'HTTPS';
                $methods['DNS'] = 'DNS';
            }

            $domainsData = [];
            $domains[] = $orderStatus['domain'];
//            $emails = ['admin', 'administrator', 'hostmaster', 'postmaster', 'webmaster'];

            if (isset($orderStatus['san']) && !empty($orderStatus['san'])) {
                foreach ($orderStatus['san'] as $san) {
                    $domains[] = $san['san_name'];
                }
            }

            foreach ($domains as $domain) {
                $emailsAssoc = [];
                $emailsArray = $api->getDomainEmails($domain);

//                $emailsArray = array_map(function ($item) use ($domain) {
//                    return sprintf('%s@%s', $item, $domain);
//                }, $emails);

                foreach ($emailsArray as $email) {
                    $emailsAssoc[$email] = $email;
                }

                $domainsData[] = [
                    'domain' => $domain,
                    'emails' => $emailsAssoc,
                ];
            }
            
            $adminUrl = $this->getAdminUrl('manage', 'ajax-action', [], [$package->module_id]);

            $url = Runtime::request()->url(true);
            $matches = [];

            if (substr($url, -1, 1) != '/') {
                $url .= '/';
            }

            preg_match("/clients\/serviceinfo\/(.*?)\/(.*?)\//s", $url, $matches);
            
            if (isset($matches[1]) && is_numeric($matches[1])) {
                $reissueCertUrl = sprintf('%s/clients/servicetab/%s/%s/adminReissueCert',
                    Inflector::trimLastChar($this->module->base_uri, '/'), $matches[1], $service->id
                );

                $this->view->set('reissueCertUrl', $reissueCertUrl);
            }

            $this->view->set('methods', $methods);
            $this->view->set('adminUrl', $adminUrl);
            $this->view->set('domains', $domainsData);
            $this->view->set('serviceId', $service->id);
            $this->view->set('orderData', $orderStatus);
            $this->view->set('isBlesta36', (new BlestaService())->isBlesta36());
        } catch (GoGetSSLApiException $e) {
            FlashMessage::error($e->getMessage());
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_DETAILS, $e->getTraceAsString());
        } catch (\Exception $e) {
            FlashMessage::error(Lang::translate('general_error'));
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_DETAILS, $e->getTraceAsString());
        }

        $this->view->set('messages', FlashMessage::messages());
        return $this->view->fetch();
    }

    /**
     * @param \stdClass $service
     * @param \stdClass $package
     * @return string|void
     */
    public function cancelService($service, $package)
    {
        $error = false;

        try {
            $config = $this->getModuleConfiguration();
            $api = $this->getAPI($config->api_username, $config->api_password);
            
            if ($clientCertificate = (new GoGetSslService())
                ->getClientCertificateData($service->id, $service->client_id)
            ) {
                $api->cancelOrder($clientCertificate->order_id, 'Order canceled for non-payment');
            }
        } catch (\RuntimeException $e) {
            $error = true;
            FlashMessage::error($e->getMessage());
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_CANCEL, $e->getTraceAsString());
        } catch (\Exception $e) {
            $error = true;
            FlashMessage::error(Lang::translate('general_error'));
            Log::logError($e->getMessage(), LogService::NAMESPACE_CERT_CANCEL, $e->getTraceAsString());
        }

        if ($error) {
            return $this->errors();
        }
    }

    /**
     * @param \stdClass $package
     * @return array
     */
    public function editPackageValidate($package)
    {
        $vars = (array) $this->vars;
        $moduleId = $vars['module_id'];
        
        if ((new GoGetSslService())->getGoGetSslModuleId() == $moduleId
            && (!isset($vars['meta']['certificate_type']) || empty($vars['meta']['certificate_type']))
        ) {
//            $this->module->Input->setErrors([
//                'api' => [
//                    'response' => Lang::translate('empty_module_configuration')
//                ]
//            ]);
            $vars['meta'] = (array) $package->meta;
        }
        
        $meta = [];
        foreach (isset($vars['meta']) ? $vars['meta'] : [] as $key => $value) {
            $meta[] = [
                'key'       => $key,
                'value'     => $value,
                'encrypted' => 0,
            ];
        }

        return $meta;
    }

    /**
     * @throws \Exception
     */
    private function manageModuleAjaxActions()
    {
        $act = Runtime::request()->get('act');
        $serviceId = Runtime::request()->get('service_id');

        foreach ($this->module->getModuleRows() as $row) {
            if (isset($row->meta->api_username)) {
                $apiCredentials = [
                    'username' => $row->meta->api_username,
                    'password' => $row->meta->api_password,
                ];
                break;
            }
        }

        if (!isset($apiCredentials)) {
            echo json_encode([
                'status'  => 'error',
                'message' => FlashMessage::staticMessage('error', 'api_credentials_invalid'),
                'refresh' => 0,
            ]);
            die;
        }

        if (!$clientCertificateData = (new GoGetSslService())
            ->getClientCertificateData($serviceId)
        ) {
            echo json_encode([
                'status'  => 'error',
                'message' => FlashMessage::staticMessage('error', 'client_cert_data_not_available'),
                'refresh' => 0,
            ]);
            die;
        }

        $refresh = 0;
        $status = 'success';
        $orderId = $clientCertificateData->order_id;

        switch ($act) {
            case 'resend_validation_email':
                try {
                    $api = $this->getApi($apiCredentials['username'], $apiCredentials['password']);
                    $api->resendValidationEmail($orderId);
                    $message = Lang::translate('validation_email_sent');
                } catch (GoGetSSLApiException $e) {
                    $status = 'error';
                    $message = $e->getMessage();
                } catch (\Exception $e) {
                    $status = 'error';
                    $message = Lang::translate('general_error');
                }
                break;

            case 'revalidate':
                $post = Runtime::request()->post();
                $domains = isset($post['domains']) ? $post['domains'] : [];

                try {
                    $api = $this->getApi($apiCredentials['username'], $apiCredentials['password']);

                    foreach ($domains as $domain) {
                        $api->changeValidationData($orderId, [
                            'new_method' => $domain['method'] == 'EMAIL' ? $domain['email'] : strtolower($domain['method']),
                            'domain'     => $domain['domain'],
                        ]);
                    }
                    $refresh = 1;
                    $message = Lang::translate('revalidated');
                } catch (GoGetSSLApiException $e) {
                    $status = 'error';
                    $message = $e->getMessage();
                } catch (\Exception $e) {
                    $status = 'error';
                    $message = Lang::translate('general_error');
                }
                break;

            case 'change_validation_email':
                $post = Runtime::request()->post();

                try {
                    $api = $this->getApi($apiCredentials['username'], $apiCredentials['password']);
                    $api->changeValidationEmail($orderId, isset($post['email']) ? $post['email'] : '');
                    $refresh = 1;
                    $message = Lang::translate('validation_email_changed');
                } catch (GoGetSSLApiException $e) {
                    $status = 'error';
                    $message = $e->getMessage();
                } catch (\Exception $e) {
                    $status = 'error';
                    $message = Lang::translate('general_error');
                }
                break;
        }

        echo json_encode([
            'status'  => $status,
            'message' => FlashMessage::staticMessage($status, $message),
            'refresh' => $refresh,
        ]);
        die;
    }

    /**
     * @return void
     */
    private function manageModuleProductsCreator()
    {
        $post = $this->vars;
        $currencies = (new CurrencyService())->getCurrenciesAsAssoc();
        $defaultCurrency = Settings::setting(\MgGoGetSsl\Util\Settings::DEFAULT_CURRENCY, 'USD');

        if (HttpHelper::isPostRequest() && $post['post_action'] == 'single') {
            $this->saveSinglePackage();
        } else if (HttpHelper::isPostRequest() && $post['post_action'] == 'multiple') {
            $this->saveMultiplePackage();
        }

        $this->initView('products-creator', 'admin');

        foreach ($this->moduleData->rows as $row) {
            if (isset($row->meta->api_username)) {
                $apiCredentials = [
                    'username' => $row->meta->api_username,
                    'password' => $row->meta->api_password,
                ];
                break;
            }
        }

        if (!isset($apiCredentials)) {
            HttpHelper::redirect($this->getAdminUrl('manage'));
            exit;
        }

        try {
            $productsArray = [];
            $api = $this->getApi($apiCredentials['username'], $apiCredentials['password']);
            $products = $api->getProducts();

            foreach ($products as $product) {
                $productsArray[$product['id']] = $product['product'];
            }

            $this->view->set('products', $productsArray);
        } catch (\Exception $e) {
            Log::logError($e->getMessage(), LogService::NAMESPACE_API, $e->getTraceAsString());
            HttpHelper::redirect($this->getAdminUrl('manage'));
            exit;
        }
        
        $packageGroups = (new PackageService())
            ->getPackageGroupsAsAssoc();

        $this->view->set('currencies', $currencies);
        $this->view->set('defaultCurrency', $defaultCurrency);
        $this->view->set('packageGroups', $packageGroups);
        $this->view->set('pricingPeriods', [
            'recurring' => Lang::translate('recurring'),
            'one_time'  => Lang::translate('one_time'),
            'free'      => Lang::translate('free'),
        ]);
    }

    /**
     * @throws \Exception
     */
    private function manageModuleTestConnection()
    {
        $status = 'success';
        $post = Runtime::request()->post();

        $username = isset($post['username']) ? $post['username'] : null;
        $password = isset($post['password']) ? $post['password'] : null;

        try {
            $this->getAPI($username, $password);
            $message = Lang::translate('connection_successful');
        } catch (\Exception $e) {
            $status = 'error';
            $message = Lang::translate('could_not_connect');
        }

        echo json_encode([
            'message' => FlashMessage::staticMessage($status, $message),
        ]);
        exit;
    }

    /**
     * @return void
     */
    private function manageModuleChangePackageStatus()
    {
        if ($packageId = filter_input(INPUT_GET, 'package_id', FILTER_SANITIZE_NUMBER_INT)) {
            $message = '';

            try {
                $package = (new PackageService())
                    ->getPackage($packageId);
                
                $packageStatus = $package->status;

                if ($packageStatus != PackageService::PACKAGE_STATUS_ACTIVE) {
                    $packageStatus = PackageService::PACKAGE_STATUS_ACTIVE;
                } else {
                    $packageStatus = PackageService::PACKAGE_STATUS_INACTIVE;
                }

                (new PackageService())
                    ->updatePackage($package, [
                        'status' => $packageStatus
                    ]);

                $status = 'success';
                $packageStatusText = Lang::translate(sprintf('status_%s', $packageStatus));
            } catch (\Exception $e) {
                $status = 'error';
                $message = Lang::translate('general_error');
                Log::logError($e->getMessage(), LogService::NAMESPACE_API, $e->getTraceAsString());
            }

            echo json_encode([
                'status'              => $status,
                'message'             => $message,
                'package_status'      => isset($packageStatus) ? $packageStatus : null,
                'package_status_text' => isset($packageStatusText) ? $packageStatusText : null
            ]);
            exit;
        }
    }

    /**
     * @throws \Exception
     */
    private function manageModuleProductsConfiguration()
    {
        $post = $this->vars;
        $currencies = (new CurrencyService())->getCurrenciesAsAssoc();

        if (isset($post['save_single_product']) && isset($post['package'][$post['save_single_product']])) {
            $this->saveSinglePackage($post['package'][$post['save_single_product']],$post['save_single_product']);
        }
        
        $this->initView('products-configuration', 'admin');

        foreach ($this->moduleData->rows as $row) {
            if (isset($row->meta->api_username)) {
                $apiCredentials = [
                    'username' => $row->meta->api_username,
                    'password' => $row->meta->api_password,
                ];
                $moduleRowId = $row->id;

                break;
            }
        }

        if (!isset($apiCredentials)) {
            HttpHelper::redirect($this->getAdminUrl('manage'));
            exit;
        }

        try {
            $packages = (new PackageService())
                ->getPackages($this->moduleData->id);

            $productsArray = [];
            $api = $this->getApi($apiCredentials['username'], $apiCredentials['password']);
            $products = $api->getProducts();

            foreach ($products as $product) {
                $productsArray[$product['id']] = $product['product'];
            }
        } catch (\Exception $e) {
            Log::logError($e->getMessage(), LogService::NAMESPACE_API, $e->getTraceAsString());
            HttpHelper::redirect($this->getAdminUrl('manage'));
            exit;
        }
        
        $packageGroups = (new PackageService())
            ->getPackageGroupsAsAssoc();
        
        foreach ($packages as &$package) {
            $months = null;
            $enableSans = null;
            $includedSans = null;
            $goGetSslProductId = null;

            foreach ($package->meta as $meta) {
                switch ($meta->key) {
                    case 'certificate_type':
                        $goGetSslProductId = $meta->value;
                        break;

                    case 'enable_sans':
                        $enableSans = $meta->value;
                        break;

                    case 'included_sans':
                        $includedSans = $meta->value;
                        break;

                    case 'months':
                        $months = $meta->value;
                        break;
                }
            }

            $package->group_ids = [];
            foreach ($package->groups as $group) {
                $package->group_ids[] = $group->id;
            }
            
            $pricingByCurrencyAndPeriod = [];
            foreach ($package->pricing as $pricing) {
                $period = $pricing->period == 'onetime' ? 'month' : $pricing->period;
                $pricingByCurrencyAndPeriod[$pricing->currency][$period][$pricing->term] = (array) $pricing;
            }

            $package->months = $months;
            $package->enable_sans = $enableSans;
            $package->included_sans = $includedSans;
            $package->go_get_ssl_product = $goGetSslProductId;
            $package->pricing_by_currency = $pricingByCurrencyAndPeriod;
        }

        $limit = 10;
        $count = count($packages);
        $page = Runtime::request()->get('p');
        $page = $page ? $page : 1;
        $offset = $page * $limit - $limit;
        $pages = ceil($count / $limit);

        if ($page > $pages) {
            $page = $pages;
            $offset = $page * $limit - $limit;
        }

        $packages = array_slice($packages,$offset, $limit);
        $pagination = $this->pagination($pages, $page, false, true, [
            'range' => 4
        ]);

        $currentUrl = Runtime::request()->url();
        if (($pos = strpos($currentUrl, '&p=')) !== false) {
            $currentUrl = substr($currentUrl, 0, $pos);
        }
        
        $this->view->set('page', $page);
        $this->view->set('pages', $pages);
        $this->view->set('packages', $packages);
        $this->view->set('currentUrl', $currentUrl);
        $this->view->set('currencies', $currencies);
        $this->view->set('products', $productsArray);
        $this->view->set('packageGroups', $packageGroups);
        $this->view->set('last', isset($pagination['last']) ? $pagination['last'] : null);
        $this->view->set('first', isset($pagination['first']) ? $pagination['first'] : null);
        $this->view->set('inRange', isset($pagination['inRange']) ? $pagination['inRange'] : null);
        $this->view->set('changeStatusHref', $this->getAdminUrl('manage', 'change-package-status'));
        $this->view->set('pricingPeriods', [
            'recurring' => Lang::translate('recurring'),
            'one_time'  => Lang::translate('one_time'),
            'free'      => Lang::translate('free'),
        ]);
        $this->view->set('statuses', [
            PackageService::PACKAGE_STATUS_ACTIVE => Lang::translate('status_active'),
            PackageService::PACKAGE_STATUS_INACTIVE => Lang::translate('status_inactive'),
            PackageService::PACKAGE_STATUS_RESTRICTED => Lang::translate('status_restricted'),
        ]);
    }

    /**
     * @param int   $pages
     * @param int   $page
     * @param bool  $nextPrev
     * @param bool  $firstLast
     * @param array $config
     * @return array
     */
    private function pagination($pages, $page, $nextPrev = false, $firstLast = false, array $config = [])
    {
        $data = [];

        if ($nextPrev) {
            $next = $page;
            $prev = 1;

            if ($page + 1 <= $pages) {
                $next = $page + 1;
            }
            if ($page - 1 > 0) {
                $prev = $page - 1;
            }
        }

        if ($firstLast) {
            $first = 1;
            $last = $pages;
        }

        if (isset($config['range'])) {
            $range = $config['range'];
            $inRange = [];

            if ($page - $range < 1) {
                $rangeStart = 1;
            } else {
                $rangeStart = $page - $range;
            }

            if ($page + $range > $pages) {
                $rangeEnd = $pages;
            } else {
                $rangeEnd = $page + $range;
            }

            for ($x = 1 ; $x <= $pages ; $x++) {
                if ($x >= $rangeStart && $x <= $rangeEnd) {
                    $inRange[] = $x;
                }
            }

            $data['inRange'] = $inRange;
        }

        if ($pages <= 1) {
            return [
                'page' => 1,
            ];
        }

        $data['pages'] = $pages;
        $data['page'] = $page;
        if ($nextPrev) {
            $data['prev'] = $page != $prev ? $prev : null;
            $data['next'] = $page != $next ? $next : null;
        }
        if ($firstLast) {
            $data['first'] = $first;
            $data['last'] = $last;
        }

        return $data;
    }

    /**
     * @return void
     */
    private function saveMultiplePackage()
    {
        $post = $this->vars;

        $packageGroups = isset($post['package_groups']) ? $post['package_groups'] : [];

        try {
            foreach ($this->moduleData->rows as $row) {
                if (isset($row->meta->api_username)) {
                    $apiCredentials = [
                        'username' => $row->meta->api_username,
                        'password' => $row->meta->api_password
                    ];

                    $moduleRowId = $row->id;
                    break;
                }
            }

            if (!isset($apiCredentials)) {
                throw new \RuntimeException(Lang::translate('no_api_credentials'));
            }

            $api = $this->getAPI($apiCredentials['username'], $apiCredentials['password']);
            $products = $api->getProducts();

            foreach ($products as $product) {
                $periods = isset($product['prices']['vendor']) ? $product['prices']['vendor'] : [];

                $months = 12;
                foreach ($periods as $period => $price) {
                    $months = $period;
                    break;
                }

                $packageMeta = [[
                    'key'   => 'certificate_type',
                    'value' => $product['id']
                ], [
                    'key'   => 'included_sans',
                    'value' => 0
                ], [
                    'key'   => 'enable_sans',
                    'value' => (int) $product['san_enabled']
                ], [
                    'key'   => 'months',
                    'value' => $months
                ]];

                $packagePricing = [];
                $currencies = (new CurrencyService())->getCurrenciesAsAssoc();
                $isFree = stripos($product['product'], 'trial') !== false;

                foreach ($currencies as $currency) {
                    $packagePricing[] = [
                        'term'     => 1,
                        'period'   => 'month',
                        'currency' => $currency
                    ];
                    $packagePricing[] = [
                        'term'     => 1,
                        'period'   => 'year',
                        'currency' => $currency
                    ];
                    $packagePricing[] = [
                        'term'     => 2,
                        'period'   => 'year',
                        'currency' => $currency
                    ];
                }

                $packageData = [
                    'name'       => $product['product'],
                    'module_id'  => $this->moduleData->id,
                    'module_row' => isset($moduleRowId) ? $moduleRowId : 0,
                    'pricing'    => $packagePricing,
                    'meta'       => $packageMeta,
                    'groups'     => $packageGroups
                ];

                (new PackageService())
                    ->savePackage($packageData);
            }

            FlashMessage::success(Lang::translate('packages_generated'));
        } catch (\RuntimeException $e) {
            Log::logError($e->getMessage(), LogService::NAMESPACE_PACKAGES, $e->getTraceAsString());
            FlashMessage::error($e->getMessage());
        } catch (GoGetSSLApiException $e) {
            Log::logError($e->getMessage(), LogService::NAMESPACE_API, $e->getTraceAsString());
            FlashMessage::error($e->getMessage());
        } catch (\Exception $e) {
            Log::logError($e->getMessage(), LogService::NAMESPACE_PACKAGES, $e->getTraceAsString());
            FlashMessage::error(Lang::translate('general_error'));
        }

        HttpHelper::redirect($this->getAdminUrl('manage', 'products-creator'));
    }

    /**
     * @param array|null $post
     * @param int|null   $packageId
     * @return void
     */
    private function saveSinglePackage(array $post = null, $packageId = null)
    {
        $invalid = false;
        $post = !empty($post) ? $post : $this->vars;

        if (empty($productName = (isset($post['product_name']) ? $post['product_name'] : null))) {
            FlashMessage::error(Lang::translate('product_name_empty'));
            $invalid = true;
        }
        if (empty($packageId) && empty($goGetSslProduct = (isset($post['go_getssl_product']) ? $post['go_getssl_product'] : null))) {
            FlashMessage::error(Lang::translate('go_getssl_product_empty'));
            $invalid = true;
        }
        if (empty($payType = (isset($post['pay_type']) ? $post['pay_type'] : null))) {
            FlashMessage::error(Lang::translate('pay_type_empty'));
            $invalid = true;
        }

        $pricingData = $post['pricing'];
        $inlucedSans = $post['included_sans'];
        $months = isset($post['months']) ? $post['months'] : 12;
        $enableSans = isset($post['enable_sans']) ? (int) $post['enable_sans'] : 0;
        $packageGroups = isset($post['package_groups']) ? $post['package_groups'] : [];

        foreach ($this->moduleData->rows as $row) {
            if (isset($row->meta->api_username)) {
                $moduleRowId = $row->id;
                break;
            }
        }

        if (!$invalid) {
            $packageMeta = [[
                'key'   => 'included_sans',
                'value' => (int) $inlucedSans
            ], [
                'key'   => 'enable_sans',
                'value' => $enableSans
            ], [
                'key'   => 'months',
                'value' => $months
            ]];

            if (isset($goGetSslProduct)) {
                $packageMeta[] = [
                    'key'   => 'certificate_type',
                    'value' => $goGetSslProduct
                ];
            }

            $packagePricing = [];
            $isFree = $payType == 'free';

            foreach ($pricingData as $currency => $pricingInCurrency) {
                foreach ($pricingInCurrency as $billingCycle => $pricing) {
                    if (!$isFree && (!isset($pricing['enable']) || !$pricing['enable'])) {
                        continue;
                    }

                    switch ($billingCycle) {
                        case 'monthly':
                            $term = 1;
                            $period = $payType == 'one_time' ? 'onetime' : 'month';
                            break;

                        case 'annually':
                            $term = 1;
                            $period = 'year';
                            break;

                        case 'biennially':
                            $term = 2;
                            $period = 'year';
                            break;
                    }

                    $packagePricing[] = [
                        'term'      => $term,
                        'period'    => $period,
                        'price'     => $isFree ? 0.0 : $pricing['price'],
                        'setup_fee' => $isFree ? 0.0 : $pricing['setup_fee'],
                        'currency'  => $currency
                    ];

                    if ($payType == 'one_time') {
                        break;
                    }
                }
            }

            $packageData = [
                'name'       => $productName,
                'module_id'  => $this->moduleData->id,
                'module_row' => isset($moduleRowId) ? $moduleRowId : 0,
                'pricing'    => $packagePricing,
                'meta'       => $packageMeta,
                'groups'     => $packageGroups
            ];

            try {
                if (empty($packageId)) {
                    (new PackageService())
                        ->savePackage($packageData);
                } else {
                    (new PackageService())
                        ->updatePackage($packageId, $packageData);

                    if ($isFree) {
                        (new PackageService())
                            ->removePackagePricing($packageId);
                    }
                }

                FlashMessage::success(Lang::translate(!empty($packageId) ? 'package_updated' : 'package_created'));
            } catch (\RuntimeException $e) {
                FlashMessage::error($e->getMessage());
                Log::logError($e->getMessage(), LogService::NAMESPACE_PACKAGES, $e->getTraceAsString());
            } catch (\Exception $e) {
                FlashMessage::error(Lang::translate('general_error'));
                Log::logError($e->getMessage(), LogService::NAMESPACE_PACKAGES, $e->getTraceAsString());
            }
        }

        if (!empty($packageId)) {
            HttpHelper::redirect($this->getAdminUrl('manage', 'products-configuration', [], [$this->moduleData->id]));
        }

        HttpHelper::redirect($this->getAdminUrl('manage', 'products-creator'));
    }

    /**
     * @return void
     */
    private function manageModuleMainPage()
    {
        $this->initView('manage', 'admin');
        $credentialsAdded = false;

        foreach ($this->moduleData->rows as $row) {
            if (isset($row->meta->api_username)) {
                $this->view->set('linkButtons', [
                    [
                        'name'       => Lang::translate('products_configuration'),
                        'attributes' => [
                            'href' => [
                                'href' => $this->getAdminUrl('manage', 'products-configuration'),
                            ]
                        ],
                    ],
                    [
                        'name'       => Lang::translate('products_creator'),
                        'attributes' => [
                            'href' => [
                                'href' => $this->getAdminUrl('manage', 'products-creator'),
                            ]
                        ],
                    ],
                    [
                        'name'       => Lang::translate('edit_configuration'),
                        'attributes' => [
                            'href' => [
                                'href' => $this->getAdminUrl('editrow', 'edit-credentials', [], [$row->id]),
                            ]
                        ],
                    ],
                ]);

                $credentialsAdded = true;
                break;
            }
        }

        if (!$credentialsAdded) {
            $this->view->set('linkButtons', [
                [
                    'name'       => Lang::translate('add_configuration'),
                    'attributes' => [
                        'href' => [
                            'href' => $this->getAdminUrl('addrow', 'add-credentials'),
                        ]
                    ],
                ]
            ]);
        }
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
     * @return string|null
     */
    private function getAction()
    {
        return filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
    }

    /**
     * Retrieves a list of rules for validating adding/editing a module row
     *
     * @param array $vars A list of input vars
     * @return array A list of rules
     */
    private function getConfigurationRules(array &$vars)
    {
        return [
            'api_username' => [
                'empty' => [
                    'rule'    => 'isEmpty',
                    'negate'  => true,
                    'message' => \Language::_('api_username_empty', true),
                ],
            ],
            'api_password' => [
                'empty' => [
                    'rule'    => 'isEmpty',
                    'negate'  => true,
                    'message' => \Language::_('api_password_empty', true),
                ],
            ],
        ];
    }

    /**
     * @param string      $method
     * @param string|null $action
     * @param array       $queryParams
     * @param array       $pathParams
     * @return string
     */
    private function getAdminUrl($method, $action = null, array $queryParams = [], array $pathParams = [])
    {
        $moduleId = isset($this->moduleData->id) ? $this->moduleData->id : (isset($this->vars->module_id) ? $this->vars->module_id : '');

        $url = sprintf('%ssettings/company/modules/%s/%s',
            $this->module->base_uri, $method, $moduleId
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
     * @param string $template
     * @param string $area
     */
    private function initView($template, $area = 'admin')
    {
        $this->view = new \View($template, $area);
        $this->view->baseUri = $this->module->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . Config::configKey('module.system_name') . DS);

        \Loader::loadHelpers($this->view, ['Form', 'Html', 'Widget']);
        $this->view->set('module', $this->moduleData);
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
     * @return string
     */
    private function errors()
    {
        $this->initView('errors');
        $this->view->set('messages', FlashMessage::messages());

        return $this->view->fetch();
    }

}
