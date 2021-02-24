<?php

namespace MgGoGetSsl\Service;

use MgGoGetSsl\Facade\Log;
use MgGoGetSsl\Facade\Config;

/**
 * @property Record $Record
 */
class GoGetSslService
{
    
    const MODULES_TABLE = 'modules';
    const GO_GET_SSL_LOGS_TABLE = 'go_get_ssl_logs';
    const GO_GET_SSL_ORDERS_TABLE = 'go_get_ssl_orders';
    const GO_GET_SSL_NOTIFICATIONS_TABLE = 'go_get_ssl_notifications';

    /**
     * GoGetSslService constructor
     */
    public function __construct()
    {
        \Loader::loadComponents($this, ['Record']);
    }

    /**
     * @return int|null
     */
    public function getGoGetSslModuleId()
    {
        $module = $this->getGoGetSslModule();

        return $module ? $module->id : null;
    }

    /**
     * @return \stdClass|null
     */
    public function getGoGetSslModule()
    {
        $module = $this->Record
            ->select()
            ->from(self::MODULES_TABLE)
            ->where('class', '=', Config::configKey('module.system_name'))
            ->fetch();

        return $module;
    }

    /**
     * @param int   $serviceId
     * @param array $data
     */
    public function saveClientCertificateOrderData($serviceId, array $data)
    {
        $this->Record
            ->insert(self::GO_GET_SSL_ORDERS_TABLE, [
                'order_id'   => $data['order_id'],
                'invoice_id' => $data['invoice_id'],
                'client_id'  => (new  ClientService())->getLoggedInClientId(),
                'service_id' => $serviceId,
                'data'       => !empty($data) ? serialize($data) : null,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
    }

    /**
     * @param int      $serviceId
     * @param int|null $clientId
     * @return array|null
     */
    public function getClientCertificateData($serviceId, $clientId = null)
    {
        if (empty($clientId)) {
            $clientId = (new ClientService())->getLoggedInClientId();
        }

        $data = $this->Record
            ->select()
            ->from(self::GO_GET_SSL_ORDERS_TABLE)
            ->where('client_id', '=', $clientId)
            ->where('service_id', '=', $serviceId)
            ->fetch();
        
        if ($data && !empty($data->data)) {
            $serialized = @unserialize($data->data);

            if (is_array($serialized)) {
                foreach ($serialized as $key => $value) {
                    $data->$key = $value;
                }
            }
        }

        return $data;
    }

    /**
     * @param \Module $module
     */
    public function installModuleCommand(\Module $module)
    {
        try {
            if ((new BlestaService())->isBlesta41()) {
                $group = [
                    'action'     => 'certificate_generate',
                    'type'       => 'client',
                    'plugin_dir' => null,
                    'tags'       => '{first_name},{last_name},{package_name}',
                ];

                if ($groupId = $module->EmailGroups->add($group)) {
                    $email = [
                        'email_group_id' => $groupId,
                        'company_id'     => isset($_SESSION['blesta_company_id']) ? $_SESSION['blesta_company_id'] : 1,
                        'lang'           => 'en_us',
                        'from'           => 'no-reply@mydomain.com',
                        'from_name'      => 'Blesta Order System',
                        'subject'        => 'GoGet SSL Certificate',
                        'text'           => 'Hi {first_name}, Now you are able to generate your certificate. Login to your account and process with Certificate Configuration',
                        'html'           => '<p>Hi {first_name},</p><p>Now you are able to generate your certificate. Login to your account and process with Certificate Configuration</p>',
                    ];

                    $module->Emails->add($email);
                }
            }

            $this->Record
                ->setField('id', [
                    'type'           => 'int',
                    'size'           => 11,
                    'auto_increment' => true,
                ])
                ->setField('service_id', [
                    'type'    => 'int',
                    'size'    => 11,
                ])
                ->setField('created_at', [
                    'type' => 'datetime',
                ])
                ->setKey(['id'], 'primary')
                ->create(self::GO_GET_SSL_NOTIFICATIONS_TABLE);

            $this->Record
                ->setField('id', [
                    'type'           => 'int',
                    'size'           => 11,
                    'auto_increment' => true,
                ])
                ->setField('namespace', [
                    'type'    => 'varchar',
                    'size'    => 255,
                    'is_null' => true,
                ])
                ->setField('type', [
                    'type' => 'varchar',
                    'size' => 10,
                ])
                ->setField('title', [
                    'type' => 'varchar',
                    'size' => 1023,
                ])
                ->setField('data', [
                    'type'    => 'longtext',
                    'is_null' => true,
                ])
                ->setField('function', [
                    'type'    => 'varchar',
                    'size'    => 1023,
                    'is_null' => true,
                ])
                ->setField('created_at', [
                    'type'    => 'datetime',
                    'is_null' => true,
                ])
                ->setKey(['id'], 'primary')
                ->create(self::GO_GET_SSL_LOGS_TABLE);

            $this->Record
                ->setField('id', [
                    'type'           => 'int',
                    'size'           => 11,
                    'auto_increment' => true
                ])
                ->setField('client_id', [
                    'type' => 'int',
                    'size' => 11,
                ])
                ->setField('order_id', [
                    'type' => 'int',
                    'size' => 11,
                ])
                ->setField('invoice_id', [
                    'type'    => 'int',
                    'size'    => 11,
                    'is_null' => true,
                ])
                ->setField('service_id', [
                    'type' => 'int',
                    'size' => 11,
                ])
                ->setField('data', [
                    'type'    => 'longtext',
                    'is_null' => true,
                ])
                ->setField('created_at', [
                    'type'    => 'datetime',
                    'is_null' => true,
                ])
                ->setField('updated_at', [
                    'type'    => 'datetime',
                    'is_null' => true,
                ])
                ->setKey(['id'], 'primary')
                ->create(self::GO_GET_SSL_ORDERS_TABLE);

        } catch (\Exception $e) {
            Log::logError($e->getMessage(), LogService::NAMESPACE_INSTALLATION, $e->getTraceAsString());
        }
    }

    /**
     * @param \stdClass|int $service
     * @return bool
     */
    public function alreadyNotifiedForService($service)
    {
        $serviceId = is_object($service) ? $service->id : $service;

        $notification = $this->Record
            ->select()
            ->from(self::GO_GET_SSL_NOTIFICATIONS_TABLE)
            ->where('service_id', '=', $serviceId)
            ->fetch();

        return !empty($notification);
    }

    /**
     * @param \stdClass|int $service
     */
    public function createServiceNotification($service)
    {
        $serviceId = is_object($service) ? $service->id : $service;

        $this->Record->insert(self::GO_GET_SSL_NOTIFICATIONS_TABLE, [
            'service_id' => $serviceId,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * @return void
     */
    public function uninstalModuleCommands()
    {
        try {
            $this->Record
                ->drop(self::GO_GET_SSL_ORDERS_TABLE);
        } catch (\Exception $e) {

        }
    }

}
