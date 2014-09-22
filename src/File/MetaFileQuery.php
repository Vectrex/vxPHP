<?php
namespace vxPHP\File;

use vxPHP\Orm\Query;
use vxPHP\Orm\QueryInterface;
use vxPHP\Orm\Exception\QueryException;
use vxPHP\Orm\Custom\Article;
use vxPHP\Database\vxPDO;

/**
 * query object which returns an array of MetaFile objects
 *
 * @example
 *
 * $articles =	vxPHP\File\MetaFileQuery::create($db)->
 * 				filterByFolder($myFolder)->
 * 				filterByReference('articles', $myArticle->getId())->
 * 				sortBy('customSort', FALSE)->
 * 				select();
 *
 * @author Gregor Kofler
 * 
 * @version 0.2.0 2014-09-19
 */
class MetaFileQuery extends Query implements QueryInterface {

	public function __construct(vxPDO $dbConnection) {

		$this->table = 'files f';
		parent::__construct($dbConnection);

	}

	/**
	 * add appropriate WHERE clause that filters for $metaFolder
	 *
	 * @param MetaFolder $category
	 * @return MetaFileQuery
	 */
	public function filterByFolder(MetaFolder $folder) {

		$this->addCondition("f.foldersID = ?", $folder->getId());
		return $this;

	}

	/**
	 * add appropriate WHERE clause that filters metafiles linked to given article
	 *
	 * @param Article $article
	 * @return MetaFileQuery
	 */

	public function filterByArticle(Article $article) {

		if($article->getId()) {
			$this->innerJoin('articles_files af', 'af.filesID = f.filesID');
			$this->addCondition("af.articlesID = ?", $article->getId());
		}
			
		return $this;

	}

	/**
	 * executes query and returns array of MetaFile instances
	 *
	 * @return array
	 */
	public function select() {

		$this->buildQueryString();
		$this->buildValuesArray();
		$rows = $this->executeQuery();

		$ids = array();

		foreach($rows as $row) {
			$ids[] = $row['filesID'];
		}

		return MetaFile::getInstancesByIds($ids);
	}

	/**
	 * adds LIMIT clause, executes query and returns array of MetaFile instances
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
			$ids[] = $row['filesID'];
		}

		return MetaFile::getInstancesByIds($ids);
	}

	/**
	/* (non-PHPdoc)
	 * @see \vxPHP\Orm\Query::selectFromTo()
	 */
	public function selectFromTo($from, $to) {
		// TODO: Auto-generated method stub
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Orm\Query::count()
	 */
	public function count() {
		// TODO: Auto-generated method stub
	}
}
