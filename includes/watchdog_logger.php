<?php

use Guzzle\Log;


/**
 * Adapts a Watchdog logger object for guzzle requests
 */
class BoxAPIWatchdogLogAdapter implements Guzzle\Log\LogAdapterInterface {
  /**
   *
   */
  public function log($message, $priority = LOG_INFO, $extras = null) {
    watchdog('box_api', $message, array(), $priority);
  }
}

