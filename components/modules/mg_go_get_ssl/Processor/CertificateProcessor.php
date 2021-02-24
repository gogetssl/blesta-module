<?php

namespace MgGoGetSsl\Processor;

use Exception;
use MgGoGetSsl\Facade\Lang;
use MgGoGetSsl\Facade\Config;
use MgGoGetSsl\Service\CSRService;
use MgGoGetSsl\Util\Inflector;
use MgGoGetSsl\API\GoGetSSLApi;
use MgGoGetSsl\Util\HttpHelper;
use MgGoGetSsl\Application\Runtime;
use MgGoGetSsl\Facade\FlashMessage;
use MgGoGetSsl\Service\BlestaService;
use MgGoGetSsl\Service\ClientService;
use MgGoGetSsl\Service\GoGetSslService;
use MgGoGetSsl\Exception\GoGetSSLApiException;

/**
 * @property Record $Record
 */
class CertificateProcessor
{

    /** @var array */
    private $certificateData;

    /** @var \stdClass */
    protected $package;

    /** @var \stdClass */
    protected $service;

    /** @var int */
    protected $step;

    /** @var GoGetSSLApi */
    protected $api;

    /** @var array */
    protected $variables;

    /** @var array */
    protected $goGetSslProduct;

    /** @var \stdClass */
    protected $client;

    /** @var string */
    protected $sessionKey;

    /** @var array */
    protected $config;

    /** @var array */
    protected $webServerMap = [
        'comodo'          => 1,
        'comodo_ggssl'    => 1,
        'comodo_ukrnames' => 1,
        'comodo_dondca'   => 1,
        'comodo_shino'    => 1,
        'comodo_comssl'   => 1,
        'rapidssl'        => 2,
        'thawte'          => 2,
        'symantec'        => 2,
        'geotrust'        => 2,
    ];

    /**
     * CertificateProcessor constructor
     *
     * @throws Exception
     * @throws \RuntimeException
     * @throws \MgGoGetSsl\Exception\GoGetSSLApiException
     * @param GoGetSSLApi $api
     * @param \stdClass   $package
     * @param \stdClass   $service
     * @param object      $config
     * @param int|null    $clientId
     */
    public function __construct(GoGetSSLApi $api, $package, $service, $config, $clientId = null)
    {
        $requestStep = Runtime::request()->get('step');
        $requestStep = $requestStep ? $requestStep : 1;

        $url    = Runtime::request()->url(true);
        $prefix = strpos($url, 'clients/servicetab') !== false ? 'admin' : 'client';

        $this->api     = $api;
        $this->config  = $config;
        $this->package = $package;
        $this->service = $service;
        $this->sessionKey = sprintf('%s_service_%s_cert_data', $prefix, $this->service->id);
        $this->step = isset($_SESSION[$this->sessionKey]['current_step'])
            ? $_SESSION[$this->sessionKey]['current_step'] : $requestStep;

        if (!isset($package->meta->certificate_type) || empty($package->meta->certificate_type)) {
            throw new \RuntimeException(Lang::translate('product_configuration_mismatch'));
        }
        
        $this->goGetSslProduct = (object) $api->getProduct($package->meta->certificate_type);
        $this->client = !empty($clientId) ? (new ClientService())->getClient($clientId) : (new ClientService())->getLoggedInClient();
        
        \Loader::loadComponents($this, ['Record']);
    }

