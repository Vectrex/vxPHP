<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Constraint\Validator;

use vxPHP\Constraint\ConstraintInterface;

/**
 * check a time input whether it matches [h]h:[m]m[:[s]s]
 * 
 * @version 0.1.0 2016-11-02
 * @author Gregor Kofler
 */
class Time implements ConstraintInterface {
	
	/**
	 * when true empty values are always considered valid
	 *
	 * @var bool
	 */
	private $emptyIsValid;
	
	/**
	 * constructor, parses options
	 *
	 * @param bool $emptyIsValid
	 */
	public function __construct($emptyIsValid) {
	
		$this->emptyIsValid = $emptyIsValid;

	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public function validate($value) {
		
		$value = trim($value);

		// value is empty and empty is valid
		
		if(!$value && $this->emptyIsValid) {
			return TRUE;
		}

		// check for matching format
		
		if(!preg_match('~^\d{1,2}:\d{1,2}(:\d{1,2})?$~', $value))	{
			return FALSE;
		}
		
		//check whether values are within range
		
		$tmp = explode(':', $value);
		
		return !((int) $tmp[0] > 23 || (int) $tmp[1] > 59 || isset($tmp[2]) && $tmp[2] > 59);

	}
}