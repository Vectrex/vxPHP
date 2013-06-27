<?php

namespace vxPHP\Orm\Custom;

use vxPHP\Orm\Custom\ArticleQuery;
use vxPHP\Orm\Custom\Exception\ArticleException;

use vxPHP\Observer\SubjectInterface;
use vxPHP\Observer\EventDispatcher;

use vxPHP\User\User;
use vxPHP\User\Admin;
use vxPHP\User\Exception\UserException;

use vxPHP\File\MetaFile;
use vxPHP\Database\Mysqldbi;

/**
 * Mapper class for articles, stored in table `articles`
 *
 * @author Gregor Kofler
 * @version 0.6.7 2013-06-19
 */

class Article implements SubjectInterface {

	private static	$instancesById,
					$instancesByAlias,
					/**
					 * @var Mysqldbi
					 */
					$db;

	private	$id,
			$alias,
			$headline,
			$data,
			$customFlags,
			$customSort,
			$referencingFiles,
			$previouslySavedValues,
			$dataCols = array('Teaser', 'Content');

	/**
	 * @var ArticleCategory
	 */
	private	$category;

	/**
	 * @var \DateTime
	 */
	private	$articleDate;

	/**
	 * @var \DateTime
	 */
	private	$displayFrom;

	/**
	 * @var \DateTime
	 */
	private	$displayUntil;

	/**
	 * @var \DateTime
	 */
	private	$lastUpdated;

	/**
	 * @var \DateTime
	 */
	private	$firstCreated;

	/**
	 * @var User
	 */
	private	$createdBy;

	/**
	 * @var User
	 */
	private	$updatedBy;

	public function __construct() {
	}

	public function __toString() {
		return $this->alias;
	}

	/**
	 * checks whether an article was changed when compared to the data used for instancing
	 * evaluates to TRUE for a new article
	 *
	 * @todo changes to referencingFiles are currently ignored
	 *
	 * @return boolean
	 */
	public function wasChanged() {

		if(is_null($this->previouslySavedValues)) {
			return TRUE;
		}

		foreach(array_keys(get_object_vars($this->previouslySavedValues)) as $p) {
			if(is_array($this->previouslySavedValues->$p)) {
				if(count(array_diff_assoc($this->previouslySavedValues->$p, $this->$p)) > 0) {
					return TRUE;
				}
			}
			else {

				// non type-strict comparison for DateTime instances

				if($this->previouslySavedValues->$p instanceof \DateTime) {

					if($this->previouslySavedValues->$p != $this->$p) {
						return TRUE;
					}

				}
				else {
					if($this->previouslySavedValues->$p !== $this->$p) {
						return TRUE;
					}
				}
			}
		}

		return FALSE;
	}
	/**
	 * store new article in database or update changes to existing article
	 *
	 * @throws ArticleException
	 */
	public function save() {

		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		if(is_null($this->headline) || trim($this->headline) == '') {
			throw new ArticleException("Headline not set. Article can't be inserted", ArticleException::ARTICLE_HEADLINE_NOT_SET);
		}

		if(is_null($this->category)) {
			throw new ArticleException("Category not set. Article can't be inserted", ArticleException::ARTICLE_CATEGORY_NOT_SET);
		}

		EventDispatcher::getInstance()->notify($this, 'beforeArticleSave');

		if(!is_null($this->id)) {

			// update

			$this->alias = self::$db->getAlias($this->headline, 'articles', $this->id);

			$cols = array_merge(
				(array) $this->getData(),
				array(
					'Alias'					=> $this->alias,
					'articlecategoriesID'	=> $this->category->getId(),
					'Headline'				=> $this->headline,
					'Article_Date'			=> is_null($this->articleDate) ? NULL : $this->articleDate->format('Y-m-d H:i:s'),
					'Display_from'			=> is_null($this->displayFrom) ? NULL : $this->displayFrom->format('Y-m-d H:i:s'),
					'Display_until'			=> is_null($this->displayUntil) ? NULL : $this->displayUntil->format('Y-m-d H:i:s'),
					'customFlags'			=> $this->customFlags,
					'customSort'			=> $this->customSort,
					'updatedBy'				=> Admin::getInstance()->getAdminId()
				)
			);

			self::$db->updateRecord('articles', $this->id, $cols);
		}

		else {

			// insert

			$this->alias = self::$db->getAlias($this->headline, 'articles');

			$cols = array_merge(
				(array) $this->getData(),
				array(
					'Alias'					=> $this->alias,
					'articlecategoriesID'	=> $this->category->getId(),
					'Headline'				=> $this->headline,
					'Article_Date'			=> is_null($this->articleDate) ? NULL : $this->articleDate->format('Y-m-d H:i:s'),
					'Display_from'			=> is_null($this->displayFrom) ? NULL : $this->displayFrom->format('Y-m-d H:i:s'),
					'Display_until'			=> is_null($this->displayUntil) ? NULL : $this->displayUntil->format('Y-m-d H:i:s'),
					'customFlags'			=> $this->customFlags,
					'customSort'			=> $this->customSort,
					'createdBy'				=> Admin::getInstance()->getAdminId()
				)
			);

			$this->id = self::$db->insertRecord('articles', $cols);

			// set file references

			if(!is_null($this->referencingFiles)) {
				foreach($this->referencingFiles as $f) {
					$f->setMetaData(array('referenced_Table' => 'articles', 'referencedID' => $this->id));
				}
			}
		}

		EventDispatcher::getInstance()->notify($this, 'afterArticleSave');

	}

