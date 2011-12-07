<?php
/**
 * mapper for metafiles 
 * 
 * requires Mysqldbi, FilesystemFile
 * database tables files, folders
 * 
 * @author Gregor Kofler
 * 
 * @version 0.3.1a 2011-12-07
 * 
 * @TODO merge rename() with commit()
 */
class MetaFile {
	private static	$instancesById		= array();
	private static	$instancesByPath	= array();
	private static	$db;

	private $filesystemFile;

	private	$metaFolder,
			$id,
			$data;

	public static function getInstance($path = NULL, $id = NULL) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}
		if(isset($path)) {
			$lookup = substr($path, 0, 1) == DIRECTORY_SEPARATOR ? $path : rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path;

			if(!isset(self::$instancesByPath[$lookup])) {
				$mf = new self($path);
				self::$instancesByPath[$lookup]		= $mf;
				self::$instancesById[$mf->getId()]	= $mf;
			}
			return self::$instancesByPath[$lookup];
		}
		else if(isset($id)) {
			if(!isset(self::$instancesById[$id])) {
				$mf = new self(NULL, $id);
				self::$instancesById[$id]								= $mf;
				self::$instancesByPath[$mf->filesystemFile->getPath()]	= $mf;
			}
			return self::$instancesById[$id];
		}
		else {
			throw new MetaFileException("Either file id or path required.");
		}
	}

	/**
	 * return all metafile instances within a certain metafolder
	 * 
	 * @param MetaFolder $folder
	 * 
	 * @return array metafiles
	 * 
	 */
	public static function getMetaFilesInFolder(MetaFolder $folder) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		$result = array();

		$files = self::$db->doPreparedQuery("SELECT * FROM files WHERE foldersID = ?", array((int) $folder->getId()));

		foreach($files as &$f) {
			if(isset(self::$instancesById[$f['filesID']])) {
				$file = self::$instancesById[$f['filesID']];
			}
			else {
				$f['FullPath'] = $folder->getFullPath().$f['File'];
				$file = new self(NULL, NULL, $f);
				self::$instancesById[$f['filesID']]							= $file;
				self::$instancesByPath[$file->filesystemFile->getPath()]	= $file;
			}
			$result[] = $file;  
		}

		return $result;
	}
	
	/**
	 * return all metafile instances referencing a certain row in certain table
	 * also handy for caching
	 * 
	 * @param int $referencedId
	 * @param string $referencedTable
	 * @param function $callBackSort
	 * 
	 * @return array metafiles
	 * 
	 */
		public static function getFilesForReference($referencedId, $referencedTable, $callBackSort = NULL) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		$result = array();

		$files = self::$db->doPreparedQuery("SELECT f.*, fo.Path FROM files f INNER JOIN folders fo ON f.foldersID = fo.foldersID WHERE referencedID = ? AND referenced_Table = ?", array((int) $referencedId, (string) $referencedTable));

		foreach($files as &$f) {
			if(isset(self::$instancesById[$f['filesID']])) {
				$file = self::$instancesById[$f['filesID']];
			}
			else {
				$f['FullPath'] = (substr($f['Path'], 0, 1) == DIRECTORY_SEPARATOR ? $f['Path'] : rtrim($_SERVER['DOCUMENT_ROOT'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$f['Path']).$f['File'];
				
				$file = new self(NULL, NULL, $f);
				self::$instancesById[$f['filesID']]							= $file;
				self::$instancesByPath[$file->filesystemFile->getPath()]	= $file;
			}
			$result[] = $file;  
		}

		if(is_null($callBackSort)) {
			return $result;
		}
		else if(is_callable($callBackSort)) {
			usort($result, $callBackSort);
			return $result;
		}
		else if(is_callable("self::$callBackSort")) {
			usort($result, array(self, 'callBackSort'));
			return $result;
		}
		else {
			throw new MetaFileException("'$callBackSort' is not callable.");
		}
	}

	/**
	 * creates a metafile instance
	 * requires either id or path stored in db  
	 * 
	 * @param string $path of metafile
	 * @param integer $id of metafile
	 */
	private function __construct($path = NULL, $id = NULL, $dbEntry = NULL) {
		if(isset($path)) {
			$this->filesystemFile = FilesystemFile::getInstance($path);
			$this->data = $this->getDbEntryByPath();
		}
		else if(isset($id)) {
			$this->data = $this->getDbEntryById($id);
			$this->filesystemFile = FilesystemFile::getInstance($this->data['FullPath']);
		}
		else if(isset($dbEntry)) {
			$this->data = $dbEntry;
			$this->filesystemFile = FilesystemFile::getInstance($this->data['FullPath']);
		}

		$this->id			= $this->data['filesID']; 
		$this->metaFolder	= MetaFolder::getInstance($this->filesystemFile->getFolder()->getPath());
	}

	private function getDbEntryByPath() {
		$rows = self::$db->doPreparedQuery(
			"SELECT f.* FROM files f INNER JOIN folders fo ON fo.foldersID = f.foldersID WHERE f.File = ? AND fo.Path = ? LIMIT 1",
			array($this->filesystemFile->getFilename(), $this->filesystemFile->getFolder()->getPath())
		);

		if(isset($rows[0])) {
			return $rows[0];
		}
		else {
			throw new Exception("MetaFile database entry for '{$this->filesystemFile->getFolder()->getPath()}{$this->filesystemFile->getFilename()}' not found.");
		}
	}

	private function getDbEntryById($id) {
		$rows = self::$db->doPreparedQuery(
			"SELECT f.*, CONCAT_WS('', fo.Path, f.File) as FullPath FROM files f INNER JOIN folders fo ON fo.foldersID = f.foldersID WHERE f.filesID = ?",
			array((int) $id)
		);
		
		if(isset($rows[0])) {
			return $rows[0];
		}
		else {
			throw new Exception("MetaFile database entry for id ($id) not found.");
		}
	}

	/**
	 * get id of metafile
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * get any data stored with metafile in database entry
	 */
	public function getData() {
		return $this->data;		
	}

	/**
	 * get referenced table stored with metafile in database entry
	 * @return string table
	 */
	public function getReferencedTable() {
		return $this->data['referenced_Table'];		
	}
	
	/**
	 * get referenced id stored with metafile in database entry
	 * @return integer id
	 */
	public function getReferencedId() {
		return $this->data['referencedID'];		
	}

	/**
	 * retrieve mime type
	 *  
	 * @param bool $force forces re-read of mime type
	 */
	public function getMimetype($force = false) {
		return $this->filesystemFile->getMimetype($force);
	}

	/**
	 * check whether mime type indicates web image
	 * (i.e. image/jpeg, image/gif, image/png)
	 *  
	 * @param bool $force forces re-read of mime type
	 */
	public function isWebImage($force = false) {
		return $this->filesystemFile->isWebImage($force);
	}

	/**
	 * retrieve file info
	 */
	public function getFileInfo() {
		return $this->filesystemFile->getFileInfo();
	}

	/**
	 * retrieves physical path of file
	 */
	public function getPath() {
		return $this->filesystemFile->getPath();
	}

	/**
	 * returns path relative to DOCUMENT_ROOT
	 * @param boolean $force
	 */
	public function getRelativePath($force = FALSE) {
		return $this->filesystemFile->getRelativePath($force);
	}
	
	/**
	 * retrieves physical filename of file
	 */
	public function getFilename() {
		return $this->filesystemFile->getFilename();
	}

	/**
	 * return metafolder of metafile
	 */
	public function getMetaFolder() {
		return $this->metaFolder;
	}

	/**
	 * returns filesystemfile of metafile
	 */
	public function getFilesystemFile() {
		return $this->filesystemFile;
	}

	/**
	 * rename metafile
	 * both filesystem file and database entry are changed synchronously
	 * 
	 * doesn't care about race conditions
	 * 
	 * @param string $to new filename
	 * @throws Exception
	 */
	public function rename($to) {
		$this->filesystemFile->rename($to);

		$oldpath = $this->filesystemFile->getPath();
		$newpath = $this->filesystemFile->getFolder()->getPath().$to;
		self::$instancesByPath[$newpath] = $this;
		unset(self::$instancesByPath[$oldpath]);

		try {
			self::$db->preparedExecute("UPDATE files SET File = ? WHERE filesID = {$this->id}", array($to));
			$this->data['File'] = $to;
		}

		catch(Exception $e) {
			throw new Exception("Rename from '$oldpath' to '$newpath' failed.");
		}
	}

	/**
	 * deletes both filesystem file and metafile and removes instance from lookup array
	 * @throws Exception
	 */
	public function delete() {
		if(self::$db->deleteRecord('files', $this->id)) {
			$this->filesystemFile->delete();
			unset(self::$instancesById[$this->id]);
			unset(self::$instancesByPath[$this->filesystemFile->getPath()]);
		}
		else {
			throw new Exception("Delete of metafile '{$this->filesystemFile->getPath()}' failed.");
		}
	}

	/**
	 * updates meta data of metafile
	 * @param array $data new data
	 */
	public function setMetaData($data) {
		unset($data['File']);
		unset($data['filesID']);

		$this->data = $data + $this->data;
		$this->commit();
	}
	
	/**
	 * commit changes to metadata by writing data to database 
	 */
	private function commit() {
		if(!self::$db->updateRecord('files', $this->id, $this->data)) {
			throw new Exception("Data commit of file '{$this->filesystemFile->getFilename()}' failed.");
		}
	}

	/**
	 * various callback functions for sorting files
	 */
	private static function sortByCustomSort($a, $b) {
		$dA = $a->getData();
		$dB = $b->getData();

		if($dA['customSort'] === $dB['customSort']) {
			return $dA['firstCreated'] < $dB['firstCreated'] ? -1 : 1;
		}
		return !is_null($dA['customSort']) && $dA['customSort'] < $dB['customSort'] ? -1 : 1;
	}
}

class MetaFileException extends Exception {
}
?>