    /**
     * @throws Exception
     * @return $this
     */
    public function processCertificateGenerate()
    {
        $requestStep = Runtime::request()->get('step');

        $requestStep = $requestStep ? $requestStep : 1;
        $csrEmpty    = Runtime::request()->post('csr_empty');
        $action      = Runtime::request()->post('action');

        if (Runtime::request()->isPost() && ($step = Runtime::request()->post('go_to_step'))) {
            $this->goToStep($step);
        }
        if ($requestStep != $this->step) {
            $this->goToStep($this->step);
        }

        $function = 'step' . $this->step;

        if (is_callable($callback = [$this, $function])) {
            call_user_func($callback);
        }

        $function = 'step' . $this->step . 'post';

        if (Runtime::request()->isPost() && is_callable($callback = [$this, $function])) {
            $error = false;

            if($csrEmpty)
            {
                FlashMessage::error(Lang::translate('empty_csr_field'));
                Runtime::request()->refresh();
                return $this;
            }

            if($action == 'generateCSR')
            {
                $params = ['serviceId' => $this->service->id];
                $csrService = new CSRService($params, $_POST);
                $csrOut = $csrService->generateCSR();

                if($csrOut['error'])
                {
                    unset($_SESSION['csr']);
                    FlashMessage::error($csrOut['message']);
                    Runtime::request()->refresh();
                    return $this;

                }

                $_SESSION['csr'] = $csrOut['csr'];
                FlashMessage::success(Lang::translate('csr_code_generated_successfully'));
                Runtime::request()->refresh();
                return $this;
            }


            try {
                call_user_func_array($callback, [Runtime::request()->post()]);
            } catch (\RuntimeException $e) {
                $error = true;
                FlashMessage::error($e->getMessage());
            } catch (Exception $e) {
                $error = true;
                FlashMessage::error(Lang::translate('general_error'));
            }

            if ($error) {
                Runtime::request()->refresh();
            }
        }
        return $this;
    }

    /**
     * @throws Exception
     * @param int|null $clientId
     * @return $this
     */
    public function processCertificateContactDetails()
    {
        if (!$clientCertificateData = (new GoGetSslService())
            ->getClientCertificateData($this->service->id, $this->client->id)
        ) {
            throw new \RuntimeException(Lang::translate('client_cert_data_not_available'));
        }

        $orderId = $clientCertificateData->order_id;
        $orderStatus = $this->api->getOrderStatus($orderId);
        
        $this->setVariable('orderData', $orderStatus);
        $this->setVariable('orgRequired', $this->goGetSslProduct->org_required);
        $this->setVariable('manageUrl', sprintf('%sservices/manage/%s/', $this->config->base_uri, $this->service->id));

        return $this;
    }

    /**
     * @throws Exception
     * @param int|null $clientId
     * @return $this
     */
    public function processCertificateReissue()
    {
        $requestStep = Runtime::request()->get('step');
        $requestStep = $requestStep ? $requestStep : 1;

        if (Runtime::request()->isPost() && ($step = Runtime::request()->post('go_to_step'))) {
            $this->goToStep($step);
        }
        if ($requestStep != $this->step) {
            $this->goToStep($this->step);
        }

        if (!$clientCertificateData = (new GoGetSslService())
            ->getClientCertificateData($this->service->id, $this->client->id)
        ) {
            throw new \RuntimeException(Lang::translate('client_cert_data_not_available'));
        }
        
        $orderId = $clientCertificateData->order_id;
        $orderStatus = $this->api->getOrderStatus($orderId);
        
        if ($orderStatus['status'] != 'active') {
            throw new \RuntimeException(Lang::translate('order_status_not_allow_to_reissue'));
        }

        $function = 'reissueStep' . $this->step;

        if (is_callable($callback = [$this, $function])) {
            call_user_func($callback);
        }

        $function = 'reissueStep' . $this->step . 'post';

        if (Runtime::request()->isPost() && is_callable($callback = [$this, $function])) {
            $error = false;

            try {
                call_user_func_array($callback, [Runtime::request()->post()]);
            } catch (\RuntimeException $e) {
                $error = true;
                FlashMessage::error($e->getMessage());
            } catch (Exception $e) {
                $error = true;
                FlashMessage::error(Lang::translate('general_error'));
            }

            if ($error) {
                Runtime::request()->refresh();
            }
        }

        return $this;
    }


