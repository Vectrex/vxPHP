<?php

namespace vxPHP\File;

use vxPHP\File\Exception\MetaFileException;
use vxPHP\File\Exception\FilesystemFileException;

use vxPHP\File\MetaFolder;
use vxPHP\File\FilesystemFile;

use vxPHP\Observer\EventDispatcher;
use vxPHP\Observer\SubjectInterface;
use vxPHP\Application\Application;
use vxPHP\User\User;
use vxPHP\Orm\Custom\Article;
use vxPHP\Orm\Custom\ArticleQuery;

/**
 * mapper for metafiles
 *
 * requires database tables files, folders
 *
 * @author Gregor Kofler
 *
 * @version 0.8.4 2014-09-19
 *
 * @todo merge rename() with commit()
 * @todo cleanup getImagesForReference()
 * @todo allow update of createdBy user
 */
class MetaFile implements SubjectInterface {

	private static	$instancesById		= array();
	private static	$instancesByPath	= array();

	/**
	 * @var FilesystemFile
	 */
	private $filesystemFile;

	/**
	 * @var MetaFolder
	 */
	private	$metaFolder;

	private	$id,
			$isObscured,
			$data,

			/**
			 * @var User
			 */
			$createdBy,

			/**
			 * @var User
			 */
			$updatedBy,
			/**
			 * @var Article[]
			 */
			$linkedArticles;
	
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

