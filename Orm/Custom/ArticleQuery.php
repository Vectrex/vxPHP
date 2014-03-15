<?php
namespace vxPHP\Orm\Custom;

use vxPHP\Orm\Query;
use vxPHP\Orm\QueryInterface;
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
 * @version 0.2.2 2014-03-15
 */
class ArticleQuery extends Query implements QueryInterface {

	/**
	 * provide initial database connection
	 * currently only allows a Mysqli backend
	 *
	 * @param Mysqldbi $dbConnection
	 */
	public function __construct(Mysqldbi $dbConnection) {

		$this->table		= 'articles';
		$this->alias		= 'a';
		$this->columns		= array('a.articlesID');

		parent::__construct($dbConnection);

	}

	/**
	 * add WHERE clause that filters for $category
	 *
	 * @param ArticleCategory $category
	 * @return ArticleQuery
	 */
	public function filterByCategory(ArticleCategory $category) {

		$this->addCondition("a.articlecategoriesID = ?", $category->getId());
		return $this;

	}

	/**
	 * add WHERE clause that filters for article category aliases
	 *
	 * @param array $categoryNames
	 */
	public function filterByCategoryNames(array $categoryNames) {
		$this->innerJoin('articlecategories c', 'c.articlecategoriesID = a.articlecategoriesID');
		$this->addCondition('c.Alias', $categoryNames, 'IN');
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

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Orm\Query::count()
	 */
	public function count() {
		// TODO: Auto-generated method stub

	}

	/**
	/* (non-PHPdoc)
	 * @see \vxPHP\Orm\Query::selectFromTo()
	 */
	public function selectFromTo($from, $to) {
		// TODO: Auto-generated method stub

	}

}
