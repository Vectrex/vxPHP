<?php
namespace vxPHP\Constraint\Validator;

use vxPHP\Constraint\ConstraintInterface;
use vxPHP\Constraint\AbstractConstraint;

/**
 * Validate IBANs
 * 
 * @author Gregor Kofler
 * @version 0.4.0 2017-12-02
 *
 */
class Iban extends AbstractConstraint
{
	private $ibanPatternsByCountry = [

	    /*
	     * official IBAN patterns according to
	     * @see https://www.swift.com/sites/default/files/resources/swift_standards_ibanregistry.pdf
	     */

        'AL' => 'AL\d{2}\d{8}[\dA-Z]{16}', // Albania
        'AD' => 'AD\d{2}\d{4}\d{4}[\dA-Z]{12}', // Andorra
        'AT' => 'AT\d{2}\d{5}\d{11}', // Austria
        'BE' => 'BE\d{2}\d{3}\d{7}\d{2}', // Belgium
        'BA' => 'BA\d{2}\d{3}\d{3}\d{8}\d{2}', // Bosnia and Herzegovina
        'BG' => 'BG\d{2}[A-Z]{4}\d{4}\d{2}[\dA-Z]{8}', // Bulgaria
        'HR' => 'HR\d{2}\d{7}\d{10}', // Croatia
        'CY' => 'CY\d{2}\d{3}\d{5}[\dA-Z]{16}', // Cyprus
        'CZ' => 'CZ\d{2}\d{20}', // Czech Republic
        'DK' => 'DK\d{2}\d{4}\d{10}', // Denmark
        'AE' => 'AE\d{2}\d{3}\d{16}', // United Arab Emirates
        'AZ' => 'AZ\d{2}[A-Z]{4}[\dA-Z]{20}', // Azerbaijan
        'BH' => 'BH\d{2}[A-Z]{4}[\dA-Z]{14}', // Bahrain
        'BR' => 'BR\d{2}\d{8}\d{5}\d{10}[A-Z][\dA-Z]', // Brazil
        'CH' => 'CH\d{2}\d{5}[\dA-Z]{12}', // Switzerland
        'CR' => 'CR\d{2}0\d{3}\d{14}', // Costa Rica
        'DE' => 'DE\d{2}\d{8}\d{10}', // Germany
        'DO' => 'DO\d{2}[\dA-Z]{4}\d{20}', // Dominican Republic
        'EE' => 'EE\d{2}\d{2}\d{2}\d{11}\d{1}', // Estonia
        'ES' => 'ES\d{2}\d{4}\d{4}\d{1}\d{1}\d{10}', // Spain
        'FI' => 'FI\d{2}\d{6}\d{7}\d{1}', // Finland
        'FO' => 'FO\d{2}\d{4}\d{9}\d{1}', // Faroe Islands
        'FR' => 'FR\d{2}\d{5}\d{5}[\dA-Z]{11}\d{2}', // France
        'GB' => 'GB\d{2}[A-Z]{4}\d{6}\d{8}', // United Kingdom of Great Britain and Northern Ireland
        'GE' => 'GE\d{2}[A-Z]{2}\d{16}', // Georgia
        'GI' => 'GI\d{2}[A-Z]{4}[\dA-Z]{15}', // Gibraltar
        'GL' => 'GL\d{2}\d{4}\d{9}\d{1}', // Greenland
        'GR' => 'GR\d{2}\d{3}\d{4}[\dA-Z]{16}', // Greece
        'GT' => 'GT\d{2}[\dA-Z]{4}[\dA-Z]{20}', // Guatemala
        'HU' => 'HU\d{2}\d{3}\d{4}\d{1}\d{15}\d{1}', // Hungary
        'IE' => 'IE\d{2}[A-Z]{4}\d{6}\d{8}', // Ireland
        'IL' => 'IL\d{2}\d{3}\d{3}\d{13}', // Israel
        'IS' => 'IS\d{2}\d{4}\d{2}\d{6}\d{10}', // Iceland
        'IT' => 'IT\d{2}[A-Z]{1}\d{5}\d{5}[\dA-Z]{12}', // Italy
        'JO' => 'JO\d{2}[A-Z]{4}\d{4}[\dA-Z]{18}', // Jordan
        'KW' => 'KW\d{2}[A-Z]{4}\d{22}', // Kuwait
        'KZ' => 'KZ\d{2}\d{3}[\dA-Z]{13}', // Kazakhstan
        'LB' => 'LB\d{2}\d{4}[\dA-Z]{20}', // Lebanon
        'LI' => 'LI\d{2}\d{5}[\dA-Z]{12}', // Liechtenstein
        'LT' => 'LT\d{2}\d{5}\d{11}', // Lithuania
        'LU' => 'LU\d{2}\d{3}[\dA-Z]{13}', // Luxembourg
        'LV' => 'LV\d{2}[A-Z]{4}[\dA-Z]{13}', // Latvia
        'MC' => 'MC\d{2}\d{5}\d{5}[\dA-Z]{11}\d{2}', // Monaco
        'MD' => 'MD\d{2}[\dA-Z]{2}[\dA-Z]{18}', // Moldova
        'ME' => 'ME\d{2}\d{3}\d{13}\d{2}', // Montenegro
        'MK' => 'MK\d{2}\d{3}[\dA-Z]{10}\d{2}', // Macedonia, Former Yugoslav Republic of
        'MR' => 'MR13\d{5}\d{5}\d{11}\d{2}', // Mauritania
        'MT' => 'MT\d{2}[A-Z]{4}\d{5}[\dA-Z]{18}', // Malta
        'MU' => 'MU\d{2}[A-Z]{4}\d{2}\d{2}\d{12}\d{3}[A-Z]{3}', // Mauritius
        'NL' => 'NL\d{2}[A-Z]{4}\d{10}', // The Netherlands
        'NO' => 'NO\d{2}\d{4}\d{6}\d{1}', // Norway
        'PK' => 'PK\d{2}[A-Z]{4}[\dA-Z]{16}', // Pakistan
        'PL' => 'PL\d{2}\d{8}\d{16}', // Poland
        'PS' => 'PS\d{2}[A-Z]{4}[\dA-Z]{21}', // Palestine, State of
        'PT' => 'PT\d{2}\d{4}\d{4}\d{11}\d{2}', // Portugal
        'QA' => 'QA\d{2}[A-Z]{4}[\dA-Z]{21}', // Qatar
        'RO' => 'RO\d{2}[A-Z]{4}[\dA-Z]{16}', // Romania
        'RS' => 'RS\d{2}\d{3}\d{13}\d{2}', // Serbia
        'SA' => 'SA\d{2}\d{2}[\dA-Z]{18}', // Saudi Arabia
        'SE' => 'SE\d{2}\d{3}\d{16}\d{1}', // Sweden
        'SI' => 'SI\d{2}\d{5}\d{8}\d{2}', // Slovenia
        'SK' => 'SK\d{2}\d{4}\d{6}\d{10}', // Slovak Republic
        'SM' => 'SM\d{2}[A-Z]{1}\d{5}\d{5}[\dA-Z]{12}', // San Marino
        'TL' => 'TL\d{2}\d{3}\d{14}\d{2}', // Timor-Leste
        'TN' => 'TN59\d{2}\d{3}\d{13}\d{2}', // Tunisia
        'TR' => 'TR\d{2}\d{5}[\dA-Z]{1}[\dA-Z]{16}', // Turkey
        'UA' => 'UA\d{2}\d{6}[\dA-Z]{19}', // Ukraine
        'VG' => 'VG\d{2}[A-Z]{4}\d{16}', // Virgin Islands, British
        'XK' => 'XK\d{2}\d{4}\d{10}\d{2}', // Republic of Kosovo

        /*
         * experimental IBAN patterns
         * currently deactivated
         *
         * @see https://bank.codes/iban/structure/
         * @see https://de.iban.com/struktur.html
         */

        /*
        'AO' => 'AO\d{2}\d{21}', // Angola
        'BF' => 'BF\d{2}\d{23}', // Burkina Faso
        'BI' => 'BI\d{2}\d{12}', // Burundi
        'BJ' => 'BJ\d{2}[A-Z]{1}\d{23}', // Benin
        'CG' => 'CG\d{2}\d{23}', // Congo
        'CI' => 'CI\d{2}[A-Z]{1}\d{23}', // Ivory Coast
        'CM' => 'CM\d{2}\d{23}', // Cameron
        'CV' => 'CV\d{2}\d{21}', // Cape Verde
        'DZ' => 'DZ\d{2}\d{20}', // Algeria
        'IR' => 'IR\d{2}\d{22}', // Iran
        'MG' => 'MG\d{2}\d{23}', // Madagascar
        'ML' => 'ML\d{2}[A-Z]{1}\d{23}', // Mali
        'MZ' => 'MZ\d{2}\d{21}', // Mozambique
        'SN' => 'SN\d{2}[A-Z]{1}\d{23}', // Senegal
        'SC' => 'SC\d{2}[A-Z]{4}\d{4}\d{16}[A-Z]{3}', // Seychelles
        'BY' => 'BY\d{2}[\dA-Z]{4}\d{4}[\dA-Z]{16}', // Belarus
        */
        // Gabon
        // Guinea Bisseau
        // Djibouti
        // Morocco
        // Ägypten
        // Equatorial Guinea
        // Togo
        // Chad
        // Niger
        // Central African Republic
    ];

