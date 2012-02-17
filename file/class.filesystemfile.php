<?php
/**
 * mapper for filesystem files
 * 
 * @author Gregor Kofler
 * 
 * @version 0.3.3 2012-02-17
 * 
 * @todo deal with 10.04 Ubuntu bug
 */

class FilesystemFile {
	private static $instances = array();

	private		$filename,
				$folder,
				$mimetype,
				$fileInfo;

	public static function getInstance($path) {
		if(!isset(self::$instances[$path])) {
			self::$instances[$path] = new self($path);
		}
		return self::$instances[$path];
	}
	
	/**
	 * constructs mapper for filesystem files
	 * 
	 * @param string $path
	 * @throws FilesystemFileException
	 */
	private function __construct($path) {
		if(file_exists($path)) {
			$this->fileInfo	= new SplFileInfo($path);
			$this->filename	= $this->fileInfo->getFilename();
			$realPath = $this->fileInfo->getPathInfo()->getRealPath();

			// workaround for bug in PHP 5.3 on Ubuntu 10.04
			if($realPath == $this->fileInfo->getRealPath()) {
				$realPath = dirname($realPath); 
			}

			$this->folder = FilesystemFolder::getInstance($realPath);
		}
		else {
			throw new FilesystemFileException("File $path does not exist!", FilesystemFileException::FILE_DOES_NOT_EXIST);
		}
	}

	/**
	 * retrieve file information provided by SplFileInfo object
	 */
	public function getFileInfo() {
		return $this->fileInfo;
	}

	/**
	 * retrieve mime type
	 * requires MimeTypeGetter
	 *  
	 * @param bool $force forces re-read of mime type
	 */
	public function getMimetype($force = false) {
		if(!isset($this->mimetype) || $force) {
			$this->mimetype = MimeTypeGetter::get($this->folder->getPath().$this->filename);
		}
		return $this->mimetype;
	}

	/**
	 * check whether mime type indicates web image
	 * (i.e. image/jpeg, image/gif, image/png)
	 *  
	 * @param bool $force forces re-read of mime type
	 */
	public function isWebImage($force = false) {
		if(!isset($this->mimetype) || $force) {
			$this->mimetype = MimeTypeGetter::get($this->folder->getPath().$this->filename);
		}
		return preg_match('~^image/(p?jpeg|png|gif)$~', $this->mimetype);
	}
	
	/**
	 * retrieve filename
	 */
	public function getFilename() {
		return $this->filename;
	}
	
	/**
	 * retrieves physical path of file
	 */
	public function getPath() {
		return $this->folder->getPath().$this->filename;
	}

	/**
	 * returns path relative to DOCUMENT_ROOT
	 * @param boolean $force
	 * @return boolean FALSE when path not within DOCUMENT_ROOT, relative path otherwise
	 */
	public function getRelativePath($force = FALSE) {
		if($this->folder->getRelativePath($force) !== FALSE) {
			return $this->folder->getRelativePath().$this->filename;
		}
		return FALSE;
	}
	
	/**
	 * return filesystem folder of file
	 */
	public function getFolder() {
		return $this->folder;
	}

	/**
	 * rename file
	 * 
	 * @param string $to new filename
	 * @throws FilesystemFileException
	 */
	public function rename($to) {
		$from		= $this->filename;
		$oldpath	= $this->folder->getPath().$from;
		$newpath	= $this->folder->getPath().$to;

		if(@rename($oldpath, $newpath)) {
			self::$instances[$newpath] = $this;
			unset(self::$instances[$oldpath]);
			$this->renameCacheEntries($to);
			$this->filename = $to;
		}

		else {
			throw new FilesystemFileException("Rename from '$oldpath' to '$newpath' failed.", FilesystemFileException::FILE_RENAME_FAILED);
		}
	}

