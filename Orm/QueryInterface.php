<?php

namespace vxPHP\Orm;

use vxPHP\Database\Mysqldbi;

/**
 * interface for custom ORM queries
 *
 * @author Gregor Kofler
 * @version 0.1.0 2013-04-10
 */
interface QueryInterface {

	public function filter($columnName, $value);
	public function where($whereClause, array $valuesToBind);
	public function sortBy($columnName, $asc);
	public function select();
	public function selectFirst();

	public static function create(Mysqldbi $dbConnection);
}