	/**
	 * delete article, unlink references in metafiles
	 */
	public function delete() {

		// only already saved articles can actively be deleted

		if(!is_null($this->id)) {

			EventDispatcher::getInstance()->notify($this, 'beforeArticleDelete');

			// delete record

			$GLOBALS['db']->deleteRecord('articles', $this->id);

			// delete instance references

			unset(self::$instancesById[$this->id]);
			unset(self::$instancesByAlias[$this->alias]);

			// unlink referenced files

			foreach($this->getReferencingFiles() as $f) {
				$f->setMetaData(array('referenced_Table' => '', 'referencedID' => ''));
			}

			EventDispatcher::getInstance()->notify($this, 'afterArticleDelete');

		}
	}

	/**
	 *
	 * @param MetaFile $file
	 */
	public function addMetafile(MetaFile $file) {

		if(!in_array($file, $this->referencingFiles)) {
			$this->referencingFiles[] = $file;

			// set reference when article already saved

			if(!is_null($this->id)) {
				$file->setMetaData(array('referenced_Table' => 'articles', 'referencedID' => $this->id));
			}
		}
	}

	/**
	 * get numeric id (primary key in db) of article
	 *
	 * @return integer
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * get alias of article
	 *
	 * @return string
	 */
	public function getAlias() {
		return $this->alias;
	}

	/**
	 * get user which created article
	 *
	 * @return User
	 */
	public function getCreatedBy() {
		return $this->createdBy;
	}

	/**
	 * get user which updated article
	 *
	 * @return User
	 */
	public function getUpdatedBy() {
		return $this->updatedBy;
	}

	/**
	 * get timestamp of article creation
	 *
	 *  @return DateTime
	 */
	public function getFirstCreated() {
		return $this->firstCreated;
	}

	/**
	 * get timestamp of last article update
	 *
	 *  @return DateTime
	 */
	public function getLastUpdated() {
		return $this->lastUpdated;
	}

	/**
	 * get custom sort value
	 *
	 * @return int
	 */
	public function getCustomSort() {
		return $this->customSort;
	}

	/**
	 * set custom sort value
	 *
	 * @param mixed $ndx
	 */
	public function setCustomSort($ndx) {
		if(is_numeric($ndx)) {
			$this->customSort = (int) $ndx;
		}
		else {
			$this->customSort = NULL;
		}
	}

