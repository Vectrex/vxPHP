<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\File;

use vxPHP\File\Exception\FilesystemFolderException;
use vxPHP\File\Exception\MetaFolderException;
use vxPHP\File\FilesystemFile;
use vxPHP\Application\Application;

/**
 * mapper for filesystem folders
 *
 * @author Gregor Kofler
 *
 * @version 0.4.0 2016-10-23
 *
 * @todo test delete()
 */

class FilesystemFolder {

	const CACHE_PATH = '.cache';

	/**
	 * caches instances of folders
	 * @var FilesystemFolder[]
	 */
	private static $instances = [];

	/**
	 * absolute path
	 * @var string
	 */
	private	$path;
	
	/**
	 * flags presence of a cache folder
	 * @var boolean
	 */
	private $cacheFound;
	
	/**
	 * relative path with application assets path as root
	 * @var string
	 */
	private $relPath;
	
	/**
	 * @var FilesystemFolder
	 */
	private $parentFolder;

	public static function getInstance($path) {

		$path = rtrim(realpath($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

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
			throw new FilesystemFolderException(sprintf('Directory %s does not exist or is no directory.', $path));
		}

	}

	/**
	 * returns path of filesystem folder
	 */
	public function getPath() {

		return $this->path;

	}

	/**
	 * returns path relative to assets path root
	 *
	 * @param boolean $force
	 * @return string
	 */
	public function getRelativePath($force = FALSE) {

		if(!isset($this->relPath) || $force) {
			$relPath = preg_replace('~^' . preg_quote(Application::getInstance()->getAbsoluteAssetsPath(), '~').'~', '', $this->path, -1, $replaced);
			$this->relPath = $replaced === 0 ? NULL : $relPath;
		}

		return $this->relPath;

	}

	/**
	 * returns all FilesystemFile instances in folder
	 * when $extension is specified, only files with extension are returned
	 *
	 * @param $extension
	 * @return array of FilesystemFile instances
	 */
	public function getFiles($extension = NULL) {

		$result = [];
		$glob = $this->path . '*';

		if(!is_null($extension)) {
			$glob .= ".$extension";
		}

		foreach(array_filter(glob($glob), 'is_file') as $f) {
			$result[] = FilesystemFile::getInstance($f, $this);
		}

		return $result;
	}

	/**
	 * returns all FilesystemFolder instances in folder
	 *
	 * @return Array
	 */
	public function getFolders() {

		$result = [];
		$files = glob($this->path . '*', GLOB_ONLYDIR);

		if($files) {
			foreach($files as $f) {
				$result[] = self::getInstance($f);
			}
		}

		return $result;

	}

	/**
	 * return parent FilesystemFolder of current folder
	 * returns NULL, when current folder is already the root folder
	 * 
	 * @param boolean $force
	 * @return FilesystemFolder
	 */
	public function getParentFolder($force = FALSE) {
		
		if(!isset($this->parentFolder) || $force) {
			
			$parentPath = realpath($this->path . '..');
			
			// flag parentFolder property, when $this is already the root folder
			
			if($parentPath === $this->path) {
				$this->parentFolder = FALSE;
			}

			else {
				$this->parentFolder = self::getInstance($parentPath);
			}

		}

		// return NULL (instead of FALSE) when there is no parent folder

		return $this->parentFolder ?: NULL;
		
	}
	
	/**
	 * checks whether a FilesystemFolder::CACHE_PATH subfolder exists
	 *
	 * @param boolean $force
	 * @return boolean result
	 */
	public function hasCache($force = FALSE) {

		if(!isset($this->cacheFound) || $force) {
			$this->cacheFound = is_dir($this->path . self::CACHE_PATH);
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
			return $this->path . self::CACHE_PATH.DIRECTORY_SEPARATOR;
		}

	}

	/**
	 * create a new subdirectory
	 * returns newly created FilesystemFolder object
	 *
	 * @param string $folderName
	 * @return FilesystemFolder
	 * @throws FilesystemFolderException
	 */
	public function createFolder($folderName) {
		
		// prefix folder path, when realpath fails (e.g. does not exist)

		if(!($path = realpath($folderName))) {

			$path = $this->path . $folderName;

		}
		
		// throw exception when $folderName cannot be established as subdirectory of current folder
		
		else if (strpos($path, $this->path) !== 0) {
			
			throw new FilesystemFolderException(sprintf("Folder %s cannot be created within folder %s.", $folderName, $this->path));
			
		}
		
		// recursively create folder(s) when when path not already exists
		
		if(!is_dir($path)) {

			if(!@mkdir($path, 0777, TRUE)) {
				throw new FilesystemFolderException(sprintf("Folder %s could not be created!", $path));
			}
			else {
				chmod($path, 0777);
			}

			$path = realpath($path);
		}

		return self::getInstance($path);

	}

	/**
	 * tries to create a FilesystemFolder::CACHE_PATH subfolder
	 *
	 * @return path
	 * @throws FilesystemFolderException
	 */
	public function createCache() {

		if($this->hasCache(TRUE)) {
			return $this->path.self::CACHE_PATH . DIRECTORY_SEPARATOR;
		}

		if(!@mkdir($this->path.self::CACHE_PATH)) {
			throw new FilesystemFolderException(sprintf('Cache folder %s could not be created!', $this->path . self::CACHE_PATH));
		}
		else {
			chmod($this->path.self::CACHE_PATH, 0777);
			return $this->path.self::CACHE_PATH . DIRECTORY_SEPARATOR;
		}

	}

	/**
	 * empties the cache when found
	 *
	 * @param boolean $force
	 * @throws FilesystemFolderException
	 */
	public function purgeCache($force = FALSE) {

		if(($path = $this->getCachePath($force))) {
			foreach(glob($path. '*') as $f) {
				if(!unlink($f)) {
					throw new FilesystemFolderException(sprintf('Cache folder %s could not be purged!', $this->path . self::CACHE_PATH));
				}
			}
		}

	}

	/**
	 * empties folder
	 * removes all files in folder and subfolders (including any cache folders)
	 *
	 * @throws FilesystemFolderException
	 */
	public function purge() {

		foreach(
			new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(
					$this->path,
					\FilesystemIterator::SKIP_DOTS
				),
				\RecursiveIteratorIterator::CHILD_FIRST) as $f) {

			if ($f->isDir()) {
				if(!@rmdir($f->getRealPath())) {
					throw new FilesystemFolderException(sprintf('Filesystem folder %s could not be deleted!', $f->getRealPath()));
				}

				self::unsetInstance($f->getRealPath());
			}
		    else {
		    	if(!@unlink($f->getRealPath())) {
		    		throw new FilesystemFolderException(sprintf('Filesystem file %s could not be deleted!', $f->getRealPath()));
		    	}

		    	FilesystemFile::unsetInstance($f->getRealPath());
			}
		}

	}

	/**
	 * deletes folder (and any contained files and folders)
	 *
	 * @throws FilesystemFolderException
	 */
	public function delete() {

		$this->purge();

		if(!@rmdir($this->path)) {
			throw new FilesystemFolderException(sprintf('Filesystem folder %s could not be deleted!', $this->path));
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

