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

use vxPHP\Database\DatabaseInterface;
use vxPHP\Application\Application;
use vxPHP\User\Exception\UserException;
use vxPHP\Session\Session;
use vxPHP\User\UserProviderInterface;

/**
 * represents users within a vxWeb application, which are stored in the
 * session after initialization
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.2.0, 2017-02-12
 *        
 */
class SessionUserProvider implements UserProviderInterface {
	
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
	 * @see \vxPHP\User\UserProviderInterface::refreshUser()
	 */
	public function refreshUser(User2 $user) {

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
				username = ?", [$user->getUsername()]
				);
		
		if(count($rows) !== 1) {
			throw new UserException(sprintf("User '%s' no longer exists.", $user->getUsername()));
		}

		$user
			->setHashedPassword($rows[0]['pwd'])
			->setRoles([new Role($rows[0]['group_alias'])])
			->replaceAttributes([
				'email' => $rows[0]['email'],
				'name' => $rows[0]['email'],
				'misc_data' => $rows[0]['misc_data'],
				'table_access' => $rows[0]['table_access'],
				'row_access' => $rows[0]['row_access'],
			])
		;

		return $user;

	}
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\User\UserProviderInterface::instanceUserByUsername()
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
	 */
	public function __construct() {

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

	/**
	 * retrieve a stored session user stored under a session key
	 * returns stored value only, when it is a SessionUser instance
	 * 
	 * @param string $sessionKey
	 * @return \vxPHP\User\SessionUser
	 */
	public function getSessionUser($sessionKey = NULL) {

		$sessionKey = $sessionKey ?: SessionUser::DEFAULT_KEY_NAME;
		
		$sessionUser = Session::getSessionDataBag()->get($sessionKey);

		if($sessionUser instanceof SessionUser) {
			return $sessionUser;
		}
		
	}
	
}