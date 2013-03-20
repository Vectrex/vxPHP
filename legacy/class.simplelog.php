<?php
/**
 * simple class for logging page hits
 * provides static methods
 * uses a session entry to avoid multiple counts
 * supports only MySQL databases
 *
 * hits expire when session is cleared or after 24 hrs
 * 
 * v2.0.1 2009-10-01
 */
class SimpleLog {

	private	static	$table			= 'log',
					$dbFields		= array(
						'REMOTE_ADDR'		=> '16',
						'HTTP_USER_AGENT'	=> '128',
						'HTTP_REFERER'		=> '128'
					),
					$totalHits		= 0,
					$periodHits		= array(),
					$disabled		= false;

	public function __construct() { }

	/**
	 * write log entries
	 */
	public static function writeLogEntry() {
		if(session_id() == '') { session_start(); }
		
		if(!self::checkLogTable()) {
			self::$disabled = true;
			return false;
		}
		
		if(!empty($_SESSION['logID']) && !empty($_SESSION['logTimestamp'])) {
			if((int) $_SESSION['logTimestamp'] + 24 * 3600 > time()) {
				return false; 
			}
		}
		if(!$newId = $GLOBALS['db']->insertRecord('log', $_SERVER)) {
			return false;
		}
		$_SESSION['logID'] = $newId;
		$_SESSION['logTimestamp'] = time();
		return true;
	}

	/**
	 * check for table and start session if needed
	 */
	private static function checkLogTable() {
		if(!isset($GLOBALS['db']) || !$GLOBALS['db'] instanceof Mysqldbi) {
			return false;
		}

		foreach(self::$dbFields as $k => $v) {
			$fields[] = "$k varchar($v)";
		}

		return $GLOBALS['db']->execute("
			CREATE TABLE IF NOT EXISTS ".self::$table." (
	  			logID int(11) NOT NULL auto_increment,
	  			create_stamp timestamp(14) NOT NULL,
	  			".implode(',', $fields).', PRIMARY KEY (logID)
			) ENGINE=MyISAM default charset=utf8');
	}

	/**
	 * get first timestamp of log
	 * 
	 * @return string timestamp
	 */
	public static function getFirstTimestamp($format = '%d.%m.%Y - %H:%m:%s') {
		if(self::$disabled) { return false; }
		$rec = $GLOBALS['db']->doQuery("select DATE_FORMAT(min(create_stamp), '$format') as field_val from ".self::$table, true);
		return $rec[0]['field_val'];
	}
	/**
	 * last timestamp of log
	 * 
	 * @return string timestamp
	 */
	public static function getLastTimestamp($format = '%d.%m.%Y - %H:%m:%s') {
		if(self::$disabled) { return false; }
		$rec = $GLOBALS['db']->doQuery("select DATE_FORMAT(max(create_stamp), '$format') as field_val from ".self::$table, true);
		return $rec[0]['field_val'];
	}

	/**
	 * all hits
	 * 
	 * @return int count
	 */
	public static function getHits() {
		if(self::$disabled) { return false; }
		$rec = $GLOBALS['db']->doQuery("select count(logID) as cnt from ".self::$table, true);
		return $rec[0]['cnt'];
	}

	/**
	 * hits filtered for user agents
	 * 
 	 * @return int count
	 */
 	public static function getUserAgentHits($pattern) {
		if(self::$disabled) { return false; }
 		$rec = $GLOBALS['db']->doQuery("select count(logID) as cnt from ".self::$table." where HTTP_USER_AGENT LIKE '%$pattern%'", true);
		return $rec[0]['cnt'];
 	}

 	/**
	 * hits per period
	 * @param string mode ('month', 'year')
	 * @param int limit (max count of entries)
	 * 
 	 * @return array results
	 */
	public static function getHitsPerPeriod($mode = 'month', $limit = null) {
		if(self::$disabled) { return false; }
		if($mode != 'month' && $mode != 'year') { return false; }

		$limit = $limit === null ? '' : " limit $limit";

		if($mode == 'month') {
			$sql = "select count(logID) as cnt, date_format(create_stamp, '%Y-%m') as l_period from ".self::$table." group by l_period order by l_period desc";
		}
		else {
			$sql = "select count(logID) as cnt, date_format(create_stamp, '%Y') as l_period from ".self::$table." group by l_period order by l_period desc";
		}
		$rec = $GLOBALS['db']->doQuery($sql, true);

		$ret = array();
		foreach($rec as $v) {
			$ret[$v['l_period']] = $v['cnt'];
		}
		return $ret;
	}
}
?>