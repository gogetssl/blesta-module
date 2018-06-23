<?php

namespace MgGoGetSsl\Cache;

interface CacheAdapterInterface
{

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key);

    /**
     * @param string $key
     * @param        $item
     * @param int    $ttl
     * @return bool
     */
    public function set($key, $item, $ttl);

    /**
     * @param string $key
     * @return bool
     */
    public function has($key);

    /**
     * @param string $key
     * @return bool
     */
    public function remove($key);

}