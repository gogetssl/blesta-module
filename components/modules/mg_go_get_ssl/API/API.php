<?php

namespace MgGoGetSsl\API;

use MgGoGetSsl\Cache\Cache;

abstract class API
{

    /** @var resource */
    private $handler;

    /** @var string */
    private $method;

    /** @var string */
    private $url;

    /** @var array */
    private $data = [];

    /** @var mixed */
    private $response;

    /** @var array */
    private $curlInfo;

    /** @var string */
    private $error;

    /** @var string */
    private $user;

    /** @var string */
    private $password;

    /** @var Cache */
    private $cache;

    /** @var bool */
    private $useCache = false;

    /** @var array */
    private $headers = [];

    /** @var array */
    private $opts = [];

    /** @var bool */
    protected $dump = false;

    /** @var bool */
    protected $throwExceptions = false;

    /**
     * @return bool
     */
    abstract public function isSuccess();

    /**
     * @return string
     */
    abstract public function errorMessage();

    /**
     * Set request params
     *
     * @param array $params
     * @return self
     */
    public function setParams(array $params)
    {
        if (isset($params['method'])) {
            $this->method = strtoupper($params['method']);
        }
        if (isset($params['data'])) {
            $this->data = $params['data'];
        }
        if (isset($params['url'])) {
            $this->url = $params['url'];
        }
        if (isset($params['password']) && isset($params['user'])) {
            $this->password = $params['password'];
            $this->user = $params['user'];
        }

        return $this;
    }

    /**
     * @param Cache $cache
     * @return self
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * @param string $header
     * @param string $value
     * @return self
     */
    public function setHeader($header, $value)
    {
        $this->headers[] = sprintf('%s: %s', $header, $value);
        return $this;
    }

    /**
     * @param mixed $option
     * @param mixed $value
     * @return self
     */
    public function setCurlOption($option, $value)
    {
        $this->opts[$option] = $value;
        return $this;
    }

    /**
     * @param string $url
     * @return string
     */
    protected function getCacheKey($url)
    {
        if (strpos($url, 'http') !== false) {
            $parts = parse_url($url);
            $url = '_url_' . (!empty($parts['query']) ? $parts['query'] : '');
        }

        return str_replace(['/', '@', '.', ',', '?', '&', '=', '[', ']', ':'], '-', urldecode($url));
    }

