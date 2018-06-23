<?php

namespace MgGoGetSsl\Cache\Adapter;

use MgGoGetSsl\Util\FileReader;
use MgGoGetSsl\Application\Runtime;
use MgGoGetSsl\Cache\CacheAdapterInterface;

class FileCacheAdapter implements CacheAdapterInterface
{

    /** @var string */
    private $cachePath;

    /** @var FileReader */
    private $fileReader;

    /**
     * FileCacheAdapter constructor
     *
     * @throws \Exception
     * @param FileReader $fileReader
     */
    public function __construct(FileReader $fileReader)
    {
        $this->fileReader = $fileReader;
        $this->init();
    }

    /**
     * @param string $key
     * @throws \Exception
     */
    private function validateCacheKey($key)
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
            throw new \Exception(sprintf('%s is not a valid cache key', $key));
        }
    }

    /**
     * Init file cache
     *
     * @throws \Exception
     */
    private function init()
    {
        $this->cachePath = Runtime::config()
            ->configKey('cache_path');

        if (!is_dir($this->cachePath)) {
            if (!mkdir($this->cachePath, 0777)) {
                throw new \Exception(sprintf('Could not create cache directory in %s', $this->cachePath));
            }
        }
        if (!is_writable($this->cachePath)) {
            throw new \Exception(sprintf('Cache path %s is not writable', $this->cachePath));
        }
    }

    /**
     * @param mixed $item
     * @return string
     */
    private function parseItem($item)
    {
        if (is_array($item)) {
            return serialize($item);
        } else if (is_object($item)) {
            return serialize(json_decode(json_encode($item), true));
        } else {
            return (string) $item;
        }
    }

    /**
     * @param string $searchKey
     * @param bool   $removeExpired
     * @return string|null
     */
    private function searchForCachedElement($searchKey, $removeExpired = false)
    {
        foreach ($this->fileReader->readLocation($this->cachePath) as $item) {
            if (strpos($item, '@') === false) {
                continue;
            }

            $parts = explode('@', $item);
            $key = $parts[0];
            $timestamp = $parts[1];

            if ($key != $searchKey) {
                continue;
            }

            $filePath = sprintf('%s/%s', $this->cachePath, $item);

            if (!$removeExpired) {
                return $filePath;
            }

            $currentTimestamp = (new \DateTime())
                ->getTimestamp();

            if ($timestamp < $currentTimestamp) {
                $this->fileReader->unlink($filePath);
                break;
            }

            return $filePath;
        }

        return null;
    }

    /**
     * @throws \Exception
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        if ($currentCacheFile = $this->searchForCachedElement($key, true)) {
            return $this->fileReader->fileContent($currentCacheFile);
        }

        throw new \Exception(sprintf('There is no cached element for key %s', $key));
    }

    /**
     * @throws \Exception
     * @param string $key
     * @param mixed  $item
     * @param int    $ttl
     * @return bool
     */
    public function set($key, $item, $ttl)
    {
        $this->validateCacheKey($key);

        $timestamp = (new \DateTime())
            ->add(new \DateInterval(sprintf('PT%sS', $ttl)))
            ->getTimestamp();

        $this->remove($key);

        $filePath = sprintf('%s/%s@%s.dat', $this->cachePath, $key, $timestamp);
        $this->fileReader->init($filePath, FileReader::MODE_WRITE_CREATE, false);
        $this->fileReader->writeLine($this->parseItem($item), false);
        $this->fileReader->closeFile();

        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return is_string($this->searchForCachedElement($key, true));
    }

    /**
     * @param string $key
     * @return bool
     */
    public function remove($key)
    {
        if ($currentCacheFile = $this->searchForCachedElement($key)) {
            return $this->fileReader->unlink($currentCacheFile);
        }

        return true;
    }

}
