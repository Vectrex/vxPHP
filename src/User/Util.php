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

use vxPHP\User\User;
use vxPHP\User\Exception\UserException;
use vxPHP\Application\Application;

/**
 * simple class to store utility methods
 *
 * @author Gregor Kofler
 * @version 1.2.0 2017-02-08
 */

class Util {

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
				a.adminID
			FROM
				admin a
				INNER JOIN admin_notifications an ON a.adminID = an.adminID
				INNER JOIN notifications n ON an.notificationsID = n.notificationsID
			WHERE
				UPPER(n.alias) = ?
			', array(strtoupper($notification_alias)));

		foreach($rows as $r) {
			$users[] = User::getInstance((int) $r['adminID']);
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
	 * various callback functions for sorting user instances
	 */
	private static function sortByName($a, $b) {
		$dA = $a->getName();
		$dB = $b->getName();

		return $dA < $dB ? -1 : 1;
	}
}
