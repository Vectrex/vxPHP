<?php

namespace vxPHP\User;

use vxPHP\User\User;
use vxPHP\User\Exception\UserException;
use vxPHP\Application\Application;

/**
 * simple class to store utility methods
 *
 * @author Gregor Kofler
 * @version 1.0.0 2014-11-13
 */

class Util {

	/**
	 * hash password, the only place where hashing should be done
	 *
	 * @param string $plainPassword
	 *
	 * @return string hashed password
	 */
	public static function hashPassword($plainPassword) {

		// use mcrypt functionality if possible

		if(function_exists('mcrypt_create_iv')) {
			$salt = strtr(base64_encode(mcrypt_create_iv(16, MCRYPT_DEV_URANDOM)), '+', '.');
		}

		// otherwise use some weaker generic replacement

		else {
			$salt = md5(uniqid('', TRUE));
		}

		// Blowfish algorithm, cost 10

		return crypt($plainPassword, '$2a$10$' . $salt);


//		$encoded = sha1($plainPwd);
//		return $encoded;

	}

	/**
	 * check whether a plain password matches the hash, the only place where checking should be done
	 *
	 * @param string $hash
	 * @param string $passwordToCheck
	 *
	 * @return boolean
	 */

	public static function checkPasswordHash($passwordToCheck, $hash) {

		return crypt($passwordToCheck, $hash) === $hash;

	}

	/**
	 * check whether a user email is already assigned
	 *
	 * @param string $email
	 * @return boolean availability
	 */
	public static function isAvailableEmail($email) {

		return !count(Application::getInstance()->getDb()->doPreparedQuery('SELECT adminID FROM admin WHERE LOWER(email) = ?', array(strtolower($email))));

	}

	/**
	 * check whether a username is already assigned
	 *
	 * @param string $username
	 * @return boolean availability
	 */
	public static function isAvailableUsername($username) {
	
		return !count(Application::getInstance()->getDb()->doPreparedQuery('SELECT adminID FROM admin WHERE username = ?', array((string) $username)));
	
	}

	/**
	 * get list of users listening to supplied notification alias
	 *
	 * @param string $notification_alias
	 * @return array [User]
	 */
	public static function getUsersToNotify($notification_alias) {

		$users = array();

		$rows = Application::getInstance()->getDb()->doPreparedQuery('
			SELECT
				adminID
			FROM
				admin a
				INNER JOIN admin_notifications an ON a.adminID = an.adminID
				INNER JOIN notifications n ON an.notificationsID = n.notificationsID
			WHERE
				UPPER(n.alias) = ?
			', array(strtoupper($notification_alias)));

		foreach($rows as $r) {
			$users[] = User::getInstance($r['adminID']);
		}

		return $users;
	}

	/**
	 * get list of users belonging to given admingroup
	 *
	 * @param string $admingroup_alias
	 * @param callback $callBackSort
	 * @throws UserException
	 *
	 * @return array [User]
	 */
	public static function getUsersBelongingToGroup($admingroup_alias, $callBackSort = NULL) {

		$users = array();

		$rows = Application::getInstance()->getDb()->doPreparedQuery('
			SELECT
				adminID
			FROM
				admin a
				INNER JOIN admingroups ag ON a.admingroupsID = ag.admingroupsID
			WHERE
				UPPER(ag.alias) = ?
			', array(strtoupper($admingroup_alias)));

		foreach($rows as $r) {
			$users[] = User::getInstance($r['adminID']);
		}
		
		if(is_null($callBackSort)) {
			return $users;
		}
		else if(is_callable($callBackSort)) {
			usort($users, $callBackSort);
			return $users;
		}
		else if(is_callable("UserAbstract::$callBackSort")) {
			usort($users, "UserAbstract::$callBackSort");
			return $users;
		}
		else {
			throw new UserException("'$callBackSort' is not callable.", UserException::SORT_CALLBACK_NOT_CALLABLE);
		}
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

	/**
	 * various callback functions for sorting user instances
	 */
	private static function sortByName($a, $b) {
		$dA = $a->getName();
		$dB = $b->getName();

		return $dA < $dB ? -1 : 1;
	}
}
