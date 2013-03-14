<?php

namespace vxPHP\File;

/**
 * Wraps various filesystem related functions
 * in particular uploads of images according to ini-settings
 * 
 * @author Gregor Kofler
 * @version 0.3.10 2012-12-24
 * 
 */
class Util {
	/**
	 * Uploads images according to the information provided in site.ini.xml
	 *
	 * @param string $input name of file input field
	 * @param string $destname name of destination file
	 * @param string $group id of block in site.ini.xml
	 * @param boolean $forceFilename forces filename (and possible overwriting)
	 * @return bool result
	 * 
	 */
	static function uploadImage($input, $destname, $group = false, $forceFilename = false) {
		if(!isset($GLOBALS['config']))					{ throw new \Exception('Missing config object!'); }
		$c = &$GLOBALS['config'];
		if(!isset($c->uploadImages))					{ throw new \Exception('Image upload parameters not configured!'); }
		if($group && !isset($c->uploadImages[$group]))	{ throw new \Exception('Image upload group not found!'); }
		
		if($_FILES[$input]['error'] == 4)	{ return NULL; }	// kein Upload
		if($_FILES[$input]['error'] != 0)	{ return FALSE; }	// andere Fehler
	
		if($group) {
			$p = $c->uploadImages[$group];
		}
		else {
			$p = isset($c->uploadImages['default'])? $c->uploadImages['default'] : $c->uploadImages[0];
		}

		foreach($p->sizes as $s) {
			if (!is_dir($s->path)) {
				if (!mkdir($s->path)) {
					return FALSE;
				}
			}
		}

		list($srcW, $srcH, $srcF) = getimagesize($_FILES[$input]['tmp_name']);
		$allowed = empty($p->allowedTypes) ? array('jpeg', 'gif', 'png') : $p->allowedTypes;
		foreach($allowed as $a) {
			if(constant('IMAGETYPE_'.strtoupper($a)) == $srcF) {
				$format = $a; break;
			}
		}
		if(!isset($format)) {
			return false;
		}

		if($format === 'jpeg') {
			$format = 'jpg';
		}
		if(empty($destname)) {
			$destname = self::getFilenameWithoutExt($_FILES[$input]['name']);
		}

		foreach($p->sizes as $s) {
			if($s->width === 'original') {
				$filename = $forceFilename ? "$destname.$format" : self::checkFileName("$destname.$format", $s->path);
				if(!copy($_FILES[$input]['tmp_name'], $s->path.$filename)) {
					return false;
				}
				continue;
			}

			$img = self::imageScaleDown(
				$_FILES[$input]['tmp_name'],
				$srcW, $srcH,
				$s->width, $s->height,
				$format
			);

			if($img === false) {
				return false;
			}

			$filename = $forceFilename ? "$destname.$format" : self::checkFileName("$destname.$format", $s->path);

			if($img === true) {
				if(!copy($_FILES[$input]['tmp_name'], $s->path.$filename)) {
					return false;
				}
			}
			else {
				switch($format) {
					case 'jpg':
						if(!imagejpeg($img, $s->path.$filename, isset($s->jpg_quality) ? $s->jpg_quality : 70)) { return false; }
						break;
					case 'gif':
						if(!imagegif($img, $s->path.$filename)) { return false; }
						break;
					case 'png':
						if(!imagepng($img, $s->path.$filename, 9)) { return false; }
						break;
					default:
						return false;
				}
			}
		}	
		return $filename;
	}
	
	/**
	 * Delete images according to the information provided in site.ini.xml
	 *
	 * @param string $pattern pattern of filenames
	 * @param string $group id of block in site.ini.xml
	 * 
	 */
	static function deleteImage($pattern, $group = false) {
		if(!isset($GLOBALS['config']))					{ return; }
		$c = &$GLOBALS['config'];
		if(!isset($c->uploadImages))					{ return; }
		if($group && !isset($c->uploadImages[$group]))	{ return; }
	
		if($group) {
			$p = $c->uploadImages[$group];
		}
		else {
			$p = isset($c->uploadImages['default'])? $c->uploadImages['default'] : $c->uploadImages[0];
		}

		foreach($p->sizes as $s) {
			$files = glob($s->path.$pattern);
			
			foreach($files as $f) {
				if(is_file($f)) {
					unlink($f);	
				}
			}
		}
	}

