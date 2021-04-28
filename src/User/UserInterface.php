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

/**
 * the core interface implemented by the user class and all derived
 * classes
 * 
 * defines the minimal method set of the user classes, without making
 * any assumptions how the users are generated or authenticated
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.2.1, 2021-04-28
 *
 */
interface UserInterface {

	/**
	 * return the (unique) username, used for both identificationa and
	 * authentication of user
	 *
	 * @return string
	 */
	public function getUsername(): string;
	
	/**
	 * return the hashed password, a plain text password should never be
	 * stored with user instances
	 * for authenticating a user with a password it should suffice to
	 * provide the hashed password
	 *
	 * @return string
	 */
	public function getHashedPassword(): string;
	
	/**
	 * return an additional attribute associated with the user (e.g.
	 * email, name, etc.)
	 * 
	 * @param string $attribute
	 * @param mixed $default
	 * @return mixed the attribute value
	 */
	public function getAttribute(string $attribute, $default);
	
	/**
	 * sets an additional attribute associated with the user
	 *
	 * @param string $attribute
	 * @param mixed $value
	 */
	public function setAttribute(string $attribute, $value);
	
	/**
	 * replaces all additional attributes associated with the user
	 * 
	 * @param array $attributes
	 */
	public function replaceAttributes(array $attributes);
	
	/**
	 * return the result of a previous authentication
	 *
	 * @return boolean
	 */
	public function isAuthenticated(): ?bool;

	/**
	 * returns all roles the user can take
	 * directly assigned roles are always returned; depending on the
	 * implementation derived roles might be returned, too
	 *
	 * @return Role[]
	 */
	public function getRoles(): array;
	
	/**
	 * check whether a role has been assigned to the user
	 * directly assigned roles are always checked; depending on the
	 * implementation derived roles might be checked, too
	 * 
	 * @param string $roleName
	 * @return boolean
	 */
	public function hasRole(string $roleName): bool;
}