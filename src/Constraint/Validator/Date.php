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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Constraint\AbstractConstraint;
use vxPHP\Application\Locale\Locale;
use vxPHP\Application\Application;

/**
 * check a date input honoring a locale setting of the application
 * in addition allowing only future dates can be configured
 * handles currently dates in german, us and iso style
 * 
 * @version 0.3.3 2021-11-28
 * @author Gregor Kofler
 */
class Date extends AbstractConstraint
{
	/**
	 * the locale applied to the validation
	 * 
	 * @var Locale
     */
	private Locale $locale;
	
	/**
	 * only dates after this date are considered valid
	 * ignored when NULL
	 * 
	 * @var \DateTime|null
     */
	private ?\DateTime $validFrom = null;
	
	/**
	 * only dates before this date are considered valid
	 * ignored when NULL
	 *
	 * @var \DateTime|null
     */
	private ?\DateTime $validUntil = null;

    /**
     * constructor, parses options
     *
     * @param array $options
     * @throws \InvalidArgumentException
     * @throws ApplicationException
     */
	public function __construct(array $options = [])
    {
        if (isset($options['locale']) && !$options['locale'] instanceof Locale) {
            throw new \InvalidArgumentException("Date validator option 'locale' is not a Locale instance.");
        }

        $appLocale = Application::getInstance()->getCurrentLocale();

        if (!isset($options['locale']) && !$appLocale) {
            throw new \InvalidArgumentException('Date validator requires either a valid locale, either passed to constructor or configured in application.');
        }

        $this->locale = $options['locale'] ?? $appLocale;

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
	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\ConstraintInterface::validate()
	 */
	public function validate(mixed $value): bool
    {
		$localeId = $this->locale->getLocaleId();

		switch($localeId) {

			case 'de':
			case 'us':
				
				// e.g. nn.n.nn or n-nn-nnnn or nn/nn/nnnn 

				$rex = '\d{1,2}([./-])\d{1,2}\1(?:\d{2}|\d{4})';

				if(!preg_match('~^' . $rex . '$~', $value, $matches))	{
					
					$this->setErrorMessage(sprintf("'%s' is not a properly formatted date string.", $value));
					return false;
				}

				// explode along separating character

				$tmp = explode($matches[1], $value);

				// expand to 4 digit year

				if(strlen($tmp[2]) === 2) {
					$tmp[2]	= substr(date('Y'), 0, 2) . $tmp[2];
				}
		
				if($localeId === 'de') {

					// dd.mm.yyyy

					if(!checkdate((int) $tmp[1], (int) $tmp[0], (int) $tmp[2])) {

						$this->setErrorMessage(sprintf("'%s' is not a valid date value.", $value));
						return false;

					}
					
					$isoFormat = sprintf('%04d-%02d-%02d', $tmp[2], $tmp[1], $tmp[0]);
					break;
				}

				// mm.dd.yyyy

                if(!checkdate((int) $tmp[0], (int) $tmp[1], (int) $tmp[2])) {

                    $this->setErrorMessage(sprintf("'%s' is not a valid date value.", $value));
                    return false;

                }

                $isoFormat = sprintf('%04d-%02d-%02d', $tmp[2], $tmp[0], $tmp[1]);
				break;
		
			default:
				
				// e.g. yyyy.mm.dd or yy-mm-dd

				$rex = '(?:\d{2}|\d{4})([./\-])\d{1,2}\1\d{1,2}';

				if(!preg_match('~^' . $rex . '$~', $value, $matches))	{

					$this->setErrorMessage(sprintf("'%s' is not a valid date value.", $value));
					return false;
				}

				// explode along separating character

				$tmp = explode($matches[1], $value);

				// expand to 4 digit year

				if(strlen($tmp[0]) === 2) {
					$tmp[0]	=  substr(date('Y'), 0, 2) . $tmp[0];
				}
		
				if(!checkdate((int) $tmp[1], (int) $tmp[2], (int) $tmp[0])) {
					
					$this->setErrorMessage(sprintf("'%s' is not a valid date value.", $value));
					return false;
				}

				$isoFormat = sprintf('%04d-%02d-%02d', $tmp[0], $tmp[1], $tmp[2]);
		}

		// check for valid from

		if($this->validFrom && $this->validFrom->format('Y-m-d') >= $isoFormat) {
			
			$this->setErrorMessage(sprintf("'%s' is not within validFrom boundary.", $value));
			return false;

		}

		// check for valid until
		
		if($this->validUntil && $this->validUntil->format('Y-m-d') <= $isoFormat) {

			$this->setErrorMessage(sprintf("'%s' is not within validUntil boundary.", $value));
			return false;
		}

		// all checks passed

		return true;
	}
}