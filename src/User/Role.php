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
 * Represents a role assigned to a user
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.1.0 2017-02-10
 */
class Role {

	/**
	 * name of the role
	 *
	 * @var string
	 */
	protected $roleName;

	public function __construct($role) {

		$this->roleName = strtolower($role);

	}

	/**
	 * get name of role
	 * 
	 * @return string
	 */
	public function getRoleName() {

		return $this->roleName;

	}
	
	/**
	 * return name of role
	 * 
	 * @return string
	 */
	public function __toString() {

		return $this->roleName;

	}
	
}