<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Orm\Custom;

use vxPHP\Orm\Custom\Exception\ArticleException;

use vxPHP\File\MetaFile;
use vxPHP\Application\Application;
use vxPHP\Observer\PublisherInterface;
use vxPHP\Database\MysqlPDOUtil;

/**
 * Mapper class for articles, stored in table `articles`
 *
 * @author Gregor Kofler
 * @version 0.99.0 2017-02-17
 */

class Article implements PublisherInterface {

	/**
	 * cached instances identified by their id
	 * 
	 * @var Article[]
	 */
	private static	$instancesById;

	/**
	 * cached instances identified by their alias
	 * 
	 * @var Article[]
	 */
	private static	$instancesByAlias;

	/**
	 * primary key
	 * 
	 * @var integer
	 */
	private	$id;
	
	/**
	 * unique alias
	 * 
	 * @var string
	 */
	private	$alias;
			
	/**
	 * headline of article
	 * 
	 * @var string
	 */
	private $headline;
			
	/**
	 * "other" data of article
	 * 
	 * @var array
	 */
	private	$data;
			
	/**
	 * arbitrary flags
	 * controllers can decide how to interpret them
	 * 
	 * @var integer
	 */
	private	$customFlags;
			
	/**
	 * numeric indicator that can be used by
	 * controllers to enhance or override other sorting rules
	 * 
	 * @var integer
	 */
	private	$customSort;

	/**
	 * flags an article as published
	 * 
	 * @var boolen
	 */
	private	$published;
	
	/**
	 * all files linked to this article
	 * 
	 * @var array MetaFile[]
	 */
	private	$linkedFiles;
			
	/**
	 * flag which indicates that linked files need to be updated
	 * 
	 * @var boolean
	 */
	private	$updateLinkedFiles;
			
	/**
	 * stores previously saved attribute values of article
	 * allows verification whether changes of article have occured
	 * 
	 * @var array
	 */
	private	$previouslySavedValues;

	/**
	 * colunms which can be retrieved with the getData() method
	 *  
	 * @var array
	 */
	private	$dataCols = ['teaser', 'content'];

	/**
	 * the category the article belongs to
	 * 
	 * @var ArticleCategory
	 */
	private	$category;

	/**
	 * the article date
	 * 
	 * @var \DateTime
	 */
	private	$articleDate;

	/**
	 * optional date information which can be used by controllers 
	 * 
	 * @var \DateTime
	 */
	private	$displayFrom;

	/**
	 * optional date information which can be used by controllers 
	 * 
	 * @var \DateTime
	 */
	private	$displayUntil;

	/**
	 * timestamp of last update of article 
	 * 
	 * @var \DateTime
	 */
	private	$lastUpdated;

	/**
	 * timestamp of article creation
	 *  
	 * @var \DateTime
	 */
	private	$firstCreated;

	/**
	 * the id of the user which created the article
	 * 
	 * @var integer
	 */
	private	$createdById;

	/**
	 * the id of the user which caused the last update
	 * 
	 * @var integer
	 */
	private	$updatedById;

	/**
	 * the id of the user which published or unpublished the article
	 *  
	 * @var User
	 */
	private	$publishedById;

	/**
	 * these attributes do not indicate
	 * a change of the record despite differing
	 * from current value
	 * 
	 * @var array
	 */
	private	$notIndicatingChange = [
		'published'
	];
	
	/**
	 * property names which are set to NULL when cloning the object
	 * 
	 * @var array
	 */
	private $propertiesToReset = [
		'updatedById',
		'id',
		'alias'
	];
	
	public function __construct() {
	}
	
	public function __clone() {
		
		// link files, when necessary
		
		if(is_null($this->linkedFiles)) {
			$this->getLinkedMetaFiles();
		}

		// reset certain properties; forces insertion when saving
		
		foreach($this->propertiesToReset as $property) {
			$this->$property = NULL;
		}

		// make sure that linked files are kept and saved

		$this->updateLinkedFiles = TRUE;
		
		// create new headline and therefore new alias

		$this->headline = 'Copy: ' . $this->headline; 

	}

	public function __toString() {

		return $this->alias;

	}

