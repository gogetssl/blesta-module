<?php

namespace MgGoGetSsl\Cache\Adapter;

use MgGoGetSsl\Cache\CacheAdapterInterface;

class DbCacheAdapter implements CacheAdapterInterface
{

    /**
     * @param string $key
     */
    public function get($key)
    {
        // TODO: Implement get() method.
    }

    /**
     * @param string $key
     * @param mixed  $item
     * @param int    $ttl
     * @return bool
     */
    public function set($key, $item, $ttl)
    {
        // TODO: Implement set() method.
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        // TODO: Implement has() method.
    }

    /**
     * @param string $key
     * @return bool
     */
    public function remove($key)
    {
        // TODO: Implement remove() method.
    }

}