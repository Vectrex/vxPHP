<?php
namespace vxPHP\Orm\Custom;

use vxPHP\Orm\Custom\Article;
use vxPHP\Orm\Custom\ArticleCategory;
use vxPHP\Database\Mysqldbi;

class ArticleQuery {

	private	$dbConnection,
			$whereClauses	= array(),
			$columnSorts	= array(),
			$valuesToBind	= array(),
			$sql,
			$sqlNeedsUpdate;

	public function __construct(Mysqldbi $dbConnection) {

		$this->sqlNeedsUpdate = TRUE;
		$this->dbConnection = $dbConnection;

	}

	public function filterByCategory(ArticleCategory $category) {

		$this->addCondition("articlecategoriesID = ?", $category->getId());
		$this->sqlNeedsUpdate = TRUE;

		return $this;

	}

	public function filter($columnName, $value) {

		$this->addCondition("$columnName = ?", $value);
		$this->sqlNeedsUpdate = TRUE;

		return $this;

	}

	public function where($whereClause, Array $valuesToBind = NULL) {

		$this->addCondition($whereClause, $valuesToBind);
		$this->sqlNeedsUpdate = TRUE;

		return $this;

	}

	public function sortBy($columnName, $asc = TRUE) {

		$sort = new \stdClass();

		$sort->column = $columnName;
		$sort->asc = !!$asc;

		$this->columnSorts[] = $sort;
		$this->sqlNeedsUpdate = TRUE;

		return $this;

	}

	public function select() {

		if($this->sqlNeedsUpdate) {
			$this->buildQueryString();
		}

		$this->buildValuesArray();

		var_dump($this->sql);

		$rows = $this->dbConnection->doPreparedQuery($this->sql, $this->valuesToBind);

		var_dump($rows);

	}

	public function selectFirst($rows = 1) {

	}

	private function addCondition($conditionString, $value) {

		$condition = new \stdClass();

		$condition->conditionString = $conditionString;
		$condition->value			= $value;

		$this->whereClauses[] = $condition;

		return $this;

	}

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
