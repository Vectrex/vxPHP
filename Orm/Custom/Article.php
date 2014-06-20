<?php

namespace vxPHP\Orm\Custom;

use vxPHP\Orm\Custom\ArticleQuery;
use vxPHP\Orm\Custom\Exception\ArticleException;

use vxPHP\Observer\SubjectInterface;
use vxPHP\Observer\EventDispatcher;

use vxPHP\User\User;
use vxPHP\User\Exception\UserException;

use vxPHP\File\MetaFile;
use vxPHP\Application\Application;

/**
 * Mapper class for articles, stored in table `articles`
 *
 * @author Gregor Kofler
 * @version 0.8.1 2014-04-13
 */

class Article implements SubjectInterface {

	private static	$instancesById,
					$instancesByAlias;

	private	$id,
			$alias,
			$headline,
			$data,
			$customFlags,
			$customSort,

			/**
			 * @var array
			 */
			$linkedFiles,
			
			/**
			 * @var boolean
			 */
			$updateLinkedFiles,
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
	 * @todo changes of linked files are currently ignored
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
	 * @todo consider transactions
	 */
	public function save() {

		$db = Application::getInstance()->getDb();

		if(is_null($this->headline) || trim($this->headline) == '') {
			throw new ArticleException("Headline not set. Article can't be inserted", ArticleException::ARTICLE_HEADLINE_NOT_SET);
		}

		if(is_null($this->category)) {
			throw new ArticleException("Category not set. Article can't be inserted", ArticleException::ARTICLE_CATEGORY_NOT_SET);
		}

		EventDispatcher::getInstance()->notify($this, 'beforeArticleSave');
		
		if(!is_null($this->id)) {

			// update

			$this->alias = $db->getAlias($this->headline, 'articles', $this->id);

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
					'updatedBy'				=> $this->updatedBy ? $this->updatedBy->getAdminId() : NULL
				)
			);

