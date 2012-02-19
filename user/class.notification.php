<?php
/**
 * mapper class for notifications
 * 
 * @author Gregor Kofler
 * @version 0.1.1 2012-02-19
 */
class Notification {
	private	$id,
			$alias,
			$subject,
			$message,
			$description,
			$attachment,
			$signature,
			$group_alias;

	private static $cachedNotificationData;

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

	public function __get($p) {
		if(property_exists($this, $p)) {
			return $this->$p;
		}
	}

	public function __toString() {
		return $this->alias;
	}

	public static function getAvailableNotifications($groupAlias = NULL) {
		if(!isset(self::$cachedNotificationData)) {
			self::queryAllNotifications();
		}

		$result = array();

		foreach(self::$cachedNotificationData as $k => $v) {
			if(!isset($groupAlias) || strtoupper($v['group_alias']) == strtoupper($groupAlias)) {
				$n = new Notification($v['Alias']);
				$result[(string) $n] = $n;
			}
		}
		return $result;
	}

	
	private static function queryAllNotifications() {
		$rows = $GLOBALS['db']->doQuery("
			SELECT
				notificationsID as id,
				n.Alias,
				IFNULL(Description, n.Alias) AS Description,
				Subject,
				Message,
				Signature,
				Attachment,
				ag.Alias as group_alias
			FROM
				notifications n
				INNER JOIN admingroups ag ON ag.admingroupsID = n.admingroupsID" ,true);

		self::$cachedNotificationData = array();

		foreach($rows as $r) {
			$r['Attachment'] = preg_split('~\s*,\s*~', $r['Attachment']);
			self::$cachedNotificationData[$r['Alias']] = $r;
		}
	}

	public function fillMessage($fieldValues) {
		$txt = $this->message;

		if(empty($txt)) {
			return '';
		}

		foreach ($fieldValues as $key => $val) {
			$txt = str_replace('{'.$key.'}', $val, $txt);
		}
		return $txt;
	}
}
?>