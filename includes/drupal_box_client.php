<?php

use Box\BoxAPI\BoxAPIClient;

/**
 * @file
 * Drupal wrapper for box_api client. Provides optional caching for methods.
 */

class DrupalBoxAPIClient {
  private $client;
  private $doCache;
  private $cacheTime;


  public function __construct(BoxAPIClient $client, $do_cache = FALSE, $cache_time = 3600) {
    $this->doCache = $do_cache;
    $this->client = $client;
    $this->cacheTime = $cache_time;
  }

  public function __call($name, $arguments) {
    if ($this->doCache) {
      $cid = $name . ':' . md5(serialize($arguments));

      if ($cache = $this->get($cid)) {
        return $cache->data;
      }
      else {
        $result = call_user_func_array(array($this->client, $name), $arguments);
        $this->set($cid, $result);

        return $result;
      }
    }
    else {
      return call_user_func_array(array($this->client, $name), $arguments);
    }
  }

  /**
   * Get an item from cache only if not expired.
   *
   * @param $id string The cache ID.
   * @return mixed The cache object.
   */
  private function get($id) {
    $cache = cache_get($id, 'cache_box_object');
    return ($cache && $this->cacheExpired($cache)) ? FALSE : $cache;
  }

  /**
   * Return whether a cache is expired.
   *
   * @param $cache object The cache object.
   */
  private function cacheExpired($cache) {
    return $cache->expire < REQUEST_TIME;
  }

  /**
   * Save data to the cache.
   *
   * @param $id string The cache ID.
   * @param $data object The data to be cached.
   * @return mixed
   */
  private function set($id, $data) {
    $expiration = $this->getExpirationTime();
    return cache_set($id, $data, 'cache_box_object', $expiration);
  }

  /**
   * Get the timestamp when an item should expire.
   *
   * @return int Unix timestamp.
   */
  private function getExpirationTime() {
      return REQUEST_TIME + $this->cacheTime;
  }
}
