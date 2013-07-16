<?php

use Guzzle\Http\Client;

/**
 * @file
 * Entity representing a set of Box API Credentials
 */

class BoxAPICreds extends Entity {
  public $id_key = '';
  public $token = '';
  public $client_id = '';
  public $client_secret = '';
  public $timestamp = REQUEST_TIME;
  public $expires = 3600;
  public $access_token = '';
  public $refresh_token = '';
  public $token_status = FALSE;
  public $options = array('redirect' => "<front>", 'auto_refresh' => TRUE);


  /**
   * Creates a new entity.
   *
   * @param $values
   *   An array of values to set, keyed by property name. If the entity type has
   *   bundles the bundle key has to be specified.
   */
  public function __construct(array $values = array()) {
    parent::__construct($values, 'box_api_creds');
  }

  /**
   * Set options by merge with default values.
   *
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   The options array.
   */
  public function setOptions($options) {
    return $this->options = array_merge($this->options, $options);
  }

  /**
   * Get options.
   *
   * @return array
   *   The options array.
   */
  public function getOptions() {
    return $this->options;
  }

  /**
   * Return a single option value.
   *
   * @param string $key
   *   The option to return.
   * @return mixed
   *   The option value if present or NULL if not found.
   */
  public function getOption($key) {
    if (isset($this->options[$key])) {
      return $this->options[$key];
    }
    return NULL;
  }

  /**
   * Set the Drupal token used to lookup this set of credentials after
   * successful authorization.
   */
  public function setDrupalToken() {
    $this->token = drupal_get_token($this->id_key);
    return $this->token;
  }

  /**
   * Check to see if this set of credentials has a valid set of tokens.
   *
   * @return bool
   */
  public function tokenIsValid() {
    if (empty($this->client_id) || empty($this->client_secret)) {
      watchdog('box_api', 'Attempting to use creds with no client ID or secret: @creds', array('@creds' => $this->id_key), WATCHDOG_ERROR);
      return FALSE;
    }

    if (!$this->token_status || empty($this->access_token) || empty($this->timestamp)) {
      if (empty($this->refresh_token)) {
        watchdog('box_api', 'Attempting to use creds with empty access token: @creds. Perform authorization with OAUTH2 first.', array('@creds' => $this->id_key), WATCHDOG_ERROR);
        return FALSE;
      }
      watchdog('box_api', 'Attempting to use creds with empty access token: @creds. Refresh token will be used to obtain new access token.', array('@creds' => $this->id_key), WATCHDOG_ERROR);
      return $this->refreshToken();
    }


    if (REQUEST_TIME < ($this->timestamp + $this->expires)) {
      return TRUE;
    }

    watchdog('box_api', 'Creds request with expired access token: @creds. Refresh token will be used to obtain new access token.', array('@creds' => $this->id_key), WATCHDOG_ERROR);
    return $this->refreshToken();

  }

  /**
   * Uses the refresh_token to updates the access token.
   */
  public function refreshToken() {
    if (REQUEST_TIME > ($this->timestamp + BOX_API_REFRESH_TOKEN_LIFETIME)) {
      watchdog('box_api', 'The refresh token for @creds has expired.', array('@creds' => $this->id_key), WATCHDOG_ERROR);
      $this->token_status = FALSE;
      $this->save();

      return $this->token_status;
    }
    $post_data = array(
      'grant_type' => 'refresh_token',
      'client_id' => str_replace('@', '', $this->client_id),
      'client_secret' => str_replace('@', '', $this->client_secret),
      'refresh_token' => str_replace('@', '', $this->refresh_token),
    );

    $client = new Client('https://api.box.com');
    $request = $client->post('/oauth2/token', NULL, $post_data);

    try {
      $response = $request->send();
    } catch (Guzzle\Http\Exception\BadResponseException $e) {
      $response = $e->getResponse();
      $response_data = json_decode($response->getBody());
      watchdog('box_api', 'Error retrieving access token from Box: !exception', array('!exception' => $e->getMessage()), WATCHDOG_ERROR);
      if (isset($response_data->error_description)) {
        drupal_set_message(t('There was a problem communicating with the Box API: !error', array('!error' => $response_data->error_description)), 'error');
      }
      else {
        drupal_set_message(t('There was a problem communicating with the Box API: !exception', array('!exception' => $e->getMessage())), 'error');
      }
      $this->token_status = FALSE;
      $this->save();

      return $this->token_status;
    }

    $response_data = json_decode($response->getBody());
    $this->access_token = $response_data->access_token;
    $this->refresh_token = $response_data->refresh_token;
    $this->token_status = TRUE;
    $this->timestamp = REQUEST_TIME;
    $this->expires = $response_data->expires_in;
    $this->save();

    return $this->token_status;
  }

  /**
   * Find a set of credentials by token.
   *
   * @param $token
   *   A token to lookup.
   * @return BoxAPICreds
   *   The loaded entity or false if not found.
   */
  static function findByToken($token) {
    $results = self::query()->propertyCondition('token', $token)->range(0, 1)->execute();

    if (isset($results['box_api_creds'])) {
      $ids = array_keys($results['box_api_creds']);
      return entity_load_single('box_api_creds', reset($ids));
    }
    return FALSE;
  }

  /**
   * Find a set of credentials by key.
   *
   * @param $key
   *   An key to lookup.
   * @return BoxAPICreds
   *   The loaded entity or false if not found.
   */
  static function findByKey($key) {
    $results = self::query()->propertyCondition('id_key', $key)->range(0, 1)->execute();

    if (isset($results['box_api_creds'])) {
      $ids = array_keys($results['box_api_creds']);
      return entity_load_single('box_api_creds', reset($ids));
    }
    return FALSE;
  }

  /**
   * Find a set of credentials by key or return a new credential set.
   *
   * This function always returns a valid BoxAPICreds object.
   *
   * @param $key
   *   An key to lookup.
   * @return BoxAPICreds
   *   The loaded entity or a new entity.
   */
  static function findByKeyOrNew($key) {
    $creds = self::findByKey($key);

    if (!$creds) {
      $creds = new BoxAPICreds;
      $creds->id_key = $key;
    }

    return $creds;
  }

  /**
   * Find all credentials.
   *
   * @return BoxAPICreds
   *   The loaded entities or an empty array if no items are found.
   */
  static function all() {
    $results = self::query()->execute();

    if (isset($results['box_api_creds'])) {
      $ids = array_keys($results['box_api_creds']);
      return entity_load('box_api_creds', $ids);
    }
    return array();
  }

  /**
   * Find all credentials.
   *
   * @param $id
   *   The ID to lookup.
   * @return BoxAPICreds
   *   The loaded entity.
   */
  static function findById($id) {
    $results = self::query()->propertyCondition('id', $id)->range(0, 1)->execute();

    if (isset($results['box_api_creds'])) {
      $ids = array_keys($results['box_api_creds']);
      return entity_load_single('box_api_creds', reset($ids));
    }
    return FALSE;
  }


  /**
   * Return a preset EntityFieldQuery
   *
   * @return EntityFieldQuery
   */
  static function query() {
    $query = new EntityFieldQuery;
    $query->entityCondition('entity_type', 'box_api_creds');
    return $query;
  }

  function label() {
    return '';
  }

  function uri() {
    return '';
  }
}
