<?php

namespace femto;

/**
 * Simple cache system.
 *
 * Usage:
 *
 * // create a cache object associated with "key"
 * $cache = new Cache('key');
 *
 * // check if the cache exists and is less than an hour old
 * if(($data = $cache->retrieve(time()-3600)) === null) {
 *     // cache didn't exist, populate $data and store it
 *     $data = 'foo';
 *     $cache->store($data);
 * }
 *
 * // empty the cache
 * $cache->purge();
 *
 * // display the cache's filename
 * echo $cache;
 *
 */
class Cache {
    /**
     * Default configuration.
     * - debug: whether debug is enabled. In debug mode cache will act as if
     *   it is always expired.
     * - dir: directory in which cached data are located
     * - raw: whether data is stored raw or as serialized php
     *
     * @var array
     */
    static public $default = [
        'debug' => False,
        'dir' => 'cache',
        'raw' => False,
    ];

    /**
     * Configuration of a specific cache instance.
     *
     * @var array
     */
    public $config;

    /**
     * File which contains the cached data.
     *
     * @var string
     */
    protected $cache_file;

    /**
     * Create a cache object associated with key.
     *
     * @param string $key Key associated to this cache instance
     * @param array $config Configuration
     */
    public function __construct($key, $config=[]) {
        $this->config = $config + self::$default;
        $hash = md5($key);
        $this->cache_file = sprintf(
          '%s/%s/%s/%s.cache',
          $this->config['dir'],
          substr($hash, 0, 2),
          substr($hash, 2, 2),
          $hash
        );
    }

    /**
     * Return data in the cache if any.
     *
     * @param int $modified Timestamp, when the original data was last modified
     * @return mixed Cached data, null if expired or not found
     */
    public function retrieve($modified) {
        if($this->valid($modified)) {
            $return = file_get_contents($this->cache_file);
            return $this->config['raw'] ? $return : unserialize($return);
        }
    }

    /**
     * Check whether the cache is valid.
     *
     * @param int $modified Timestamp, when the original data was last modified
     * @return bool True if valid, false otherwise
     */
    public function valid($modified) {
        if($this->config['debug']) return false;
        return @filemtime($this->cache_file) > $modified;
    }

    /**
     * Store data in the cache.
     *
     * @param mixed $value Data to cache
     */
    public function store($value) {
        @mkdir(dirname($this->cache_file), 0777, true);
        if(!$this->config['raw']) {
            $value = serialize($value);
        }
        file_put_contents($this->cache_file, $value);
    }

    /**
     * Purge data from the cache.
     *
     */
    public function purge() {
        @unlink($this->cache_file);
    }

    /**
     * Obtain the cache file for direct interaction.
     *
     */
    public function __toString() {
        return $this->cache_file;
    }
}

/**
 * Cache objet associated to a specific file. Modified time become the file's.
 *
 * // create a cache object associated with "key"
 * $cache = new Cache('path/to/file', 'key');
 *
 * // check if the cache exists and is less than an hour old
 * if(($data = $cache->retrieve()) === null) {
 *     // cache didn't exist, populate $data and store it
 *     $data = 'foo';
 *     $cache->store($data);
 * }
 *
 * // empty the cache
 * $cache->purge();
 *
 */
class FileCache extends Cache {
    /**
     * File associated to this cache object.
     *
     * @var string
     */
    protected $file;

    /**
     * Create a cache object associated with file (and optional key).
     *
     * @param string $file File associated to this cache instance
     * @param string $key Key associated to this cache instance
     * @param array $config Configuration
     */
    public function __construct($file, $key='', $config=[]) {
        $this->file = $file;
        parent::__construct($file.$key, $config);
    }

    /**
     * Return data in the cache if any.
     *
     * @param int $modified Timestamp, when the original data was last modified
     * @return mixed Cached data, null if expired or not found
     */
    public function retrieve($modified=null) {
        return parent::retrieve($modified);
    }

    /**
     * Check whether the cache is valid.
     *
     * @param int $modified Timestamp, when the original data was last modified
     * @return bool True if valid, false otherwise
     */
    public function valid($modified=null) {
        if($modified == null) {
            $modified = filemtime($this->file);
        }
        return parent::valid($modified);
    }
}
