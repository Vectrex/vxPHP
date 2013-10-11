<?php

namespace vxPHP\Template\Util;

use vxPHP\File\FilesystemFolder;
use vxPHP\File\Exception\FilesystemFolderException;
use vxPHP\Application\Application;

/**
 * helper class to sync and update templates both in filesystem and database
 *
 * @author Gregor Kofler
 * @version 0.3.9 2013-10-05
 *
 */

class SimpleTemplateUtil {
	private static $maxPageRevisions;

	/**
	 * public function for
	 * syncing file and db based templates
	 *
	 * can either
	 * create template files from db entries
	 * insert templates from template files into db
	 * update templates
	 */

	public static function syncTemplates() {

		$config	= Application::getInstance()->getConfig();
		$db		= Application::getInstance()->getDb();

		$locales = empty($config->site->locales) ? array() : $config->site->locales;

		// fetch file info

		$tpl = array();

		// "universal" templates

		self::getTplFiles('universal', $tpl);

		// localized templates

		foreach($locales as $l) {
			self::getTplFiles($l, $tpl);
		}

		// fetch db info

		$dbTpl = array();

		// "universal" templates

		$rows = $db->doQuery("
			SELECT
				p.pagesID,
				p.Template,
				p.Alias,
				date_format(max(r.templateUpdated), '%Y-%m-%d %H:%i:%s') AS lastUpdate,
				UNIX_TIMESTAMP(max(r.templateUpdated)) AS lastUpdateTS
			FROM
				revisions r
				RIGHT JOIN pages p ON r.pagesID = p.pagesID
			WHERE
				r.Locale IS NULL OR r.Locale = ''
			GROUP BY
				pagesID,
				Template
			", TRUE);

		foreach($rows as $r) {
			$dbTpl['universal'][$r['Template']] = $r;
		}

		// localized templates

		foreach($locales as $l) {
			$rows = $db->doPreparedQuery("
				SELECT
					p.pagesID,
					p.Template,
					p.Alias,
					date_format(max(r.templateUpdated), '%Y-%m-%d %H:%i:%s') AS lastUpdate,
					UNIX_TIMESTAMP(max(r.templateUpdated)) AS lastUpdateTS
				FROM
					revisions r
					RIGHT JOIN pages p ON r.pagesID = p.pagesID
				WHERE
					r.Locale = ?
			GROUP BY
					pagesID,
					Template
			", array($l));

			foreach($rows as $r) {
				$dbTpl[$l][$r['Template']] = $r;
			}
		}

		// update templates based on db info

		foreach($dbTpl as $l => $t) {
			foreach($t as $k => $v) {
				if(!isset($tpl[$l][$k]) || ($tpl[$l][$k]['fmtime'] < $v['lastUpdateTS'])) {

					$localeSQL = $l == 'universal' ? "(r.Locale IS NULL OR r.Locale = '')" : "r.Locale = '$l'";

					$rows = $db->doQuery("
						SELECT
							r.Markup
						FROM
							revisions r
						WHERE
							pagesID = {$v['pagesID']} AND $localeSQL
						ORDER BY
							templateUpdated DESC
						LIMIT 1
					", TRUE);

					if(!empty($rows[0])) {
						self::createTemplate($v + $rows[0], $l);
					}
				}
			}
		}

		// update db on template files

		foreach($tpl as $l => $t) {

			foreach($t as $k => $v) {
				if(!isset($dbTpl[$l][$k])) {
					self::insertTemplate($v, $l);
				}
				else if(empty($dbTpl[$l][$k]['lastUpdateTS']) || ($v['fmtime'] > $dbTpl[$l][$k]['lastUpdateTS'])) {
					self::updateTemplate(array_merge($dbTpl[$l][$k], $v), $l);
				}
			}
		}
	}

	/**
	 * retrieve metadata of template stored in database
	 *
	 * @param string $pageId
	 */
	public static function getPageMetaData($pageId, $locale = '') {

		if(($db = Application::getInstance()->getDb())) {

			if($db->tableExists('pages') && $db->tableExists('revisions')) {
				$data = $db->doPreparedQuery("
					SELECT
						r.Title,
						a.Name,
						r.Keywords,
						r.Description,
						r.templateUpdated as lastChanged,
						IFNULL(r.locale, '') AS locale_sort
					FROM
						revisions r
						INNER JOIN pages p ON r.pagesID = p.pagesID
						LEFT JOIN admin a ON r.authorID = a.adminID
					WHERE
						p.Alias = ? AND
						r.locale IS NULL OR r.locale = ?
					ORDER BY
						locale_sort DESC, active DESC, r.lastUpdated DESC
					LIMIT 1
					",
					array(
						strtoupper($pageId),
						$locale
					)
				);

				if(!empty($data[0])) {
					unset($data[0]['locale_sort']);
					return $data[0];
				}
			}
		}

		return FALSE;
	}

	/**
	 * public function for
	 * adding a revision to a template
	 * @param array $data new revision data
	 */
	public static function addRevision($data) {
		$locale = $data['Locale'];
		unset($data['Locale']);
		self::deleteOldRevisions($data['pagesID'], $locale);
		return self::insertRevision($data, $locale);
	}

	private static function getTplFiles($locale, &$tpl) {

		$path	= self::getPath($locale);

		try {
			$files	= FilesystemFolder::getInstance($path)->getFiles('htm');
		}
		catch(FilesystemFolderException $e) {
			return;
		}

		foreach($files as $f) {
			$fi = $f->getFileInfo();

			$tpl[$locale][$f->getFilename()] = array(
				'Template' => $f,
				'fmtime' => $fi->getMTime(),
				'fmtimef' => date('Y-m-d H:i:s', $fi->getMTime())
			);
		}
	}

	/**
	 * create a new template file
	 * @param array $data content, mtime, etc. of template
	 * @param string $locale locale of template
	 */
	private static function createTemplate($data, $locale = 'universal') {

		$path = self::getPath($locale);

		$handle = fopen($path.$data['Template'], 'w');
		if(!$handle) {
			return FALSE;
		}
		if(fwrite($handle, $data['Markup']) === FALSE) {
			return FALSE;
		}
		fclose($handle);

		@chmod($path.$data['Template'], 0666);
		@touch($path.$data['Template'], $data['lastUpdateTS']);

		return TRUE;
	}

	/**
	 * insert page data and first revision
	 * @param array $data template data
	 * @param string $locale of template
	 */
	private static function insertTemplate($data, $locale = 'universal') {

		$db = Application::getInstance()->getDb();

		$alias = strtoupper(basename($data['Template']->getFilename(), '.htm'));

		$rows = $db->doQuery("SELECT pagesID from pages WHERE Alias = '$alias'", TRUE);

		// insert only revision (locale might differ)
		if(!empty($rows)) {
			$newId = $rows[0]['pagesID'];
		}
		else {
			if(!($newId = $db->insertRecord('pages',
			array(
				'Template' => $data['Template']->getFilename(),
				'Alias' => $alias
			)
			))) {
				return FALSE;
			}
		}

		$markup = file_get_contents($data['Template']->getPath());

		return self::insertRevision(
			array(
				'Markup' => $markup,
				'Rawtext' => self::extractRawtext($markup),
				'pagesID' => $newId,
				'templateUpdated' => $data['fmtimef']
			), $locale
		);
	}

	/**
	 * delete old revisions and add new revision
	 */
	private static function updateTemplate($data, $locale = 'universal') {

		$metaData = self::getPageMetaData($data['Alias']);
		$markup = file_get_contents($data['Template']->getPath());

		self::deleteOldRevisions($data['pagesID'], $locale);

		return self::insertRevision(
			array_merge($metaData,
			array(
				'Markup' => $markup,
				'Rawtext' => self::extractRawtext($markup),
				'pagesID' => $data['pagesID'],
				'templateUpdated' => $data['fmtimef']
			)), $locale
		);
	}

	private static function insertRevision($row, $locale = 'universal') {

		$config	= Application::getInstance()->getConfig();
		$db		= Application::getInstance()->getDb();

		$row			= self::sanitizeTemplateData($row);
		$row['Rawtext']	= self::extractRawtext($row['Markup']);

		if(!empty($locale) && $locale != 'universal' && in_array($locale, $config->site->locales)) {
			$row['Locale'] = $locale;
			$localeSQL = "r.Locale = '$locale'";
		}
		else {
			$localeSQL = "(r.Locale IS NULL OR r.Locale = '')";
		}

		$db->execute("update revisions r set active = NULL where active = 1 AND pagesID = {$row['pagesID']} AND $localeSQL");
		$row['active'] = 1;

		return $db->insertRecord('revisions', $row);
	}

	private static function deleteOldRevisions($pagesID, $locale = 'universal') {

		$config	= Application::getInstance()->getConfig();
		$db		= Application::getInstance()->getDb();

		if(empty(self::$maxPageRevisions)) {
			self::$maxPageRevisions =	isset($config->site->max_page_revisions) &&
										is_numeric($config->site->max_page_revisions) ?
										((int) $config->site->max_page_revisions > 0 ? (int) $config->site->max_page_revisions : 1) :
										5;
		}

		$delIds = array();

		$localeSQL = !empty($locale) && $locale != 'universal' && in_array($locale, $config->site->locales) ?
			"Locale = '$locale'" :
			"(Locale IS NULL OR Locale = '')";

		$db->doQuery("
			SELECT
				revisionsID
			FROM
				revisions r
			WHERE
				pagesID = $pagesID AND $localeSQL
			ORDER BY
				templateUpdated DESC");

		if($db->numRows < self::$maxPageRevisions) {
			return TRUE;
		}

		for($i = self::$maxPageRevisions - 1; $i--;) {
			$r = $db->queryResult->fetch_assoc();
			array_push($delIds, $r['revisionsID']);
		}

		return $db->execute("
			DELETE FROM
				revisions
			WHERE
				revisionsID NOT IN (".implode(',', $delIds).") AND pagesID = $pagesID AND $localeSQL");
	}

	private static function getPath($locale) {
		return
			rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).
			(defined('TPL_PATH') ? TPL_PATH : DIRECTORY_SEPARATOR).
			($locale === 'universal' ? '' : ($locale . DIRECTORY_SEPARATOR) );
	}

	/**
	 * extract raw text data from php/html template
	 */
	private static function extractRawtext($text) {
		return strip_tags(htmlspecialchars_decode(preg_replace(array('~\s+~', '~<br\s*/?>~', '~<\s*script.*?>.*?</\s*script\s*>~', '~<\?(php)?.*?\?>~'), array(' ', ' ', '', '') , $text)));
	}

	/**
	 * sanitize keywords, description
	 */
	private static function sanitizeTemplateData($data) {
		if(!empty($data['Keywords'])) {
			$data['Keywords']	= preg_replace(array('~\s+~', '~\s*,\s*~', '~[^ \w\däöüß,.-]~i'), array(' ', ', ', ''), trim($data['Keywords']));
		}
		if(!empty($data['Description'])) {
			$data['Description']= preg_replace(array('~\s+~', '~[^ \pL\d,.-]~'), array(' ', ''), trim($data['Description']));
		}
		return $data;
	}
}