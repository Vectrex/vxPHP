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

use vxPHP\Constraint\AbstractConstraint;

/**
 * check a string whether it can be interpreted
 * as time string in a [h]h:[m]m[:[s]s] form
 * 
 * @version 0.4.0 2020-04-30
 * @author Gregor Kofler
 */
class Time extends AbstractConstraint
{
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public function validate(mixed $value): bool
    {
        $value = trim($value);

		// check for matching format
		
		if(!preg_match('~^\d{1,2}:\d{1,2}(:\d{1,2})?$~', $value)) {
			$this->setErrorMessage(sprintf("'%s' is not a properly formatted time string.", $value));
			return false;
		}
		
		//check whether values are within range
		
		$tmp = explode(':', $value);
		
		if(((int) $tmp[0] > 23 || (int) $tmp[1] > 59 || (isset($tmp[2]) && $tmp[2] > 59))) {
			$this->setErrorMessage(sprintf("'%s' is an invalid time value.", $value));
			return false;
		}

		return true;
	}
}