	/**
	 * upload misc file
	 * 
	 * @param string name of FILE-input
	 * @param string filename
	 * @param string directory
	 * @return string new filename
	 */
	static function uploadFile($input, $name = NULL, $dir = '') {

		// no upload

		if($_FILES[$input]['error'] == 4) {
			return NULL;
		}

		// other error

		if($_FILES[$input]['error'] != 0) {
			return FALSE;
		}		
		
		if($dir !== '') {
			$dir = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		}

		if(!is_dir($dir)) {
			if(!mkdir($dir,0777)) {
				return FALSE;
			}
		}

		if(!isset($name) || trim($name) === '') {
			$name = $_FILES[$input]['name'];
		}

		$fn = self::checkFileName($name, $dir);
		
		if(!move_uploaded_file($_FILES[$input]['tmp_name'], $dir.$fn)) {
			return FALSE;
		}

		return $fn;
	}
	
	/**
	 * Check filename and avoid doublettes
	 * 
	 * @param string $wanted_filename
	 * @param string $path
	 * @param integer $starting_index used in renamed file
	 * 
	 * @return string cleared filename
	 */
	public static function checkFileName($filename, $path, $ndx = 2) {

		$filename = str_replace(
			array(' ', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'),
			array('_', 'ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'),
			$filename);

		$filename = preg_replace('/[^0-9a-z_#,;\-\.\(\)]/i', '_', $filename);

		$path = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

		if(!file_exists($path.$filename)) {
			return $filename;
		}

		$pathinfo = pathinfo($filename);

		if(!empty($pathinfo['extension'])) {
			$pathinfo['extension'] = '.'.$pathinfo['extension'];
		}
		while(file_exists($path.sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']))) {
			++$ndx;
		}

		return sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']);
	}

	/**
	 * Builds a (partial) tree to a specified subdir
	 *
	 * @param string $dir
	 * @param boolean $noFiles
	 * @return object $tree
	 */
	public static function getTree($dir, $listFiles = false) {
		if(is_dir($dir)) {
			$pathSegs = explode('/', rtrim($dir, '/'));

			$tree = new \stdClass();
			$tree->path = $pathSegs[0];
			$tree->name = $pathSegs[0];
			self::buildTree($pathSegs, 1, $tree, $listFiles);
			return $tree;
		}
		return false;
	}
	private static function buildTree($segs, $level, \stdClass $tree, $listFiles) {
		if($listFiles) {
			$tree->files = array();
		}
		$tree->subDirs = array();

		$path = implode('/', array_slice($segs, 0, $level));
		
		$i = new \DirectoryIterator($path);

		foreach($i as $file) {

			if(!$file->isDot()) {
				$fn = $file->getFileName();

				if($listFiles && $file->isFile() && substr($fn, 0, 1) !== '.') {
					$f = new \stdClass();
					$f->name = $fn;
					$f->size = self::getFileSize("$path/$fn");
					$f->mimeType = MimeTypeGetter::get("$path/$fn");
					$f->mTime = $file->getMTime();
					array_push($tree->files, $f);
				}
				else if($file->isDir() && substr($fn, 0, 1) !== '.') {
					$sd = new \stdClass();
					$sd->name = $fn;
					$sd->path = "$path/$fn";
					if($sd->path === implode('/', array_slice($segs, 0, $level+1))) {
						self::buildTree($segs, $level+1, $sd, $listFiles);
					}
					array_push($tree->subDirs, $sd);
				}
			}
		}
	}

	/**
	 * 
	 * Retrieve all files within a directory
	 * hidden files are omitted
	 * @param string|DirectoryIterator $dir
	 * @return array $filesObjects
	 */
	public static function getFiles($dir = '.') {
		if(!$dir instanceof \DirectoryIterator) {
			if($dir != '') {
				$dir = rtrim($dir, '/').'/';
			}
			try {
				$i = new \DirectoryIterator($dir);
			} catch (\Exception $e) {
				return false;
			}
		}
		else {
			$i = $dir;
		}

		$files = array();

		foreach($i as $file) {
			$fn = $file->getFileName();
			if(!$file->isDot() && $file->isFile() && substr($fn, 0, 1) !== '.') {
				$f = new \stdClass();
				$f->name = $fn;
				$f->size = self::getFileSize("$dir/$fn");
				$f->mimeType = MimeTypeGetter::get("$dir/$fn");
				$f->mTime = $file->getMTime();
				$files[] = $f;
			}
		}

		return $files;
	}

	/**
	 * recursively delete directory
	 * @param string directory
	 * @return bool result
	 */
	static function delDir($dir) {
		$dir = preg_replace('/\/$/', '', $dir);
		if(!is_dir($dir)) { return false; }
		$entries = array_diff(scandir($dir), array('.', '..'));

		foreach($entries as $v) {
			if(is_file("$dir/$v")) {
				@unlink("$dir/$v");
				continue;
			}
			if(!self::delDir("$dir/$v")) { return false; }
		}
		return rmdir($dir);
	}

	/**
	 * recursively delete directory
	 * directory itself is not deleted
	 * @param string directory
	 * @return bool result
	 * 
	 * @todo clean up return values and parameter assignment
	 */
	static function emptyDir($dir, $root = true) {
		
		$dir = preg_replace('/\/$/', '', $dir);
		if(!is_dir($dir)) { return false; }
		$entries = array_diff(scandir($dir), array('.', '..'));

		foreach($entries as $v) {
			if(is_file("$dir/$v")) {
				@unlink("$dir/$v");
				continue;
			}
			if(!self::delDir("$dir/$v", false)) { return false; }
		}
		if($root) {
			return true;
		}
		return rmdir($dir);
	}

	/**
	 * delete file
	 * @param string filename
	 * @param string directory
	 * @return bool result
	 */
	static function delFile ($filename, $dir = '') {
		if($dir != '') {
			$dir = preg_replace('/\/$/', '', $dir).'/';
		}

		if (!file_exists($dir.$filename)) { return false; }
		return unlink($dir.$filename);
	}

	/**
	 * scan dir without ./.. and return files with extension $ext
	 * @param string path
	 * @param string extension
	 * @return array filenames
	 */
	static function getDir($dir = '.', $ext = NULL) {
		if($dir != '') {
			$dir = rtrim($dir, '/').'/';
		}
		try {
			$i = new \DirectoryIterator($dir);
		} catch (\Exception $e) {
			return false;
		}

		$files = array();

		foreach($i as $file) {
			$fn = $file->getFileName();
			if(!$file->isDot() && $file->isFile() && substr($fn, 0, 1) !== '.') {
				if(!isset($ext) || preg_match("~$ext\$~", $fn)) {
					$files[] = $fn;
				}
			}
		}

		return $files;
	}

	/**
	 * get properly formatted size of file
	 * @param string filename with path
	 * @return string filesize
	 */
	static function getFileSize($file) {
		if(!file_exists($file) || !is_file($file)) { return null; }
		$fs = filesize($file);
		return
			$fs > 1000000 ?
			number_format($fs/1048576, 2, DECIMAL_POINT, THOUSANDS_SEP).'MB' :
			number_format($fs/1024, 2, DECIMAL_POINT, THOUSANDS_SEP).'kB';
	}

	/**
	 * get file extension
	 * @param string filename
	 * @return string extension
	 */
	static function getExtension($filename) {
		$fnTeile = explode('.',$filename);
		return array_pop($fnTeile);
	}

	/**
	 * get filename without extension
	 * @param string filename
	 * @return string filename without extension
	 */
	static function getFilenameWithoutExt ($filename) {
		$fnTeile = explode('.',$filename);
		array_pop($fnTeile);
		return implode('.', $fnTeile);
	}

	/**
	 * checks if gfx file (JPG, GIF, PNG) exists
	 * @param string path
	 * @param string basename
	 * @return bool result
	 */
	static function imgExists($path, $filename) {
		if(file_exists($path.$filename.'jpg')) { return true; }
		if(file_exists($path.$filename.'gif')) { return true; }
		if(file_exists($path.$filename.'png')) { return true; }
		return false;
	}

	/**
	 * scaling down of a jpg file, aspect ratio is maintained
	 * 
	 * @param ressource sourcefile
	 * @param integer new width
	 * @param integer new height
	 * @param mixed allowed image types
	 * @return ressource scaled down image or true (no scaling needed
	 */
	public static function imageScaleDown ($src, $srcW, $srcH, $destW, $destH, $format) {

		if(!in_array($format, array('jpg', 'gif', 'png'))) {
			return false;
		}

		if(empty($destW) || empty($destH)) {
			if (!empty($destW)) {
				if ($destW >= $srcW) {
					return true;		// no scaling needed - just copy it
				}
				$destH = round($srcH/$srcW*$destW);
			}
			else {
				if ($destH >= $srcH) {
					return true;		// no scaling needed - just copy it
				}
				$destW = round($srcW/$srcH*$destH);
			}
		}
		else {
			$srcRatio = $srcW/$srcH;
			$destRatio = $destW/$destH;
			
			if($srcRatio < $destRatio) {
				// base it on height
				if ($destH >= $srcH) {
					return true;		// no scaling needed - just copy it
				}
				$destW = round($srcW/$srcH*$destH);
			}
			else {
				// base it on width
				if ($destW >= $srcW) {
					return true;		// no scaling needed - just copy it
				}
				$destH = round($srcH/$srcW*$destW);
			}
		}

		switch ($format) {
			case 'jpg':	$iold = imagecreatefromjpeg	($src); break;
			case 'gif':	$iold = imagecreatefromgif	($src); break;
			case 'png':	$iold = imagecreatefrompng	($src); break;
		}

		$inew = imagecreatetruecolor($destW,$destH);
		imagecopyresampled($inew, $iold,0,0,0,0,$destW,$destH,$srcW,$srcH);
		return $inew;
	}
}
?>