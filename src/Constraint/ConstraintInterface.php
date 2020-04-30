<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Constraint;

/**
 * Constraints offer a single validate method
 * the boolean result of the check of the passed parameter is returned
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.1.1, 2020-04-30
 */
interface ConstraintInterface
{
	/**
	 * Check a value
	 * 
	 * @param mixed $value
	 * @return bool
	 */
	public function validate($value): bool;
}