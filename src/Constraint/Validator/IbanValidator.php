<?php
namespace vxPHP\Constraint\Validator;

use vxPHP\Constraint\ConstraintInterface;
use vxPHP\Constraint\AbstractConstraint;

/**
 * Validate IBANs
 * 
 * @author Gregor Kofler
 * @version 0.2.0 2017-01-27
 *
 */
class IbanValidator extends AbstractConstraint implements ConstraintInterface {

	private $countriesLength = [
		'al' => 28,
		'ad' => 24,
		'at' => 20,
		'az' => 28,
		'bh' => 22,
		'be' => 16,
		'ba' => 20,
		'br' => 29,
		'bg' => 22,
		'cr' => 21,
		'hr' => 21,
		'cy' => 28,
		'cz' => 24,
		'dk' => 18,
		'do' => 28,
		'ee' => 20,
		'fo' => 18,
		'fi' => 18,
		'fr' => 27,
		'ge' => 22,
		'de' => 22,
		'gi' => 23,
		'gr' => 27,
		'gl' => 18,
		'gt' => 28,
		'hu' => 28,
		'is' => 26,
		'ie' => 22,
		'il' => 23,
		'it' => 27,
		'jo' => 30,
		'kz' => 20,
		'kw' => 30,
		'lv' => 21,
		'lb' => 28,
		'li' => 21,
		'lt' => 20,
		'lu' => 20,
		'mk' => 19,
		'mt' => 31,
		'mr' => 27,
		'mu' => 30,
		'mc' => 27,
		'md' => 24,
		'me' => 22,
		'nl' => 18,
		'no' => 15,
		'pk' => 24,
		'ps' => 29,
		'pl' => 28,
		'pt' => 25,
		'qa' => 29,
		'ro' => 24,
		'sm' => 27,
		'sa' => 24,
		'rs' => 22,
		'sk' => 24,
		'si' => 19,
		'es' => 24,
		'se' => 24,
		'ch' => 21,
		'tn' => 24,
		'tr' => 26,
		'ae' => 23,
		'gb' => 22,
		'vg' => 24
	];

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\AbstractConstraint::validate()
	 */
	public function validate($value) {
		
		$this->clearErrorMessage();

		$iban = strtolower(preg_replace('/\s+/', '',$value));

		// no IBAN exceeds 34 chars and starts with country code and checksum

		if(!preg_match('/^[a-z]{2}[0-9]{2}[a-z0-9]{1,30}$/', $iban)) {
			
			$this->setErrorMessage('IBAN does not meet formal requirements.');
			return FALSE;
		}

		// IBAN must provide a valid country code and match the country's IBAN length

		if(!isset($this->countriesLength[substr($iban, 0, 2)]) || strlen($iban) !== $this->countriesLength[substr($iban, 0, 2)]) {

			$this->setErrorMessage('IBAN with unknown country code.');
			return FALSE;

		}

		// rearrange country code and checksum

		$movedChar		= substr($iban, 4) . substr($iban, 0, 4);
		$movedCharArray	= str_split($movedChar);
		$newString		= '';

		$codeA			= ord('a') - 10;

		foreach($movedCharArray as $key) {
			
			// replace characters

			if(!is_numeric($key)) {
				
				// a is mapped to 10, b to 11, ... z ... 35

				$key = ord($key) - $codeA;

			}

			$newString .= $key;
		}

		if(function_exists('bcmod')) {
			$result = bcmod($newString, '97') === '1';
		}
		else {
			$result = $this->bcmod97($newString) === 1;
		}

		if(!$result) {

			$this->setErrorMessage('IBAN checksum failed.');

		}

		return $result;

	}

	/**
	 * fallback for bcmod() function
	 * modulus is hardcoded with 97
	 * 
	 * @param string $bigInt
	 * @return number
	 */
	private function bcmod97 ($bigInt) {

		$rest = 0;
	
		foreach (str_split($bigInt, 7) as $part) {
			$rest = ($rest . $part) % 97;
		}

		return $rest;
	}

}
