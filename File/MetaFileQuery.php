<?php
namespace vxPHP\File;

use vxPHP\Database\Mysqldbi;
use vxPHP\Orm\Query;
use vxPHP\Orm\QueryInterface;
use vxPHP\Orm\Exception\QueryException;

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
 * @version 0.1.0 2013-04-10
 */
class MetaFileQuery extends Query implements QueryInterface {

	public function __construct(Mysqldbi $dbConnection) {

		$this->selectSql = 'SELECT * FROM files';
		parent::__construct($dbConnection);

	}

	/**
	 * add appropriate WHERE clause that filters for $metaFolder
	 *
	 * @param MetaFolder $category
	 * @return MetaFileQuery
	 */
	public function filterByFolder(MetaFolder $folder) {

		$this->addCondition("foldersID = ?", $folder->getId());
		return $this;

	}

	/**
	 * add appropriate WHERE clause that filters metafiles referencing
	 * given $referencedId in $referencedTable
	 *
	 * @param string $referencedTable
	 * @param int $referencedId
	 * @return MetaFileQuery
	 */

	public function filterByReference($referencedTable, $referencedId) {

		if(!is_numeric($referencedId)) {
			throw new QueryException("Invalid 'referencedId' for " . __CLASS__ . '::' . __METHOD__);
		}

		$this->addCondition("referenced_Table = ?", $referencedTable);
		$this->addCondition("referencedID = ?", (int) $referencedId);

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


	public function count() {

	}
}
