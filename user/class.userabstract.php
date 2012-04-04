<?php
/**
 * abstract base class for for admins and members
 * 
 * @author Gregor Kofler
 * @version 0.5.2 2012-04-04
 */

abstract class UserAbstract {
	/*
	 * constants for different authentication levels, mirroring authentication_flags in db table
	 */
	const AUTH_SUPERADMIN			= 1;
	const AUTH_PRIVILEGED			= 16;
	const AUTH_OBSERVE_TABLE		= 256;
	const AUTH_OBSERVE_ROW			= 4096;

	protected $id;
	protected $adminid;
	protected $name;
	protected $email;
	protected $pwd;
	protected $table_access = array();
	protected $row_access = array();

	protected $groupid;
	protected $group_alias;
	protected $privilege_level;
	protected $authenticated = FALSE;

	protected $cachedNotifications;

	/*
	 * various getters
	 */
	public function __toString() {
		return $this->id;
	}

	public function getId() {
		return $this->id;
	}

	public function getAdminId() {
		return $this->adminid;
	}

	public function getName() {
		return $this->name;
	}

	public function getAdmingroup() {
		return $this->group_alias;
	}
	
	public function getPrivilegeLevel() {
		return $this->privilege_level;
	}

	public function hasTableAccess($table) {
		if(is_array($this->table_access)) {
			return in_array(strtolower($table), $this->table_access);
		}
	}

	public function hasRowAccess($row) {
		if(is_array($this->row_access)) {
			return in_array(strtolower($row), $this->row_access);
		}
	}

	public function getRowAccess() {
		return $this->row_access;
	}

	public function getTableAccess() {
		return $this->table_access;
	}

	public function hasSuperAdminPrivileges() {
		return $this->privilege_level <= self::AUTH_SUPERADMIN;
	}

	public function hasPrivileges() {
		return $this->privilege_level <= self::AUTH_PRIVILEGED;
	}

	public function isAuthenticated() {
		return $this->authenticated;
	}