	/**
	 * get article date
	 *
	 * @return DateTime
	 */
	public function getDate() {
		return $this->articleDate;
	}

	/**
	 * set article date, omitting argument deletes date value
	 *
	 * @param DateTime $articleDate
	 */
	public function setDate(\DateTime $articleDate = NULL) {
		$this->articleDate = $articleDate;
	}

	/**
	 * get displayFrom date
	 *
	 * @return DateTime
	 */
	public function getDisplayFrom() {
		return $this->displayFrom;
	}

	/**
	 * set displayFrom date, omitting argument deletes date value
	 *
	 * @param DateTime $articleDate
	 */
	public function setDisplayFrom(\DateTime $displayFrom = NULL) {
		$this->displayFrom = $displayFrom;
	}

	/**
	 * get displayUntil date
	 *
	 * @return DateTime
	 */
	public function getDisplayUntil() {
		return $this->displayUntil;
	}

	/**
	 * set displayUntil date, omitting argument deletes date value
	 *
	 * @param DateTime $articleDate
	 */
	public function setDisplayUntil(\DateTime $displayUntil = NULL) {
		$this->displayUntil = $displayUntil;
	}

	/**
	 * assign article to category
	 *
	 * @param ArticleCategory $category
	 */
	public function setCategory(ArticleCategory $category) {
		$this->category = $category;
	}

	/**
	 * get assigned category
	 *
	 * @return ArticleCategory
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * set headline of article; this also dertermines the alias
	 *
	 * @param string $headline
	 */
	public function setHeadline($headline) {
		$this->headline = trim($headline);
	}

	/**
	 * get headline
	 *
	 * @return string
	 */
	public function getHeadline() {
		return $this->headline;
	}

	/**
	 * get misc article data
	 * when $ndx is omitted all misc data is returned in an associative array
	 *
	 * @param string $ndx
	 * @return mixed
	 */
	public function getData($ndx = NULL) {
		if(is_null($ndx)) {
			return $this->data;
		}
		if(isset($this->data[$ndx])) {
			return $this->data[$ndx];
		}
	}

	/**
	 * sets misc data of article; only keys listet in Article::dataCols are accepted
	 *
	 * @param array $data
	 */
	public function setData(array $data) {
		foreach($this->dataCols as $c) {
			if(isset($data[$c])) {
				$this->data[$c] = $data[$c];
			}
		}
	}

	/**
	 * returns array of files referencing this article
	 *
	 * @return array
	 */
	public function getReferencingFiles() {
		if(!is_null($this->id) && is_null($this->referencingFiles)) {
			$this->referencingFiles = MetaFile::getFilesForReference($this->id, 'articles', 'sortByCustomSort');
		}
		return $this->referencingFiles;
	}

	/**
	 * create Article instance from data supplied in $articleData
	 *
	 * @param array $articleData
	 * @return Article
	 */
	private static function createInstance(array $articleData) {

		$article = new self();

		// set identification

		$article->alias		= $articleData['Alias'];
		$article->id		= $articleData['articlesID'];

		// set category

		$article->category	= ArticleCategory::getInstance($articleData['articlecategoriesID']);

		// set admin information

		try {
			$article->createdBy = new User();
			$article->createdBy->setUser($articleData['createdBy']);
		}
		catch(UserException $e) {}

		try {
			$article->updatedBy = new User();
			$article->updatedBy->setUser($articleData['updatedBy']);
		}
		catch(UserException $e) {}

		// set date information

		if(!empty($articleData['Display_from'])) {
			$article->displayFrom = new \DateTime($articleData['Display_from']);
		}

		if(!empty($articleData['Display_until'])) {
			$article->displayUntil = new \DateTime($articleData['Display_until']);
		}

		if(!empty($articleData['Article_Date'])) {
			$article->articleDate = new \DateTime($articleData['Article_Date']);
		}

		if(!empty($articleData['firstCreated'])) {
			$article->firstCreated = new \DateTime($articleData['firstCreated']);
		}

		if(!empty($articleData['lastUpdated'])) {
			$article->lastUpdated = new \DateTime($articleData['lastUpdated']);
		}

		// flags and sort

		$article->customFlags	= $articleData['customFlags'];
		$article->customSort	= $articleData['customSort'];

		// set various text fields

		$article->setHeadline($articleData['Headline']);
		$article->setData($articleData);

		// backup values to check whether record was changed

		$article->previouslySavedValues = new \stdClass();

		$article->previouslySavedValues->headline		= $article->headline;
		$article->previouslySavedValues->category		= $article->category;
		$article->previouslySavedValues->data			= $article->data;
		$article->previouslySavedValues->displayFrom	= $article->displayFrom;
		$article->previouslySavedValues->displayUntil	= $article->displayUntil;
		$article->previouslySavedValues->articleDate	= $article->articleDate;
		$article->previouslySavedValues->customFlags	= $article->customFlags;
		$article->previouslySavedValues->customSort		= $article->customSort;

		return $article;
	}

