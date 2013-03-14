<?php

namespace vxPHP\User;

use vxPHP\User\User;
use vxPHP\User\Exception\UserException;

/**
 * simple class to store utility methods
 *
 * @author Gregor Kofler
 * @version 0.1.0
 */

class Util {

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
	 * @param callback $callBackSort
	 * @throws UserException
	 *
	 * @return array users
	 */
	public static function getUsersBelongingToGroup($admingroup_alias, $callBackSort = NULL) {

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
