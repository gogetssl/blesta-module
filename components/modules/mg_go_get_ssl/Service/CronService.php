<?php


namespace MgGoGetSsl\Service;


use MgGoGetSsl\API\GoGetSSLApi;
use MgGoGetSsl\Exception\GoGetSSLApiException;
use MgGoGetSsl\Facade\Config;
use MgGoGetSsl\Facade\Lang;
use MgGoGetSsl\Facade\Log;

class CronService
{

    /** @var \Module */
    protected $module;

    /**
     * CronService constructor.
     * @param $module
     */
    public function __construct(\Module $module)
    {
        $this->module = $module;
        \Loader::loadModels($module, ['Record', 'CronTasks', 'ModuleManager']);
    }

    public function cancelCertificate()
    {
        try {
            $reason       = Lang::translate('scheduled_cancel_description');
            $api          = $this->getAPI();
            $canceledData = $this->getCanceledServiceCertificateData();

            foreach ($canceledData as $key => $data)
            {
                try {
                    if($data['order_status'] != 'canceled')
                    {
                        $response = $api->cancelOrder($data['order_id'], $reason);
                        if($response)
                        {
                            $this->updateCertificateStatus($key, $data);
                            Log::logSuccess("Cron GoGetSSL", LogService::NAMESPACE_CRON,
                                "Cancel SSL certificate SUCCESS service_ID:".$key." order_ID:".$data['order_id'],
                                "MgGoGetSsl\CronService::cancelCertificate()");
                        }

                    }
                }
                catch (\Exception $e)
                {
                    Log::logError("Cron GoGetSSL", LogService::NAMESPACE_CRON,
                        "Cancel SSL certificate FAILED service_ID:".$key." order_ID:".$data['order_id'],
                        "MgGoGetSsl\CronService::cancelCertificate()");
                }
            }
        }
        catch (\Exception $e)
        {
            Log::logError("Cron GoGetSSL", LogService::NAMESPACE_CRON,
                "Cron job FAILED".$e->getMessage(),
                "MgGoGetSsl\CronService::cancelCertificate()");
        }

        return true;
    }

    public function createCronTask()
    {
        $task = $this->module->CronTasks->getByKey('go_get_order_cancellation', 'mg_go_get_ssl', 'module');
        if (!$task) {
            $task = [
                'key'         => 'go_get_order_cancellation',
                'task_type'   => 'module',
                'dir'         => 'mg_go_get_ssl',
                'name'        => Lang::translate('scheduled_cancel_name'),
                'description' => Lang::translate('scheduled_cancel_description'),
                'type'        => 'interval'
            ];
            $task_id = $this->module->CronTasks->add($task);
        } else {
            $task_id = $task->id;
        }
         if($task_id)
         {
             $task_run = array(
                 'interval' => '60',
                 'enabled' => 1,
             );
             $this->module->CronTasks->addTaskRun($task_id, $task_run);
         }
         return true;
    }

    public function deleteCronTask($lastInstance)
    {
        $cronTaskRun = $this->module->CronTasks->getTaskRunByKey('go_get_order_cancellation', 'mg_go_get_ssl', false, 'module');
        if ($lastInstance) {
            $cronTask = $this->module->CronTasks->getByKey('go_get_order_cancellation', 'mg_go_get_ssl', 'module');
            if ($cronTask) {
                $this->module->CronTasks->deleteTask($cronTask->id, 'module', 'mg_go_get_ssl');
            }
        }
        if ($cronTaskRun) {
            $this->module->CronTasks->deleteTaskRun($cronTaskRun->task_run_id);
        }
    }

    private function getCanceledServiceCertificateData()
    {
        $canceledCertificateData = [];
        $goGetSslService = new GoGetSslService();

        $canceledServices = $this->module->Record
            ->select()
            ->from('services')
            ->where('services.status', '=', 'canceled')
            ->fetchAll();

        foreach ($canceledServices as $service)
        {
            $result = $goGetSslService->getClientCertificateData($service->id, $service->client_id);
            if(!empty($result))
            {
                $canceledCertificateData[$service->id] = unserialize($result->data);
                $canceledCertificateData[$service->id]['client_id'] = $service->client_id;
            }
        }

        return $canceledCertificateData;

    }

    private function updateCertificateStatus($serviceId, $data)
    {
        unset($data['client_id']);
        $data['order_status'] = "canceled";
        $serializeData = serialize($data);

        return $this->module->Record
            ->where('service_id', '=', $serviceId)
            ->update('go_get_ssl_orders', [
                'data' => $serializeData,
            ]);

    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @return GoGetSSLApi
     */
    private function getAPI()
    {
        $config = $this->getModuleConfiguration();
        $row    = $this->getModuleRows($config['module_id']);
        return (new GoGetSSLApi($row->api_username, $row->api_password, Config::configKey('api.url')))
            ->auth();
    }

    /**
     * Returns all module rows available to the current module
     *
     * @param $moduleId
     * @param null $module_group_id The ID of the module group to filter rows by
     * @return array An array of stdClass objects each representing a module row, false if no module set
     */
    final public function getModuleRows($moduleId, $module_group_id = null)
    {
        $result = $this->module->ModuleManager->getRows($moduleId, $module_group_id);
        return $result[0]->meta;
    }


    /**
     * @throws \RuntimeException
     * @return array
     */
    private function getModuleConfiguration()
    {
        $config = [];
        $configurationData = $this->module->Record
            ->select()
            ->from('modules')
            ->join('module_rows', 'module_rows.module_id', '=',  'modules.id', false)
            ->join('module_row_meta', 'module_row_meta.module_row_id', '=', 'module_rows.id', false)
            ->where('modules.class', '=', 'mg_go_get_ssl')
            ->fetchAll();
        $config['module_id'] = $configurationData[0]->module_id;
        $config['class']     = $configurationData[0]->class;

        foreach ($configurationData as $data)
        {
            $config[$data->key] = $data->value;
        }
        return $config;
    }
}