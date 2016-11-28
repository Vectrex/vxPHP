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
use vxPHP\Constraint\AbstractConstraint;

/**
 * check a time input whether it matches [h]h:[m]m[:[s]s]
 * 
 * @version 0.3.0 2016-11-28
 * @author Gregor Kofler
 */
class Time extends AbstractConstraint implements ConstraintInterface {
	
	/**
	 * constructor, parses options
	 */
	public function __construct() {
	
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public function validate($value) {
		
		// check for matching format
		
		if(!preg_match('~^\d{1,2}:\d{1,2}(:\d{1,2})?$~', $value))	{
			
			$this->setErrorMessage(sprintf("'%s' is not a properly formatted time string.", $value));
			return FALSE;

		}
		
		//check whether values are within range
		
		$tmp = explode(':', $value);
		
		if(((int) $tmp[0] > 23 || (int) $tmp[1] > 59 || isset($tmp[2]) && $tmp[2] > 59)) {

			$this->setErrorMessage(sprintf("'%s' is an invalid time value.", $value));
			return FALSE;
			
		}

		return TRUE;

	}
}