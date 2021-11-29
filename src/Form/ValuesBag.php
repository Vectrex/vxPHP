<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Form;

/**
 * ValuesBag is a container for key/value pairs for form values
 */
class ValuesBag implements \IteratorAggregate, \Countable, \ArrayAccess
{
	/**
	 * the value
	 * @var array
	 */
	protected array $values;

	/**
	 * Constructor
	 * @param array $values
	 */
	public function __construct(array $values = [])
    {
		$this->values = $values;
	}

	/**
	 * returns all values
	 * @return array
	 */
	public function all(): array
    {
		return $this->values;
	}

	/**
	 * returns the values keys
	 * @return array
	 */
	public function keys(): array
    {
		return array_keys($this->values);
	}

	/**
	 * replaces the current values with a new set
	 * @param array $values
	 */
	public function replace(array $values = []): void
    {
		$this->values = $values;
	}

	/**
	 * adds values to the existing ones
	 * @param array $values
	 */
	public function add(array $values = []): void
    {
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
	 */
	public function get(string $key, $default = null)
    {
		return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
	}

	/**
	 * set a value by name
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set(string $key, $value): void
    {
		$this->values[$key] = $value;
	}

	/**
	 * returns TRUE if the value is defined
	 *
	 * @param string $key
	 * @return Boolean
	 */
	public function has(string $key): bool
    {
		return array_key_exists($key, $this->values);
	}

	/**
	 * removes a value identified by $key
	 * 
	 * @param string $key
	 */
	public function remove(string $key): void
    {
		unset($this->values[$key]);
	}

	/**
	 * {@inheritDoc}
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator(): \ArrayIterator
    {
		return new \ArrayIterator($this->values);
	}

	/**
	 * 
	 * {@inheritDoc}
	 * @see Countable::count()
	 */
	public function count(): int
    {
		return count($this->values);
	}
	
	/**
	 * {@inheritDoc}
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($offset, $value): void
    {
		if (is_null($offset)) {
			throw new \InvalidArgumentException('Invalid NULL offset. Only named offsets allowed.');
		}
		$this->values[$offset] = $value;
	}
	
	/**
	 * {@inheritDoc}
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($offset): bool
    {
		return isset($this->values[$offset]);
	}
	
	/**
	 * {@inheritDoc}
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($offset): void
    {
		unset($this->values[$offset]);
	}
	
	/**
	 * {@inheritDoc}
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($offset)
    {
		return $this->values[$offset] ?? null;
	}
}