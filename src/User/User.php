<?php

namespace vxPHP\User;

use vxPHP\User\Notification\Notification;
use vxPHP\User\Exception\UserException;
use vxPHP\User\Util;

use vxPHP\Mail\Email;
use vxPHP\Application\Application;
use vxPHP\Session\Session;

/**
 * @author Gregor Kofler
 * @version 1.1.0 2015-03-12
 */

class User {
	/*
	 * constants for different authentication levels, mirroring authentication_flags in db table
	 */
	const AUTH_SUPERADMIN			= 1;
	const AUTH_PRIVILEGED			= 16;
	const AUTH_OBSERVE_TABLE		= 256;
	const AUTH_OBSERVE_ROW			= 4096;

	protected	$adminid,
				$username,
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
	protected static	$instancesByUsername = array();
	
						/**
						 * @var User
						 */
	protected static	$userInSession;
	
	/**
	 * retrieve a user instance
	 * with $id being an integer the adminID is considered, with $id being a 
	 * 
	 * @param string|int $id
	 * 
	 * @throws \InvalidArgumentException
	 */
	public static function getInstance($id) {

		if(!is_scalar($id) || empty($id)) {
			throw new \InvalidArgumentException("Invalid argument for instantiating a user.");
		}

		// return a previously instantiated use

		if(is_int($id) && isset(self::$instancesById[$id])) {
			return self::$instancesById[$id];
		}

		if(isset(self::$instancesByUsername[$id])) {
			return self::$instancesByUsername[$id];
		}

		// try to retrieve user from database

		$idColumn = is_int($id) ? 'adminID': 'username';

		$rows = Application::getInstance()->getDb()->doPreparedQuery("
			SELECT
				a.*,
				ag.privilege_Level,
				ag.admingroupsID as groupid,
				LOWER(ag.alias) as group_alias

			FROM
				admin a
				LEFT JOIN admingroups ag on a.admingroupsID = ag.admingroupsID

			WHERE
				$idColumn = ?", array($id));

		$user = new static();

		if(!empty($rows[0])) {
		
			foreach($rows[0] as $k => $v) {
				$k = strtolower($k);
				if(property_exists($user, $k)) {
					$user->$k = $v;
				}
			}
				
			$user->table_access	= empty($user->table_access)	? array() : array_map('strtolower', preg_split('/\s*,\s*/', $user->table_access));
			$user->row_access	= empty($user->row_access)		? array() : array_map('strtolower', preg_split('/\s*,\s*/', $user->row_access));
		
		}
		
		else {
			throw new UserException("User '$id' does not exist.", UserException::USER_DOES_NOT_EXIST);
		}
		
		self::$instancesById[$user->adminid] = $user;
		if(($username = $user->getUsername())) {
			self::$instancesByUsername[$username] = $user;
		}
		
		return $user;
		
	}
	
	/**
	 * search session for user instance, register and return it
	 * 
	 * @return \vxPHP\User\User
	 */
	public static function getSessionUser() {
		
		$session = Session::getSessionDataBag();

		if(($user = $session->get('user'))) {
			
			if($user instanceof static) {

				// re-establish same object reference
				
				$id = $user->getAdminId();

				// write user if not already instantiated
				
				if(!isset(self::$instancesById[$id])) {
					self::$instancesById[$id] = $user;
					if(($username = $user->getUsername())) {
						self::$instancesByUsername[$username] = $user;
					}
				}

				// return user instance 

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
	public function __construct() {
	}

	public function storeInSession() {

		Session::getSessionDataBag()->set('user', $this);
		self::$userInSession = $this;

	}
	
	public function removeFromSession() {

		Session::getSessionDataBag()->remove('user');
		self::$userInSession = NULL;

	}

	public function __toString() {
		return $this->username;
	}

	/**
	 * return primary key of user record
	 * only populated with already saved user
	 *
	 * @return integer
	 */
	public function getAdminId() {
		return $this->adminid;
	}

	/**
	 * get password hash
	 * 
	 * @return string
	 */
	public function getPasswordHash() {
		return $this->pwd;
	}

	/**
	 * set password of user
	 * 
	 * @param string $password
	 */
	public function setPassword($password) {
		$this->pwd = Util::hashPassword($password);
		return $this;
	}

	/**
	 * return username
	 * 
	 * @return string
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * set username
	 * 
	 * @param string $username
	 * @return \vxPHP\User\User
	 */
	public function setUsername($username) {
		$this->username = $username;
		return $this;
	}

	/**
	 * get email of user
	 * 
	 * @return string
	 */
	public function getEmail() {
		return $this->email;
	}

	/**
	 * set email of user
	 * 
	 * @param string $email
	 * @return \vxPHP\User\User
	 */
	public function setEmail($email) {
		$this->email = $email;
		return $this;
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
	 * set name
	 * 
	 * @param string $name
	 * @return \vxPHP\User\User
	 */
	public function setName($name) {
		$this->name = $name;
		return $this;
	}

	/**
	 * return optional data stored with user record
	 *
	 * @return string
	 */
	public function getMiscData() {
		return $this->misc_data;
	}

	/**
	 * set optional data stored with user record
	 * 
	 * @param string $datastring
	 * @return \vxPHP\User\User
	 */
	public function setMiscData($datastring) {
		$this->misc_data = $datastring;
		return $this;
	}

	/**
	 * get alias name of admin group
	 * 
	 * @return string
	 */
	public function getAdmingroup() {
		return $this->group_alias;
	}
	
	/**
	 * set admin group by group alias
	 * this automatically updates the privilege level
	 * 
	 * @return \vxPHP\User\User
	 */
	public function setAdmingroup($groupAlias) {
		
		$rows = Application::getInstance()->getDb()->doPreparedQuery('SELECT * FROM admingroups WHERE alias = ?', array(strtoupper($groupAlias)));

		if(!count($rows)) {
			throw new UserException("Unknown admin group '$groupAlias'", UserException::UNKNOWN_ADMIN_GROUP);
		}
		
		$this->group_alias		= $groupAlias;
		$this->groupid			= $rows[0]['admingroupsID'];
		$this->privilege_level	= $rows[0]['privilege_level'];

		return $this;
	}

	/**
	 * get privilege level of admin group
	 * 
	 * @return int
	 */
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

	/**
	 * check for level "superadmin"
	 * @return boolean
	 */
	public function hasSuperAdminPrivileges() {
		return $this->privilege_level <= self::AUTH_SUPERADMIN;
	}

	/**
	 * check for level "privilege"
	 * @return boolean
	 */
	public function hasPrivileges() {
		return $this->privilege_level <= self::AUTH_PRIVILEGED;
	}

	/**
	 * check whether user is authenticated
	 * @return boolean
	 */
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
	 * save user data, either inserts or updates a record
	 * no checks for uniqueness of email or username are performed
	 * 
	 * @return \vxPHP\User\User
	 */
	public function save() {

		$data = array(
			'name'			=> $this->name,
			'username'		=> $this->username,
			'pwd'			=> $this->pwd,
			'email'			=> $this->email,
			'misc_data'		=> $this->misc_data,
			'admingroupsID'	=> $this->groupid,
			'table_access'	=> empty($this->table_access)	? NULL : implode(',', $this->table_access),
			'row_access'	=> empty($this->row_access)		? NULL : implode(',', $this->row_access)
		);

		$db = Application::getInstance()->getDb();

		// update if admin id is set 

		if(is_null($this->adminid)) {
			$this->adminid = $db->insertRecord('admin', $data);
		}
		
		// insert otherwise

		else {
			$db->updateRecord('admin', $this->adminid, $data);
		}
		
		self::$instancesById[$this->adminid] = $this;
		if(!empty($this->username)) {
			self::$instancesByUsername[$this->username] = $this;
		}

		return $this;
	}
	
	/**
	 * delete user and remove it from database
	 *
	 * @return boolean
	 */
	public function delete() {

		if(!is_null($this->adminid)) {

			// delete cached instances
			
			unset (self::$instancesById[$this->adminid]);
			if(!empty($this->username)) {
				unset (self::$instancesByUsername[$this->username]);
			}

			return !!Application::getInstance()->getDb()->deleteRecord('admin', $this->adminid);

		}
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
				$this->cachedNotifications[$r['alias']] = new Notification($r['alias']);
			}
		}
		return array_values($this->cachedNotifications);
	}

	protected function queryNotifications() {
		return Application::getInstance()->getDb()->doPreparedQuery("
			SELECT
				alias
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

		$db = Application::getInstance()->getDb();

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
		$m->setSubject	(defined('DEFAULT_MAIL_SUBJECT_PREFIX') ? DEFAULT_MAIL_SUBJECT_PREFIX . ' ' : '' . $notification->subject);
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
