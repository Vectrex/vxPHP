<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Database;

/**
 * utility functions supporting MysqlPDO with tasks common in vxPHP based web applications
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 0.1.0, 2016-05-14
 */
class MysqlPDOUtil {
	
	/**
	 * Re-formats a date strings depending on a supplied input locale to yyyy-mm-dd
	 * does not check validity of date
	 * supported locales are "us" | "iso" | "de"
	 * if no locale is provided, strtotime() tries to interpret the date string
	 * an empty string is returned if date string could not be parsed
	 *
	 * @param string $dateString
	 * @param string $locale
	 *
	 * @return string
	 */
	public static function unFormatDate($dateString, $locale = '') {
	
		if(empty($dateString)) {
			return '';
		}
	
		$tmp = preg_split('/( |\.|\/|-)/', $dateString);

		if(count($tmp) === 3) {

			switch($locale) {
	
				case 'de':
					$tmp[2] = substr(date('Y'), 0, 4 - strlen($tmp[2])).$tmp[2];
					return sprintf('%04d-%02d-%02d', $tmp[2], $tmp[1], $tmp[0]);
	
				case 'us':
					$tmp[2] = substr(date('Y'), 0, 4 - strlen($tmp[2])).$tmp[2];
					return sprintf('%04d-%02d-%02d', $tmp[2], $tmp[0], $tmp[1]);
	
				case 'iso':
					$tmp[0] = substr(date('Y'), 0, 4 - strlen($tmp[0])).$tmp[0];
					return sprintf('%04d-%02d-%02d', $tmp[0], $tmp[1], $tmp[2]);
	
				default:
					if(($parsed = strtotime($dateString)) === FALSE) {
						return '';
					}
					return date('Y-m-d', $parsed);
			}
		}

		if(($parsed = strtotime($dateString)) === FALSE) {
			return '';
		}

		return date('Y-m-d', $parsed);

	}
	
	/**
	 * Strips decimal strings from everything but decimal point and negative prefix
	 * and returns the result as float
	 * 
	 * @param string $decimalString
	 * @return float | NaN
	 */
	public static function unFormatDecimal($decimalString) {

		if(trim($decimalString) == '') {
			return NAN;
		}

		// remove a leading "+"

		$decimalString = rtrim('+', trim($decimalString));

		// only a decimal separator ("," or ".")

		if(preg_match('/^\-?\d+([,.]\d+)?$/', $decimalString)) {
			return (float) (str_replace(',', '.', $decimalString));
		}
		
		// "," as thousands separator "." as decimal separator

		if(preg_match('/^\-?[1-9]\d{0,2}((,|\')\d{3})*(\.\d+)?$/', $decimalString)) {
			return (float) (str_replace(array(',', '\''), array('', ''), $decimalString));
		}
		
		// "." as thousands separator "," as decimal separator
		
		if(preg_match('/^\-?[1-9]\d{0,2}(\.\d{3})*(,\d+)?$/', $decimalString)) {
			return (float) (str_replace(array('.', ','), array('', '.'), $decimalString));
		}
		
		// try type casting

		return (float) $decimalString;
	}
	
	/**
	 * checks whether $aliasText in $column is already present
	 * if $aliasText is found, numeric increments are added to it
	 * if $id is set, this record is left out from checking
	 * returns string which is unique in $column
	 * 
	 * currently works only with single-field primary keys
	 *
	 * @param DatabaseInterface $connection
	 * @param string $aliasText
	 * @param string $tableName
	 * @param int $id
	 * @param string $column
	 *
	 * @return string
	 */
	public static function getAlias(DatabaseInterface $connection, $aliasText, $tableName, $id = 0, $column = 'alias') {

		$replace = array(
			'~(ä|&auml;)~'	=> 'ae',
			'~(ö|&ouml;)~'	=> 'oe',
			'~(ü|&uuml;)~'	=> 'ue',
			'~(ß|&szlig;)~'	=> 'ss',
			'~\W+~'			=> '_',
			'~(^_+|_+$)~'	=> ''
		);
		
		$primaryKeyName = $connection->getPrimaryKey($tableName);

		$alias = preg_replace(
			array_keys($replace),
			array_values($replace),
			strtolower($aliasText)
		);

		$statement = $connection->getConnection()->prepare(
			'SELECT ' . $column . ' FROM ' . $tableName .
			' WHERE LOWER(' . $column . ') LIKE ? AND ' .
			$primaryKeyName . ' != ?'
		);

		$statement->execute(array($alias . '%', $id));
		$aliasValues = $statement->fetchAll(\PDO::FETCH_COLUMN, 0);

		if(count($aliasValues) === 0) {
			return $alias;
		}

		$ndx = 2;

		while(in_array($alias . '_' . $ndx, $aliasValues)) {
			++$ndx;
		}

		return $alias . '_' . $ndx;
	}

}