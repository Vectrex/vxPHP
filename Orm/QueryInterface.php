<?php

namespace vxPHP\Orm;

use vxPHP\Database\Mysqldbi;

/**
 * interface for custom ORM queries
 *
 * @author Gregor Kofler
 * @version 0.1.1 2013-05-24
 */
interface QueryInterface {

	public function filter($columnName, $value);
	public function where($whereClause, array $valuesToBind);
	public function sortBy($columnName, $asc);
	public function select();
	public function count();

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

	public static function create(Mysqldbi $dbConnection);
}