    /**
     * @return bool
     */
    private function isJsonRequest()
    {
        foreach ($this->headers as $header) {
            if (stripos($header, 'Content-Type') !== false
                && stripos($header, 'application/json') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make a custom request
     *
     * @throws \Exception
     * @param string      $method
     * @param string|null $url
     * @param array       $data
     */
    private function request($method, $url = null, array $data = [])
    {
        $this->error = null;
        $this->method = strtoupper($method);

        if (is_callable($callback = [$this, 'extendRequestData'])) {
            $data = call_user_func_array($callback, [$data]);
        }

        if (!empty($data)) {
            if ($this->isJsonRequest()) {
                $this->data = json_encode($data);
            } else {
                $this->data = http_build_query($data);
            }
        }
        if (substr($url, -1, 1) != '/' && ($this->method != 'GET' || empty($data))) {
//            $url .= '/';
        }
        if ($this->method == 'GET' && !empty($data)) {
            $url = sprintf('%s?%s', $url, http_build_query($data));
        }

        if ($this->useCache && $this->cache->has($this->getCacheKey($url))) {
            $this->response = $this->cache->get($this->getCacheKey($url));
            $this->curlInfo['http_code'] = 200;
            $this->useCache = false;
            return;
        }

        $this->handler = curl_init();

        $headers = $this->headers;

        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $this->method,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 15,
        ];

        if (!empty($this->opts)) {
            $opts += $this->opts;
        }

        if (!empty($this->password) && !empty($this->user)) {
            $opts[CURLOPT_USERPWD] = sprintf('%s:%s', $this->user, $this->password);
        }
        
        if (in_array($this->method, ['POST', 'PUT', 'PATCH'])) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $this->data;

            //$headers[] = sprintf('Content-Length: %s', strlen($this->data));
        }

        if (is_callable($callback = [$this, 'extendHeaders'])) {
            $headers = call_user_func_array($callback, [$headers]);
        }

        if (!empty($headers)) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($this->handler, $opts);

        $this->response = curl_exec($this->handler);
        $this->curlInfo = curl_getinfo($this->handler);
        
        if (curl_errno($this->handler)) {
            $this->error = curl_error($this->handler);
        
            if ($this->throwExceptions) {
                throw new \Exception($this->error);
            }
        }

        curl_close($this->handler);

        if ($this->dump) {
            echo 'URL:<pre>';
            print_r(urldecode($url));
            echo '</pre>';

            echo 'ERROR: <pre>';
            var_dump($this->error);
            echo '</pre>';

            if (!empty($this->data)) {
                echo 'DATA: <pre>';
                print_r($this->data);
                echo '</pre>';
            }

            echo '<pre>';
            print_r($this->curlInfo);
            echo '</pre>';
            
            echo 'RESPONSE: <pre>';
            print_r($this->response);
            echo '</pre>'; die;
        }

        if ($this->useCache) {
            $this->cache->set($this->getCacheKey($url), $this->response);
            $this->useCache = false;
        }
    }

    /**
     * Use cache for current request
     */
    protected function cacheRequest()
    {
        $this->cache instanceof Cache && ($this->useCache = true);
    }

    /**
     * @return int|null
     */
    protected function httpCode()
    {
        return isset($this->curlInfo['http_code']) ? $this->curlInfo['http_code'] : null;
    }

    /**
     * Get request response
     *
     * @param bool $jsonDecode
     * @param bool $asArray
     * @return array|string
     */
    protected function response($jsonDecode = true, $asArray = true)
    {   
        return $jsonDecode ? json_decode($this->response, $asArray) : $this->response;
    }

    /**
     * @return string|null
     */
    protected function error()
    {
        return $this->error;
    }

    /**
     * @param string|null $method
     * @return string
     */
    protected function method($method = null)
    {
        if (!empty($method)) {
            $this->method = $method;
        }

        return $this->method;
    }

    /**
     * Send GET request
     *
     * @throws \Exception
     * @param string $url
     * @param array  $data
     * @param bool   $absoluteUrl
     * @return $this
     */
    protected function sendGET($url, array $data = [], $absoluteUrl = false)
    {
        $this->request('GET', $absoluteUrl ? $url : $this->url . $url, $data);
        return $this;
    }

    /**
     * Send PUT request
     *
     * @throws \Exception
     * @param string $url
     * @param array  $data
     * @param bool   $absoluteUrl
     * @return $this
     */
    protected function sendPUT($url, array $data = [], $absoluteUrl = false)
    {
        $this->request('PUT', $absoluteUrl ? $url : $this->url . $url, $data);
        return $this;
    }

    /**
     * Send POST request
     *
     * @throws \Exception
     * @param string $url
     * @param array  $data
     * @param bool   $absoluteUrl
     * @return $this
     */
    protected function sendPOST($url, array $data = [], $absoluteUrl = false)
    {
        $this->request('POST', $absoluteUrl ? $url : $this->url . $url, $data);
        return $this;
    }

    /**
     * Send POST request
     *
     * @throws \Exception
     * @param string $url
     * @param array  $data
     * @param bool   $absoluteUrl
     * @return $this
     */
    protected function sendPATCH($url, array $data = [], $absoluteUrl = false)
    {
        $this->request('PATCH', $absoluteUrl ? $url : $this->url . $url, $data);
        return $this;
    }

    /**
     * Send DELETE request
     *
     * @throws \Exception
     * @param string $url
     * @param array  $data
     * @param bool   $absoluteUrl
     * @return $this
     */
    protected function sendDELETE($url, array $data = [], $absoluteUrl = false)
    {
        $this->request('DELETE', $absoluteUrl ? $url : $this->url . $url);
        return $this;
    }

}
