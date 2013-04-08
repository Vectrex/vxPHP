<?php
namespace vxPHP\File;

use vxPHP\Database\Mysqldbi;
class MetaFileQuery {

	public function __construct(Mysqldbi $dbConnection) {

	}

	public function select() {

	}

	public function selectFirst($rows = 1) {

	}

	public function filter($columnName, $value) {

	}

	public function filterByFolder(MetaFolder $folder) {

	}

	public function filterByReference($referencedTable, $referencedId) {

	}

	public function where($whereClause, Array $valuesToBind = NULL) {

	}

	public function sortBy($columnName, $asc = TRUE) {

	}

	public static function create(Mysqldbi $dbConnection) {
		return new self($dbConnection);
	}
}
