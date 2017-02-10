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

use vxPHP\User\Exception\UserException;

/**
 * Represents a class which instantiates User objects,
 * which can then be authenticated
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.1.0 2017-02-10
 *
 */
interface UserInstancerInterface {
	
	/**
	 * create a user instance identified by a (unique) username
	 * throws a UserException when the user is not found 
	 * 
	 * @param string $username
	 * @return User2
	 * @throws UserException
	 */
	public function instanceUserByUsername($username);
	
	/**
	 * refresh a user
	 * 
	 * @param User2 $user
	 */
	public function refreshUser(User2 $user);

}