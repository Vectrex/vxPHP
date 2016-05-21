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
 * @version 0.1.0 2015-03-14
 */
class SessionDataBag implements \IteratorAggregate, \Countable {
	
	/**
	 * @var array
	 */
	private $data;

	/**
	 * initialize data with empty array
	 */
	public function __construct() {
		
		$this->data = array();

	}

	/**
	 * initialize bag and establish reference to underlying array
	 * 
	 * @param array $data
	 */
	public function initialize(array &$data) {

		$this->data = &$data;

	}

	/**
	 * get complete data array
	 * 
	 * @return array
	 */
	public function all() {
		
		return $this->data;
		
	}

	/**
	 * empties data array;
	 * returns previously held data
	 * 
	 * @return array
	 */
	public function clear() {

		$oldData = $this->data;
		$this->data = array();
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
	public function get($key, $default = NULL) {

		return array_key_exists($key, $this->data) ? $this->data[$key] : $default;

	}

	/**
	 * set key value of data array
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value) {
		
		$this->data[$key] = $value;

	}

	/**
	 * checks if key is defined
	 * 
	 * @param string $key
	 * 
	 * @return bool
	 */
	public function has($key) {

		return array_key_exists($key, $this->data);

	}
	
	/**
	 * unset key value;
	 * returns previously held data
	 * 
	 * @param string $key
	 * @return mixed
	 */
	public function remove($key) {

		if(!array_key_exists($key, $this->data)) {
			return NULL;
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
	public function replace(array $data) {

		$this->data = array();

		foreach ($data as $k => $v) {
			$this->data[$k] = $v;
		}

	}
	
	/* (non-PHPdoc)
	 * @see Countable::count()
	 */
	public function count() {

		return count($this->data);

	}

	/* (non-PHPdoc)
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator() {

		return new \ArrayIterator($this->data);

	}

	
}