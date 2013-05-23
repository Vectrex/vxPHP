<?php

namespace vxPHP\Orm;

use vxPHP\Database\Mysqldbi;
use vxPHP\Orm\QueryInterface;

/**
 * abstract class for ORM queries
 *
 * @author Gregor Kofler
 * @version 0.1.3 2013-05-24
 */
abstract class Query implements QueryInterface {

	/**
	 * @var Mysqldbi
	 */
	protected	$dbConnection;

	protected	$whereClauses = array (),
				$columnSorts = array (),
				$valuesToBind = array (),
				$selectSql,
				$sql,
				$lastQuerySql;


	/**
	 * provide initial database connection
	 * currently only allows a Mysqli backend
	 *
	 * @param Mysqldbi $dbConnection
	 */
	public function __construct(Mysqldbi $dbConnection) {

		$this->dbConnection = $dbConnection;

	}

	/**
	 * add WHERE clause that filters articles where $columnName matches $value
	 *
	 * @param string $columnName
	 * @param string|number $value
	 * @return ArticleQuery
	 */
	public function filter($columnName, $value) {

		$this->addCondition("$columnName = ?", $value);
		return $this;

	}

	/**
	 * add an "arbitrary" WHERE clause and values to bind
	 *
	 * @param string $whereClause
	 * @param array $valuesToBind
	 * @return ArticleQuery
	 */
	public function where($whereClause, array $valuesToBind = NULL) {

		$this->addCondition($whereClause, $valuesToBind);
		return $this;

	}

	/**
	 * add ORDER BY clause
	 *
	 * @param string $columnName
	 * @param boolean $asc
	 * @return ArticleQuery
	 */
	public function sortBy($columnName, $asc = TRUE) {

		$sort = new \stdClass();

		$sort->column = $columnName;
		$sort->asc = !!$asc;

		$this->columnSorts[] = $sort;

		return $this;

	}

	/**
	 * static method for convenience reasons
	 * avoids assigning ArticleQuery instance to variable before
	 * specifying and executing query
	 *
	 * @param Mysqldbi $dbConnection
	 * @return CustomQuery
	 */
	public static function create(Mysqldbi $dbConnection) {
		return new static($dbConnection);
	}

	/**
	 * executes query and returns number of rows
	 *
	 * @return int
	 */
	abstract public function count();

	/**
	 * executes query and returns array of (custom) row instances
	 *
	 * @return array
	 */
	abstract public function select();

	/**
	 * adds LIMIT clause, executes query and returns array of (custom) row instances
	 *
	 * @param number $rows
	 * @return array
	 */
	abstract public function selectFirst($rows = 1);

	/**
	/* adds LIMIT clause with offset and count, executes query and returns array of (custom) row instances
	 *
	 * @see \vxPHP\Orm\QueryInterface::selectFromTo()
	 */
	public function selectFromTo($from, $to) {
		// TODO: Auto-generated method stub

	}


	/**
	 * stores WHERE clause and values which must be bound
	 *
	 * @param string $conditionString
	 * @param unknown $value
	 */
	protected function addCondition($conditionString, $value) {

		$condition = new \stdClass();

		$condition->conditionString = $conditionString;
		$condition->value			= $value;

		$this->whereClauses[] = $condition;

	}

	/**
	 * builds query string by parsing WHERE and ORDER BY clauses
	 */
	protected function buildQueryString() {

		$w = array();
		$s = array();

		foreach($this->whereClauses as $where) {
			$w[] = $where->conditionString;
		}

		foreach($this->columnSorts as $sort) {
			$s[] = $sort->column . ($sort->asc ? '' : ' DESC');
		}

		$this->sql = $this->selectSql;

		if(count($w)) {
			$this->sql .= ' WHERE (' . implode(') AND (', $w) .')';
		}
		if(count($s)) {
			$this->sql .= ' ORDER BY ' . implode(', ', $s);
		}

	}

	/**
	 * prepares array containing values which must be bound to prepared statement
	 */
	protected function buildValuesArray() {

		foreach($this->whereClauses as $where) {

			if(is_null($where->value)) {
				continue;
			}
			if(is_array($where->value)) {
				$this->valuesToBind = array_merge($this->valuesToBind, $where->value);
			}
			else {
				$this->valuesToBind[] = $where->value;
			}

		}

	}

	/**
	 * bind values and execute the SQL statement
	 * returns array of records
	 *
	 * @todo caching/do not prepare statement again, if query hasn't changed
	 *
	 * @return array
	 */
	protected function executeQuery() {

		$this->lastQuerySql = $this->sql;
		return $this->dbConnection->doPreparedQuery($this->sql, $this->valuesToBind);

	}

}
