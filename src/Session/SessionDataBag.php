<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Session;

/**
 * stores session data in iterable container
 * 
 * @author Gregor Kofler
 * 
 * @version 0.2.1 2025-01-13
 */
class SessionDataBag implements \IteratorAggregate, \Countable
{
	/**
	 * @var array
	 */
	private array $data;

	/**
	 * initialize data with empty array
	 */
	public function __construct()
    {
		$this->data = [];
	}

	/**
	 * initialize bag and establish reference to underlying array
	 * 
	 * @param array $data
	 */
	public function initialize(array &$data): void
    {
		$this->data = &$data;
	}

	/**
	 * get complete data array
	 * 
	 * @return array
	 */
	public function all(): array
    {
		return $this->data;
	}

	/**
	 * empties data array;
	 * returns previously held data
	 * 
	 * @return array
	 */
	public function clear(): array
    {
		$oldData = $this->data;
		$this->data = [];
		return $oldData;
	}

	/**
	 * get key value of data array;
	 * returns $default, when $key is not found
	 * 
	 * @param string $key
	 * @param mixed $default
	 * 
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed
    {
		return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
	}

	/**
	 * set key value of data array
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function set(string $key, mixed $value): void
    {
		$this->data[$key] = $value;
	}

	/**
	 * checks if key is defined
	 * 
	 * @param string $key
	 * 
	 * @return bool
	 */
	public function has(string $key): bool
    {
		return array_key_exists($key, $this->data);
	}
	
	/**
	 * unset key value;
	 * returns previously held data
	 * 
	 * @param string $key
	 * @return mixed
	 */
	public function remove(string $key): mixed
    {
		if(!array_key_exists($key, $this->data)) {
			return null;
		}

		$oldValue = $this->data[$key];
		unset($this->data[$key]);

		return $oldValue;
	}

	/**
	 * replace current data array
	 * 
	 * @param array $data
	 */
	public function replace(array $data): void
    {
		$this->data = $data;
	}
	
	/* (non-PHPdoc)
	 * @see Countable::count()
	 */
	public function count(): int
    {
		return count($this->data);
	}

	/* (non-PHPdoc)
	 * @see \IteratorAggregate::getIterator()
	 */
	public function getIterator(): \Traversable
    {
		return new \ArrayIterator($this->data);
	}
}