		if(isset($path)) {

			$lookup = Application::getInstance()->extendToAbsoluteAssetsPath($path);

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
	 * return array of MetaFiles identified by primary keys
	 *
	 * @param array $ids
	 *
	 * @return array
	 */
	public static function getInstancesByIds(array $ids) {

		$toRetrieveById = array();

		// collect ids that must be read from db

		foreach($ids as $id) {
			if(!isset(self::$instancesById[$id])) {
				$toRetrieveById[] = (int) $id;
			}
		}

		// build and execute query, if necessary

		if(count($toRetrieveById)) {

			$rows = Application::getInstance()->getDb()->doPreparedQuery('
				SELECT
					f.*,
					CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath
				FROM
					files f
					INNER JOIN folders fo ON fo.foldersID = f.foldersID
				WHERE
					f.filesID IN (' . implode(',', array_fill(0, count($toRetrieveById), '?')) . ')',
			$toRetrieveById);

			foreach($rows as $row) {
				$mf = new self(NULL, NULL, $row);
				self::$instancesById[$mf->getId()]						= $mf;
				self::$instancesByPath[$mf->filesystemFile->getPath()]	= $mf;
			}
		}

		// return instances

		$metafiles = array();

		foreach($ids as $id) {
			$metafiles[] = self::$instancesById[$id];
		}

		return $metafiles;

	}

	/**
	 * return MetaFiles identified by paths
	 *
	 * @param array $paths
	 *
	 * @return array
	 */
	public static function getInstancesByPaths(array $paths) {

		$toRetrieveByPath	= array();
		$lookupPaths		= array();

		// collect paths, that must be read from db

		$lookupPaths = array();

		foreach($paths as $path) {

			$lookup = Application::getInstance()->extendToAbsoluteAssetsPath($path);

			$lookupPaths[] = $lookup;

			if(!isset(self::$instancesByPath[$lookup])) {
				$pathinfo = pathinfo($lookup);

				$toRetrieveByPath[] = $pathinfo['basename'];
				$toRetrieveByPath[] = $pathinfo['dirname'] . DIRECTORY_SEPARATOR;
				$toRetrieveByPath[] = str_replace(Application::getInstance()->getAbsoluteAssetsPath(), '', $pathinfo['dirname']) . DIRECTORY_SEPARATOR;
			}
		}

		// build and execute query, if necessary

		if(count($toRetrieveByPath)) {

			$where = array_fill(0, count($toRetrieveByPath) / 3, 'f.File = ? AND fo.Path IN (?, ?)');

			$rows = Application::getInstance()->getDb()->doPreparedQuery('
				SELECT
					f.*,
					CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath
				FROM
					files f
					INNER JOIN folders fo ON fo.foldersID = f.foldersID
				WHERE
					'. implode(' OR ', $where),
			$toRetrieveByPath);

			foreach($rows as $row) {
				$mf = new self(NULL, NULL, $row);
				self::$instancesById[$mf->getId()]						= $mf;
				self::$instancesByPath[$mf->filesystemFile->getPath()]	= $mf;
			}
		}

		// return instances

		$metafiles = array();

		foreach($lookupPaths as $path) {
			$metafiles[] = self::$instancesByPath[$path];
		}

		return $metafiles;

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

		// instance all filesystem files in folder, to speed up things

		FilesystemFile::getFilesystemFilesInFolder($folder->getFilesystemFolder());

		$result = array();

		$files = Application::getInstance()->getDb()->doPreparedQuery("SELECT f.*, CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath FROM files f INNER JOIN folders fo ON f.foldersID = fo.foldersID WHERE fo.foldersID = ?", array((int) $folder->getId()));

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
	 * return all metafile instances linked to an article
	 * 
	 * @param Article $article
	 * @param callback $callBackSort
	 * @throws MetaFileException
	 * @return array:\vxPHP\File\MetaFile
	 */
	public static function getFilesForArticle(Article $article, $callBackSort = NULL) {

		$result = array();
		
		$files = Application::getInstance()->getDb()->doPreparedQuery("
			SELECT
				f.*,
				CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) AS FullPath
			FROM
				files f
				INNER JOIN folders fo ON f.foldersID = fo.foldersID
				INNER JOIN articles_files af ON af.filesID = f.filesID
			WHERE
				af.articlesID = ?
			ORDER BY
				af.customSort
			", array($article->getId()));
		
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
	 * return all metafile instances linked to an article with mimetype 'image/jpeg', 'image/png', 'image/gif'
	 *
	 * @param Article $article
	 * @param callback $callBackSort
	 * @throws MetaFileException
	 * @return array:\vxPHP\File\MetaFile
	 */
	public static function getImagesForArticle(Article $article, $callBackSort = NULL) {
		
		$result = array();

		$mimeTypes = array('image/jpeg', 'image/png', 'image/gif');

		$files = Application::getInstance()->getDb()->doPreparedQuery("
			SELECT
				f.*,
				CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath
			FROM
				files f
				INNER JOIN folders fo ON f.foldersID = fo.foldersID
				INNER JOIN articles_files af ON af.filesID = f.filesID
			WHERE
				af.articlesID = ?
				AND f.Mimetype IN ('".implode("','", $mimeTypes)."')
			ORDER BY
				af.customSort
			", array($article->getId()));
				
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
	 * check whether $filename is already taken by a metafile in folder $f
	 *
	 * @param string $filename
	 * @param MetaFolder $f
	 * @return boolean is_available
	 */
	public static function isFilenameAvailable($filename, MetaFolder $f) {

		// $filename is not available, if metafile with $filename is already instantiated

		if(isset(self::$instancesByPath[$f->getFullPath().$filename])) {
			return FALSE;
		}

		// check whether $filename is found in database entries

		return count(
			Application::getInstance()->
			getDb()->
			doPreparedQuery("
				SELECT
					filesID
				FROM
					files
				WHERE
					foldersID = ? AND
					( File LIKE ? OR Obscured_Filename LIKE ? )
				",
				array((int) $f->getId(), (string) $filename, (string) $filename)
			)
		) === 0;
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
		$this->filesystemFile	= FilesystemFile::getInstance(Application::getInstance()->extendToAbsoluteAssetsPath($this->data['FullPath']));
		$this->metaFolder		= MetaFolder::getInstance($this->filesystemFile->getFolder()->getPath());

		// when record features an obscured_filename, the FilesystemFile is bound to this obscured filename, while the metafile always references the non-obscured filename

		$this->isObscured		= $this->data['File'] !== $this->filesystemFile->getFilename();
	}

	public function __toString() {
		return $this->getPath();
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

		$rows = Application::getInstance()->getDb()->doPreparedQuery(
			"SELECT f.*, CONCAT(fo.Path, IFNULL(f.Obscured_Filename, f.File)) as FullPath FROM files f INNER JOIN folders fo ON fo.foldersID = f.foldersID WHERE f.File = ? AND fo.Path IN(?, ?) LIMIT 1",
			array(
				$pathinfo['basename'],
				$pathinfo['dirname'].DIRECTORY_SEPARATOR,
				str_replace(Application::getInstance()->getAbsoluteAssetsPath(), '', $pathinfo['dirname']) . DIRECTORY_SEPARATOR
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

		$rows = Application::getInstance()->getDb()->doPreparedQuery(
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
	 * stub, might be dropped entirely
	 * 
	 * @param Article $article
	 */
	public function linkArticle(Article $article) {
		
	}

	/**
	 * stub, might be dropped entirely
	 *
	 * @param Article $article
	 */
	public function unlinkArticle(Article $article) {
	
	}

	/**
	 * get all articles linked to a file
	 * 
	 * @return Article[]
	 */
	public function getLinkedArticles() {

		if(is_null($this->linkedArticles)) {

			$this->linkedArticles = ArticleQuery::create(Application::getInstance()->getDb())
				->innerJoin('articles_files af', 'a.articlesID = af.articlesID')
				->where('af.filesID = ?', array($this->id))
				->select();

		}
		
		return $this->linkedArticles;
	}

	/**
	 * get user instance which created database entry of metafile
	 * the creator is considered immutable
	 *
	 * @return NULL|\vxPHP\User\User
	 */
	public function getCreatedBy() {
	
		if(is_null($this->createdBy)) {
				
			// no user was stored with instance
	
			if(empty($this->data['createdBy'])) {
				return NULL;
			}
				
			// retrieve user instance and store it for subsequent calls
				
			else {
				$this->createdBy = User::getInstance($this->data['createdBy']);
			}
		}
	
		return $this->createdBy;
	}
	
	/**
	 * get user instance which last updated database entry of metafile
	 * the updater can be changed
	 *
	 * @return NULL|\vxPHP\User\User
	 */
	public function getUpdatedBy() {
	
		if(is_null($this->updatedBy)) {
	
			// no user was stored with instance
	
			if(empty($this->data['updatedBy'])) {
				return NULL;
			}
	
			// retrieve user instance and store it for subsequent calls
	
			else {
				$this->createdBy = User::getInstance($this->data['updatedBy']);
			}
		}
	
		return $this->updatedBy;
	}
	
	/**
	 * retrieve mime type
	 *
	 * @param bool $force forces re-read of mime type
	 * @return string
	 */
	public function getMimetype($force = FALSE) {
		return $this->filesystemFile->getMimetype($force);
	}

	/**
	 * check whether mime type indicates web image
	 * (i.e. image/jpeg, image/gif, image/png)
	 *
	 * @param bool $force forces re-read of mime type
	 * @return boolean
	 */
	public function isWebImage($force = FALSE) {
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
	 * returns path relative to assets path root
	 * NULL if file is outside assets path
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
			Application::getInstance()->getDb()->execute('UPDATE files SET File = ? WHERE filesID = ?', array($to, $this->id));
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
			Application::getInstance()->getDb()->execute('UPDATE files SET foldersID = ? WHERE filesID = ?', array($destination->getId(), $this->id));
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

		if(Application::getInstance()->getDb()->deleteRecord('files', $this->id)) {
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
		if(!Application::getInstance()->getDb()->updateRecord('files', $this->id, $this->data)) {
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