	/**
	 * updates names of cache entries
	 * 
 	 * @param string $to new filename
	 */
	private function renameCacheEntries($to) {
		if(($cachePath = $this->folder->getCachePath(TRUE))) {

			$toPathInfo		= pathinfo($to);

			$di	= new DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {

				$filename	= $fileinfo->getFilename();

				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($filename, $this->filename) !== 0 
				) {
					continue;
				}

				$renamed = substr_replace($filename, $to, 0, strlen($this->filename));
				rename($fileinfo->getRealPath(), $fileinfo->getPath().DIRECTORY_SEPARATOR.$renamed);
			}
		}
	}

	/**
	 * deletes file and removes instance from lookup array
	 * @throws FilesystemFileException
	 */
	public function delete() {
		if(@unlink($this->getPath())) {
			unset(self::$instances[$this->getPath()]);
			$this->deleteCacheEntries();
		}
		else {
			throw new FilesystemFileException("Delete of file '{$this->getPath()}' failed.", FilesystemFileException::FILE_DELETE_FAILED);
		}
	}
	
	/**
	 * cleans up cache entries associated with
	 * "original" file
	 */
	private function deleteCacheEntries() {
		if(($cachePath = $this->folder->getCachePath(TRUE))) {

			$di	= new DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {
				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($fileinfo->getFilename(), $this->filename) !== 0 
				) {
					continue;
				}

				unlink($fileinfo->getRealPath());
			}
		}
	}

	/**
	 * remove all cache entries of file
	 */
	public function clearCacheEntries() {
		$this->deleteCacheEntries();
	}
	
	/**
	 * retrieve information about cached files
	 * @return array information
	 */
	public function getCacheInfo() {

		if(($cachePath = $this->folder->getCachePath(TRUE))) {
			$size	= 0;
			$count	= 0;

			$di	= new DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {
				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($fileinfo->getFilename(), $this->filename) !== 0 
				) {
					continue;
				}
				++$count;
				$size += $fileinfo->getSize();
			}
			return array('count' => $count, 'totalSize' => $size);
		}
		return FALSE;
	}

	/**
	 * creates a meta file based on filesystem file
	 * @return MetaFile
	 * @throws FilesystemFileException
	 */
	public function createMetaFile() {
		global $db;

		$relPath = $this->folder->getPath();
		
		if(strpos($relPath, $_SERVER['DOCUMENT_ROOT']) === 0) {
			$relPath = ltrim(substr_replace($relPath, '', 0, strlen($_SERVER['DOCUMENT_ROOT'])), DIRECTORY_SEPARATOR); 
		}

		if(count($db->doPreparedQuery(
			"SELECT f.filesID FROM files f INNER JOIN folders fo ON fo.foldersID = f.foldersID WHERE f.File = ? AND fo.Path IN (?, ?) LIMIT 1",
			array($this->filename, $relPath, $this->folder->getPath())
		))) {
			throw new FilesystemFileException("Metafile '{$this->filename}' in '{$relPath}' already exists.", FilesystemFileException::METAFILE_ALREADY_EXISTS);
		}

		$mf = $this->folder->createMetaFolder();
		
		if(!($filesID = $db->insertRecord('files', array(
			'foldersID'	=> $mf->getId(),
			'File'		=> $this->filename,
			'Mimetype'	=> $this->getMimetype()
		)))) {
			throw new FilesystemFileException("Could not create metafile for '{$this->filename}'.", FilesystemFileException::METAFILE_CREATION_FAILED);
		}
		else {
			$mf = MetaFile::getInstance(NULL, $filesID);
			EventDispatcher::getInstance()->notify($mf, 'metafileCreated');
			return $mf;
		}
	}

	/**
	 * uploads file from $_FILES[$fileInputName] to $dir
	 * if $name is omitted, the filename of the uploaded file is kept
	 * filename is sanitized 
	 * 
	 * @param string $fileInputName
	 * @param FilesystemFolder $dir
	 * @param string $name
	 * @return NULL|boolean|FilesystemFile
	 */
	public static function uploadFile($fileInputName, FilesystemFolder $dir, $name = NULL) {

		// no upload
	
		if($_FILES[$fileInputName]['error'] == 4) {
			return NULL;
		}
	
		// other error
	
		if($_FILES[$fileInputName]['error'] != 0) {
			return FALSE;
		}

		if(is_null($name) || trim($name) === '') {
			$name = $_FILES[$fileInputName]['name'];
		}

		$fn = self::sanitizeFilename($name, $dir);

		if(!move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $dir->getPath().$fn)) {
			return FALSE;
		}
	
		return self::getInstance($dir->getPath().$fn);
	}

	/**
	 * clean up $filename and avoid doublettes within folder $dir
	 *
	 * @param string $filename
	 * @param FilesystemFolder $dir
	 * @param integer $starting_index used in renamed file
	 * @return string
	 */
	private static function sanitizeFilename($filename, FilesystemFolder $dir, $ndx = 2) {
	
		$filename = str_replace(
			array(' ', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'),
			array('_', 'ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'),
			$filename);

		$filename = preg_replace('/[^0-9a-z_#,;\-\.\(\)]/i', '_', $filename);

		if(!file_exists($dir->getPath().$filename)) {
			return $filename;
		}

		$pathinfo = pathinfo($filename);

		if(!empty($pathinfo['extension'])) {
			$pathinfo['extension'] = '.'.$pathinfo['extension'];
		}
		while(file_exists($dir->getPath().sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']))) {
			++$ndx;
		}

		return sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']);
	}
}

class FilesystemFileException extends Exception {
	const	FILE_DOES_NOT_EXIST			= 1;
	const	FILE_RENAME_FAILED			= 2;
	const	FILE_DELETE_FAILED			= 3;
	const	METAFILE_CREATION_FAILED	= 4;
	const	METAFILE_ALREADY_EXISTS		= 5;
}
?>