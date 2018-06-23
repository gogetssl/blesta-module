<?php

namespace MgGoGetSsl\Service;

/**
 * @property Record $Record
 */
class CurrencyService
{

    const CURRENCIES_TABLE = 'currencies';

    /**
     * CurrencyService constructor
     */
    public function __construct()
    {
        \Loader::loadComponents($this, ['Record']);
    }

    /**
     * @param int|null $companyId
     * @return array
     */
    public function getCurrencies($companyId = null)
    {
        if (empty($companyId)) {
            $companyId = $_SESSION['blesta_company_id'];
        }

        return $this->Record
            ->select()
            ->from(self::CURRENCIES_TABLE)
            ->where('company_id', '=', $companyId)
            ->fetchAll();
    }

    /**
     * @param int|null $companyId
     * @return array
     */
    public function getCurrenciesAsAssoc($companyId = null)
    {
        $currenciesArray = [];

        foreach ($this->getCurrencies($companyId) as $currency) {
            $currenciesArray[$currency->code] = $currency->code;
        }

        return $currenciesArray;
    }

}
