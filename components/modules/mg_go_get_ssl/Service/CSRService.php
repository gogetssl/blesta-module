<?php


namespace MgGoGetSsl\Service;


use MgGoGetSsl\Facade\Lang;
use RuntimeException;

class CSRService
{
    private $params;
    private $post;

    /**
     * CSRService constructor.
     * @param $params
     * @param $post
     */
    public function __construct($params, $post)
    {
        $this->params = $params;
        $this->post   = $post;
    }

    public function generateCSR()
    {
        try {
            $scrOut = $this->generate();
            return [
                'error' => false,
                'csr'   => $scrOut
            ];
        }
        catch (\RuntimeException $e)
        {
            var_dump("error:zero");
            return [
                "error"   => true,
                "message" => $e->getMessage()
            ];
        }
    }

    private function validateForm()
    {
        if (!filter_var($this->post['EA'], FILTER_VALIDATE_EMAIL))
        {
            throw new RuntimeException(Lang::translate('invalid_email_address'));
        }
        if (!preg_match("/^[A-Z]{2}$/i", $this->post['C']))
        {
            throw new RuntimeException(Lang::translate('invalid_country_code'));
        }
    }

    private function generate()
    {
        $this->validateForm();

        $dn = array(
            'countryName'            => strtoupper($this->post['C']),
            'stateOrProvinceName'    => $this->post['ST'],
            'localityName'           => $this->post['L'],
            'organizationName'       => $this->post['O'],
            'organizationalUnitName' => $this->post['OU'],
            'commonName'             => $this->post['CN'],
            'emailAddress'           => $this->post['EA'],
        );

        $privKey = openssl_pkey_new(array(
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ));

            if ($privKey)
            {
                $serviceId = $this->params['serviceId'];
                openssl_pkey_export($privKey, $pKeyOut);
                $csr = openssl_csr_new($dn, $privKey);
                if (!$csr)
                {
                    throw new RuntimeException(Lang::translate('csr_code_generate_failed'));
                }

                openssl_csr_export($csr, $csrOut);
            }
            else
            {
                throw new RuntimeException(Lang::translate('csr_code_generate_failed'));
            }
            return $csrOut;
    }

    public function savePrivateKeyToDatabase($serviceid, $privKey)
    {
        try
        {
            $sslRepo = new \MGModule\SSLCENTERWHMCS\eRepository\whmcs\service\SSL();
            $sslService = $sslRepo->getByServiceId((int) $serviceid);

            $sslService->setConfigdataKey('private_key', encrypt($privKey));
            $sslService->save();
        }
        catch (\Exception $ex)
        {
            throw new RuntimeException(Lang::translate('csr_code_generate_failed'));
        }
    }
}