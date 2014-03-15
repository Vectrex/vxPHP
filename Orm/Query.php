<?php

namespace vxPHP\Orm;

use vxPHP\Database\Mysqldbi;
use vxPHP\Orm\QueryInterface;

/**
 * abstract class for ORM queries
 *
 * @author Gregor Kofler
 * @version 0.1.4 2014-03-15
 */
abstract class Query implements QueryInterface {

	/**
	 * @var Mysqldbi
	 */
	protected	$dbConnection;

	protected	$columns		= array(),
				$table,
				$alias,
				$innerJoins		= array(),
				$whereClauses	= array (),
				$columnSorts	= array (),
				$valuesToBind	= array (),
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
	 * @param string|number|array $value
	 * @return ArticleQuery
	 */
	public function filter($columnName, $value) {

		if(is_array($value)) {
			$this->addCondition($columnName, $value, 'IN');
		}

		else {
			$this->addCondition($columnName, $value, '=');
		}
		return $this;

	}

	/**
	 *
	 * @param unknown $table
	 * @param unknown $on
	 */
	public function innerJoin($table, $on) {

		$join			= new \stdClass();
		$join->table	= $table;
		$join->on		= $on;

		$this->innerJoins[] = $join;
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
	 * @see \vxPHP\Orm\QueryInterface::dumpSql()
	 */
	public function dumpSql() {

		if(!$this->sql) {
			$this->buildQueryString();
		}

		return $this->sql;

	}


	/**
	 * stores WHERE clause and values which must be bound
	 * when an operator is supplied, $conditionOrColumn will hold a column name,
	 * otherwise a condition including comparison operator
	 *
	 * @param string $conditionOrColumn
	 * @param string|number|array $value
	 * @param string $operator
	 */
	protected function addCondition($conditionOrColumn, $value, $operator = NULL) {

		$condition = new \stdClass();

		$condition->conditionOrColumn	= $conditionOrColumn;
		$condition->value				= $value;
		$condition->operator			= strtoupper($operator);

		$this->whereClauses[] = $condition;

	}

	/**
	 * builds query string by parsing WHERE and ORDER BY clauses
	 * @todo column names are currently masked in MySQL style
	 * @todo incomplete masking (e.g. ON clauses)
	 */
	protected function buildQueryString() {

		$w = array();
		$s = array();

		// start SQL statement

		$this->sql = 'SELECT ';

		// add columns

		if(!$this->columns || !count($this->columns)) {
			$this->sql .= '*';
		}
		else {
			$this->sql .= sprintf('`%s`', str_replace('.', '`.`', implode('`,`', $this->columns)));
		}

		// add table

		$this->sql .= sprintf(' FROM `%s`', str_replace('.', '`.`', $this->table));

		// add alias

		if($this->alias) {
			$this->sql .= sprintf(' `%s`', $this->alias);
		}

		// add INNER JOINs

		foreach($this->innerJoins as $join) {
			$this->sql .= sprintf(' INNER JOIN `%s` ON %s', preg_replace('/\s+/', '` `', trim($join->table)), $join->on);
		}

		// build WHERE clause

		foreach($this->whereClauses as $where) {

			// complete condition when no operator set

			if(!$where->operator) {
				$w[] = $where->conditionOrColumn;
			}

			// otherwise parse operator

			else {
				if($where->operator === 'IN') {
					$w[] = sprintf(
						'`%s` IN (%s)',
						str_replace('.', '`.`', $where->conditionOrColumn),
						implode(', ', array_fill(0, count($where->value), '?'))
					);
				}
				else {
					$w[] = sprintf(
						'`%s` %s ?',
						str_replace('.', '`.`', $where->conditionOrColumn),
						$where->operator
					);
				}
			}
		}

		// build SORT clause

		foreach($this->columnSorts as $sort) {
			$s[] = $sort->column . ($sort->asc ? '' : ' DESC');
		}

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
