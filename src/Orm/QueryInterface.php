<?php

namespace vxPHP\Orm;

use vxPHP\Database\DatabaseInterface;

/**
 * interface for custom ORM queries
 *
 * @author Gregor Kofler
 * @version 0.2.0 2014-09-19
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
	 */
	public function selectFirst($count);

	/**
	 *
	 * @param int $from
	 * @param int $to
	 */
	public function selectFromTo($from, $to);

	public static function create(DatabaseInterface $dbConnection);
}