	/**
	 * checks whether an article was changed when compared to the data used for instancing
	 * evaluates to TRUE for a new article
	 * if $evaluateAll is TRUE, attributes in notIndicatingChange are still checked
	 *
	 * @todo changes of linked files are currently ignored
	 * 
	 * @param boolean $evaluateAll
	 *
	 * @return boolean
	 */
	public function wasChanged($evaluateAll = FALSE) {

		if(is_null($this->previouslySavedValues)) {
			return TRUE;
		}
		
		foreach(array_keys(get_object_vars($this->previouslySavedValues)) as $p) {

			// some attributes might not indicate a change

			if(in_array($p, $this->notIndicatingChange) && !$evaluateAll) {
				continue;
			}

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

		// a headline is a required attribute

		if(is_null($this->headline) || trim($this->headline) == '') {
			throw new ArticleException("Headline not set. Article can't be inserted", ArticleException::ARTICLE_HEADLINE_NOT_SET);
		}

		// a category is a required attribute
		
		if(is_null($this->category)) {
			throw new ArticleException("Category not set. Article can't be inserted", ArticleException::ARTICLE_CATEGORY_NOT_SET);
		}

		// allow listeners to react to event

		ArticleEvent::create(ArticleEvent::BEFORE_ARTICLE_SAVE, $this)->trigger();

		// afterwards collect all current data in array

		$cols = array_merge(
			(array) $this->getData(),
			[
				'Alias'					=> $this->alias,
				'articlecategoriesID'	=> $this->category->getId(),
				'Headline'				=> $this->headline,
				'Article_Date'			=> is_null($this->articleDate) ? NULL : $this->articleDate->format('Y-m-d H:i:s'),
				'Display_from'			=> is_null($this->displayFrom) ? NULL : $this->displayFrom->format('Y-m-d H:i:s'),
				'Display_until'			=> is_null($this->displayUntil) ? NULL : $this->displayUntil->format('Y-m-d H:i:s'),
				'published'				=> $this->published,
				'customFlags'			=> $this->customFlags,
				'customSort'			=> $this->customSort,
				'publishedBy'			=> $this->publishedById ?: NULL,
				'updatedBy'				=> $this->updatedById ?: NULL
			]
		);

		if(!is_null($this->id)) {

			// is a full update necessary?

			if($this->wasChanged()) {

				// update
	
				$this->alias = MysqlPDOUtil::getAlias($db, $this->headline, 'articles', $this->id);
				$db->updateRecord('articles', $this->id, $cols);

			}

			// were attributes changed, which don't indicate an update (e.g. 'published')?

			else if($this->wasChanged(TRUE)) {

				// update, but don't set lastUpdated and updatedBy

				unset($cols['updatedBy']);
				$this->alias = MysqlPDOUtil::getAlias($db, $this->headline, 'articles', $this->id);
				$db->ignoreLastUpdated()->updateRecord('articles', $this->id, $cols);
			
			}
		}

		else {

			// insert

			$this->alias = MysqlPDOUtil::getAlias($db, $this->headline, 'articles');

			$cols = array_merge(
				(array) $this->getData(),
				[
					'Alias'					=> $this->alias,
					'articlecategoriesID'	=> $this->category->getId(),
					'Headline'				=> $this->headline,
					'Article_Date'			=> is_null($this->articleDate) ? NULL : $this->articleDate->format('Y-m-d H:i:s'),
					'Display_from'			=> is_null($this->displayFrom) ? NULL : $this->displayFrom->format('Y-m-d H:i:s'),
					'Display_until'			=> is_null($this->displayUntil) ? NULL : $this->displayUntil->format('Y-m-d H:i:s'),
					'published'				=> $this->published,
					'customFlags'			=> $this->customFlags,
					'customSort'			=> $this->customSort,
					'publishedBy'			=> $this->publishedById ?: NULL,
					'createdBy'				=> $this->createdById ?: NULL
				]
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

		ArticleEvent::create(ArticleEvent::AFTER_ARTICLE_SAVE, $this)->trigger();

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

			ArticleEvent::create(ArticleEvent::BEFORE_ARTICLE_DELETE, $this)->trigger();
				
			// delete record

			$db = Application::getInstance()->getDb(); 
			$db->deleteRecord('articles', $this->id);

			// delete instance references

			unset(self::$instancesById[$this->id]);
			unset(self::$instancesByAlias[$this->alias]);

			// unlink referenced files

			if(!is_null($this->linkedFiles)) {
				foreach($this->linkedFiles as $file) {
					$file->unlinkArticle($this);
				}
			}
			
			$db->deleteRecord('articles_files', array('articlesID' => $this->id));

			ArticleEvent::create(ArticleEvent::AFTER_ARTICLE_DELETE, $this)->trigger();

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
	 * set user id which created the article
	 * will only be effective with first save of article
	 * 
	 * @param integer $userId
	 * @return \vxPHP\Orm\Custom\Article
	 */
	public function setCreatedById($userId) {

		if(is_null($this->createdById)) {
			$this->createdById = (int) $userId;
		}
		return $this;

	}

	/**
	 * get user id which created article
	 *
	 * @return integer
	 */
	public function getCreatedById() {

		return $this->createdById;

	}

	/**
	 * set user id which (last) updated the article
	 *
	 * @param integer $userId
	 * @return \vxPHP\Orm\Custom\Article
	 */
	public function setUpdatedById($userId) {
	
		$this->updatedById = (int) $userId;
		return $this;
	
	}
	
	/**
	 * get id of user which (last) updated article
	 *
	 * @return integer
	 */
	public function getUpdatedById() {

		return $this->updatedById;

	}

	/**
	 * get id of user which (un)published article
	 *
	 * @return integer
	 */
	public function getPublishedById() {

		return $this->publishedById;

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

		$ndx = strtolower($ndx);

		if(isset($this->data[$ndx])) {
			return $this->data[$ndx];
		}
	}

	/**
	 * sets misc data of article; only keys listet in Article::dataCols are accepted
	 *
	 * @param array $data
	 * @return \vxPHP\Orm\Custom\Article
	 */
	public function setData(array $data) {

		$data = array_change_key_case($data, CASE_LOWER);

		foreach($this->dataCols as $c) {
			if(isset($data[$c])) {
				$this->data[$c] = $data[$c];
			}
		}
		
		return $this;

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
	 * set 'published' attribute and store user id
	 * 
	 * @param integer $userId
	 * @return \vxPHP\Orm\Custom\Article
	 */
	public function publish($userId = NULL) {

		$this->setPublishedById($userId);
		$this->published = TRUE;
				
		return $this;

	}

	/**
	 * unset 'published' attribute and store user id
	 * 
	 * @param integer $userId
	 * @return \vxPHP\Orm\Custom\Article
	 */
	public function unpublish($userId = NULL) {

		$this->setPublishedById($userId);
		$this->published = FALSE;

		return $this;

	}

	/**
	 * get state of published flag
	 * 
	 * @return boolean
	 */
	public function isPublished() {

		return !!$this->published;

	}

	/**
	 * set id of user who published or unpublished article 
	 * helper method for publish and unpublish
	 *  
	 * @param integer $userId
	 */
	private function setPublishedById($userId = NULL) {

		// was a user specified?

		if($userId) {
			$this->publishedById = $userId;
		}

		// do we have a session user?

		else if($user = Application::getInstance()->getCurrentUser()) {
			$this->publishedById = $user->getAttribute('id');
		}

		// delete any previously set user id

		else {
			$this->publishedById = NULL;
		}

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

		// set user id's
		
		$article->setCreatedById($articleData['createdBy']);
		$article->setUpdatedById($articleData['updatedBy']);
		$article->setPublishedById($articleData['publishedBy']);

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

		$article->published		= $articleData['published'];
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
		$article->previouslySavedValues->published		= $article->published;
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
	 * when $ids is not set, all available articles are instantiated
	 *
	 * @param array $ids contains mixed article ids or alias
	 * @return array
	 */
	public static function getInstances(array $ids = NULL) {

		$db = Application::getInstance()->getDb();

		$articles = array();

		// get all articles

		if(is_null($ids)) {

			foreach($db->doPreparedQuery('SELECT a.* FROM articles a') as $row) {

				if(!isset(self::$instancesById[$row['articlesID']])) {

					$article = self::createInstance($row);

					self::$instancesByAlias[$article->alias]	= $article;
					self::$instancesById[$article->id]			= $article;
						
				}

				$articles[] = self::$instancesById[$row['articlesID']];
			}
		}

		else {

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

			foreach($ids as $id) {
				$articles[] = self::getInstance($id);
			}
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

}