    private $territories = [
        'AX' => 'FI', // Aland Islands
        'BL' => 'FR', // Saint Barthelemy
        'GF' => 'FR', // French Guyana
        'MF' => 'FR', // Saint Martin (French part)
        'MQ' => 'FR', // Martinique
        'NC' => 'FR', // New Caledonia
        'PF' => 'FR', // French Polynesia
        'PM' => 'FR', // Saint Pierre et Miquelon
        'RE' => 'FR', // Reunion
        'TF' => 'FR', // French Southern Territories
        'YT' => 'FR', // Mayotte
        'WF' => 'FR', // Wallis and Futuna Islands
    ];

	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\Constraint\AbstractConstraint::validate()
	 */
	public function validate($value): bool
    {
		$this->clearErrorMessage();

		$iban = strtoupper(preg_replace('/\s+/', '', $value));

		// no IBAN exceeds 34 chars and starts with country code and checksum

		if(!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {

			$this->setErrorMessage('IBAN does not meet basic formal requirements.');
			return false;

		}

		// IBAN must provide a valid country code and match the country's IBAN length

        $country = substr($iban, 0, 2);

		if(!array_key_exists($country, $this->ibanPatternsByCountry)) {

            $this->setErrorMessage(sprintf("IBAN with unknown country code '%s'.", $country));
            return false;

        }

        if(!preg_match('/' . $this->ibanPatternsByCountry[$country] . '/', $iban)) {

            $this->setErrorMessage(sprintf("Invalid IBAN format for country code '%s'.", $country));
            return false;

        }

		// rearrange country code and checksum

		$rearrangedIban = substr($iban, 4) . substr($iban, 0, 4);
		$IbanCharArray = str_split($rearrangedIban);
		$newString = '';
		$codeA = ord('A') - 10;

		foreach($IbanCharArray as $key) {
			
			// replace characters

			if(!is_numeric($key)) {
				
				// A is mapped to 10, B to 11, ... Z ... 35

				$key = ord($key) - $codeA;

			}

			$newString .= $key;
		}

		$result = bcmod($newString, '97') === '1';

		if(!$result) {

			$this->setErrorMessage('IBAN checksum failed.');
			return false;

		}

		return true;
	}
}
