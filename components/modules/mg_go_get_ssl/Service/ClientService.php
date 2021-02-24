<?php

namespace MgGoGetSsl\Service;

use MgGoGetSsl\Facade\Lang;

/**
 * @property Record $Record
 */
class ClientService
{

    const CLIENTS_TABLE = 'clients';
    const CONTACTS_TABLE = 'contacts';
    const CONTACT_NUMBERS = 'contact_numbers';
    const SERVICES = 'services';

    /**
     * CurrencyService constructor
     */
    public function __construct()
    {
        \Loader::loadComponents($this, ['Record']);
    }

    /**
     * @return int|null
     */
    public function getLoggedInClientId()
    {
        return isset($_SESSION['blesta_client_id']) ? $_SESSION['blesta_client_id'] : null;
    }

    /**
     * @throws \RuntimeException
     * @param int  $clientId
     * @param bool $hydrateContact
     * @param bool $hydratePhoneNumbers
     * @return \stdClass
     */
    public function getClient($clientId, $hydrateContact = true, $hydratePhoneNumbers = true)
    {
        $client = $this->Record
            ->select()
            ->from(self::CLIENTS_TABLE)
            ->where('id', '=', $clientId)
            ->fetch();

        if ($client && $hydrateContact) {
            $client->contact = $this->Record
                ->select()
                ->from(self::CONTACTS_TABLE)
                ->where('client_id', '=', $client->id)
                ->fetch();

            if ($client->contact && $hydratePhoneNumbers) {
                $client->phone_numbers = $this->Record
                    ->select()
                    ->from(self::CONTACT_NUMBERS)
                    ->where('contact_id', '=', $client->contact->id)
                    ->fetchAll();
            }
        }

        if (!isset($client) || !$client) {
            throw new \RuntimeException(Lang::translate('client_not_found'));
        }

        return $client;
    }

    public function getClientByServiceId($serviceId)
    {
        $service = $this->Record
            ->select()
            ->from(self::SERVICES)
            ->where('id', '=', $serviceId)
            ->fetch();

        return $this->getClient($service->client_id);
    }

    /**
     * @throws \RuntimeException
     * @param bool $hydrateContact
     * @param bool $hydratePhoneNumbers
     * @return \stdClass
     */
    public function getLoggedInClient($hydrateContact = true, $hydratePhoneNumbers = true)
    {
        return $this->getClient($this->getLoggedInClientId(),$hydrateContact, $hydratePhoneNumbers);
    }

}
