<?php
/**
 * mapper for metafiles 
 * 
 * requires Mysqldbi, FilesystemFile
 * database tables files, folders
 * 
 * @author Gregor Kofler
 * 
 * @version 0.4.9 2012-07-29
 * 
 * @TODO merge rename() with commit()
 * @TODO cleanup getImagesForReference()
 */
class MetaFile implements Subject {
	private static	$instancesById		= array();
	private static	$instancesByPath	= array();
	private static	$db;

	private $filesystemFile;

	private	$metaFolder,
			$id,
			$isObscured,
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
	 * faster than Metafolder::getMetafiles()
	 * 
	 * @param MetaFolder $folder
	 * @param callback $callBackSort
	 * 
	 * @return Array metafiles
	 */
	public static function getMetaFilesInFolder(MetaFolder $folder, $callBackSort = NULL) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		$result = array();

		$files = self::$db->doPreparedQuery("SELECT f.*, CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath FROM files f INNER JOIN folders fo ON f.foldersID = fo.foldersID WHERE fo.foldersID = ?", array((int) $folder->getId()));

		foreach($files as &$f) {
			if(isset(self::$instancesById[$f['filesID']])) {
				$file = self::$instancesById[$f['filesID']];
			}
			else {
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
		else if(is_callable("Metafile::$callBackSort")) {
			usort($result, "Metafile::$callBackSort");
			return $result;
		}
		else {
			throw new MetaFileException("'$callBackSort' is not callable.");
		}
	}
	
	/**
	 * return all metafile instances referencing a certain row in certain table
	 * also handy for caching
	 * 
	 * @param int $referencedId
	 * @param string $referencedTable
	 * @param callback $callBackSort
	 * @throws MetaFileException
	 * 
	 * @return array metafiles
	 * 
	 */
	public static function getFilesForReference($referencedId, $referencedTable, $callBackSort = NULL) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		$result = array();

		$files = self::$db->doPreparedQuery("
			SELECT
				f.*,
				CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) AS FullPath
			FROM
				files f
				INNER JOIN folders fo ON f.foldersID = fo.foldersID
			WHERE
				referencedID = ? AND
				referenced_Table = ?
				", array((int) $referencedId, (string) $referencedTable));

		foreach($files as &$f) {
			if(isset(self::$instancesById[$f['filesID']])) {
				$file = self::$instancesById[$f['filesID']];
			}
			else {
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
		else if(is_callable("Metafile::$callBackSort")) {
			usort($result, "Metafile::$callBackSort");
			return $result;
		}
		else {
			throw new MetaFileException("'$callBackSort' is not callable.");
		}
	}

	/**
	 * @param int $referencedId
	 * @param string $referencedTable
	 * @param callback $callBackSort
	 * @throws MetaFileException
	 * 
	 * @return array metafiles with mimetype 'image/jpeg', 'image/png', 'image/gif'
	 */
	public static function getImagesForReference($referencedId, $referencedTable, $callBackSort = NULL) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		$result = array();

		$mimeTypes = array('image/jpeg', 'image/png', 'image/gif');

		$files = self::$db->doPreparedQuery("
			SELECT
				f.*,
				CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath
			FROM
				files f
				INNER JOIN folders fo ON f.foldersID = fo.foldersID
			WHERE
				referencedID = ? AND
				referenced_Table = ? AND
				Mimetype IN ('".implode("','", $mimeTypes)."')
				", array((int) $referencedId, (string) $referencedTable));

		foreach($files as &$f) {
			if(isset(self::$instancesById[$f['filesID']])) {
				$file = self::$instancesById[$f['filesID']];
			}
			else {
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
		else if(is_callable("Metafile::$callBackSort")) {
			usort($result, "Metafile::$callBackSort");
			return $result;
		}
		else {
			throw new MetaFileException("'$callBackSort' is not callable.");
		}
	}

	/**
	 * check whether $filename is already taken by a metafile in folder $f
	 * 
	 * @param string $filename
	 * @param MetaFolder $f
	 * @return boolean is_available
	 */
	public static function isFilenameAvailable($filename, MetaFolder $f) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		// $filename is not available, if metafile with $filename is already instantiated

		if(isset(self::$instancesByPath[$f->getFullPath().$filename])) {
			return FALSE;
		}
		
		// check whether $filename is found in database entries

		return count(self::$db->doPreparedQuery("SELECT filesID FROM files WHERE foldersID = ? AND ( File LIKE ? OR Obscured_Filename LIKE ? )", array((int) $f->getId(), (string) $filename, (string) $filename))) === 0;
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
			$this->data = $this->getDbEntryByPath($path);
		}
		else if(isset($id)) {
			$this->data = $this->getDbEntryById($id);
		}
		else if(isset($dbEntry)) {
			$this->data = $dbEntry;
		}

		$this->id				= $this->data['filesID'];
		$this->filesystemFile	= FilesystemFile::getInstance($this->data['FullPath']);
		$this->metaFolder		= MetaFolder::getInstance($this->filesystemFile->getFolder()->getPath());
		
		// when record features an obscured_filename, the FilesystemFile is bound to this obscured filename, while the metafile always references the non-obscured filename 

		$this->isObscured		= $this->data['File'] !== $this->filesystemFile->getFilename();
	}

	private function getDbEntryByPath($path) {
		$pathinfo = pathinfo($path);
		$rows = self::$db->doPreparedQuery(
			"SELECT f.*, CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath FROM files f INNER JOIN folders fo ON fo.foldersID = f.foldersID WHERE f.File = ? AND fo.Path IN(?, ?) LIMIT 1",
			array(
				$pathinfo['basename'],
				$pathinfo['dirname'].DIRECTORY_SEPARATOR,
				str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, '', $pathinfo['dirname']).DIRECTORY_SEPARATOR
			)
		);

		if(isset($rows[0])) {
			return $rows[0];
		}
		else {
			throw new Exception("MetaFile database entry for '$path' not found.");
		}
	}

	private function getDbEntryById($id) {
		$rows = self::$db->doPreparedQuery(
			"SELECT f.*, CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath FROM files f INNER JOIN folders fo ON fo.foldersID = f.foldersID WHERE f.filesID = ?",
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
	 * retrieves metafile name of file
	 * differs from physical filename, when file is obscured 
	 */
	public function getMetaFilename() {
		return $this->data['File'];
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
	public function rename($to)	{
		
		// obscured files only need to rename the metadata

		if(!$this->isObscured) {
			$this->filesystemFile->rename($to);
		}

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
	 * filesystem file will be kept when $keepFilesystemFile is TRUE
	 * 
	 * @param boolean $keepFilesystemFile
	 * 
	 * @throws Exception
	 */
	public function delete($keepFilesystemFile = FALSE) {
		EventDispatcher::getInstance()->notify($this, 'beforeMetafileDelete');

		if(self::$db->deleteRecord('files', $this->id)) {
			unset(self::$instancesById[$this->id]);
			unset(self::$instancesByPath[$this->filesystemFile->getPath()]);

			if(!$keepFilesystemFile) {
				$this->filesystemFile->delete();
			}
		}
		else {
			throw new Exception("Delete of metafile '{$this->filesystemFile->getPath()}' failed.");
		}
	}
	
	/**
	 * obscure filename
	 * renames filesystem file, then updates metafile data in db and sets isObscured flag
	 * 
	 * @param string $obscuredFilename
	 */
	public function obscureTo($obscuredFilename) {

		// rename filesystem file

		$this->filesystemFile->rename($obscuredFilename);

		// set metafile db attributes
		$this->setMetaData(array('Obscured_Filename' => $obscuredFilename));

		// set isObscured flag
		$this->isObscured = TRUE;
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
			throw new MetaFileException("Data commit of file '{$this->filesystemFile->getFilename()}' failed.");
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
