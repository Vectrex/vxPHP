<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Webpage;

class MetaInformation implements \Countable, \IteratorAggregate {

	private $metaData = array();

	public function __construct() {
	}

	public static function createFromDb($pageId) {

	}

	/**
	 * get a metadata value by its name
	 * returns $default, if $key is not found
	 *
	 * @param string $name
	 * @param mixed $default
	 * @return string
	 */
	public function get($name, $default = NULL) {

		if (!array_key_exists($name, $this->metaData)) {
			return $default;
		}

		return $this->metaData[$name];

	}

	/**
	 * set or add a metadata value
	 *
	 * @param string $name
	 * @param unknown $value
	 */
	public function set($name, $value) {
		$this->metaData[$name] = $value;
	}

	/**
	 * check existence of a metadata value
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function has($name) {
		return array_key_exists($name, $this->metaData);
	}

	/**
	 * remove a metadata value
	 *
	 * @param string $name
	 */
	public function remove($name) {
		unset($this->metaData[$name]);
	}

	/**
	 * returns the number of metadata value
	 *
	 * @return int
	 */
	public function count() {
		return count($this->metaData);
	}

	/**
	 * returns an iterator for metadata values
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator() {
		return new \ArrayIterator($this->metaData);
	}

}