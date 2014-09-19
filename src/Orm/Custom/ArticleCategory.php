<?php

namespace vxPHP\Orm\Custom;

use vxPHP\Orm\Custom\Exception\ArticleCategoryException;
use vxPHP\Orm\Custom\Article;
use vxPHP\Application\Application;

/**
 * Mapper class for articlecategories, stored in table `articlecategories`
 *
 * @author Gregor Kofler
 * @version 0.2.2 2014-09-19
 */

class ArticleCategory {

	private static	$instancesById		= array(),
					$instancesByAlias	= array();

	private	$id,
			$alias,
			$level, $l, $r,
			$title,
			$customSort,
			$articles;

	/**
	 * @var ArticleCategory
	 */
	private $parentCategory;


	/**
	 * create a new category
	 * a freshly created category has no representation in the database yet
	 * persistence requires a save()
	 *
	 * @param string $title
	 * @param ArticleCategory $parentCategory
	 */
	public function __construct($title, ArticleCategory $parentCategory = NULL) {
		$this->parentCategory	= $parentCategory;
		$this->title			= $title;
	}

	public function __toString() {
		return $this->alias;
	}

	public function __destruct() {

		// @todo clean up nesting

	}

	/**
	 * save category
	 *
	 * @todo only INSERTs are supported, UPDATE has to be implemented
	 * @throws ArticleCategoryException
	 */
	public function save() {

		$db		= Application::getInstance()->getDb();
		$data	= array();

		if(is_null($this->parentCategory)) {

			// prepare to insert top level category

			$rows			= $db->doPreparedQuery('SELECT MAX(r) + 1 AS l FROM articlecategories');
			$this->l		= !isset($rows[0]['l']) ? 0 : $rows[0]['l'];
			$this->r		= $rows[0]['l'] + 1;
			$this->level	= 0;
		}

		else {

			// prepare to insert subcategory

			// in case parent category has not been saved - save it

			if(is_null($this->parentCategory->id)) {
				$this->parentCategory->save();
			}

			try {
				$nsData = $this->parentCategory->getNsData();
			}
			catch(ArticleCategoryException $e) {
				throw $e;
			}

			$this->l		= $nsData['r'];
			$this->r		= $nsData['r'] + 1;
			$this->level	= $nsData['level'] + 1;

			$db->doPreparedExecute("UPDATE articlecategories SET r = r + 2 WHERE r >= ?", array($this->l));
			$db->doPreparedExecute("UPDATE articlecategories SET l = l + 2 WHERE l > ?", array($this->r));
		}

		// insert category data

		$this->alias = $db->getAlias($this->title, 'articlecategories');

		$this->id = $db->insertRecord('articlecategories', array(
			'Alias'			=> $this->alias,
			'l'				=> $this->l,
			'r'				=> $this->r,
			'level'			=> $this->level,
			'Title'			=> $this->title,
			'customSort'	=> $this->customSort
		));

		self::$instancesByAlias[$cat->alias]	= $this;
		self::$instancesById[$cat->id]			= $this;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getAlias() {
		return $this->alias;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	public function setTitle($title) {
		$this->title = trim($title);
	}


	/**
	 * @return int
	 */
	public function getCustomSort() {
		return $this->customSort;
	}

	public function setCustomSort($ndx) {
		$this->customSort = (int) $ndx;
	}

	/**
	 * retrieves all articles assigned to this article category
	 *
	 * @return array
	 */
	public function getArticles() {
		if(is_null($this->articles)) {
			$this->articles = Article::getArticlesForCategory($this);
		}
		return $this->articles;
	}

	public function setParent(ArticleCategory $parent) {

		// @todo update nesting of previous parent category

		$this->parentCategory = $parent;
	}

	private function getNsData() {

		// re-read data for already stored categories, if not already retrieved

		if(!is_null($this->id)) {
			if(is_null($this->r) && is_null($this->l) && is_null($this->level)) {
				$rows = Application::getInstance()->getDb()->doPreparedQuery("SELECT r, l, level FROM articlecategories c WHERE articlecategoriesID = ?", array((int) $this->id));
			}
		}
		return array('r' => $this->r, 'l' => $this->l, 'level' => $this->level);
	}

	/**
	 * returns articlecategory instance identified by numeric id or alias
	 *
	 * @param mixed $id
	 * @throws ArticleCategoryException
	 * @return \vxPHP\Orm\Custom\ArticleCategory
	 */
	public static function getInstance($id) {

		$db = Application::getInstance()->getDb();

		if(is_numeric($id)) {
			$id = (int) $id;
			if(isset(self::$instancesById[$id])) {
				return self::$instancesById[$id];
			}

			$col = 'articlecategoriesID';
		}
		else {
			if(isset(self::$instancesByAlias[$id])) {
				return self::$instancesByAlias[$id];
			}

			$col = 'Alias';
		}

		$rows = $db->doPreparedQuery("
			SELECT
				c.*,
				p.articlecategoriesID AS parentID
			FROM
				articlecategories c
				LEFT JOIN articlecategories p ON p.l < c.l AND p.r > c.r AND p.level = c.level - 1
			WHERE
				c.$col = ?", array($id));

		if(empty($rows)) {
			throw new ArticleCategoryException("Category with $col '$id' does not exist.", ArticleCategoryException::ARTICLECATEGORY_DOES_NOT_EXIST);
		}

		$row  = $rows[0];

		if(!empty($row['level'])) {
			if(empty($row['parentID'])) {
				throw new ArticleCategoryException("Category '{$row['Title']}' not properly nested.", ArticleCategoryException::ARTICLECATEGORY_NOT_NESTED);
			}
			else {
				$cat = new self($row['Title'], ArticleCategory::getInstance($row['parentID']));
			}
		}
		else {
			$cat = new self($row['Title']);
		}

		$cat->id			= $row['articlecategoriesID'];
		$cat->alias			= $row['Alias'];
		$cat->r				= $row['r'];
		$cat->l				= $row['l'];
		$cat->level			= $row['level'];
		$cat->customSort	= $row['customSort'];

		self::$instancesByAlias[$cat->alias]	= $cat;
		self::$instancesById[$cat->id]			= $cat;

		return $cat;
	}

	/**
	 * returns array of ArticleCategory objects identified by numeric id or alias
	 *
	 * @param array $ids contains mixed category ids or alias
	 * @throws ArticleCategoryException
	 * @return array
	 */
	public static function getInstances(array $ids) {

		$db = Application::getInstance()->getDb();

		$toRetrieveById		= array();
		$toRetrieveByAlias	= array();

		foreach($ids as $id) {

			if(is_numeric($id)) {
				$id = (int) $id;

				if(!isset(self::$instancesById[$id])) {
					$toRetrieveById[] = $id;
				}
			}

			else {
				if(!isset(self::$instancesByAlias[$id])) {
					$toRetrieveByAlias[] = $id;
				}
			}

			$where = array();

			if(count($toRetrieveById)) {
				$where[] = 'c.articlecategoriesID IN (' . implode(',', array_fill(0, count($toRetrieveById), '?')). ')';
			}
			if(count($toRetrieveByAlias)) {
				$where[] = 'c.alias IN (' . implode(',', array_fill(0, count($toRetrieveByAlias), '?')). ')';
			}

			if(count($where)) {

				$rows = $db->doPreparedQuery('
					SELECT
						c.*,
						p.articlecategoriesID AS parentID
					FROM
						articlecategories c
						LEFT JOIN articlecategories p ON p.l < c.l AND p.r > c.r AND p.level = c.level - 1
					WHERE
						' . implode(' OR ', $where),
					array_merge($toRetrieveById, $toRetrieveByAlias)
				);

				foreach($rows as $row) {

					if(!empty($row['level'])) {
						if(empty($row['parentID'])) {
							throw new ArticleCategoryException("Category '{$row['Title']}' not properly nested.", ArticleCategoryException::ARTICLECATEGORY_NOT_NESTED);
						}
						else {
							$cat = new self($row['Title'], ArticleCategory::getInstance($row['parentID']));
						}
					}
					else {
						$cat = new self($row['Title']);
					}

					$cat->id			= $row['articlecategoriesID'];
					$cat->alias			= $row['Alias'];
					$cat->r				= $row['r'];
					$cat->l				= $row['l'];
					$cat->level			= $row['level'];
					$cat->customSort	= $row['customSort'];

					self::$instancesByAlias[$cat->alias]	= $cat;
					self::$instancesById[$cat->id]			= $cat;
				}
			}
		}

		$categories = array();

		foreach($ids as $id) {
			$categories[] = self::getInstance($id);
		}

		return $categories;
	}


	/**
	 * retrieve all available categories sorted by $sortCallback
	 *
	 * @param mixed $sortCallback
	 * @throws ArticleCategoryException
	 * @return array categories
	 */
	public static function getArticleCategories($sortCallback = NULL) {

		$cat = array();

		foreach(
			Application::getInstance()->getDb()->doPreparedQuery(
				'SELECT articlecategoriesID FROM articlecategories'
			)
		as $r) {
			$cat[] = self::getInstance($r['articlecategoriesID']);
		}

		if(is_null($sortCallback)) {
			return $cat;
		}

		if(is_callable($sortCallback)) {
			usort($cat, $sortCallback);
			return $cat;
		}

		if(is_callable("self::$sortCallback")) {
			usort($cat, "self::$sortCallback");
			return $cat;
		}

		throw new ArticleCategoryException("'$sortCallback' is not callable.", ArticleCategoryException::ARTICLECATEGORY_SORT_CALLBACK_NOT_CALLABLE);
	}

	/**
	 * various callback functions for sorting categories
	 */
	private static function sortByCustomSort($a, $b) {
		$csa = $a->getCustomSort();
		$csb = $b->getCustomSort();

		if($csa === $csb) {
			return $a->getTitle() < $b->getTitle() ? -1 : 1;
		}
		if(is_null($csa)) {
			return 1;
		}
		if(is_null($csb)) {
			return -1;
		}
		return $csa < $csb ? -1 : 1;
	}
}
