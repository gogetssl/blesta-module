<?php

namespace MgGoGetSsl\Service;

use MgGoGetSsl\Facade\Lang;
use MgGoGetSsl\Facade\Log;
use MgGoGetSsl\Facade\Settings;

/**
 * @property Record $Record
 * @property Emails $Emails
 * @property Countries $Countries
 */
class BlestaService
{

    const MODULES_TABLE = 'modules';
    const SERVICES_TABLE = 'services';
    const MODULE_ROWS_TABLE = 'module_rows';
    const PACKAGE_PRICING_TABLE = 'package_pricing';

    /**
     * BlestaService constructor
     */
    public function __construct()
    {
        if ($this->isBlesta41()) {
            \Loader::loadComponents($this, ['Record', 'Emails', 'Countries']);
        } else {
            \Loader::loadComponents($this, ['Record']);
        }
    }

    /**
     * @param bool $asAssoc
     * @return array
     */
    public function countries($asAssoc = true)
    {
        if ($this->isBlesta36()) {
            return unserialize(file_get_contents(sprintf('%s/resources/countries.dat', dirname(__DIR__))));
        }

        $countries = $this->Countries->getList();

        if (!$asAssoc) {
            return $countries;
        }

        $countriesAssoc = [];
        foreach ($countries as $country) {
            $countriesAssoc[$country->alpha2] = $country->name;
        }
        
        return $countriesAssoc;
    }

    /**
     * @param int $serviceId
     * @return \stdClass|null
     */
    public function getService($serviceId)
    {
        return $this->Record
            ->select()
            ->from(self::SERVICES_TABLE)
            ->where('id', '=', $serviceId)
            ->fetch();
    }

    /**
     * @param \stdClass|int $service
     * @return int|null
     */
    public function getPackageIdByService($service)
    {
        if (!is_object($service)) {
            $service = $this->getService($service);
        }

        if (!$service) {
            return null;
        }

        $packagePricingId = $service->pricing_id;

        $pricing = $this->Record
            ->select()
            ->from(self::PACKAGE_PRICING_TABLE)
            ->where('id', '=', $packagePricingId)
            ->fetch();

        return $pricing ? $pricing->package_id : null;
    }

    /**
     * @param int $moduleRowId
     * @return \stdClass|null
     */
    public function getModuleByModuleRowId($moduleRowId)
    {
        $moduleRow = $this->Record
            ->select()
            ->from(self::MODULE_ROWS_TABLE)
            ->where('id', '=', $moduleRowId)
            ->fetch();

        if (!$moduleRow) {
            return null;
        }

        return $this->Record
            ->select()
            ->from(self::MODULES_TABLE)
            ->where('id', '=', $moduleRow->module_id)
            ->fetch();
    }

    /**
     * @param int $pricingId
     * @return \stdClass|null
     */
    public function getModuleByPricingId($pricingId)
    {
        $pricing = $this->Record
            ->select()
            ->from(self::PACKAGE_PRICING_TABLE)
            ->where('id', '=', $pricingId)
            ->fetch();

        if (!$pricing) {
            return null;
        }

        $package = (new PackageService())
            ->getPackage($pricing->package_id);

        if (!$package) {
            return null;
        }

        return $this->Record
            ->select()
            ->from(self::MODULES_TABLE)
            ->where('id', '=', $package->module_id)
            ->fetch();
    }

    /**
     * @return bool
     */
    public function isBlesta36()
    {
        return version_compare(Settings::setting('database_version'), '3.6.1', '<=');
    }

    /**
     * @return bool
     */
    public function isBlesta41()
    {
        return version_compare(Settings::setting('database_version'), '4.1', '>=');
    }

    /**
     * @return int
     */
    public function getCompanyId()
    {
        return isset($_SESSION['blesta_company_id']) ? $_SESSION['blesta_company_id'] : 1;
    }

    /**
     * @throws \RuntimeException
     * @param string   $template
     * @param int|null $clientId
     * @param array    $tags
     * @param string   $lang
     */
    public function sendEmailToClient($template, $clientId = null, array $tags = [], $lang = 'en_us')
    {
        if (empty($clientId)) {
            $clientId = (new ClientService())->getLoggedInClientId();
        }

        $to = '';
        $companyId = $this->getCompanyId();
        $client = (new ClientService())->getClient($clientId);

        if (!$clientId) {
            throw new \RuntimeException(Lang::translate('client_not_found'));
        }

        if (isset($client->contact->email)) {
            $to = $client->contact->email;
        }
        if (isset($client->contact->first_name)) {
            $tags['first_name'] = $client->contact->first_name;
            $tags['last_name'] = $client->contact->last_name;
        }

        $options = [
            'to_client' => $clientId
        ];

        if (!$this->Emails->send($template, $companyId, $lang, $to, $tags, null, null, null, $options)) {
            throw new \RuntimeException(sprintf('%s: %s', Lang::translate('unable_to_send_email'), $template));
        }

        Log::logInfo('Mail sent!');
    }

}
