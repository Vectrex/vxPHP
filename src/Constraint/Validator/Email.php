<?php

namespace vxPHP\Constraint\Validator;

use vxPHP\Constraint\AbstractConstraint;

/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * check an email for validity
 *
 * @version 0.1.2 2020-09-18
 * @author Gregor Kofler
 */
class Email extends AbstractConstraint
{
	/**
	 * indicate which type of additional checking (MX or host) is required
	 *
	 * @var bool
	 */
	private $checkType;
	
	/**
	 * stores the regular expression against the email is checked
	 *
	 * @var string
	 */
	private $regExp;

    /**
     * build regular expression against which
     * email is checked
     *
     * @param string|null $type
     */
	public function __construct(string $type = null)
    {
		if($type) {
			$allowedTypes = 'checkMX checkHost';
			$type = strtolower($type);

			if(!in_array($type, explode(' ', strtolower($allowedTypes)), true)) {
				throw new \InvalidArgumentException(sprintf("Invalid type for DNS checking '%s'; allowed types are '%s'.", $type, str_replace(' ', "', '", $allowedTypes)));
			}
            $this->checkType = $type;
		}
		
        // taken the regexp from vee validate (and vuelidate)

        $this->regExp = '/^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/';
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\AbstractConstraint::validate()
	 */
	public function validate($value): bool
    {
		if(!preg_match($this->regExp, $value)) {
			$this->setErrorMessage(sprintf("'%s' does not appear to be a valid email.", $value));
			return false;
		}
		
		// extract host information
		
		$host = substr($value, strpos($value, '@') + 1);
		
		if($this->checkType === 'checkmx') {
			if(!checkdnsrr($host)) {
				$this->setErrorMessage(sprintf("MX lookup for '%s' failed.", $value));
				return false;
			}
		}
		else if(($this->checkType === 'checkhost') && !(checkdnsrr($host) || checkdnsrr($host, 'A') || checkdnsrr($host, 'AAAA'))) {
            $this->setErrorMessage(sprintf("A, AAAA or MX lookup for '%s' failed.", $value));
            return false;
        }

		return true;
	}
}