    /**
     * @throws Exception
     * @param int|null $clientId
     * @return $this
     */
    public function processCertificateDetails()
    {
        if (!$clientCertificateData = (new GoGetSslService())
            ->getClientCertificateData($this->service->id, $this->client->id)
        ) {
            throw new \RuntimeException(Lang::translate('client_cert_data_not_available'));
        }

        $orderId = $clientCertificateData->order_id;

        $orderStatus = $this->api->getOrderStatus($orderId);

        $brand = $this->goGetSslProduct->brand;
        $brandsWithOnlyEmailValidation = Config::configKey(Lang::translate('brands_with_only_email_validation'));

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

        if (isset($orderStatus['san']) && !empty($orderStatus['san'])) {
            foreach ($orderStatus['san'] as $san) {
                $domains[] = $san['san_name'];
            }
        }
        
        foreach ($domains as $domain) {
            $emailsAssoc = [];
            $emailsArray = $this->api->getDomainEmails($domain);

            foreach ($emailsArray as $email) {
                $emailsAssoc[$email] = $email;
            }

            $domainsData[] = [
                'domain' => $domain,
                'emails' => $emailsAssoc,
            ];
        }

        $this->setVariable('methods', $methods);
        $this->setVariable('domains', $domainsData);
        $this->setVariable('orderData', $orderStatus);
        $this->setVariable('isBlesta36', (new BlestaService())->isBlesta36());
        $this->setVariable('manageUrl', sprintf('%sservices/manage/%s/', $this->config->base_uri, $this->service->id));

        $this->certificateData = $this->variables();

        if (($action = Runtime::request()->get('action')) && Runtime::request()->isAjax()) {
            $refresh = 0;
            $message = '';
            $status = 'success';

            switch ($action) {

                case 'resend_certificate_email':
                    try {
                        $blestaService =  new BlestaService();
                        $result = $blestaService->resendCertificateEmail($this->client, $this->certificateData);
                        $status = $result['status'];
                        $message = $result['message'];
                    } catch (\Exception $e) {
                        $status = 'error';
                        $message = Lang::translate('general_error');
                    }
                    break;

                case 'resend_validation_email':
                    try {
                        $this->api->resendValidationEmail($orderId);
                        $message = Lang::translate('validation_email_sent');
                    } catch (GoGetSSLApiException $e) {
                        $status = 'error';
                        $message = $e->getMessage();
                    } catch (Exception $e) {
                        $status = 'error';
                        $message = Lang::translate('general_error');
                    }
                    break;

                case 'revalidate':
                    $post = Runtime::request()->post();
                    $domains = isset($post['domains']) ? $post['domains'] : [];

                    try {
                        foreach ($domains as $domain) {
                            $this->api->changeValidationData($orderId, [
                                'new_method' => $domain['method'] == 'EMAIL' ? $domain['email'] : strtolower($domain['method']),
                                'domain'     => $domain['domain'],
                            ]);
                        }
                        $refresh = 1;
                        $message = Lang::translate('revalidated');
                    } catch (GoGetSSLApiException $e) {
                        $status = 'error';
                        $message = $e->getMessage();
                    } catch (Exception $e) {
                        $status = 'error';
                        $message = Lang::translate('general_error');
                    }
                    break;

                case 'change_validation_email':
                    $post = Runtime::request()->post();

                    try {
                        $this->api->changeValidationEmail($orderId, isset($post['email']) ? $post['email'] : '');
                        $refresh = 1;
                        $message = Lang::translate('validation_email_changed');
                    } catch (GoGetSSLApiException $e) {
                        $status = 'error';
                        $message = $e->getMessage();
                    } catch (Exception $e) {
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

        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentStep()
    {
        return $this->step;
    }

    /**
     * @return array
     */
    public function variables()
    {
        $variables = is_array($this->variables) ? $this->variables : [];
        $cerContact = $this->getCerContact();
        $techContact = $this->getTechContact();
        $variables['orderData']['admin_addressline2'] = $cerContact->address2;
        $variables['orderData']['admin_postalcode']   = $cerContact->zip;
        $variables['orderData']['admin_region']       = $cerContact->state;
        $variables['orderData']['tech_postalcode']    = $techContact->tech_postalcode;
        $variables['orderData']['tech_region']        = $techContact->tech_region;
        return $variables;
    }


    /**
     * @return object
     */

    public function getCerContact()
    {
        return $this->client->contact;
    }

    /**
     * @return object
     */

    public function getTechContact()
    {
        return $this->config;
    }

    /**
     * @throws Exception
     * @param int $step
     */
    private function goToStep($step)
    {
        if ($step < 1) {
            $step = 1;
        } else if ($step > 3) {
            $step = 3;
        }

        $_SESSION[$this->sessionKey]['current_step'] = $step;
        HttpHelper::redirect(Runtime::request()->url(true) . '?step=' . $step);
    }


    /**
     * @throws Exception
     */
    private function previousStep()
    {
        $this->goToStep($_SESSION[$this->sessionKey]['current_step'] - 1);
    }

    /**
     * @throws Exception
     */
    private function nextStep()
    {
        $this->goToStep($_SESSION[$this->sessionKey]['current_step'] + 1);
    }

    /**
     * @throws Exception
     * @throws GoGetSSLApiException
     */
    private function reissueStep1()
    {
        $typeId = $this->webServerMap[$this->goGetSslProduct->brand];
        $webServers = $this->api->getWebServers($typeId);

        $webServersArray = [
            '' => Lang::translate('choose_one'),
        ];
        foreach ($webServers as $webServer) {
            $webServersArray[$webServer['id']] = $webServer['software'];
        }

        $data = isset($_SESSION[$this->sessionKey]) ? $_SESSION[$this->sessionKey] : [];

        $data['csr'] = isset($data['csr']) ? $data['csr'] : '-----BEGIN CERTIFICATE REQUEST-----

-----END CERTIFICATE REQUEST-----';

        $this->setVariable('data', $data);
        $this->setVariable('sansEnabled', isset($this->package->meta->enable_sans) ? $this->package->meta->enable_sans : false);
        $this->setVariable('includedSans', isset($this->package->meta->included_sans) ? $this->package->meta->included_sans : 0);
        $this->setVariable('webServerTypes', $webServersArray);
    }

    /**
     * @throws Exception
     * @param array $post
     */
    private function reissueStep1post(array $post)
    {
        $requiredFields = [
            'web_server_type', 'csr'
        ];

        foreach ($requiredFields as $requiredField) {
            if (!isset($post[$requiredField]) || trim($post[$requiredField]) === '' || $post[$requiredField] === null) {
                throw new \RuntimeException(Lang::translate(sprintf('field_%s_empty', $requiredField)));
            }
        }

        if ($sansDomainsString = (isset($post['sans_domains']) ? $post['sans_domains'] : false)) {
            $sansDomains = Inflector::explodeByNewLine($sansDomainsString);
            $_SESSION[$this->sessionKey]['sans_domains'] = $sansDomainsString;

            $includedSans = isset($this->package->meta->included_sans) ? $this->package->meta->included_sans : 0;

            if (count($sansDomains) > $includedSans) {
                throw new \RuntimeException(sprintf('%s %s %s',
                    Lang::translate('included_sans_limit_exceeded'), $includedSans, Lang::translate('domains')
                ));
            }

            foreach ($sansDomains as $sansDomain) {
                if (!Inflector::validateDomain($sansDomain)) {
                    throw new \RuntimeException(sprintf('%s %s', $sansDomain, Lang::translate('is_not_valid_domain')));
                }
            }

            $post['sans_domains_array'] = $sansDomains;
        }

        try {
            $decodedCsr = $this->api->decodeCSR($post['csr']);
        } catch (GoGetSSLApiException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $post['decoded_csr'] = $decodedCsr;
        $_SESSION[$this->sessionKey] = $post;

        if (!isset($post['decoded_csr']['CN']) || empty($post['decoded_csr']['CN'])) {
            throw new \RuntimeException(Lang::translate('no_main_domain_found'));

        }

        $this->goToStep(2);
    }

    /**
     * @throws Exception
     * @throws GoGetSSLApiException
     */
    private function reissueStep2()
    {
        $domains = [];
        $domainsData = [];
        $data = $_SESSION[$this->sessionKey];

        if (!isset($data['decoded_csr']['CN']) || empty($data['decoded_csr']['CN'])) {
            throw new \RuntimeException(Lang::translate('no_main_domain_found'));
        }

        $mainDomain = $domain = $data['decoded_csr']['CN'];
        $domains[] = $domain;

        $domains = isset($data['sans_domains_array']) ? array_merge($domains, $data['sans_domains_array']) : $domains;

        foreach ($domains as $domain) {
            $emailsAssoc = [];
            $emailsArray = $this->api->getDomainEmails($domain);

            foreach ($emailsArray as $email) {
                $emailsAssoc[$email] = $email;
            }

            $domainsData[] = [
                'domain' => $domain,
                'emails' => $emailsAssoc,
            ];
        }

        $brand = $this->goGetSslProduct->brand;
        $brandsWithOnlyEmailValidation = Config::configKey('brands_with_only_email_validation');

        $methods = [
            'EMAIL' => 'EMAIL',
        ];

        if (!in_array($brand, $brandsWithOnlyEmailValidation)) {
            $methods['HTTP'] = 'HTTP';
            $methods['HTTPS'] = 'HTTPS';
            $methods['DNS'] = 'DNS';
        }

        $this->setVariable('methods', $methods);
        $this->setVariable('mainDomain', $mainDomain);
        $this->setVariable('domains', $domainsData);
    }

    /**
     * @throws Exception
     * @param array $post
     */
    private function reissueStep2post(array $post)
    {
        if (!$clientCertificateData = (new GoGetSslService())
            ->getClientCertificateData($this->service->id, $this->client->id)
        ) {
            throw new \RuntimeException(Lang::translate('client_cert_data_not_available'));
        }

        $orderId = $clientCertificateData->order_id;

        $data = $_SESSION[$this->sessionKey];

        $orderData = [];
        $orderData['csr'] = $data['csr'];
        $orderData['webserver_type'] = $data['web_server_type'];

        $domains = $post['domains'];
        $hasSans = isset($data['sans_domains_array']) && !empty($data['sans_domains_array']) && count($domains) > 1;
        foreach ($domains as $domain) {
            if (isset($domain['is_main']) && $domain['is_main']) {
                $dcvMethod = strtolower($domain['method']);
                $approverEmail = $domain['email'];
                continue;
            }

            $approverEmails[] = $domain['method'] == 'EMAIL' ? $domain['email'] : strtolower($domain['method']);
        }

        if (!isset($dcvMethod)) {
            throw new \RuntimeException(Lang::translate('no_dcv_method_set'));
        }

        $orderData['dcv_method'] = $dcvMethod;
        $orderData['approver_email'] = $orderData['dcv_method'] == 'email' ? $approverEmail : '';

        $brand = $this->goGetSslProduct->brand;
        $brandsWithOnlyEmailValidation = Config::configKey('brands_with_only_email_validation');

        if ($hasSans) {
            $orderData['dns_names'] = implode(',', $data['sans_domains_array']);
            $orderData['approver_emails'] = implode(',', $approverEmails);
        }

        if (in_array($brand, $brandsWithOnlyEmailValidation) && isset($orderData['approver_emails'])) {
            unset($orderData['approver_emails']);
        }

        try {
            $orderStatus = $this->api->getOrderStatus($orderId);

            if ($hasSans && count($data['sans_domains_array']) > $orderStatus['total_domains'] && $orderStatus['total_domains'] >= 0) {
                $count = count($data['sans_domains_array']) - $orderStatus['total_domains'];
                $this->api->addSslSanOrder($orderId, $count);
            }

            $this->api->reissueOrder($orderId,$orderData);

        } catch (GoGetSSLApiException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        FlashMessage::success(Lang::translate('certificate_configuration_complete'));
        if($this->config->base_uri == "/admin/") {
            HttpHelper::redirect(sprintf('%sclients/servicetab/%s/%s/adminDetailsCert/', $this->config->base_uri, $this->service->client_id,$this->service->id));

        } elseif($this->config->base_uri == "/client/") {
            HttpHelper::redirect(sprintf('%sservices/manage/%s/clientDetailsCert/', $this->config->base_uri,  $this->service->id));
        }
    }

    /**
     * @return void
     */
    private function reissueStep3()
    {
        $_SESSION[$this->sessionKey] = null;
        $this->setVariable('dashboardUrl', $this->config->base_uri);
    }

    /**
     * @throws \MgGoGetSsl\Exception\GoGetSSLApiException
     * @throws Exception
     */
    private function step1()
    {

        $this->setVariable('orderTypes', [
            ''      => Lang::translate('choose_one'),
            'new'   => Lang::translate('new_order'),
            'renew' => Lang::translate('renewal'),
        ]);

        $typeId = $this->webServerMap[$this->goGetSslProduct->brand];
        $webServers = $this->api->getWebServers($typeId);

        $webServersArray = [
            '' => Lang::translate('choose_one'),
        ];
        foreach ($webServers as $webServer) {
            $webServersArray[$webServer['id']] = $webServer['software'];
        }

        $phoneNumbers = $this->client->phone_numbers;
        if (is_array($phoneNumbers) && !empty($phoneNumbers)) {
            $number = reset($phoneNumbers);
            $this->client->phone_number = $number->number;
        } else {
            $this->client->phone_number = null;
        }

        $data = isset($_SESSION[$this->sessionKey]) ? $_SESSION[$this->sessionKey] : [];

        $data['admin_first_name']        = isset($data['admin_first_name'])        ? $data['admin_first_name'] : $this->client->contact->first_name;
        $data['admin_last_name']         = isset($data['admin_last_name'])         ? $data['admin_last_name'] : $this->client->contact->last_name;
        $data['admin_organization_name'] = isset($data['admin_organization_name']) ? $data['admin_organization_name'] : $this->client->contact->company;
        $data['admin_job_title']         = isset($data['admin_job_title'])         ? $data['admin_job_title'] : null;
        $data['admin_email']             = isset($data['admin_email'])             ? $data['admin_email'] : $this->client->contact->email;
        $data['admin_address1']          = isset($data['admin_address1'])          ? $data['admin_address1'] : $this->client->contact->address1;
        $data['admin_address2']          = isset($data['admin_address2'])          ? $data['admin_address2'] : $this->client->contact->address2;
        $data['admin_city']              = isset($data['admin_city'])              ? $data['admin_city'] : $this->client->contact->city;
        $data['admin_state']             = isset($data['admin_state'])             ? $data['admin_state'] : $this->client->contact->state;
        $data['admin_zipcode']           = isset($data['admin_zipcode'])           ? $data['admin_zipcode'] : $this->client->contact->zip;
        $data['admin_country']           = isset($data['admin_country'])           ? $data['admin_country'] : $this->client->contact->country;
        $data['admin_phone_number']      = isset($data['admin_phone_number'])      ? $data['admin_phone_number'] : $this->client->phone_number;

        $data['sans_domains'] = isset($data['sans_domains']) ? $data['sans_domains'] : null;
        $data['order_type'] = isset($data['order_type']) ? $data['order_type'] : null;
        $data['web_server_type'] = isset($data['web_server_type']) ? $data['web_server_type'] : null;
        $data['csr'] = isset($data['csr']) ? $data['csr'] : '-----BEGIN CERTIFICATE REQUEST-----

-----END CERTIFICATE REQUEST-----';

        if(isset($_SESSION['csr']))
        {
            $data['csr'] = $_SESSION['csr'];
            unset($_SESSION['csr']);
        }


        $this->setVariable('orgRequired', $this->goGetSslProduct->org_required);
        $this->setVariable('sansEnabled', isset($this->package->meta->enable_sans) ? $this->package->meta->enable_sans : false);
        $this->setVariable('includedSans', isset($this->package->meta->included_sans) ? $this->package->meta->included_sans : 0);
        $this->setVariable('data', $data);
        $this->setVariable('countries', (new BlestaService())->countries());
        $this->setVariable('webServerTypes', $webServersArray);
    }

    /**
     * @throws Exception
     * @throws \RuntimeException
     * @param array $post
     */
    private function step1post(array $post)
    {
        $_SESSION[$this->sessionKey] = $post;

        $requiredFields = [
            'order_type', 'web_server_type', 'csr', 'admin_first_name', 'admin_last_name',
            'admin_organization_name', 'admin_job_title', 'admin_email', 'admin_address1',
            'admin_city', 'admin_state', 'admin_zipcode', 'admin_country', 'admin_phone_number',
        ];

        if ($this->goGetSslProduct->org_required) {
            $requiredFields = array_merge([
                'org_name', 'org_division', 'org_duns', 'org_addressline1', 'org_city', 'org_country',
                'org_phone', 'org_region'
            ], $requiredFields);
        }

        foreach ($requiredFields as $requiredField) {
            if (!isset($post[$requiredField]) || trim($post[$requiredField]) === '' || $post[$requiredField] === null) {
                throw new \RuntimeException(Lang::translate(sprintf('field_%s_empty', $requiredField)));
            }
        }

        if ($sansDomainsString = (isset($post['sans_domains']) ? $post['sans_domains'] : false)) {
            $sansDomains = Inflector::explodeByNewLine($sansDomainsString);
            $_SESSION[$this->sessionKey]['sans_domains'] = $sansDomainsString;

            $includedSans = isset($this->package->meta->included_sans) ? $this->package->meta->included_sans : 0;

            if (count($sansDomains) > $includedSans) {
                throw new \RuntimeException(sprintf('%s %s %s',
                    Lang::translate('included_sans_limit_exceeded'), $includedSans, Lang::translate('domains')
                ));
            }

            foreach ($sansDomains as $sansDomain) {
                if (!Inflector::validateDomain($sansDomain)) {
                    throw new \RuntimeException(sprintf('%s %s', $sansDomain, Lang::translate('is_not_valid_domain')));
                }
            }

            $post['sans_domains_array'] = $sansDomains;
        }

        try {
            $decodedCsr = $this->api->decodeCSR($post['csr']);
        } catch (GoGetSSLApiException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $post['decoded_csr'] = $decodedCsr;
        $_SESSION[$this->sessionKey] = $post;

        if (!isset($post['decoded_csr']['CN']) || empty($post['decoded_csr']['CN'])) {
            throw new \RuntimeException(Lang::translate('no_main_domain_found'));
        }

        $this->goToStep(2);
    }

    /**
     * @throws Exception
     * @throws GoGetSSLApiException
     */
    private function step2()
    {
        $domains = [];
        $domainsData = [];
        $data = $_SESSION[$this->sessionKey];

        if (!isset($data['decoded_csr']['CN']) || empty($data['decoded_csr']['CN'])) {
            throw new \RuntimeException(Lang::translate('no_main_domain_found'));
        }

        $mainDomain = $domain = $data['decoded_csr']['CN'];
        $domains[] = $domain;

        $domains = isset($data['sans_domains_array']) ? array_merge($domains, $data['sans_domains_array']) : $domains;

        foreach ($domains as $domain) {
            $emailsAssoc = [];
            $emailsArray = $this->api->getDomainEmails($domain);

            foreach ($emailsArray as $email) {
                $emailsAssoc[$email] = $email;
            }

            $domainsData[] = [
                'domain' => $domain,
                'emails' => $emailsAssoc,
            ];
        }

        $brand = $this->goGetSslProduct->brand;
        $brandsWithOnlyEmailValidation = Config::configKey('brands_with_only_email_validation');

        $methods = [
            'EMAIL' => 'EMAIL',
        ];

        if (!in_array($brand, $brandsWithOnlyEmailValidation)) {
            $methods['HTTP']  = 'HTTP';
            $methods['HTTPS'] = 'HTTPS';
            $methods['DNS']   = 'DNS';
        }

        $this->setVariable('methods', $methods);
        $this->setVariable('mainDomain', $mainDomain);
        $this->setVariable('domains', $domainsData);
    }

    /**
     * @throws Exception
     * @throws \RuntimeException
     * @param array $post
     */
    private function step2post(array $post)
    {
        $data = $_SESSION[$this->sessionKey];
        $useAdminContact = isset($this->config->use_admin_contact) ? $this->config->use_admin_contact : 0;

        $packagePricing = isset($this->service->package_pricing) ? $this->service->package_pricing : null;
        $term = $packagePricing ? $packagePricing->term : 1;
        $period = $packagePricing ? $packagePricing->period : 'free';
        
        $billingPeriods = [
            'day'     => 1,
            'week'    => 1,
            'month'   => 1,
            'year'    => 12,
            'onetime' => 12,
        ];

        $productAvailablePeriods = array_keys($this->goGetSslProduct->prices['vendor']);
        $periodValue = isset($billingPeriods[$period]) ? $billingPeriods[$period] : 12;
        $periodValue *= $term;

        if (!in_array($periodValue, $productAvailablePeriods)) {
            $min = min($productAvailablePeriods);
            $max = max($productAvailablePeriods);

            if ($periodValue < $min) {
                $periodValue = $min;
            } else if ($periodValue > $max) {
                $periodValue = $max;
            } else {
                $periodValue = $min;
            }
        }

        $approverEmails = [];
        $domains = $post['domains'];
        $hasSans = isset($data['sans_domains_array']) && !empty($data['sans_domains_array']) && count($domains) > 1;
        foreach ($domains as $domain) {
            if (isset($domain['is_main']) && $domain['is_main']) {
                $dcvMethod = strtolower($domain['method']);
                $approverEmail = $domain['email'];
                continue;
            }

            $approverEmails[] = $domain['method'] == 'EMAIL' ? $domain['email'] : strtolower($domain['method']);
        }

        if (!isset($dcvMethod)) {
            throw new \RuntimeException(Lang::translate('no_dcv_method_set'));
        }

        $orderData['tech_firstname']    = $useAdminContact ? $data['admin_first_name'] : $this->config->tech_firstname;
        $orderData['tech_lastname']     = $useAdminContact ? $data['admin_last_name'] : $this->config->tech_lastname;
        $orderData['tech_organization'] = $useAdminContact ? $data['admin_organization_name'] : $this->config->tech_organization;
        $orderData['tech_addressline1'] = $useAdminContact ? $data['admin_address1'] : $this->config->tech_addressline1;
        $orderData['tech_phone']        = $useAdminContact ? $data['admin_phone_number'] : $this->config->tech_phone;
        $orderData['tech_title']        = $useAdminContact ? $data['admin_job_title'] : $this->config->tech_title;
        $orderData['tech_email']        = $useAdminContact ? $data['admin_email'] : $this->config->tech_email;
        $orderData['tech_city']         = $useAdminContact ? $data['admin_city'] : $this->config->tech_city;
        $orderData['tech_country']      = $useAdminContact ? $data['admin_country'] : $this->config->tech_country;
        $orderData['tech_postalcode']   = $useAdminContact ? $data['admin_zipcode'] : $this->config->tech_postalcode;
        $orderData['tech_region']       = $useAdminContact ? $data['admin_state'] : $this->config->tech_region;
        $orderData['tech_fax']          = $useAdminContact ? '' : $this->config->tech_fax;

        $orderData['admin_firstname']    = $data['admin_first_name'];
        $orderData['admin_lastname']     = $data['admin_last_name'];
        $orderData['admin_organization'] = $data['admin_organization_name'];
        $orderData['admin_title']        = $data['admin_job_title'];
        $orderData['admin_addressline1'] = $data['admin_address1'];
        $orderData['admin_addressline2'] = $data['admin_address2'];
        $orderData['admin_phone']        = $data['admin_phone_number'];
        $orderData['admin_email']        = $data['admin_email'];
        $orderData['admin_city']         = $data['admin_city'];
        $orderData['admin_country']      = $data['admin_country'];
        $orderData['admin_postalcode']   = $data['admin_zipcode'];
        $orderData['admin_region']       = $data['admin_state'];

        $orderData['server_count']   = -1;
        $orderData['csr']            = $data['csr'];
        $orderData['period']         = $periodValue;
        $orderData['dcv_method']     = $dcvMethod;
        $orderData['webserver_type'] = $data['web_server_type'];
        $orderData['product_id']     = $this->package->meta->certificate_type;
        $orderData['approver_email'] = $orderData['dcv_method'] == 'email' ? $approverEmail : '';

        $brand = $this->goGetSslProduct->brand;
        $brandsWithOnlyEmailValidation = Config::configKey('brands_with_only_email_validation');

        if ($hasSans) {
            $orderData['dns_names'] = implode(',', $data['sans_domains_array']);
            $orderData['approver_emails'] = implode(',', $approverEmails);
        }

        if (in_array($brand, $brandsWithOnlyEmailValidation) && isset($orderData['approver_emails'])) {
            unset($orderData['approver_emails']);
        }

        if ($this->goGetSslProduct->org_required) {
            $orderData['org_name']         = $data['org_name'];
            $orderData['org_division']     = $data['org_division'];
            $orderData['org_duns']         = $data['org_duns'];
            $orderData['org_addressline1'] = $data['org_addressline1'];
            $orderData['org_city']         = $data['org_city'];
            $orderData['org_country']      = $data['org_country'];
            $orderData['org_fax']          = $data['org_fax'];
            $orderData['org_phone']        = $data['org_phone'];
            $orderData['org_postalcode']   = $data['org_postalcode'];
            $orderData['org_region']       = $data['org_region'];
        }

        try {
            switch ($data['order_type']) {
                case 'new':
                    $result = $this->api->addSslOrder($orderData);
                    break;

                case 'renew':
                    $result = $this->api->addSslRenewOrder($orderData);
                    break;
            }
        } catch (GoGetSSLApiException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        (new GoGetSslService())
            ->saveClientCertificateOrderData($this->service->id, $result);

        FlashMessage::success(Lang::translate('certificate_configuration_complete'));
        HttpHelper::redirect(sprintf('%sservices/manage/%s/clientDetailsCert/', $this->config->base_uri, $this->service->id));
//        $this->goToStep(3);
    }

    /**
     * @return void
     */
    private function step3()
    {
        $_SESSION[$this->sessionKey] = null;
        $this->setVariable('dashboardUrl', $this->config->base_uri);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    private function setVariable($key, $value)
    {
        $this->variables[$key] = $value;

        return $this;
    }

}
