<?php
class ValidatingFunctions {
	/**
	 * collection of static methods for validating form element values,
	 * when more complex validation rules apply
	 * 
	 * @version 0.1.0 2011-11-18
	 * @author Gregor Kofler
	 */

	/**
	 * Check date input
	 * depending on SITE_LOCALE constant
	 * 
	 * @param string $date_string
	 * @param boolen $future allow only future dates
	 * @param string $locale override
	 * @return $result
	 */
	static function checkDateInput($date_string, $future = FALSE, $locale = NULL) {

		$locale = $locale == null ? (!defined('SITE_LOCALE') ? 'de' : SITE_LOCALE) : $locale;

		switch($locale) {
			case 'de':

			case 'us':
				$rex = '\d{1,2}(\.|/|\-)\d{1,2}\1\d{0,4}';

				if(!preg_match('~^'.$rex.'$~', $date_string, $matches))	{
					return FALSE;
				}

				$tmp	= explode($matches[1], $date_string);
				$tmp[2]	= strlen($tmp[2]) < 4 ? substr(date('Y'), 0, 4-strlen($tmp[2])).$tmp[2] : $tmp[2];

				if($locale == 'de'){
					if(!checkdate($tmp[1],$tmp[0],$tmp[2])) {
						return FALSE;
					}
					break;
				}
				if(!checkdate($tmp[0],$tmp[1],$tmp[2])) {
					return FALSE;
				}
				break;

			default:
				$rex = '\d{2}(\d{2})?(\.|/|\-)\d{1,2}\2\d{1,2}';
				if(!preg_match('~^'.$rex.'$~', $date_string, $matches))	{
					return FALSE;
				}

				$tmp = explode($matches[2], $date_string);
				$tmp[0]	= strlen($tmp[0]) < 4 ? substr(date('Y'), 0, 4-strlen($tmp[0])).$tmp[0] : $tmp[0];

				if(!checkdate($tmp[1],$tmp[2],$tmp[0])) {
					return FALSE;
				}
		}

		if($future) {
			
			$dformat	= '%04d%02d%02d'; 
			$now		= date('Ymd');

			switch($locale) {
				case 'de':
					if(sprintf($dformat, $tmp[2],$tmp[1],$tmp[0]) < $now) {
						return FALSE;
					}
					break;

				case 'us':
					if(sprintf($dformat, $tmp[2],$tmp[0],$tmp[1]) < $now) {
						return FALSE;
					}
					break;

				default:
					if(sprintf($dformat, $tmp[0],$tmp[1],$tmp[2]) < $now) {
						return FALSE;
					}
			}
		}

		// additional checks could go here

		return TRUE;
	}

	/**
	 * Check time input H[H]:[M]M[:[S]S]
	 * 
	 * @param string time
	 * @return bool result
	 */
	static function checkTimeInput($time) {
		// check for matching format
		if(!preg_match('~^\d{1,2}:\d{1,2}(:\d{1,2})?$~', $time))	{
			return FALSE;
		}

		//check whether values are within range

		$tmp = explode(':', $time);
		if((int) $tmp[0] > 23 || (int) $tmp[1] > 59 || isset($tmp[2]) && $tmp[2] > 59)	{
			return FALSE;
		}
		return TRUE;
	}
}
?>