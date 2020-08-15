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
 * @version 0.1.1 2020-04-30
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
	 * @param string $type
	 */
	public function __construct($type = null)
    {
		if($type) {
			$allowedTypes = 'checkMX checkHost';
			$type = strtolower($type);

			if(!in_array($type, explode(' ', strtolower($allowedTypes)), true)) {
				throw new \InvalidArgumentException(sprintf("Invalid type for DNS checking '%s'; allowed types are '%s'.", $type, str_replace(' ', "', '", $allowedTypes)));
			}
            $this->checkType = $type;
		}
		
		$qtext          = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
		$dtext          = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
		$atom           = '[^\\x00-\\x20"(),.:;<>@\\x5b-\\x5d\\x7f-\\xff]+';
		$atom_umlaut    = '(?:[^\\x00-\\x20"(),.:;<>@\\x5b-\\x5d\\x7f-\\xff]|[äöüÄÖÜ])+';
		$quoted_pair    = '\\x5c[\\x00-\\x7f]';

		$domain_literal = "\\x5b(?:$dtext|$quoted_pair)*\\x5d";
		$quoted_string  = "\\x22(?:$qtext|$quoted_pair)*\\x22";
		$domain_ref     = $atom_umlaut;
		$sub_domain     = "(?:$domain_ref|$domain_literal)";
		$word           = "(?:$atom|$quoted_string)";

		//now a two-part domain identifier is required (not conforming to RFC822)

		$domain         = "$sub_domain(?:\\x2e$sub_domain)+";	// "$sub_domain(\\x2e$sub_domain)*"
		
		//capturing parantheses added

		$local_part     = "$word(?:\\x2e$word)*";
		
		// put everything together

		$this->regExp = '/^(' . $local_part . ')@(' . $domain .')$/';

        // vee validate (and vuelidate)
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