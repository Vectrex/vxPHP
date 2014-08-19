<?php

namespace vxPHP\File;

/**
 * Wraps various filesystem related functions
 * in particular uploads of images according to ini-settings
 *
 * @author Gregor Kofler
 * @version 0.4.1 2013-09-10
 *
 */
class Util {

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
	 * scan dir without ./.. and return files with extension $ext
	 *
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
}
