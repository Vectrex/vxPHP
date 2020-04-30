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
 * Abstract class for pooling constraint methods
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * 
 * @version 0.2.1, 2020-04-30
 */
abstract class AbstractConstraint implements ConstraintInterface
{
	/**
	 * error message giving a description of the constraint violation
	 * set by a validator subclass
	 * 
	 * @var string
	 */
	protected $errorMessage;
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	abstract public function validate($value): bool;

	/**
	 * get error message
	 * 
	 * @return string
	 */
	public function getErrorMessage(): ?string
    {
		return $this->errorMessage;
	}

	/**
	 * set error message
	 * 
	 * @param string $message
	 */
	protected function setErrorMessage(string $message): void
    {
		$this->errorMessage = $message;
	}

	/**
	 * clear error message
	 * called by a validator prior to starting a validation
	 */
	protected function clearErrorMessage(): void
    {
		$this->errorMessage = '';
	}
}