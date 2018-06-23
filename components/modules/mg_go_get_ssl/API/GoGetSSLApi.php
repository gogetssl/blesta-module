<?php

namespace MgGoGetSsl\API;

use MgGoGetSsl\Facade\Lang;
use MgGoGetSsl\Exception\GoGetSSLApiException;
use MgGoGetSsl\Util\Inflector;

class GoGetSSLApi extends API
{

    /** @var bool */
    protected $dump = false;

    /** @var bool */
    protected $throwExceptions = true;

    /** @var string */
    protected $apiUrl;

    /** @var string */
    protected $key;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var string */
    protected $baseUrl;

    /**
     * GoGetSSLApi constructor
     *
     * @param string $username
     * @param string $password
     * @param string $baseUrl
     */
    public function __construct($username, $password, $baseUrl)
    {
        $this->username = $username;
        $this->password = $password;
        $this->baseUrl = $baseUrl;
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @return $this
     */
    public function auth()
    {
        $this->sendPOST($this->resourcePath('/auth'), [
            'user' => $this->username,
            'pass' => $this->password
        ]);
        
        $this->handleError();

        if ($response = $this->response()) {
            $this->key = $response['key'];
        }

        return $this;
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @return array
     */
    public function getProducts()
    {
        $this->sendGET($this->resourcePath('/products/ssl'));
        $this->handleError();

        $response = $this->response();

        return isset($response['products']) ? $response['products'] : [];
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int $productId
     * @return array
     */
    public function getProduct($productId)
    {
        $this->sendGET($this->resourcePath('/products/ssl/' . $productId));
        $this->handleError();

        $response = $this->response();
        
        return isset($response['product']) ? $response['product'] : [];
    }

    /**
     * @throws GoGetSSLApiException
     * @throws \Exception
     * @param int $typeId
     * @return array
     */
    public function getWebServers($typeId)
    {
        $this->sendGET($this->resourcePath('/tools/webservers/' . $typeId));
        $this->handleError();

        $response = $this->response();

        return isset($response['webservers']) ? $response['webservers'] : [];
    }

    /**
     * @throws GoGetSSLApiException
     * @throws \Exception
     * @param string $csr
     * @return array
     */
    public function decodeCSR($csr)
    {
        $this->sendPOST($this->resourcePath('/tools/csr/decode', true), [
            'csr' => $csr
        ]);
        $this->handleError();

        $response = $this->response();

        return isset($response['csrResult']) ? $response['csrResult'] : [];
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param array $data
     * @return array
     */
    public function addSslOrder(array $data)
    {
        $this->sendPOST($this->resourcePath('/orders/add_ssl_order', true), $data);
        $this->handleError();

        return $this->response();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param array $data
     * @return array
     */
    public function addSslRenewOrder(array $data)
    {
        $this->sendPOST($this->resourcePath('/orders/add_ssl_renew_order', true), $data);
        $this->handleError();

        return $this->response();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int $orderId
     * @return array
     */
    public function getOrderStatus($orderId)
    {
        $this->sendGET($this->resourcePath('/orders/status/ '. $orderId));
        $this->handleError();

        return $this->response();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int $orderId
     * @return array
     */
    public function resendValidationEmail($orderId)
    {
        $this->sendGET($this->resourcePath('/orders/ssl/resend_validation_email/' . $orderId));
        $this->handleError();

        return $this->response();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int   $orderId
     * @param array $data
     * @return array
     */
    public function changeValidationData($orderId, array $data)
    {
        $this->sendPOST($this->resourcePath('/orders/ssl/change_validation_method/' . $orderId, true), $data);
        $this->handleError();

        return $this->response();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int    $orderId
     * @param string $email
     * @return array
     */
    public function changeValidationEmail($orderId, $email)
    {
        $this->sendPOST($this->resourcePath('/orders/ssl/change_validation_email/' . $orderId, true), [
            'approver_email' => $email
        ]);
        $this->handleError();

        return $this->response();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param string $domain
     * @return array
     */
    public function getDomainEmails($domain)
    {
        $this->sendPOST($this->resourcePath('/tools/domain/emails/', true), [
            'domain' => $domain
        ]);
        $this->handleError();
        
        $response = $this->response();

        return isset($response['ComodoApprovalEmails']) ? $response['ComodoApprovalEmails'] : reset($response);
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int   $orderId
     * @param array $data
     * @return array
     */
    public function reissueOrder($orderId, array $data)
    {
        $this->sendPOST($this->resourcePath('/orders/ssl/reissue/' . $orderId, true), $data);
        $this->handleError();

        return $this->response();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int    $orderId
     * @param string $reason
     * @return array
     */
    public function cancelOrder($orderId, $reason)
    {
        $this->sendPOST($this->resourcePath('/orders/cancel_ssl_order/', true), [
            'order_id' => $orderId,
            'reason'   => $reason
        ]);
        $this->handleError();

        return $this->response();
    }

    /**
     * @deprecated
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int    $orderId
     * @return array
     */
    public function activateOrder($orderId)
    {
        $this->sendGET($this->resourcePath('/orders/ssl/activate/' . $orderId));
        $this->handleError();

        return $this->response();
    }

    /**
     * @throws \Exception
     * @throws GoGetSSLApiException
     * @param int $orderId
     * @param int $count
     * @return array
     */
    public function addSslSanOrder($orderId, $count)
    {
        $this->sendPOST($this->resourcePath('/orders/add_ssl_san_order/', true), [
            'order_id' => $orderId,
            'count'    => $count
        ]);
        $this->handleError();

        return $this->response();
    }

    /**
     * @param array $data
     * @return array
     */
    public function extendRequestData(array $data)
    {
        $data['auth_key'] = $this->key;

        return $data;
    }

    /**
     * @param string $url
     * @param bool   $includeAuthKey
     * @return string
     */
    private function resourcePath($url, $includeAuthKey = false)
    {
        $url = sprintf('%s/%s',
            Inflector::trimLastChar($this->baseUrl, '/'),
            Inflector::trimFirstChar($url, '/')
        );

        if (!$includeAuthKey) {
            return $url;
        }

        $sign = strpos($url, '?') === false ? '?' : '&';

        return $url . ($includeAuthKey ? $sign . 'auth_key=' . $this->key : '');
    }

    /**
     * @throws GoGetSSLApiException
     */
    private function handleError()
    {
        if ($this->isSuccess()) {
            return;
        }

        $error = $this->errorMessage();

        if (!empty($error)) {
            throw new GoGetSSLApiException($error);
        }

        switch ($this->httpCode()) {
            case 401:
                $errorMessageKey = 'unauthorized';
                break;

            default:
                $errorMessageKey = 'general_error';
        }

        throw new GoGetSSLApiException(Lang::translate(sprintf('api_error.%s', $errorMessageKey)));
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        $response = $this->response();
        $error = isset($response['error']) && $response['error'];

        return in_array($this->httpCode(), [200, 201, 204]) && !$error;
    }

    /**
     * @return string
     */
    public function errorMessage()
    {
        $response = $this->response();
        $message = isset($response['message']) ? $response['message'] : '';
        $description = isset($response['description']) ? $response['description'] : '';

        return $message . '. ' . $description;
    }

}
