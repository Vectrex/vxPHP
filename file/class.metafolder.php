<?php
/**
 * mapper for metafolder 
 * 
 * requires Mysqldbi, FilesystemFolder
 * database tables files, folders
 * 
 * @author Gregor Kofler
 * 
 * @version 0.4.9 2012-02-16
 *
 * @todo won't know about drive letters on windows systems
 * @todo delete()
 */
class MetaFolder {
	private static	$instancesById		= array();
	private static	$instancesByPath	= array();
	private static	$db;

	private $filesystemFolder,
			$fullPath,
			$name;

	private	$id,
			$data,
			$level, $l, $r,
			$obscure_files,
			$metaFiles,
			$metaFolders;

	/**
	 * retrieve metafolder instance by either primary key of db entry
	 * or path - both relative and absolute paths are allowed
	 */
	public static function getInstance($path = NULL, $id = NULL) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		if(isset($path)) {
			$path = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

			$lookup = substr($path, 0, 1) == DIRECTORY_SEPARATOR ? $path : rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path;

			if(!isset(self::$instancesByPath[$lookup])) {
				$mf = new self($path);
				self::$instancesByPath[$mf->getFullPath()]	= $mf;
				self::$instancesById[$mf->getId()]			= $mf;
			}
			return self::$instancesByPath[$lookup];
		}
		else if(isset($id)) {
			if(!isset(self::$instancesById[$id])) {
				$mf = new self(NULL, $id);
				self::$instancesById[$id]					= $mf;
				self::$instancesByPath[$mf->getFullPath()]	= $mf;
			}
			return self::$instancesById[$id];
		}
		else {
			throw new MetaFolderException("Either folder id or path required!");
		}
	}

	/**
	 * creates a metafolder instance
	 * requires either id or path stored in db  
	 * 
	 * @param string $path of metafolder
	 * @param integer $id of metafolder
	 */
	private function __construct($path = NULL, $id = NULL, $dbEntry = NULL) {
		if(isset($path)) {
			$path = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			$this->fullPath = substr($path, 0, 1) == DIRECTORY_SEPARATOR ? $path : rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path;
			$this->data = $this->getDbEntryByPath($path);
		}
		else if(isset($id)) {
			$this->data = $this->getDbEntryById($id);
			$this->fullPath = substr($this->data['Path'], 0, 1) == DIRECTORY_SEPARATOR ? $this->data['Path'] : rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->data['Path'];
		}
		else if(isset($dbEntry)) {
			$this->data = $dbEntry;
			$this->fullPath = substr($this->data['Path'], 0, 1) == DIRECTORY_SEPARATOR ? $this->data['Path'] : rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->data['Path'];
		}
		$this->filesystemFolder = FilesystemFolder::getInstance($this->fullPath);

		$this->id				= $this->data['foldersID'];
		$this->level			= $this->data['level'];
		$this->l				= $this->data['l'];
		$this->r				= $this->data['r'];
		$this->obscure_files	= (boolean) $this->data['Obscure_Files'];
		$this->name				= basename($this->fullPath);
	}

	private function getDbEntryByPath($path) {
		if(substr($path, 0, 1) == DIRECTORY_SEPARATOR) {
			$altPath = trim(str_replace($_SERVER['DOCUMENT_ROOT'], '', $path), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			
		}
		else {
			$altPath = $this->fullPath;
		}
		$rows = self::$db->doPreparedQuery(
			"SELECT * FROM folders WHERE Path = ? OR Path = ? LIMIT 1",
			array((string) $path, (string) $altPath)
		);

		if(isset($rows[0])) {
			return $rows[0];
		}
		else {
			throw new MetaFolderException("MetaFolder database entry for '{$this->fullPath} ($path)' not found.");
		}
	}

	private function getDbEntryById($id) {
		$rows = self::$db->doPreparedQuery(
			"SELECT * FROM folders WHERE foldersID = ? LIMIT 1",
			array((int) $id)
		);

		if(isset($rows[0])) {
			return $rows[0];
		}
		else {
			throw new MetaFolderException("MetaFolder database entry for id ($id) not found.");
		}
	}

	/**
	 * refreshes nesting of instance by re-reading database entry (l, r, level)
	 */
	private function refreshNesting() {
		$rows = self::$db->doPreparedQuery(
			"SELECT l, r, level FROM folders WHERE foldersID = ? LIMIT 1",
			array((int) $this->id)
		);
		$this->level	= $this->data['level'] = $rows[0]['level'];
		$this->l		= $this->data['l'] = $rows[0]['l'];
		$this->r		= $this->data['r'] = $rows[0]['r'];
	}

	/**
	 * several getters
	 */
	public function getFullPath() {
		return $this->fullPath;
	}

	public function getId() {
		return $this->id;
	}

	public function getName() {
		return $this->name;
	}
	
	public function getMetaData() {
		return $this->data;
	}

	public function getFilesystemFolder() {
		return $this->filesystemFolder;
	}
	
	public function obscuresFiles() {
		return $this->obscure_files;
	}

	/**
	 * returns path relative to DOCUMENT_ROOT
	 * @param boolean $force
	 */
	public function getRelativePath($force = FALSE) {
		return $this->filesystemFolder->getRelativePath($force);
	}
	
	/**
	 * return all metafiles within this folder
	 * 
	 * @param boolean $force forces re-reading of metafolder
	 */
	public function getMetaFiles($force = FALSE) {
		if(!isset($this->metaFiles) || $force) {
			$this->metaFiles = array();
			foreach(self::$db->doQuery("SELECT filesID FROM files WHERE foldersID = {$this->id}", true) as $f) {
				$this->metaFiles[] = MetaFile::getInstance(NULL, $f['filesID']);
			}
		}
		return $this->metaFiles;
	}

	/**
	 * return all metafolders within this folder
	 * 
	 * @param boolean $force forces re-reading of metafolder
	 */
	public function getMetaFolders($force = FALSE) {
		if(!isset($this->metaFolders) || $force) {
			$this->metaFolders = array();
			foreach(self::$db->doQuery("SELECT foldersID from folders WHERE l > {$this->l} AND r < {$this->r} AND level = {$this->level} + 1", true) as $f) {
				$this->metaFolders[] = self::getInstance(NULL, $f['foldersID']);
			}
		}
		return $this->metaFolders;
	}

	/**
	 * return parent metafolder or NULL if already top folder
	 */
	public function getParentMetafolder() {
		if(!$this->level) {
			return NULL;
		}
		$pathSegs = explode(DIRECTORY_SEPARATOR, rtrim($this->getFullPath(), DIRECTORY_SEPARATOR));
		array_pop($pathSegs);
		return self::getInstance(implode(DIRECTORY_SEPARATOR, $pathSegs));
	}

	/**
	 * deletes the metafolder from database, removes instance from lookup array
	 * does not empty or delete the filesystemfolder
	 */
	public function delete() {
	}

	/**
	 * retrieves all currently in database stored metafolders
	 * main purpose is reduction of db queries
	 *   
	 * @param boolean $force forces re-reading of metafolders
	 */
	public static function instantiateAllExistingMetaFolders($force = FALSE) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}
		$rows = self::$db->doQuery("SELECT * FROM folders", true);

		foreach($rows as $r) {
			if($force || !isset(self::$instancesById[$r['foldersID']])) {
				$f = new self(NULL, NULL, $r);
				self::$instancesByPath[$f->getFullPath()]	= $f;
				self::$instancesById[$r['foldersID']]		= $f;
			}
		}
	}

	/**
	 * creates metafolder from supplied filesystem folder
	 * nested set is updated accordingly
	 *
	 * @param FilesystemFolder $f
	 * @param array $metaData optional data for folder
	 * @throws MetaFolderException
	 */
	public static function create(FilesystemFolder $f, Array $metaData = array()) {
		try {
			self::getInstance($f->getPath());
		}

		catch(MetaFolderException $e) {
			self::$db->autocommit(FALSE);

			if(strpos($f->getPath(), $_SERVER['DOCUMENT_ROOT']) === 0) {
				$metaData['Path'] = trim(substr($f->getPath(), strlen($_SERVER['DOCUMENT_ROOT'])), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			}
			else {
				$metaData['Path'] = rtrim($f->getPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			}

			$metaData['Alias'] = strtolower(preg_replace('~[\\\\/]~', '_', rtrim($metaData['Path'], DIRECTORY_SEPARATOR)));
	
			if(!isset($metaData['Access']) || !preg_match('~^rw?$~i', $metaData['Access'])) {
				$metaData['Access'] = 'RW';
			}
			else {
				$metaData['Access'] = strtoupper($metaData['Access']);
			}
			
			$tree = explode(DIRECTORY_SEPARATOR, trim($metaData['Path'], DIRECTORY_SEPARATOR));

			if(count($tree) == 1) {

				//no parent
				$rows = self::$db->doQuery("SELECT MAX(r) + 1 AS l FROM folders", TRUE);
				$metaData['l'] = !isset($rows[0]['l']) ? 0 : $rows[0]['l'];
				$metaData['r'] = $rows[0]['l'] + 1;
				$metaData['level'] = 0;
			}

			else {
				array_pop($tree);

				try {
					$parent = self::getInstance(implode(DIRECTORY_SEPARATOR, $tree).DIRECTORY_SEPARATOR);

					// parent Dir
					$rows = self::$db->doQuery("SELECT r, l, level FROM folders WHERE foldersID = {$parent->getId()}", TRUE);
					self::$db->execute("UPDATE folders SET r = r + 2 WHERE r >= {$rows[0]['r']}");
					self::$db->execute("UPDATE folders SET l = l + 2 WHERE l > {$rows[0]['r']}");
					$metaData['l'] = $rows[0]['r'];
					$metaData['r'] = $rows[0]['r'] + 1;
					$metaData['level'] = $rows[0]['level'] + 1;

				} catch(MetaFolderException $e) {

					// no parent
					$rows = $this->db->doQuery("SELECT MAX(r) + 1 AS l FROM folders", TRUE);
					$metaData['l'] = !isset($rows[0]['l']) ? 0 : $rows[0]['l'];
					$metaData['r'] = $rows[0]['l'] + 1;
					$metaData['level'] = 0;
				}
			}

			$id = self::$db->insertRecord('folders', $metaData);
			
			// refresh nesting for all active metafolder instances
			foreach(array_keys(self::$instancesById) as $id) {
				self::getInstance(NULL, $id)->refreshNesting();
			}

			self::$db->commit();
			self::$db->autocommit(TRUE);

			return self::getInstance($f->getPath());
		}

		throw new MetaFolderException("Metafolder for {$f->getPath()} already exists.");
	}
}

class MetaFolderException extends Exception {
}

?>
