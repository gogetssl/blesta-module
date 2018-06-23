<?php

namespace MgGoGetSsl\Util;

final class HttpHelper
{

    private function __clone() {}
    private function __construct() {}
    private function __wakeup() {}

    /**
     * Whether request is AJAX
     *
     * @return bool
     */
    public static function isAjaxRequest()
    {
        $server = filter_input_array(INPUT_SERVER);

        return isset($server['HTTP_X_REQUESTED_WITH']) && strtolower($server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get current request full URL
     *
     * @return string
     */
    public static function getFullUrl()
    {
        $server = filter_input_array(INPUT_SERVER);
        $uri = $server['REQUEST_URI'];

        if (substr($uri, 0, 1) == '/') {
            $uri = substr($uri, 1);
        }

        return sprintf('http%s://%s/%s', isset($server['HTTPS']) ? 's' : '', $server['HTTP_HOST'], $uri);
    }

    /**
     * Redirect to given location
     *
     * @param string $url
     */
    public static function redirect($url)
    {
        ob_clean();
        header(sprintf('Location: %s', $url));
        die;
    }

    /**
     * Get request method (e.g. GET)
     *
     * @return string
     */
    public static function getRequestMethod()
    {
        return strtoupper(filter_input(INPUT_SERVER, 'REQUEST_METHOD'));
    }

    /**
     * Whether request is POST
     *
     * @return bool
     */
    public static function isPostRequest()
    {
        return strcasecmp(filter_input(INPUT_SERVER, 'REQUEST_METHOD'), 'POST') === 0;
    }

    /**
     * @return null|string
     */
    public static function getClientIP()
    {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } else if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP);
    }

}
