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
 * check against an arbitrary regular expression
 * 
 * @version 0.1.3 2021-11-28
 * @author Gregor Kofler
 */
class RegularExpression extends AbstractConstraint
{
	/**
	 * regular expression the value is matched against
	 * 
	 * @var string
	 */
	private string $regExp;
	
	/**
	 * constructor
	 * checks whether passed regular expression appears valid
	 *
	 * @param string $regExp
	 * @throws \InvalidArgumentException
	 * 
	 */
	public function __construct(string $regExp)
    {
		if(@preg_match($regExp, '') === false) {
			throw new \InvalidArgumentException(sprintf("'%s' is not a valid regular expression.", $regExp));
		}
		$this->regExp = $regExp;
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public function validate(mixed $value): bool
    {
		return (bool) preg_match($this->regExp, $value);
	}
}