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

use vxPHP\Security\Password\PasswordEncrypter;

/**
 * Represents a basic user
 * wraps authentication and role assignment
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 2.0.0 2017-02-20 
 */
class User {
	
	/**
	 * name of user
	 * 
	 * @var string
	 */
	protected $username;
	
	/**
	 * the hashed password
	 * 
	 * @var string
	 */
	protected $hashedPassword;
	
	/**
	 * additional attributes of user
	 * like email, full name
	 * all attributes are lower key cased
	 * 
	 * @var array
	 */
	protected $attributes;
	
	/**
	 * all roles of user
	 * 
	 * @var Role[]
	 */
	protected $roles;
	
	/**
	 * indicate whether a previous authentication
	 * of the user was successful
	 * 
	 * @var boolean
	 */
	protected $authenticated;

	/**
	 * constructor
	 * 
	 * @param string $username
	 * @param string $hashedPassword
	 * @param array $roles
	 * @param array $attributes
	 * @throws \InvalidArgumentException
	 */
	public function __construct($username, $hashedPassword = '', array $roles = [], array $attributes = []) {
		
		$username = trim($username);
		
		if(!$username) {
			throw new \InvalidArgumentException('An empty username is not allowed.');
		}
		
		$this->username = $username;
		$this->setHashedPassword($hashedPassword);
		$this->setRoles($roles);
		$this->attributes = array_change_key_case($attributes, CASE_LOWER);

	}

	/**
	 * return the username
	 * 
	 * @return string
	 */
	public function getUsername() {

		return $this->username;

	}

	/**
	 * return username when object is cast to string
	 *
	 * @return string
	 */
	public function ___toString() {

		return $this->username;

	}
	
	/**
	 * return the password hash
	 * 
	 * @return string
	 */
	public function getHashedPassword() {

		return $this->hashedPassword;

	}

	/**
	 * set password hash; if password hash
	 * differs from previously set hash any previous
	 * authentication result is reset
	 * 
	 * @param string $hashedPassword
	 * @return \vxPHP\User\User
	 */
	public function setHashedPassword($hashedPassword) {

		if($hashedPassword !== $this->hashedPassword) {
			$this->authenticated = FALSE;
			$this->hashedPassword = $hashedPassword;
		}

		return $this;

	}

	/**
	 * return an additional attribute
	 * 
	 * @param string $attribute
	 * @param mixed $default
	 * @return string|mixed
	 */
	public function getAttribute($attribute, $default = NULL) {

		if (!$this->attributes || !array_key_exists(strtolower($attribute), $this->attributes)) {
			return $default;
		}
		return $this->attributes[strtolower($attribute)];
	
	}

	/**
	 * set an additional attribute
	 * 
	 * @param string $attribute
	 * @param mixed $value
	 * @return \vxPHP\User\User
	 */
	public function setAttribute($attribute, $value) {
		
		$this->attributes[strtolower($attribute)] = $value;
		return $this;

	}
	
	/**
	 * replace all attributes
	 * 
	 * @param array $attributes
	 * @return \vxPHP\User\User
	 */
	public function replaceAttributes(array $attributes) {
		
		$this->attributes = array_change_key_case($attributes, CASE_LOWER);
		return $this;
		
	}
	
	/**
	 * compare passed plain text password with
	 * stored hashed password and store result
	 * 
	 * @param unknown $plaintextPassword
	 * @return \vxPHP\User\User
	 */
	public function authenticate($plaintextPassword) {
		
		$encrypter = new PasswordEncrypter();
		$this->authenticated = $encrypter->isPasswordValid($plaintextPassword, $this->hashedPassword);
		return $this;

	}
	
	/**
	 * return result of previous authentication
	 * 
	 * @return boolean
	 */
	public function isAuthenticated() {
		
		return $this->authenticated;
		
	}

	/**
	 * check whether user can take a certain role
	 * 
	 * @param string $roleName
	 * @return boolean
	 */
	public function hasRole($roleName) {

		return array_key_exists(strtolower($roleName), $this->roles);

	}

	/**
	 * set all roles of user
	 * 
	 * @param Role[]
	 * @throws \InvalidArgumentException
	 * @return \vxPHP\User\User
	 */
	public function setRoles(array $roles) {
		
		$this->roles = [];
		
		foreach($roles as $role) {

			if(!$role instanceof Role) {
				throw new \InvalidArgumentException('Role is not a role instance.');
			}
			if(array_key_exists($role->getRoleName(), $this->roles)) {
				throw new \InvalidArgumentException(sprintf("Role '%s' defined twice.", $role->getRoleName()));
			}

			$this->roles[$role->getRoleName()] = $role;

		}

		return $this;
	}
	
	/**
	 * return all roles a user can take
	 *
	 * @return Role[]
	 */
	public function getRoles() {
	
		return array_values($this->roles);

	}

	/**
	 * return all possible roles and subroles - defined by a role
	 * hierarchy - the user can take
	 * 
	 * @param RoleHierarchy $roleHierarchy
	 * @return Role[]
	 */
	public function getRolesAnSubRoles(RoleHierarchy $roleHierarchy) {

		$possibleRoles = [];
		
		foreach($this->roles as $role) {
			
			$possibleRoles[] = $role;
			$possibleRoles = array_merge($possibleRoles, $roleHierarchy->getSubRoles($role));
			
			return $possibleRoles;

		}
	}
	
	
}