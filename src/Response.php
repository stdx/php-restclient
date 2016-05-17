<?php

namespace RestClient;

use ArrayAccess;
use Iterator;

class Response implements Iterator, ArrayAccess {

  /**
   * The response body.
   *
   * @var mixed
   */
  private $response;

  /**
   * Parsed response header object.
   *
   * @var array
   */
  private $headers;

  /**
   * Response info object.
   *
   * @var mixed
   */
  private $info;

  /**
   * Response error string
   *
   * @var mixed
   */
  private $error;

  /**
   * Decoded response body. Populated as-needed.
   *
   * @var
   */
  private $decoded_response;

  /**
   * @var array
   */
  private $options;

  /**
   * Response constructor.
   *
   * @param array $options
   */
  public function __construct($options = array()) {
    $this->options = $options;
  }

  /**
   * @return mixed
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * @param mixed $response
   */
  public function setResponse($response) {
    $this->response = $response;
  }

  /**
   * @return mixed
   */
  public function getHeaders() {
    return $this->headers;
  }

  /**
   * @param mixed $headers
   */
  public function setHeaders($headers) {
    $this->headers = $headers;
  }

  /**
   * @return mixed
   */
  public function getInfo() {
    return $this->info;
  }

  /**
   * @param mixed $info
   */
  public function setInfo($info) {
    $this->info = $info;
  }

  /**
   * @return mixed
   */
  public function getError() {
    return $this->error;
  }

  /**
   * @param mixed $error
   */
  public function setError($error) {
    $this->error = $error;
  }

  /**
   * Return the current element
   *
   * @link http://php.net/manual/en/iterator.current.php
   * @return mixed Can return any type.
   * @since 5.0.0
   */
  public function current() {
    return current($this->decoded_response);
  }

  /**
   * Move forward to next element
   *
   * @link http://php.net/manual/en/iterator.next.php
   * @return void Any returned value is ignored.
   * @since 5.0.0
   */
  public function next() {
    return next($this->decoded_response);
  }

  /**
   * Return the key of the current element
   *
   * @link http://php.net/manual/en/iterator.key.php
   * @return mixed scalar on success, or null on failure.
   * @since 5.0.0
   */
  public function key() {
    return key($this->decoded_response);
  }

  /**
   * Checks if current position is valid
   *
   * @link http://php.net/manual/en/iterator.valid.php
   * @return boolean The return value will be casted to boolean and then evaluated.
   * Returns true on success or false on failure.
   * @since 5.0.0
   */
  public function valid() {
    return is_array($this->decoded_response)
    && (key($this->decoded_response) !== null);
  }

  /**
   * Rewind the Iterator to the first element
   *
   * @link http://php.net/manual/en/iterator.rewind.php
   * @return void Any returned value is ignored.
   * @since 5.0.0
   */
  public function rewind() {
    $this->decodeResponse();

    return reset($this->decoded_response);
  }

  /**
   * @return mixed
   * @throws RestClientException
   */
  public function decodeResponse() {
    if (empty($this->decoded_response)) {
      $format = $this->get_response_format();


      if (!array_key_exists($format, $this->options['decoders']))
        throw new RestClientException("'${format}' is not a supported " .
          "format, register a decoder to handle this response.");

      $this->decoded_response = call_user_func(
        $this->options['decoders'][$format], $this->response);
    }

    return $this->decoded_response;
  }


  /**
   * @return mixed
   * @throws RestClientException
   */
  private function get_response_format() {
    if (!$this->response)
      throw new RestClientException(
        "A response must exist before it can be decoded.");

    // User-defined format.
    if (!empty($this->options['format']))
      return $this->options['format'];


    // Extract format from response content-type header.
    if (!empty($this->headers['content_type']) &&
      preg_match($this->options['format_regex'], $this->headers['content_type'], $matches)
    ) {
      return $matches[2];
    }

    throw new RestClientException(
      "Response format could not be determined.");
  }

  /**
   * Offset to retrieve
   *
   * @link http://php.net/manual/en/arrayaccess.offsetget.php
   *
   * @param mixed $offset <p>
   * The offset to retrieve.
   * </p>
   *
   * @return mixed Can return all value types.
   * @since 5.0.0
   */
  public function offsetGet($offset) {
    $this->decodeResponse();
    if (!$this->offsetExists($offset))
      return null;

    return is_array($this->decoded_response) ?
      $this->decoded_response[$offset] : $this->decoded_response->{$offset};
  }

  /**
   * Whether a offset exists
   *
   * @link http://php.net/manual/en/arrayaccess.offsetexists.php
   *
   * @param mixed $offset <p>
   * An offset to check for.
   * </p>
   *
   * @return boolean true on success or false on failure.
   * </p>
   * <p>
   * The return value will be casted to boolean if non-boolean was returned.
   * @since 5.0.0
   */
  public function offsetExists($offset) {
    $this->decodeResponse();

    return is_array($this->decoded_response) ?
      isset($this->decoded_response[$offset]) : isset($this->decoded_response->{$offset});
  }

  /**
   * Offset to set
   *
   * @link http://php.net/manual/en/arrayaccess.offsetset.php
   *
   * @param mixed $offset <p>
   * The offset to assign the value to.
   * </p>
   * @param mixed $value <p>
   * The value to set.
   * </p>
   *
   * @return void
   * @since 5.0.0
   */
  public function offsetSet($offset, $value) {
    throw new RestClientException("Decoded response data is immutable.");
  }

  /**
   * Offset to unset
   *
   * @link http://php.net/manual/en/arrayaccess.offsetunset.php
   *
   * @param mixed $offset <p>
   * The offset to unset.
   * </p>
   *
   * @return void
   * @since 5.0.0
   */
  public function offsetUnset($offset) {
    throw new RestClientException("Decoded response data is immutable.");
  }
}