<?php
namespace vxPHP\Form;

/**
 * ValuesBag is a container for key/value pairs for form values
 */
class ValuesBag implements \IteratorAggregate, \Countable, \ArrayAccess {
	/**
	 * the value
	 * @var array
	 */
	protected $values;

	/**
	 * Constructor
	 * @param array $values
	 */
	public function __construct(array $values = array()) {

		$this->values = $values;

	}

	/**
	 * returns all values
	 * @return array
	 */
	public function all() {

		return $this->values;

	}

	/**
	 * returns the values keys
	 * @return array
	 */
	public function keys() {

		return array_keys($this->values);

	}

	/**
	 * replaces the current values with a new set
	 * @param array $values
	 */
	public function replace(array $values = array()) {

		$this->values = $values;

	}

	/**
	 * adds values to the existing ones
	 * @param array $values
	 */
	public function add(array $values = array()) {

		$this->values = array_replace($this->values, $values);

	}

	/**
	 * returns a value by name
	 * falls back to $default value, when key does not exist
	 *
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return mixed
	 *
	 * @throws \InvalidArgumentException
	 */
	public function get($key, $default = NULL) {

		return array_key_exists($key, $this->values) ? $this->values[$key] : $default;

	}

	/**
	 * set a value by name
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set($key, $value) {

		$this->values[$key] = $value;

	}

	/**
	 * returns TRUE if the value is defined
	 *
	 * @param string $key
	 * @return Boolean
	 */
	public function has($key) {

		return array_key_exists($key, $this->values);

	}

	/**
	 * removes a value identified by $key
	 * 
	 * @param string $key
	 */
	public function remove($key) {

		unset($this->values[$key]);

	}

	/**
	 * {@inheritDoc}
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator() {

		return new \ArrayIterator($this->values);

	}

	/**
	 * 
	 * {@inheritDoc}
	 * @see Countable::count()
	 */
	public function count() {

		return count($this->values);

	}
	
	/**
	 * {@inheritDoc}
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($offset, $value) {

		if (is_null($offset)) {
			throw new \InvalidArgumentException('Invalid NULL offset. Only named offsets allowed.');
		}
		$this->values[$offset] = $value;

	}
	
	/**
	 * {@inheritDoc}
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($offset) {

		return isset($this->values[$offset]);

	}
	
	/**
	 * {@inheritDoc}
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($offset) {

		unset($this->values[$offset]);

	}
	
	/**
	 * {@inheritDoc}
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($offset) {

		return isset($this->values[$offset]) ? $this->values[$offset] : NULL;

	}
	
}