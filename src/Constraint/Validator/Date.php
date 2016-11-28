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
use vxPHP\Application\Locale\Locale;
use vxPHP\Application\Application;

/**
 * check a date input honoring a locale setting of the application
 * in addition allowing only future dates can be configured
 * handles currently dates in german, us and iso style
 * 
 * @version 0.3.0 2016-11-28
 * @author Gregor Kofler
 */
class Date extends AbstractConstraint implements ConstraintInterface {
	
	/**
	 * the locale applied to the validation
	 * 
	 * @var Locale
	 */
	private $locale;
	
	/**
	 * only dates after this date are considered valid
	 * ignored when NULL
	 * 
	 * @var \DateTime
	 */
	private $validFrom;
	
	/**
	 * only dates before this date are considered valid
	 * ignored when NULL
	 *
	 * @var \DateTime
	 */
	private $validUntil;
	
	/**
	 * constructor, parses options
	 * 
	 * @param array $options
	 * @throws \InvalidArgumentException
	 */
	public function __construct(array $options = []) {
		
		$this->locale = isset($options['locale']) ? $options['locale'] : Application::getInstance()->getCurrentLocale();
		
		if(!$this->locale instanceof Locale) {
			throw new \InvalidArgumentException("Date validator option 'locale' is not a Locale instance.");
		}

		if(isset($options['validFrom'])) {

			if(!$options['validFrom'] instanceof \DateTime) {
				throw new \InvalidArgumentException("Date validator option 'validFrom' is not a DateTime instance.");
			}
			
			$this->validFrom = $options['validFrom'];
			
		}

		if(isset($options['validUntil'])) {

			if(!$options['validUntil'] instanceof \DateTime) {
				throw new \InvalidArgumentException("Date validator option 'validUntil' is not a DateTime instance.");
			}

			$this->validUntil = $options['validUntil'];

		}
		
		
		if(is_null($this->locale)) {
			throw new \InvalidArgumentException('Date validator requires either a valid locale, either passed to constructor or configured in application.');
		}

	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public function validate($value) {

		$localeId = $this->locale->getLocaleId();

		switch($localeId) {

			case 'de':
			case 'us':
				
				// e.g. nn.n.nn or n-nn-nnnn or nn/nn/nnnn 

				$rex = '\d{1,2}([\.\/-])\d{1,2}\1(?:\d{2}|\d{4})';

				if(!preg_match('~^' . $rex . '$~', $value, $matches))	{
					
					$this->setErrorMessage(sprintf("'%s' is not a properly formatted date string.", $value));
					return FALSE;
				}

				// explode along separating character

				$tmp = explode($matches[1], $value);

				// expand to 4 digit year

				if(strlen($tmp[2]) === 2) {
					$tmp[2]	=  substr(date('Y'), 0, 2) . $tmp[2];
				}
		
				if($localeId === 'de') {

					// dd.mm.yyyy

					if(!checkdate($tmp[1], $tmp[0], $tmp[2])) {

						$this->setErrorMessage(sprintf("'%s' is not a valid date value.", $value));
						return FALSE;

					}
					
					$isoFormat = sprintf('%04d-%02d-%02d', $tmp[2], $tmp[1], $tmp[0]);
					break;
				}

				// mm.dd.yyyy

				else if(!checkdate($tmp[0], $tmp[1], $tmp[2])) {
					
					$this->setErrorMessage(sprintf("'%s' is not a valid date value.", $value));
					return FALSE;
				}

				$isoFormat = sprintf('%04d-%02d-%02d', $tmp[2], $tmp[0], $tmp[1]);
				break;
		
			default:
				
				// e.g. yyyy.mm.dd or yy-mm-dd

				$rex = '(?:\d{2}|\d{4})(\.|/|\-)\d{1,2}\1\d{1,2}';

				if(!preg_match('~^' . $rex . '$~', $value, $matches))	{

					$this->setErrorMessage(sprintf("'%s' is not a valid date value.", $value));
					return FALSE;
				}

				// explode along separating character

				$tmp = explode($matches[2], $value);

				// expand to 4 digit year

				if(strlen($tmp[0]) === 2) {
					$tmp[0]	=  substr(date('Y'), 0, 2) . $tmp[0];
				}
		
				if(!checkdate($tmp[1], $tmp[2], $tmp[0])) {
					
					$this->setErrorMessage(sprintf("'%s' is not a valid date value.", $value));
					return FALSE;
				
				}

				$isoFormat = sprintf('%04d-%02d-%02d', $tmp[0], $tmp[1], $tmp[2]);
		}

		// check for valid from

		if($this->validFrom && $this->validFrom->format('Y-m-d') >= $isoFormat) {
			
			$this->setErrorMessage(sprintf("'%s' is not within validFrom boundary.", $value));
			return FALSE;

		}

		// check for valid until
		
		if($this->validUntil && $this->validUntil->format('Y-m-d') <= $isoFormat) {

			$this->setErrorMessage(sprintf("'%s' is not within validUntil boundary.", $value));
			return FALSE;

		}

		// all checks passed

		return TRUE;
		
	}

}