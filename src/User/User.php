<?php

namespace vxPHP\User;

use vxPHP\User\Notification\Notification;
use vxPHP\User\Exception\UserException;
use vxPHP\User\Util;

use vxPHP\Mail\Email;
use vxPHP\Application\Application;

/**
 * @author Gregor Kofler
 * @version 0.6.2 2014-09-19
 */

class User {
	/*
	 * constants for different authentication levels, mirroring authentication_flags in db table
	 */
	const AUTH_SUPERADMIN			= 1;
	const AUTH_PRIVILEGED			= 16;
	const AUTH_OBSERVE_TABLE		= 256;
	const AUTH_OBSERVE_ROW			= 4096;

	protected	$id,
				$adminid,
				$name,
				$email,
				$pwd,
				$misc_data,

				$table_access = array(),
				$row_access = array(),

				$groupid,
				$group_alias,

				$privilege_level,
				$authenticated = FALSE,

				$cachedNotifications;
				
						/**
						 * @var array
						 */
	protected static	$instancesById = array();

						/**
						 * @var array
						 */
	protected static	$instancesByEmail = array();
	
						/**
						 * @var User
						 */
	protected static	$userInSession;
	
	/**
	 * retrieve a user instance
	 * with $allowLaziness set to FALSE, the database is not searched
	 * 
	 * @param string|int $id
	 * @param boolean $allowLaziness
	 * 
	 * @throws \InvalidArgumentException
	 */
	public static function getInstance($id, $allowLaziness = TRUE) {

		if(!is_scalar($id)) {
			throw new \InvalidArgumentException("Invalid argument for instantiating a user.");
		}

		if(isset(self::$instancesById[$id])) {
			return self::$instancesById[$id];
		}

		if(isset(self::$instancesByEmail[$id])) {
			return self::$instancesByEmail[$id];
		}

		if(!$allowLaziness) {
			return NULL;
		}

		$user = new static($id);

		self::$instancesById	[$user->getAdminId()]	= $user;
		self::$instancesByEmail	[$user->getId()]		= $user;
		
		return $user;
		
	}
	
	/**
	 * stub
	 * 
	 * @todo
	 * @param array $userData
	 */
	public static function createUser(array $userData) {
		
	}
	
	/**
	 * 
	 */
	public static function getSessionUser() {
		
		if(!empty($_SESSION['user'])) {
			
			$user = $_SESSION['user'];

			if($user instanceof static) {

				// re-establish same object reference
				
				$id = $user->getAdminId();

				// write user if not already instantiated
				
				if(!isset(self::$instancesById[$id])) {
					self::$instancesById	[$id]				= $user;
					self::$instancesByEmail	[$user->getId()]	= $user;
				}

				// return 

				$user = self::$instancesById[$id];
				self::$userInSession = $user;
				return self::$userInSession;
				
			}
		}

	}

