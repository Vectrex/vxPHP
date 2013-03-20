<?php
/**
 * mapper for filesystem folders
 * 
 * @author Gregor Kofler
 * 
 * @version 0.3.3 2012-11-08
 *
 * @todo test delete()
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

	public static function unsetInstance($path) {
		if(isset(self::$instances[$path])) {
			unset(self::$instances[$path]);
		}
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
	 * 
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
	 * returns all FilesystemFile instances in folder
	 * 
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
	 * returns all FilesystemFolder instances in folder
	 * 
	 * @return Array
	 */
	public function getFolders() {
		$result = array();
		$files = glob($this->path.'*', GLOB_ONLYDIR);

		if($files) {
			foreach($files as $f) {
				$result[] = self::getInstance($f);
			}
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
	 * @return path
	 */
	public function getCachePath($force = FALSE) {
		if($this->hasCache($force)) {
			return $this->path.self::CACHE_PATH.DIRECTORY_SEPARATOR;
		}
	}

	/**
	 * create a new subdirectory
	 * returns newly created FilesystemFolder object
	 * 
	 * @param string $folderName
	 * @return FilesystemFolder
	 * @throws FileSystemFolderException
	 */	
	public function createFolder($folderName) {
		if(!@mkdir($this->path.$folderName)) {
			throw new FileSystemFolderException("Folder ".$this->path.DIRECTORY_SEPARATOR.$folderName." could not be created!");
		}
		else {
			chmod($this->path.$folderName, 0777);
		}
		return self::getInstance($this->path.$folderName);
	}	
	
	/**
	 * tries to create a FilesystemFolder::CACHE_PATH subfolder
	 * 
	 * @return path
	 * @throws FileSystemFolderException
	 */
	public function createCache() {
		if($this->hasCache(TRUE)) {
			return $this->path.self::CACHE_PATH.DIRECTORY_SEPARATOR;
		}

		if(!@mkdir($this->path.self::CACHE_PATH)) {
			throw new FileSystemFolderException("Cache folder ".$this->path.self::CACHE_PATH." could not be created!");
		}
		else {
			chmod($this->path.self::CACHE_PATH, 0777);
			return $this->path.self::CACHE_PATH.DIRECTORY_SEPARATOR;
		}
	}

	/**
	 * empties the cache when found
	 * 
	 * @param boolean $force
	 * @throws FileSystemFolderException
	 */
	public function purgeCache($force = FALSE) {
		if(($path = $this->getCachePath($force))) {
			foreach(glob($path. '*') as $f) {
				if(!unlink($f)) {
					throw new FileSystemFolderException("Cache folder ".$this->path.self::CACHE_PATH." could not be purged!");
				}
			}
		}
	}

	/**
	 * empties folder
	 * removes all files in folder and subfolders (including any cache folders)
	 * 
	 * @throws FileSystemFolderException
	 */
	public function purge() {

		foreach(
			new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$this->path,
					FilesystemIterator::SKIP_DOTS
				),
				RecursiveIteratorIterator::CHILD_FIRST) as $f) {

			if ($f->isDir()) {
				if(!@rmdir($f->getRealPath())) {
					throw new FileSystemFolderException("Filesystem folder {$f->getRealPath()} could not be deleted!");
				}
				self::unsetInstance($f->getRealPath());
			}
		    else {
		    	if(!@unlink($f->getRealPath())) {
		    		throw new FileSystemFolderException("Filesystem file {$f->getRealPath()} could not be deleted!");
		    	}
		    	FilesystemFile::unsetInstance($f->getRealPath());
			}
		}
	}

	/**
	 * deletes folder (and any contained files and folders)
	 *  
	 * @throws FileSystemFolderException
	 */
	public function delete() {
		$this->purge();
		if(!@rmdir($this->path)) {
			throw new FileSystemFolderException("Filesystem folder {$this->path} could not be deleted!");
		}
		self::unsetInstance($this->path);		
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
