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
 * check string whether it matches IPv4 or IPv6 address
 * 
 * @version 0.1.1 2020-04-30
 * @author Gregor Kofler
 */
class Ip extends AbstractConstraint
{
	/**
	 * version against which address is validated 
	 * 
	 * @var string
	 */
	private $version;

	/**
	 * required flags for filter_var() depending on version
	 * 
	 * @var integer
	 */
	private $flags;

	/**
	 * set type of validation
	 * 
	 * @param string $version
	 * @throws \InvalidArgumentException
	 */
	public function __construct($version = 'all')
    {
		$allowedVersions = [
			'all'                 => null,
			'v4'                  => FILTER_FLAG_IPV4,
			'v6'                  => FILTER_FLAG_IPV6,
			'all_no_priv_range'   => FILTER_FLAG_NO_PRIV_RANGE,
			'v4_no_priv_range'    => FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE,
			'v6_no_priv_range'    => FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE,
			'all_no_res_range'    => FILTER_FLAG_NO_RES_RANGE,
			'v4_no_res_range'     => FILTER_FLAG_IPV4 | FILTER_FLAG_NO_RES_RANGE,
			'v6_no_res_range'     => FILTER_FLAG_IPV6 | FILTER_FLAG_NO_RES_RANGE,
			'all_no_public_range' => FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
			'v4_no_public_range'  => FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
			'v6_no_public_range'  => FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
		];

		$version = strtolower($version);

		if(!array_key_exists($version, $allowedVersions)) {
			throw new \InvalidArgumentException(sprintf("'%s' is an invalid IP address version; allowed are '%s'.", $version, implode("', '", array_keys($allowedVersions))));
		}

		$this->version = $version;
		$this->flags = $allowedVersions[$version];
	}

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public function validate($value): bool
    {
		if(!filter_var($value, FILTER_VALIDATE_IP, $this->flags)) {
			$this->setErrorMessage(sprintf("'%s' does not appear to be a valid IP address.", $value));
			return false;
		}

		return true;
	}
}