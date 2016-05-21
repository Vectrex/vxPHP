<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\User\Notification;

use vxPHP\Application\Application;
/**
 * mapper class for notifications
 *
 * @author Gregor Kofler
 * @version 0.1.5 2015-04-28
 */
class Notification {
	private		$id,
				$alias,
				$subject,
				$message,
				$description,
				$attachment,
				$signature,
				$group_alias,
				$not_displayed;

	private static $cachedNotificationData;

	/**
	 * create a notification instance identified by its alias
	 * 
	 * @param string $alias
	 */
	public function __construct($alias) {

		if(!isset(self::$cachedNotificationData)) {
			self::queryAllNotifications();
		}

		if(isset(self::$cachedNotificationData[$alias])) {
			foreach(self::$cachedNotificationData[$alias] as $k => $v) {
				$k = strtolower($k);
				if(property_exists($this, $k)) {
					$this->$k = $v;
				}
			}
		}
	
	}

	/**
	 * expose private properties
	 * 
	 * @param string $p
	 */	
	public function __get($p) {

		if(property_exists($this, $p)) {
			return $this->$p;
		}

	}

	public function __toString() {

		return $this->alias;

	}

	/**
	 * get all notification instances assigned to an admingroup identified by $groupAlias
	 * 
	 * @param string $groupAlias
	 * @return multitype:\vxPHP\User\Notification\Notification
	 */
	public static function getAvailableNotifications($groupAlias = NULL) {

		if(!isset(self::$cachedNotificationData)) {
			self::queryAllNotifications();
		}

		$result = array();

		foreach(self::$cachedNotificationData as $v) {
			if(!isset($groupAlias) || strtoupper($v['group_alias']) == strtoupper($groupAlias)) {
				$n = new Notification($v['Alias']);
				$result[(string) $n] = $n;
			}
		}

		return $result;

	}

	private static function queryAllNotifications() {

		$rows = Application::getInstance()->getDb()->doPreparedQuery("
			SELECT
				notificationsID as id,
				n.Alias,
				IFNULL(Description, n.Alias) AS Description,
				Subject,
				Message,
				Signature,
				Attachment,
				Not_Displayed,
				ag.Alias as group_alias

			FROM
				notifications n
				INNER JOIN admingroups ag ON ag.admingroupsID = n.admingroupsID
		");

		self::$cachedNotificationData = array();

		foreach($rows as $r) {
			$r['Attachment'] = preg_split('~\s*,\s*~', $r['Attachment']);
			self::$cachedNotificationData[$r['Alias']] = $r;
		}

	}

	/**
	 * fill placeholders in notification message
	 * returns message
	 * 
	 * @param array $fieldValues
	 * @return string
	 */
	public function fillMessage(array $fieldValues = array()) {

		$txt = $this->message;

		if(empty($txt)) {
			return '';
		}

		foreach ($fieldValues as $key => $val) {
			$txt = str_replace('{' . $key . '}', $val, $txt);
		}
		return $txt;
	
	}
}