	/**
	 * get user data
	 * 
	 * if $dataOrId is an associative array, array values become user data
	 * otherwise user data is retrieved from database
	 * 
	 * @param mixed $dataOrId
	 */
	public function setUser($dataOrId) {
		if(!is_array($dataOrId)) {
			$rows = $GLOBALS['db']->doPreparedQuery("
				SELECT
					a.*,
					ag.Privilege_Level,
					ag.admingroupsID as groupid,
					LOWER(ag.Alias) as group_alias
				FROM
					admin a
					INNER JOIN admingroups ag on a.admingroupsID = ag.admingroupsID
				WHERE
					Email = ?", array((string) $dataOrId));

			if(!empty($rows[0])) {
				$this->id = $dataOrId;
				
				foreach($rows[0] as $k => $v) {
					$k = strtolower($k);
					if(property_exists($this, $k)) {
						$this->$k = $v;
					}
				}
			}
		}

		else {
			$this->id = (string) $dataOrId['Email'];
	
			foreach($dataOrId as $k => $v) {
				$k = strtolower($k);
				if(property_exists($this, $k)) {
					$this->$k = $v;
				}
			}
		}

		$this->table_access	= empty($this->table_access)	? array() : array_map('strtolower', preg_split('/\s*,\s*/', $this->table_access));
		$this->row_access	= empty($this->row_access)		? array() : array_map('strtolower', preg_split('/\s*,\s*/', $this->row_access));
	}

	/**
	 * authenticate user by checking stored password
	 * against argument
	 * 
	 * @param string $pwd
	 */
	public function authenticate($pwd) {
		$this->authenticated = $this->pwd == self::encodePwd($pwd);
	}
	
	/**
	 * allow update of non-critical user data, i.e. Name, Email, Password
	 * 
	 * @param array $data
	 * @return success
	 */
	public function restrictedUpdate(Array $data) {
		$set = array();

		foreach($data as $k => $v) {
			if(in_array($k, array('PWD', 'Email', 'Name'))) {
				$set[$k] = $v;
			}
		}
		
		try {
			$GLOBALS['db']->updateRecord('admin', $this->adminid, $set);
			foreach($set as $k => $v) {
				$k = strtolower($k);
				$this->$k = $v;
			}
			$this->id = $this->email;
			return TRUE;
		}
		catch(MysqldbiException $e) {
			return FALSE;
		}
	}
	
	/**
	 * delete user, removes user from database
	 * 
	 * @return sucess
	 */
	public function delete() {
		return $GLOBALS['db']->deleteRecord('admin', $this->adminid);
	}

	/**
	 * retrieve notification aliases assigned to user
	 * 
	 * @return void|array assigned notifications
	 */
	public function getNotifications() {
		if(empty($this->id)) {
			return;
		}

		if(!isset($this->cachedNotifications)) {
			$this->cachedNotifications = array();
			$rows = $this->queryNotifications();
			foreach($rows as $r) {
				$this->cachedNotifications[$r['Alias']] = new Notification($r['Alias']);
			}
		}
		return array_values($this->cachedNotifications);
	}

	protected function queryNotifications() {
		return $GLOBALS['db']->doPreparedQuery("
			SELECT
				Alias
			FROM
				notifications n
				INNER JOIN admin_notifications an ON (n.notificationsID = an.notificationsID AND adminID = ?)
		", array($this->adminid));
	}
	
	/**
	 * stores allowed notification for user in database
	 * 
	 * @param array $notification_aliases
	 */
	public function setNotifications(Array $aliases) {
		$db = $GLOBALS['db'];

		if(!isset($this->id)) {
			return;
		}

		$db->preparedExecute('DELETE FROM admin_notifications WHERE adminID = ?', array($this->adminid));
		$this->cachedNotifications = NULL;

		$available = Notification::getAvailableNotifications($this->group_alias);

		$ids = array();
		foreach($aliases as $a) {
			if(isset($available[$a])) {
				$ids[] = $available[$a]->id;
			}
		}

		if(!empty($ids)) {
			foreach($ids as $i) {
				$db->preparedExecute("INSERT INTO admin_notifications (adminID, notificationsID) VALUES(?, ?)", array($this->adminid, $i));
			}
			$this->getNotifications();
		}
	}

	/**
	 * check whether user gets notified by certain notification, identified by its alias
	 * 
	 * @param string $alias
	 * @return boolean status
	 */
	public function getsNotified($alias) {
		if(!isset($this->cachedNotifications)) {
			$this->getNotifications();
		}
		return isset($this->cachedNotifications[$alias]);
	}

	/**
	 * submits notification to user, when notification is assigned to user
	 * 
	 * @param string $alias
	 * @param array $varData
	 * 
	 * @return boolean success
	 */
	public function notify($alias, Array $varData = array()) {
		if(!$this->getsNotified($alias)) {
			return true;
		}

		$notification = $this->cachedNotifications[$alias];
		$txt = $notification->fillMessage($varData); 

		if(empty($txt)) {
			return true;
		}

		$m = new Email();

		$m->setReceiver	($this->email);
		$m->setSubject	(defined('DEFAULT_MAIL_SUBJECT_PREFIX') ? DEFAULT_MAIL_SUBJECT_PREFIX.' ' : '' . $notification->subject);
		$m->setMailText	($txt);
		$m->setSig		($notification->signature);

		if(!empty($notification->attachment)) {
			foreach($notification->attachments as $a) {
				$m->addAttachment($a);
			}
		}

		if($m->send()) {
			$this->logNotification($notification);
			return true;
		}
		return false;
	}

	private function logNotification(Notification $notification) {
	}

	/**
	 * encode password, the only place where encoding should be done
	 * 
	 * @param string $plainPwd
	 * @return string encoded password
	 */
	public static function encodePwd($plainPwd) {
		$encoded = sha1($plainPwd);
		return $encoded;
	}
	
	/**
	 * check whether user id is not already assigned to other user
	 * 
	 * @param string $id
	 * @return boolean availability
	 */
	public static function isAvailableId($id) {
		$rows = $GLOBALS['db']->doPreparedQuery('SELECT adminID FROM admin WHERE Email = ?', array((string) $id));
		return empty($rows);
	}

	/**
	 * get list of users listening to supplied notification alias
	 * 
	 * @param string $notification_alias
	 * @return array $users
	 */
	public static function getUsersToNotify($notification_alias) {
		$users = array();
		$rows = $GLOBALS['db']->doPreparedQuery('
			SELECT
				Email
			FROM
				admin a
				INNER JOIN admin_notifications an ON a.adminID = an.adminID
				INNER JOIN notifications n ON an.notificationsID = n.notificationsID
			WHERE
				UPPER(n.Alias) = ?
			', array(strtoupper($notification_alias)));
		foreach($rows as $r) {
			$u = new User();
			$u->setUser($r['Email']);
			$users[] = $u;
		}
		return $users;
	}
	
	/**
	 * get list of users belonging to given admingroup
	 * 
	 * @param string $admingroup_alias
	 * @return array users
	 */
	public static function getUsersBelongingToGroup($admingroup_alias) {
		$users = array();
		$rows = $GLOBALS['db']->doPreparedQuery('
			SELECT
				Email
			FROM
				admin a
				INNER JOIN admingroups ag ON a.admingroupsID = ag.admingroupsID
			WHERE
				UPPER(ag.Alias) = ?
			', array(strtoupper($admingroup_alias)));
		foreach($rows as $r) {
			$u = new User();
			$u->setUser($r['Email']);
			$users[] = $u;
		}
		return $users;
	}

	/**
	 * searches $_SESSION for user object
	 * 
	 * @return Admin $user
	 */
	public static function getCurrentUser() {
		if(isset($_SESSION['user'])) {
			return unserialize($_SESSION['user']);
		}
	}
}

class UserException extends Exception {
}
?>