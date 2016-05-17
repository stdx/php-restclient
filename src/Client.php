<?php

namespace RestClient;

/**
 * The client.
 *
 * @package RestClient
 */
class Client {

  private $options;


  /**
   * Client constructor.
   *
   * @param array $options
   */
  public function __construct($options = array()) {
    $default_options = array(
      'headers' => array(),
      'parameters' => array(),
      'curl_options' => array(),
      'user_agent' => "PHP RestClient/0.1.4",
      'base_url' => null,
      'format' => null,
      'format_regex' => "/(\w+)\/(\w+)(;[.+])?/",
      'decoders' => array(
        'json' => 'json_decode',
        'php' => 'unserialize'
      ),
      'username' => null,
      'password' => null
    );

    $this->options = array_merge($default_options, $options);
    if (array_key_exists('decoders', $options))
      $this->options['decoders'] = array_merge(
        $default_options['decoders'], $options['decoders']);
  }

  /**
   * @param $key
   * @param $value
   */
  public function setOption($key, $value) {
    $this->options[$key] = $value;
  }

  /**
   *  Decoder callbacks must adhere to the following pattern:
   *  array my_decoder(string $data)
   *
   * @param $format
   * @param $method
   */
  public function registerDecoder($format, $method) {
    $this->options['decoders'][$format] = $method;
  }


  /**
   * Execute a GET request.
   *
   * @param $url
   * @param array $parameters
   * @param array $headers
   *
   * @return Response
   */
  public function get($url, $parameters = array(), $headers = array()) {
    return $this->execute($url, 'GET', $parameters, $headers);
  }

  /**
   * Execute the request.
   *
   * @param $url
   * @param string $method
   * @param array $parameters
   * @param array $headers
   *
   * @return Response
   */
  public function execute($url, $method = 'GET', $parameters = array(), $headers = array()) {
    $requestUrl = $url;

    $curlopt = array(
      CURLOPT_HEADER => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_USERAGENT => $this->options['user_agent']
    );

    if ($this->options['username'] && $this->options['password'])
      $curlopt[CURLOPT_USERPWD] = sprintf("%s:%s",
        $this->options['username'], $this->options['password']);

    if (count($this->options['headers']) || count($headers)) {
      $curlopt[CURLOPT_HTTPHEADER] = array();
      $headers = array_merge($this->options['headers'], $headers);
      foreach ($headers as $key => $value) {
        $curlopt[CURLOPT_HTTPHEADER][] = sprintf("%s:%s", $key, $value);
      }
    }

    if ($this->options['format']) {
      $requestUrl .= '.' . $this->options['format'];
    }


    // Allow passing parameters as a pre-encoded string (or something that
    // allows casting to a string). Parameters passed as strings will not be
    // merged with parameters specified in the default options.
    if (is_array($parameters)) {
      $parameters = array_merge($this->options['parameters'], $parameters);
      $parameters_string = $this->formatQuery($parameters);
    } else
      $parameters_string = (string)$parameters;

    if (strtoupper($method) == 'POST') {
      $curlopt[CURLOPT_POST] = true;
      $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;
    } elseif (strtoupper($method) != 'GET') {
      $curlopt[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
      $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;
    } elseif ($parameters_string) {
      $requestUrl .= strpos($url, '?') ? '&' : '?';
      $requestUrl .= $parameters_string;
    }

    if ($this->options['base_url']) {
      if ($requestUrl[0] != '/' && substr($this->options['base_url'], -1) != '/') {
        $requestUrl = '/' . $requestUrl;
      }
      $requestUrl = $this->options['base_url'] . $requestUrl;
    }
    $curlopt[CURLOPT_URL] = $requestUrl;

    if ($this->options['curl_options']) {
      // array_merge would reset our numeric keys.
      foreach ($this->options['curl_options'] as $key => $value) {
        $curlopt[$key] = $value;
      }
    }

    return $this->parse_response($curlopt);
  }

  /**
   * Format the query string.
   *
   * @param $parameters
   * @param string $primary
   * @param string $secondary
   *
   * @return string
   */
  private function formatQuery($parameters, $primary = '=', $secondary = '&') {
    $query = "";
    foreach ($parameters as $key => $value) {
      $pair = array(urlencode($key), urlencode($value));
      $query .= implode($primary, $pair) . $secondary;
    }

    return rtrim($query, $secondary);
  }

  /**
   * @param array $curlOptions
   *
   * @return Response
   */
  private function parse_response($curlOptions) {

    $handle = curl_init();
    curl_setopt_array($handle, $curlOptions);

    $result = curl_exec($handle);
    $info = (object)curl_getinfo($handle);
    $error = curl_error($handle);

    curl_close($handle);

    $headers = array();
    $http_ver = strtok($result, "\n");

    while ($line = strtok("\n")) {
      if (strlen(trim($line)) == 0) break;

      list($key, $value) = explode(':', $line, 2);
      $key = trim(strtolower(str_replace('-', '_', $key)));
      $value = trim($value);
      if (empty($headers[$key]))
        $headers[$key] = $value;
      elseif (is_array($headers[$key]))
        $headers[$key][] = $value;
      else
        $headers[$key] = array($headers[$key], $value);
    }

    $response = new Response($this->options);
    $response->setHeaders($headers);
    $response->setResponse(strtok(""));
    $response->setError($error);
    $response->setInfo($info);

    return $response;
  }

  /**
   * Execute a POST request.
   *
   * @param $url
   * @param array $parameters
   * @param array $headers
   *
   * @return Response
   */
  public function post($url, $parameters = array(), $headers = array()) {
    return $this->execute($url, 'POST', $parameters, $headers);
  }

  /**
   * Execute a PUT request.
   *
   * @param $url
   * @param array $parameters
   * @param array $headers
   *
   * @return Response
   */
  public function put($url, $parameters = array(), $headers = array()) {
    return $this->execute($url, 'PUT', $parameters, $headers);
  }

  /**
   * Execute a DELETE request.
   *
   * @param $url
   * @param array $parameters
   * @param array $headers
   *
   * @return Response
   */
  public function delete($url, $parameters = array(), $headers = array()) {
    return $this->execute($url, 'DELETE', $parameters, $headers);
  }


}