			$db->updateRecord('articles', $this->id, $cols);
		}

		else {

			// insert

			$this->alias = $db->getAlias($this->headline, 'articles');

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
					'updatedBy'				=> $this->createdBy ? $this->createdBy->getAdminId() : NULL,
					'createdBy'				=> $this->createdBy ? $this->createdBy->getAdminId() : NULL
				)
			);

			$this->id = $db->insertRecord('articles', $cols);

		}
		
		// store link information for linked files if linked files were changed in any way
		
		if($this->updateLinkedFiles) {

			// delete all previous entries
	
			$db->deleteRecord('articles_files', array('articlesID' => $this->id), TRUE);
			
			// save new references and use position in array as customSort value

			foreach($this->linkedFiles as $sortPosition => $file) {
				$db->insertRecord('articles_files', array(
					'articlesID'	=> $this->id,
					'filesID'		=> $file->getId(),
					'customSort'	=> $sortPosition
				));
			}
			
			$this->updateLinkedFiles = FALSE;
		}

		EventDispatcher::getInstance()->notify($this, 'afterArticleSave');

	}

	/**
	 * delete article, unlink references in metafiles
	 * 
	 * @todo consider transactions
	 * 
	 */
	public function delete() {

		// only already saved articles can actively be deleted

		if(!is_null($this->id)) {

			EventDispatcher::getInstance()->notify($this, 'beforeArticleDelete');

			// delete record

			$db = Application::getInstance()->getDb(); 
			$db->deleteRecord('articles', $this->id);

			// delete instance references

			unset(self::$instancesById[$this->id]);
			unset(self::$instancesByAlias[$this->alias]);

			// unlink referenced files

			foreach($this->linkedFiles as $file) {
				$file->unlinkArticle($this);
			}
			
			$db->deleteRecord('articles_files', array('articlesID' => $this->id));

			EventDispatcher::getInstance()->notify($this, 'afterArticleDelete');

		}
	}

	/**
	 * link a metafile to the article; additionally links article to metaFile
	 * when $sortPosition is set, the file reference is moved to this position within the files array
	 * 
	 * @param MetaFile $file
	 * @param int $sortPosition
	 */
	public function linkMetaFile(MetaFile $file, $sortPosition = NULL) {

		// get all linked files if not done previously

		if(is_null($this->linkedFiles)) {
			$this->getLinkedMetaFiles();
		}

		if(!in_array($file, $this->linkedFiles)) {

			// append file when no sort position is set or sort position beyond linked files length

			if(is_null($sortPosition) || !is_numeric($sortPosition) || (int) $sortPosition >= count($this->linkedFiles)) {
				$this->linkedFiles[] = $file;
			}
			
			// otherwise insert reference at given position

			else {
				array_splice($this->files, $sortPosition, 0, $file);
			}

			$file->linkArticle($this);
			$this->updateLinkedFiles = TRUE;

		}
	}
	
	/**
	 * remove a file reference
	 * ensures proper re-ordering of files array
	 * 
	 * @param MetaFile $file
	 */
	public function unlinkMetaFile(MetaFile $file) {

		// get all linked files if not done previously

		if(is_null($this->linkedFiles)) {
			$this->getLinkedMetaFiles();
		}

		// remove file reference if file is linked and ensure continuous numeric indexes

		if(($pos = array_search($file, $this->linkedFiles, TRUE)) !== FALSE) {
			array_splice($this->linkedFiles, $pos, 1);

			$file->unlinkArticle($this);
			$this->updateLinkedFiles = TRUE;
				
		}
	}
	
	/**
	 * set custom sort value $file
	 * 
	 * @param MetaFile $file
	 * @param int $sortPosition
	 */
	public function setCustomSortOfMetaFile(MetaFile $file, $sortPosition) {

		// get all linked files if not done previously
		
		if(is_null($this->linkedFiles)) {
			$this->getLinkedMetaFiles();
		}

		// is $file linked?

		if(($pos = array_search($file, $this->linkedFiles, TRUE)) !== FALSE) {
		
			// is $sortPosition valid and different from current position

			if(is_numeric($sortPosition) && (int) $sortPosition !== $pos) {

				// remove at old position

				array_splice($this->linkedFiles, $pos, 1);
				
				// insert at new position
				
				array_splice($this->linkedFiles, $sortPosition, 0, array($file));

				$this->updateLinkedFiles = TRUE;

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
	 * set user which created the article
	 * will only be effective with first save of article
	 * 
	 * @param User $user
	 */
	public function setCreatedBy(User $user) {
		if(is_null($this->createdBy)) {
			$this->createdBy = $user;
		}
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
	 * set user which updated the article
	 * 
	 * @param User $user
	 */
	public function setUpdatedBy(User $user) {
		$this->updatedBy = $user;
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
	 * returns array of MetaFile instances linked to the article
	 * 
	 * @return MetaFile[]
	 */
	public function getLinkedMetaFiles() {

		if(!is_null($this->id) && is_null($this->linkedFiles)) {
			$this->linkedFiles = MetaFile::getFilesForArticle($this);
		}

		return $this->linkedFiles;
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
			$article->createdBy = User::getInstance($articleData['createdBy']);
		}
		catch(\InvalidArgumentException $e) {}
		catch(UserException $e) {}

		try {
			$article->updatedBy = User::getInstance($articleData['updatedBy']);
		}
		catch(\InvalidArgumentException $e) {}
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

		$db = Application::getInstance()->getDb();

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

		$rows = $db->doPreparedQuery("
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
				$where[] = 'a.articlesID IN (' . implode(',', array_fill(0, count($toRetrieveById), '?')). ')';
			}
			if(count($toRetrieveByAlias)) {
				$where[] = 'a.alias IN (' . implode(',', array_fill(0, count($toRetrieveByAlias), '?')). ')';
			}

			if(count($where)) {
				$rows = $db->doPreparedQuery('
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

		$rows = Application::getInstance()->getDb()->doPreparedQuery('SELECT * FROM articles WHERE articlecategoriesID = ?', array($category->getId()));

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
