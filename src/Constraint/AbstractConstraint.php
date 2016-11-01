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

use vxPHP\Constraint\ConstraintInterface;

/**
 * Abstract class for pooling constraint methods
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.1.0, 2016-10-31
 */
abstract class AbstractConstraint implements ConstraintInterface {
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public abstract function validate($value);

}