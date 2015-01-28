<?php

namespace vxPHP\Orm;

use vxPHP\Database\vxPDO;

/**
 * interface for custom ORM queries
 *
 * @author Gregor Kofler
 * @version 0.2.1 2015-01-28
 */
interface QueryInterface {

	public function filter($columnName, $value);
	public function where($whereClause, array $valuesToBind);
	public function sortBy($columnName, $asc);
	public function select();
	public function count();

	/**
	 * dumps SQL string of current query
	 * (invokes building of SQL string if not already built)
	 *
	 * @return sqlString
	 */
	public function dumpSql();

	/**
	 *
	 * @param int $count
	 * 
	 * @return array
	 */
	public function selectFirst($count);

	/**
	 *
	 * @param int $from
	 * @param int $to
	 * 
	 * @return array
	 */
	public function selectFromTo($from, $to);

	/**
	 * @param vxPDO $dbConnection
	 * @return QueryInterface
	 */
	public static function create(vxPDO $dbConnection);
}
