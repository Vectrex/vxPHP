<?php

namespace vxPHP\File;

/**
 * Wraps various filesystem related functions
 * in particular uploads of images according to ini-settings
 *
 * @author Gregor Kofler
 * @version 0.4.0 2013-09-08
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
}
