<?php
namespace vxPHP\Orm\Custom;

use vxPHP\Orm\Custom\CustomQuery;
use vxPHP\Orm\Custom\CustomQueryInterface;
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
 * @version 0.2.0 2013-04-10
 *
 * @todo avoid preparation of statement when underlying SQL doesn't change
 *
 */
class ArticleQuery extends CustomQuery implements CustomQueryInterface {

	/**
	 * provide initial database connection
	 * currently only allows a Mysqli backend
	 *
	 * @param Mysqldbi $dbConnection
	 */
	public function __construct(Mysqldbi $dbConnection) {

		$this->selectSql = 'SELECT articlesID FROM articles';
		parent::__construct($dbConnection);

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
	 * executes query and returns array of Article instances
	 *
	 * @return array
	 */
	public function select() {

		$this->buildQueryString();
		$this->buildValuesArray();
		$rows = $this->executeQuery();

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

		$rows = $this->executeQuery();

		$ids = array();

		foreach($rows as $row) {
			$ids[] = $row['articlesID'];
		}

		return Article::getInstances($ids);
	}

}
