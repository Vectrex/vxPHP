<?php

namespace vxPHP\Database;

/**
 * utility functions supporting vxPDO with tasks common in vxPHP/vxWeb
 *
 * This class is part of the vxPHP framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 *
 * @author Gregor Kofler, info@gregorkofler.com
 *
 * @version 0.1.0, 2014-09-05
 */
class vxPDOUtil {
	
	public static function customSort() {
	}
	
	public static function formatDate() {
	
	}
	
	public static function formatDecimal() {
	
	}
	
	/**
	 * checks whether $aliasText in $column is already present
	 * if $aliasText is found, numeric increments are added to it
	 * if $id is set, this record is left out from checking
	 * returns string which is unique in $column
	 * 
	 * currently works only with single-field primary keys
	 *
	 * @param vxPDO $connection
	 * @param string $aliasText
	 * @param string $tableName
	 * @param int $id
	 * @param string $column
	 *
	 * @return string
	 */
	public static function getAlias(vxPDO $connection, $aliasText, $tableName, $id = 0, $column = 'alias') {

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
		
		$statement = $connection->prepare(
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