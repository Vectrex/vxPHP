<?php
namespace vxPHP\Orm\Custom;

use vxPHP\Orm\Custom\Article;
use vxPHP\Orm\Custom\ArticleCategory;
use vxPHP\Database\Mysqldbi;

/**
 * query object which returns an array of Article objects
 *
 * @example
 *
 * $articles =	vxPHP\Orm\Custom\ArticleQuery::create($db)->
 * 				filterByCategory($myCat)->
 * 				where('article_date < ?', new DateTime()->format('Y-m-d'))->
 * 				sortBy('customSort', FALSE)->
 * 				sortBy('Headline')->
 * 				selectFirst(2);
 *
 * @author Gregor Kofler
 * @version 0.1.0 2013-03-29
 * 
 * @todo avoid preparation of statement when underlying SQL doesn't change
 *
 */
class ArticleQuery {

	private	$dbConnection,
			$whereClauses	= array(),
			$columnSorts	= array(),
			$valuesToBind	= array(),
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
	 * add appropriate WHERE clause that filters for $category
	 *
	 * @param ArticleCategory $category
	 * @return ArticleQuery
	 */
	public function filterByCategory(ArticleCategory $category) {

		$this->addCondition("articlecategoriesID = ?", $category->getId());
		return $this;

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
	public function where($whereClause, Array $valuesToBind = NULL) {

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
	 * executes query and returns array of Article instances
	 *
	 * @return array
	 */
	public function select() {

		$this->buildQueryString();
		$this->buildValuesArray();

		//@todo do not prepare statement again, if query hasn't changed

		$this->lastQuerySql = $this->sql;

		$rows = $this->dbConnection->doPreparedQuery($this->sql, $this->valuesToBind);

		$ids = array();

		foreach($rows as $row) {
			$ids[] = $row['articlesID'];
		}

		return Article::getInstances($ids);
	}

	/**
	 * adds LIMIT clause, executes query and returns array of Article instances
	 *
	 * @param number $rows
	 * @return array
	 */
	public function selectFirst($rows = 1) {

		$this->buildQueryString();
		$this->buildValuesArray();

		$this->sql .= " LIMIT $rows";

		//@todo do not prepare statement again, if query hasn't changed

		$this->lastQuerySql = $this->sql;

		$rows = $this->dbConnection->doPreparedQuery($this->sql, $this->valuesToBind);

		$ids = array();

		foreach($rows as $row) {
			$ids[] = $row['articlesID'];
		}

		return Article::getInstances($ids);
	}

	/**
	 * static method for convenience reasons
	 * avoids assigning ArticleQuery instance to variable before
	 * specifying and executing query
	 *
	 * @param Mysqldbi $dbConnection
	 * @return ArticleQuery
	 */
	public static function create(Mysqldbi $dbConnection) {
		return new self($dbConnection);
	}

	/**
	 * stores WHERE clause and values which must be bound
	 *
	 * @param string $conditionString
	 * @param unknown $value
	 */
	private function addCondition($conditionString, $value) {

		$condition = new \stdClass();

		$condition->conditionString = $conditionString;
		$condition->value			= $value;

		$this->whereClauses[] = $condition;

	}

	/**
	 * builds query string by parsing WHERE and ORDER BY clauses
	 */
	private function buildQueryString() {

		$w = array();
		$s = array();

		foreach($this->whereClauses as $where) {
			$w[] = $where->conditionString;
		}

		foreach($this->columnSorts as $sort) {
			$s[] = $sort->column . ($sort->asc ? '' : ' DESC');
		}

		$this->sql = 'SELECT articlesID FROM articles';
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
	private function buildValuesArray() {

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
}
