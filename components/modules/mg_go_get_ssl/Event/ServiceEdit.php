<?php

namespace MgGoGetSsl\Event;

use MgGoGetSsl\Facade\Log;
use MgGoGetSsl\Service\LogService;
use MgGoGetSsl\Service\BlestaService;
use MgGoGetSsl\Service\GoGetSslService;
use MgGoGetSsl\Service\PackageService;

class ServiceEdit
{

    /**
     * @return \Closure
     */
    public function handle()
    {
        return function($event)
        {
            if ((new BlestaService())->isBlesta36()) {
                return;
            }

            $params = $event->getParams();

            $tags = [];
            $serviceId = $params['service_id'];
            $pricingId = $params['vars']['pricing_id'];
            $clientId = $params['vars']['client_id'];
            $status = $params['vars']['status'];
            
            if ($status != 'active') {
                return;
            }
            if ((new GoGetSslService())->alreadyNotifiedForService($serviceId)) {
                return;
            }

            $module = (new BlestaService())->getModuleByPricingId($pricingId);

            if (!$module || ($module->id != (new GoGetSslService())->getGoGetSslModuleId())) {
                return;
            }

            if ($packageId = (new BlestaService())->getPackageIdByService($serviceId)) {
                try {
                    $package = (new PackageService())->getPackage($packageId);
                    $tags['package_name'] = $package->name;
                } catch (\Exception $e) {

                }
            }

            try {
                (new BlestaService())->sendEmailToClient('certificate_generate', $clientId, $tags);
                (new GoGetSslService())->createServiceNotification($serviceId);
            } catch (\Exception $e) {
                Log::logError($e->getMessage(), LogService::NAMESPACE_EMAILS, $e->getTraceAsString());
            }
        };
    }

}