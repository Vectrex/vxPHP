<?php
/**
 * mapper for filesystem folders
 * 
 * @author Gregor Kofler
 * 
 * @version 0.2.8 2011-12-26
 *
 */

class FilesystemFolder {

	const CACHE_PATH = '.cache';

	private static $instances = array();

	private	$path;
	private $cacheFound;
	private $relPath;

	public static function getInstance($path) {
		$path = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

		if(!isset(self::$instances[$path])) {
			self::$instances[$path] = new self($path);
		}
		return self::$instances[$path];
	}

	private function __construct($path) {
		if(is_dir($path)) {
			$this->path = $path; 
		}
		else {
			throw new FileSystemFolderException("Directory $path does not exist or is no directory.");
		}
	}

	/**
	 * returns path of filesystem folder 
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * returns path relative to DOCUMENT_ROOT
	 * @param boolean $force
	 * @return boolean FALSE when path not within DOCUMENT_ROOT, relative path otherwise
	 */
	public function getRelativePath($force = FALSE) {
		if(!isset($this->relPath) || $force) {
			$relPath = preg_replace('~^'.preg_quote(rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, '~').'~', '', $this->path, -1, $replaced);
			$this->relPath = $replaced === 0 ? FALSE : $relPath;
		}
		return $this->relPath;
	}

	/**
	 * returns all files in folder
	 * @return array of FilesystemFile instances
	 */
	public function getFiles() {
		$result = array(); 
		foreach(array_filter(glob($this->path.'*'), 'is_file') as $f) {
			$result[] = FilesystemFile::getInstance($f);
		}
		return $result;
	}
	
	/**
	 * returns all folders in folder
	 * @return array of FilesystemFolder instances
	 */
	public function getFolders() {
		$result = array(); 
		foreach(glob($this->path.'*', GLOB_ONLYDIR) as $f) {
			$result[] = self::getInstance($f);
		}
		return $result;
	}

	/**
	 * checks whether a FilesystemFolder::CACHE_PATH subfolder exists
	 * 
	 * @param boolean $force
	 * @return boolean result
	 */
	public function hasCache($force = FALSE) {
		if(!isset($this->cacheFound) || $force) {
			$this->cacheFound = is_dir($this->path.self::CACHE_PATH);
		}
		return $this->cacheFound;
	}

	/**
	 * checks whether a FilesystemFolder::CACHE_PATH subfolder
	 * returns path to subfolder when folder exists, undefined otherwise
	 * 
	 * @param boolean $force
	 * @return string path
	 */
	public function getCachePath($force = FALSE) {
		if($this->hasCache($force)) {
			return $this->path.self::CACHE_PATH.DIRECTORY_SEPARATOR;
		}
	}

	/**
	 * tries to create a FilesystemFolder::CACHE_PATH subfolder
	 * return path
	 * @throws Exception
	 */
	public function createCache() {
		if($this->hasCache(TRUE)) {
			return $this->path.self::CACHE_PATH.DIRECTORY_SEPARATOR;
		}
		
		if(!mkdir($this->path.self::CACHE_PATH)) {
			throw new FileSystemFolderException("Cache folder ".$this->path.self::CACHE_PATH." could not be created!");
		}
		else {
			chmod($this->path.self::CACHE_PATH, 0777);
			return $this->path.self::CACHE_PATH.DIRECTORY_SEPARATOR;
		}
	}

	/**
	 * creates metafolder from current filesystemfolder
	 */
	public function createMetaFolder() {
		try {
			return MetaFolder::getInstance($this->getPath());
		}
		catch(MetaFolderException $e) {
			return MetaFolder::create($this);
		}
	}
}

class FileSystemFolderException extends Exception {
}
?>