	/**
	 * create new user instance retrieved from database
	 * 
	 * @param string|int $id
	 * @throws UserException
	 */
	protected function __construct($id) {

		if(is_numeric($id)) {
			$id			= (int) $id;
			$idColumn	= 'adminID';
		}
		
		else {
			$idColumn	= 'Email';
		}

		$rows = Application::getInstance()->getDb()->doPreparedQuery("
			SELECT
				a.*,
				ag.Privilege_Level,
				ag.admingroupsID as groupid,
				LOWER(ag.Alias) as group_alias
			FROM
				admin a
				INNER JOIN admingroups ag on a.admingroupsID = ag.admingroupsID
			WHERE
				$idColumn = ?", array($id));
		
		if(!empty($rows[0])) {
			$this->id = $rows[0]['Email'];
		
			foreach($rows[0] as $k => $v) {
				$k = strtolower($k);
				if(property_exists($this, $k)) {
					$this->$k = $v;
				}
			}
			
			$this->table_access	= empty($this->table_access)	? array() : array_map('strtolower', preg_split('/\s*,\s*/', $this->table_access));
			$this->row_access	= empty($this->row_access)		? array() : array_map('strtolower', preg_split('/\s*,\s*/', $this->row_access));
				
		}

		else {
			throw new UserException("User '$id' does not exist.", UserException::USER_DOES_NOT_EXIST);
		}

	}

	public function storeInSession() {
		
		self::$userInSession	= $this;
		$_SESSION['user']		= $this;

	}
	
	public function removeFromSession() {

		self::$userInSession	= NULL;
		$_SESSION['user']		= NULL;
	}

	/*
	 * various getters
	 */
	public function __toString() {
		return $this->id;
	}

	public function getId() {
		return $this->id;
	}

	/**
	 * return primary key of user record
	 *
	 * @return integer
	 */
	public function getAdminId() {
		return $this->adminid;
	}

	/**
	 * return name of user
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * return optional data stored with user record
	 *
	 * @return string
	 */
	public function getMiscData() {
		return $this->misc_data;
	}

	public function getAdmingroup() {
		return $this->group_alias;
	}

	public function getPrivilegeLevel() {
		return $this->privilege_level;
	}

	/**
	 * check whether user is allowed to access $table
	 *
	 * @param string $table
	 * @return boolean
	 */
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

	/**
	 * return all rows the user is allowed to access
	 *
	 * @return array
	 */
	public function getRowAccess() {
		return $this->row_access;
	}

	/**
	 * return all tables the user is allowed to access
	 *
	 * @return array
	 */
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
	 * authenticate user by checking stored hash
	 * against argument
	 *
	 * @param string $pwd
	 */
	public function authenticate($pwd) {
		$this->authenticated = Util::checkPasswordHash($pwd, $this->pwd);
	}

	/**
	 * allow update of non-critical user data, i.e. Name, Email, Password
	 *
	 * @param array $data
	 * @return success
	 * 
	 */
	public function restrictedUpdate(Array $data) {
		$set = array();

		foreach($data as $k => $v) {
			if(in_array($k, array('PWD', 'Email', 'Name', 'misc_data'))) {
				$set[$k] = $v;
			}
		}

		Application::getInstance()->getDb()->updateRecord('admin', $this->adminid, $set);
		foreach($set as $k => $v) {
			$k = strtolower($k);
			$this->$k = $v;
		}
		$this->id = $this->email;
		return TRUE;
	}

	/**
	 * delete user, removes user from database
	 *
	 * @return sucess
	 */
	public function delete() {
		return $db = Application::getInstance()->getDb()->deleteRecord('admin', $this->adminid);
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
		return $db = Application::getInstance()->getDb()->doPreparedQuery("
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

		$db = $db = Application::getInstance()->getDb();

		if(!isset($this->id)) {
			return;
		}

		$db->execute('DELETE FROM admin_notifications WHERE adminID = ?', array($this->adminid));
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
				$db->execute("INSERT INTO admin_notifications (adminID, notificationsID) VALUES(?, ?)", array($this->adminid, $i));
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
	 * when $overridePreferences is set to true, notification is sent, ignoring user preferences or admin group assignments
	 *
	 * @param string $alias
	 * @param array $varData
	 * @param boolean $overridePreferences
	 *
	 * @return boolean success
	 */
	public function notify($alias, Array $varData = array(), $overridePreferences = FALSE) {
		if($overridePreferences) {
			$notification = new Notification($alias);
		}
		else {
			if(!$this->getsNotified($alias)) {
				return TRUE;
			}

			$notification = $this->cachedNotifications[$alias];
		}

		$txt = $notification->fillMessage($varData);

		if(empty($txt)) {
			return TRUE;
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

		if($m->send() === TRUE) {
			$this->logNotification($notification);
			return TRUE;
		}
		return FALSE;
	}

	private function logNotification(Notification $notification) {
	}
}
