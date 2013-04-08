<?php

namespace vxPHP\File;

use vxPHP\File\Exception\MetaFileException;
use vxPHP\File\Exception\FilesystemFileException;

use vxPHP\File\MetaFolder;
use vxPHP\File\FilesystemFile;

use vxPHP\Observer\EventDispatcher;
use vxPHP\Observer\SubjectInterface;

/**
 * mapper for metafiles
 *
 * requires database tables files, folders
 *
 * @author Gregor Kofler
 *
 * @version 0.5.0 2012-11-19
 *
 * @TODO merge rename() with commit()
 * @TODO cleanup getImagesForReference()
 */
class MetaFile implements SubjectInterface {
	private static	$instancesById		= array();
	private static	$instancesByPath	= array();
	private static	$db;

	private $filesystemFile;

	private	$metaFolder,
			$id,
			$isObscured,
			$data;

	/**
	 * returns MetaFile instance alternatively identified by its path or its primary key in the database
	 *
	 * @param string $path
	 * @param integer $id
	 * @throws MetaFileException
	 *
	 * @return MetaFile
	 */
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
	 * return MetaFiles either identified by primary keys or paths
	 *
	 * @param array $paths
	 * @param array $ids
	 * @throws MetaFileException
	 *
	 * @return array
	 */
	public static function getInstances(array $paths = NULL, array $ids = NULL) {

		$retrieveByPath	= array();
		$retrieveById	= array();

		if(!is_null($paths)) {
			foreach($paths as $path) {

			}
		}

		if(!is_null($ids)) {
			foreach($ids as $id) {

			}
		}
	}

	/**
	 * return all metafile instances within a certain metafolder
	 * faster than Metafolder::getMetafiles()
	 *
	 * @param MetaFolder $folder
	 * @param callback $callBackSort
	 *
	 * @return array metafiles
	 */
	public static function getMetaFilesInFolder(MetaFolder $folder, $callBackSort = NULL) {
		if(!isset(self::$db)) {
			self::$db = $GLOBALS['db'];
		}

		// instance all filesystem files in folder, to speed up things

		FilesystemFile::getFilesystemFilesInFolder($folder->getFilesystemFolder());

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
		else if(is_callable("self::$callBackSort")) {
			usort($result, "self::$callBackSort");
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

	/**
	 * retrieves file metadata stored in database
	 *
	 * @param string $path
	 * @throws MetaFileException
	 *
	 * @return array
	 */
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
			throw new MetaFileException("MetaFile database entry for '$path' not found.");
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
			throw new MetaFileException("MetaFile database entry for id ($id) not found.");
		}
	}

	/**
	 * get id of metafile
	 *
	 * @return integer
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * get any data stored with metafile in database entry
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * get referenced table stored with metafile in database entry
	 *
	 * @return string table
	 */
	public function getReferencedTable() {
		return $this->data['referenced_Table'];
	}

	/**
	 * get referenced id stored with metafile in database entry
	 *
	 * @return integer
	 */
	public function getReferencedId() {
		return $this->data['referencedID'];
	}

	/**
	 * retrieve mime type
	 *
	 * @param bool $force forces re-read of mime type
	 * @return string
	 */
	public function getMimetype($force = false) {
		return $this->filesystemFile->getMimetype($force);
	}

	/**
	 * check whether mime type indicates web image
	 * (i.e. image/jpeg, image/gif, image/png)
	 *
	 * @param bool $force forces re-read of mime type
	 * @return boolean
	 */
	public function isWebImage($force = false) {
		return $this->filesystemFile->isWebImage($force);
	}

	/**
	 * retrieve file info
	 *
	 * @return SplFileInfo
	 */
	public function getFileInfo() {
		return $this->filesystemFile->getFileInfo();
	}

	/**
	 * retrieves physical path of file
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->filesystemFile->getPath();
	}

	/**
	 * returns path relative to DOCUMENT_ROOT
	 *
	 * @param boolean $force
	 * @return string
	 */
	public function getRelativePath($force = FALSE) {
		return $this->filesystemFile->getRelativePath($force);
	}

	/**
	 * retrieves physical filename of file
	 *
	 * @return string
	 */
	public function getFilename() {
		return $this->filesystemFile->getFilename();
	}

	/**
	 * retrieves metafile name of file
	 * differs from physical filename, when file is obscured
	 *
	 * @return string
	 */
	public function getMetaFilename() {
		return $this->data['File'];
	}

	/**
	 * return metafolder of metafile
	 *
	 * @return MetaFolder
	 */
	public function getMetaFolder() {
		return $this->metaFolder;
	}

	/**
	 * returns filesystemfile of metafile
	 *
	 * @return FilesystemFile
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

		$oldpath = $this->filesystemFile->getPath();
		$newpath = $this->filesystemFile->getFolder()->getPath().$to;

		if(!$this->isObscured) {
			try {
				$this->filesystemFile->rename($to);
			}
			catch(FilesystemFileException $e) {
				throw new MetaFileException("Rename from '$oldpath' to '$newpath' failed. '$oldpath' already exists.");
			}
		}

		try {
			self::$db->preparedExecute("UPDATE files SET File = ? WHERE filesID = ?", array($to, $this->id));
		}

		catch(\Exception $e) {
			throw new MetaFileException("Rename from '$oldpath' to '$newpath' failed.");
		}

		$this->data['File'] = $to;

		self::$instancesByPath[$newpath] = $this;
		unset(self::$instancesByPath[$oldpath]);
	}

	/**
	 * move file to a new folder
	 *
	 * @param MetaFolder $destination
	 * @throws MetaFileException
	 */
	public function move(MetaFolder $destination) {

		// nothing to do

		if($destination === $this->metaFolder) {
			return;
		}

		// move filesystem file first

		try {
			$this->filesystemFile->move($destination->getFilesystemFolder());
		}
		catch(FilesystemFileException $e) {
			throw new MetaFileException("Moving '{$this->getFilename()}' to '{$destination->getFullPath()}' failed.");
		}

		// update reference in db

		try {
			self::$db->preparedExecute("UPDATE files SET foldersID = ? WHERE filesID = ?", array($destination->getId(), $this->id));
		}
		catch(\Exception $e) {
			throw new MetaFileException("Moving '{$this->getFilename()}' to '{$destination->getFullPath()}' failed.");
		}

		// update instance lookup

		unset(self::$instancesByPath[$this->getPath()]);
		$this->metaFolder = $destination;
		self::$instancesByPath[$this->getPath()] = $this;
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
			throw new MetaFileException("Delete of metafile '{$this->filesystemFile->getPath()}' failed.");
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
		if(is_null($dA['customSort'])) {
			return 1;
		}
		if(is_null($dB['customSort'])) {
			return -1;
		}
		return $dA['customSort'] < $dB['customSort'] ? -1 : 1;
	}
}