	/**
	 * returns article instance identified by numeric id or alias
	 *
	 * @param mixed $id
	 * @throws ArticleException
	 * @return Article
	 */
	public static function getInstance($id) {

		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		if(is_numeric($id)) {
			$id = (int) $id;
			if(isset(self::$instancesById[$id])) {
				return self::$instancesById[$id];
			}

			$col = 'articlesID';
		}
		else {
			if(isset(self::$instancesByAlias[$id])) {
				return self::$instancesByAlias[$id];
			}

			$col = 'Alias';
		}

		$rows = self::$db->doPreparedQuery("
			SELECT
				a.*
			FROM
				articles a
			WHERE
				a.$col = ?", array($id));

		if(empty($rows)) {
			throw new ArticleException("Article with $col '$id' does not exist.", ArticleException::ARTICLE_DOES_NOT_EXIST);
		}

		// generate and store instance

		$article = self::createInstance($rows[0]);

		self::$instancesByAlias[$article->alias]	= $article;
		self::$instancesById[$article->id]			= $article;

		return $article;
	}

	/**
	 * returns array of Article objects identified by numeric id or alias
	 *
	 * @param array $ids contains mixed article ids or alias
	 * @return array
	 */
	public static function getInstances(array $ids) {

		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

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
				$where[] = 'a.articlesID IN (' . implode(',', array_fill(0, count($toRetrieveById), '?')). ')';
			}
			if(count($toRetrieveByAlias)) {
				$where[] = 'a.alias IN (' . implode(',', array_fill(0, count($toRetrieveByAlias), '?')). ')';
			}

			if(count($where)) {
				$rows = self::$db->doPreparedQuery('
					SELECT
						a.*
					FROM
						articles a
					WHERE
						' . implode(' OR ', $where),
				array_merge($toRetrieveById, $toRetrieveByAlias));

				foreach($rows as $row) {
					$article = self::createInstance($row);
					self::$instancesByAlias[$article->alias]	= $article;
					self::$instancesById[$article->id]			= $article;
				}
			}
		}

		$articles = array();

		foreach($ids as $id) {
			$articles[] = self::getInstance($id);
		}

		return $articles;

	}

	/**
	 * get all articles assigned to given $category
	 *
	 * @param ArticleCategory $category
	 * @return Array
	 */
	public static function getArticlesForCategory(ArticleCategory $category) {
		$articles = array();

		$rows = $GLOBALS['db']->doPreparedQuery('SELECT * FROM articles WHERE articlecategoriesID = ?', array($category->getId()));

		foreach($rows as $r) {
			if(!isset(self::$instancesById[$r['articlesID']])) {

				// create Article instance if it does not yet exist

				$article = self::createInstance($r);

				self::$instancesByAlias[$article->alias]	= $article;
				self::$instancesById[$article->id]			= $article;
			}

			$articles[] = self::$instancesById[$r['articlesID']];
		}

		return $articles;
	}

	public static function ArticlesFromQuery(ArticleQuery $query) {
		return $this;
	}
}
