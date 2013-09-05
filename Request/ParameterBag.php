<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Request;

/**
 * ParameterBag is a container for key/value pairs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class ParameterBag implements \IteratorAggregate, \Countable {
	/**
	 * Parameter storage.
	 *
	 * @var array
	 */
	protected $parameters;

	/**
	 * Constructor
	 *
	 * @param array $parameters
	 *
	 * @api
	 */
	public function __construct(array $parameters = array()) {
		$this->parameters = $parameters;
	}

	/**
	 * Returns the parameters
	 *
	 * @return array
	 *
	 * @api
	 */
	public function all() {
		return $this->parameters;
	}

	/**
	 * Returns the parameter keys
	 *
	 * @return array
	 *
	 * @api
	 */
	public function keys() {
		return array_keys($this->parameters);
	}

	/**
	 * Replaces the current parameters by a new set.
	 *
	 * @param array $parameters An array of parameters
	 *
	 * @api
	 */
	public function replace(array $parameters = array()) {
		$this->parameters = $parameters;
	}

	/**
	 * Adds parameters.
	 *
	 * @param array $parameters An array of parameters
	 *
	 * @api
	 */
	public function add(array $parameters = array()) {
		$this->parameters = array_replace($this->parameters, $parameters);
	}

	/**
	 * Returns a parameter by name.
	 *
	 * @param string  $path    The key
	 * @param mixed   $default The default value if the parameter key does not exist
	 * @param boolean $deep    If TRUE, a path like foo[bar] will find deeper items
	 *
	 * @return mixed
	 *
	 * @throws \InvalidArgumentException
	 *
	 * @api
	 */
	public function get($path, $default = NULL, $deep = FALSE) {

		if (!$deep || FALSE === $pos = strpos($path, '[')) {
			return array_key_exists($path, $this->parameters) ? $this->parameters[$path] : $default;
		}

		$root = substr($path, 0, $pos);
		if (!array_key_exists($root, $this->parameters)) {
			return $default;
		}

		$value = $this->parameters[$root];
		$currentKey = NULL;
		for ($i = $pos, $c = strlen($path); $i < $c; $i++) {
			$char = $path[$i];

			if ('[' === $char) {
				if (NULL !== $currentKey) {
					throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "[" at position %d.', $i));
				}

				$currentKey = '';
			} elseif (']' === $char) {
				if (NULL === $currentKey) {
					throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "]" at position %d.', $i));
				}

				if (!is_array($value) || !array_key_exists($currentKey, $value)) {
					return $default;
				}

				$value = $value[$currentKey];
				$currentKey = NULL;
			} else {
				if (NULL === $currentKey) {
					throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "%s" at position %d.', $char, $i));
				}

				$currentKey .= $char;
			}
		}

		if (NULL !== $currentKey) {
			throw new \InvalidArgumentException(sprintf('Malformed path. Path must end with "]".'));
		}

		return $value;
	}

	/**
	 * Sets a parameter by name.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @api
	 */
	public function set($key, $value) {

		$this->parameters[$key] = $value;

	}

	/**
	 * Returns TRUE if the parameter is defined.
	 *
	 * @param string $key The key
	 *
	 * @return Boolean TRUE if the parameter exists, FALSE otherwise
	 *
	 * @api
	 */
	public function has($key) {

		return array_key_exists($key, $this->parameters);

	}

	/**
	 * Removes a parameter.
	 *
	 * @param string $key The key
	 *
	 * @api
	 */
	public function remove($key) {

		unset($this->parameters[$key]);

	}

	/**
	 * Returns the alphabetic characters of the parameter value.
	 *
	 * @param string  $key     The parameter key
	 * @param mixed   $default The default value if the parameter key does not exist
	 * @param boolean $deep    If TRUE, a path like foo[bar] will find deeper items
	 *
	 * @return string The filtered value
	 *
	 * @api
	 */
	public function getAlpha($key, $default = '', $deep = FALSE) {

		return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default, $deep));

	}

	/**
	 * Returns the alphabetic characters and digits of the parameter value.
	 *
	 * @param string  $key     The parameter key
	 * @param mixed   $default The default value if the parameter key does not exist
	 * @param boolean $deep    If TRUE, a path like foo[bar] will find deeper items
	 *
	 * @return string The filtered value
	 *
	 * @api
	 */
	public function getAlnum($key, $default = '', $deep = FALSE) {

		return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default, $deep));

	}

	/**
	 * Returns the digits of the parameter value.
	 *
	 * @param string  $key     The parameter key
	 * @param mixed   $default The default value if the parameter key does not exist
	 * @param boolean $deep    If TRUE, a path like foo[bar] will find deeper items
	 *
	 * @return string The filtered value
	 *
	 * @api
	 */
	public function getDigits($key, $default = '', $deep = FALSE) {

		// we need to remove - and + because they're allowed in the filter
		return str_replace(array('-', '+'), '', $this->filter($key, $default, $deep, FILTER_SANITIZE_NUMBER_INT));

	}

	/**
	 * Returns the parameter value converted to integer.
	 *
	 * @param string  $key     The parameter key
	 * @param mixed   $default The default value if the parameter key does not exist
	 * @param boolean $deep    If TRUE, a path like foo[bar] will find deeper items
	 *
	 * @return integer The filtered value
	 *
	 * @api
	 */
	public function getInt($key, $default = 0, $deep = FALSE) {

		return (int) $this->get($key, $default, $deep);

	}

	/**
	 * Filter key.
	 *
	 * @param string  $key     Key.
	 * @param mixed   $default Default = NULL.
	 * @param boolean $deep    Default = FALSE.
	 * @param integer $filter  FILTER_* constant.
	 * @param mixed   $options Filter options.
	 *
	 * @see http://php.net/manual/en/function.filter-var.php
	 *
	 * @return mixed
	 */
	public function filter($key, $default = NULL, $deep = FALSE, $filter = FILTER_DEFAULT, $options = array()) {

		$value = $this->get($key, $default, $deep);

		// Always turn $options into an array - this allows filter_var option shortcuts.

		if (!is_array($options) && $options) {
			$options = array('flags' => $options);
		}

		// Add a convenience check for arrays.

		if (is_array($value) && !isset($options['flags'])) {
			$options['flags'] = FILTER_REQUIRE_ARRAY;
		}

		return filter_var($value, $filter, $options);

	}

	/**
	 * Returns an iterator for parameters.
	 *
	 * @return \ArrayIterator An \ArrayIterator instance
	 */
	public function getIterator() {

		return new \ArrayIterator($this->parameters);

	}

	/**
	 * Returns the number of parameters.
	 *
	 * @return int The number of parameters
	 */
	public function count() {

		return count($this->parameters);

	}
}