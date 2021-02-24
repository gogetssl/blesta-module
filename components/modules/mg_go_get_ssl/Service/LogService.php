<?php

namespace MgGoGetSsl\Service;

/**
 * @property Record $Record
 */
class LogService
{

    const LOGS_TABLE = 'go_get_ssl_logs';

    const SUCCESS = 'success';
    const ERROR = 'error';
    const INFO = 'info';
    const WARN = 'warning';

    const NAMESPACE_API = 'api';
    const NAMESPACE_EMAILS = 'emails';
    const NAMESPACE_PACKAGES = 'packages';
    const NAMESPACE_INSTALLATION = 'installation';
    const NAMESPACE_CERT_CANCEL = 'cert_cancel';
    const NAMESPACE_CERT_GENERATE = 'cert_generate';
    const NAMESPACE_CERT_REISSUE = 'cert_reissue';
    const NAMESPACE_CERT_DETAILS = 'cert_details';
    const NAMESPACE_CERT_CONTACTS = 'cert_contacts';
    const NAMESPACE_CERT_RENEW = 'cert_renew';
    const NAMESPACE_CRON = 'cron';

    /**
     * LogService constructor
     */
    public function __construct()
    {
        \Loader::loadComponents($this, ['Record']);
    }

    /**
     * @param string|null $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public function logError($title, $namespace = null, $data = null, $function = null)
    {
        $this->log(self::ERROR, $title, $namespace, $data, $function);
    }

    /**
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public function logSuccess($title, $namespace = null, $data = null, $function = null)
    {
        $this->log(self::SUCCESS, $title, $namespace, $data, $function);
    }

    /**
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public function logInfo($title, $namespace = null, $data = null, $function = null)
    {
        $this->log(self::INFO, $title, $namespace, $data, $function);
    }

    /**
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public function logWarning($title, $namespace = null, $data = null, $function = null)
    {
        $this->log(self::WARN, $title, $namespace, $data, $function);
    }

    /**
     * @param string      $type
     * @param string      $title
     * @param string|null $namespace
     * @param null        $data
     * @param string|null $function
     */
    public function log($type, $title, $namespace = null, $data = null, $function = null)
    {
        if (is_array($data)) {
            $data = print_r($data, true);
        } else if ($data instanceof \stdClass) {
            $data = print_r((array) $data, true);
        } else if (!empty($data) && !is_string($data)) {
            $data = serialize($data);
        }

        if (empty($namespace)) {
            $namespace = 'general';
        }

        $debug = debug_backtrace();
        $previous = isset($debug[3]) ? $debug[3] : [];

        if (empty($function)) {
            $function = sprintf('%s:%s()', isset($previous['class']) ? $previous['class'] : '', isset($previous['function']) ? $previous['function'] : '');
        }

        $this->Record->insert(self::LOGS_TABLE, [
            'title'      => $title,
            'namespace'  => $namespace,
            'type'       => $type,
            'function'   => $function,
            'data'       => $data,
            'created_at' => date('Y-m-d H:i:s', time()),
        ]);
    }


}
