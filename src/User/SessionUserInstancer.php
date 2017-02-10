<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\User;

use vxPHP\User\UserInstancerInterface;
use vxPHP\Database\DatabaseInterface;
use vxPHP\Application\Application;
use vxPHP\User\Exception\UserException;
use vxPHP\Session\Session;

/**
 *
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.1.0
 *        
 */
class SessionUserInstancer implements UserInstancerInterface {
	
	const AUTH_SUPERADMIN		= 1;
	const AUTH_PRIVILEGED		= 16;
	const AUTH_OBSERVE_TABLE	= 256;
	const AUTH_OBSERVE_ROW		= 4096;
	
	private $roles = [
		'superadmin' => ['privileged'],
		'privileged' => ['observe_table'],
		'observe_table' => ['observe_row']
	];

	/**
	 * @var RoleHierarchy
	 */
	private $roleHierarchy;

	/**
	 * @var User[]
	 */
	private $usersByUsername = [];
	
	/**
	 * @var User[]
	 */
	private $usersById = [];

	/**
	 * @var DatabaseInterface
	 */
	private $db;
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\User\UserInstancerInterface::refreshUser()
	 */
	public function refreshUser(User2 $user) {

		// TODO Auto-generated method stub

	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\User\UserInstancerInterface::instanceUserByUsername()
	 */
	public function instanceUserByUsername($username) {

		if(array_key_exists($username, $this->usersByUsername)) {
			return $this->usersByUsername[$username];
		}
		
		$rows = $this->db->doPreparedQuery("
			SELECT
				a.*,
				ag.privilege_Level,
				ag.admingroupsID as groupid,
				LOWER(ag.alias) as group_alias
		
			FROM
				admin a
				LEFT JOIN admingroups ag on a.admingroupsID = ag.admingroupsID
		
			WHERE
				username = ?", [$username]
		);
		
		if(count($rows) !== 1) {
			throw new UserException(sprintf("User '%s' not found or not unique.", $username));
		}
		
		$user = new SessionUser(
			$username,
			$rows[0]['pwd'],
			[
				new Role($rows[0]['group_alias'])
			],
			[
				'email' => $rows[0]['email'],
				'name' => $rows[0]['email'],
				'misc_data' => $rows[0]['misc_data'],
				'table_access' => $rows[0]['table_access'],
				'row_access' => $rows[0]['row_access'],
			]
		);
		
		$this->usersByUsername[$username] = $user;
		$this->usersById[$rows[0]['adminID']] = $user;
		
		return $user;

	}
	
	/**
	 * constructor
	 * initializes role hierarchy
	 */
	public function __construct() {
		
		$this->roleHierarchy = new RoleHierarchy($this->roles);
		$this->db = Application::getInstance()->getDb();

	}

	/**
	 * remove session user from session
	 * returns the removed session user
	 * 
	 * @param string $sessionKey
	 * @throws UserException
	 * @return \vxPHP\User\SessionUser|mixed
	 */
	public function unsetSessionUser($sessionKey = NULL) {
		
		$sessionKey = $sessionKey ?: SessionUser::DEFAULT_KEY_NAME;
		
		$user = Session::getSessionDataBag()->get($sessionKey);

		if($user) {

			if(!$user instanceof SessionUser) {
				throw new UserException(sprintf("Session key '%s' doesn't hold a SessionUser instance.", $sessionKey));
			}
			Session::getSessionDataBag()->remove($sessionKey);

		}
		
		return $user;

	}

	public function getSessionUser($sessionKey = NULL) {

		$sessionKey = $sessionKey ?: SessionUser::DEFAULT_KEY_NAME;
		
		return Session::getSessionDataBag()->get($sessionKey);
		
	}
	
}