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
 * @version 2.1.3 2021-05-22
 */
class User implements UserInterface
{
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
	public function __construct(string $username, string $hashedPassword = '', array $roles = [], array $attributes = [])
    {
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
	 * {@inheritDoc}
	 * @see \vxPHP\User\UserInterface::getUsername()
	 */
	public function getUsername(): string
    {
		return $this->username;
	}

	/**
	 * return username when object is cast to string
	 *
	 * @return string
	 */
	public function __toString()
    {
		return $this->username;
	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\User\UserInterface::getHashedPassword()
	 */
	public function getHashedPassword(): string
    {
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
	public function setHashedPassword(string $hashedPassword): User
    {
		if($hashedPassword !== $this->hashedPassword) {
			$this->authenticated = false;
			$this->hashedPassword = $hashedPassword;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\User\UserInterface::getAttribute()
	 */
	public function getAttribute(string $attribute, $default = null): ?string
    {
		if (!$this->attributes || !array_key_exists(strtolower($attribute), $this->attributes)) {
			return $default;
		}
		return $this->attributes[strtolower($attribute)];
	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\User\UserInterface::setAttribute()
	 * @return \vxPHP\User\User
	 */
	public function setAttribute($attribute, $value) {
		
		$this->attributes[strtolower($attribute)] = $value;
		return $this;

	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\User\UserInterface::replaceAttributes()
	 * @return \vxPHP\User\User
	 */
	public function replaceAttributes(array $attributes): User
    {
		$this->attributes = array_change_key_case($attributes, CASE_LOWER);
		return $this;
	}
	
	/**
	 * compare passed plain text password with
	 * stored hashed password and store result
	 * 
	 * @param string $plaintextPassword
	 * @return \vxPHP\User\User
	 */
	public function authenticate(string $plaintextPassword): User
    {
		$encrypter = new PasswordEncrypter();
		$this->authenticated = $encrypter->isPasswordValid($plaintextPassword, $this->hashedPassword);
		return $this;
    }

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\User\UserInterface::isAuthenticated()
	 */
	public function isAuthenticated(): ?bool
    {
		return $this->authenticated;
	}

	/**
	 * check whether user can take a certain role, only directly
	 * assigned roles are checked
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\User\UserInterface::hasRole()
	 */
	public function hasRole(string $roleName): bool
    {
		return array_key_exists(strtolower($roleName), $this->roles);
	}

	/**
	 * set all roles of user
	 * 
	 * @param Role[]
	 * @throws \InvalidArgumentException
	 * @return \vxPHP\User\User
	 */
	public function setRoles(array $roles): User
    {
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
	 * return all directly assigned roles
	 * 
	 * {@inheritDoc}
	 * @see \vxPHP\User\UserInterface::getRoles()
	 */
	public function getRoles(): array
    {
        return array_values($this->roles);
	}

	/**
	 * return all possible roles and subroles - defined by a role
	 * hierarchy - the user can take
	 * 
	 * @param RoleHierarchy $roleHierarchy
	 * @return Role[]
	 */
	public function getRolesAndSubRoles(RoleHierarchy $roleHierarchy): array
    {
		$possibleRoles = [];
		
		foreach($this->roles as $role) {
			$possibleRoles[] = $role;
			array_push ($possibleRoles, ...$roleHierarchy->getSubRoles($role));
		}

        return $possibleRoles;
	}
}