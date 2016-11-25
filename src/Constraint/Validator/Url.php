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
 * check an URL for validity
 * 
 * @version 0.1.0 2016-11-25
 * @author Gregor Kofler
 */
class Url extends AbstractConstraint implements ConstraintInterface {
	
	const MATCH_PATTERN = '~^
		(%s)://														# protocol
		(([\pL\pN-]+:)?([\pL\pN-]+)@)?								# optional auth
		(
			([\pL\pN\pS-\.])+(\.?([\pL\pN]|xn\-\-[\pL\pN-]+)+\.?)	# domain name
			|														# or
			\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}						# IPv4 address
			|														# or
			\[
				(?:(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){6})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:::(?:(?:(?:[0-9a-f]{1,4})):){5})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){4})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,1}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){3})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,2}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){2})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,3}(?:(?:[0-9a-f]{1,4})))?::(?:(?:[0-9a-f]{1,4})):)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,4}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,5}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,6}(?:(?:[0-9a-f]{1,4})))?::))))
			\]														# IPv6 address
		)
		(:[0-9]+)?                          					    # optional port
		(/?|/\S+|\?\S*|\#\S*)                   					# optional slash with string or query or fragment
	$~ixu';

	
	/**
	 * indicate whether DNS records should be checked 
	 *
	 * @var bool
	 */
	private $checkDns;

	/**
	 * allowed schemes
	 * 
	 * @var array
	 */
	private $schemes = ['http', 'https'];

	/**
	 * constructor, parses options
	 *
	 * @param bool $checkDns
	 * @param array $schemes
	 */
	public function __construct($checkDns = FALSE, array $schemes = NULL) {
	
		$this->checkDns = $checkDns;

		if($schemes) {
			$this->schemes = $schemes;
		}

	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public function validate($value) {
		
		$value = trim($value);

		// check for matching format
		
		if(!preg_match(sprintf(static::MATCH_PATTERN, implode('|', $this->schemes), $value))) {

			$this->setErrorMessage(sprintf("Value '%s' is not a valid URL.", $value));
			return FALSE;

		}

		//@TODO IPv4 range check
		
		if($this->checkDns) {

			$host = parse_url($value, PHP_URL_HOST);
			
			if(!checkdnsrr($host, 'ANY')) {

				$this->setErrorMessage(sprintf("Host '%s' could not be resolved.", $host));
				return FALSE;

			}
		}
		
		return TRUE;

	}
}