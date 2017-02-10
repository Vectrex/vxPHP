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
class RoleHierarchy {

	/**
	 * array containing role names and reflecting the hierarchy of roles
	 * 
	 * @var array
	 */
	protected $hierarchy;

	/**
	 * map with all 
	 *
	 * @var array
	 */
	protected $mappedRoleNames;

	/**
	 * set a hierarchy of roles defined by an array 
	 * containing this hierarchy
	 * 
	 * e.g.
	 * 'superadmin' => ['admin'],
	 * 'admin' => ['author', 'privileged'],
	 * 'privileged' => ['visitor']
	 * 
	 * superadmin will not only be able to reach the admin role
	 * but also author, privileged and visitor  
	 * 
	 * @param array $hierarchy
	 */
	public function __construct(array $hierarchy) {

		$this->hierarchy = array_change_key_case($hierarchy, CASE_LOWER);
		$this->buildRoleMap();

	}

	/**
	 * get all possible sub roles of a role
	 * 
	 * @param $role the parent role
	 */
	public function getSubRoles(Role $role) {
		
		$parentName = $role->getRoleName();
		$subRoles = [];
		
		if(array_key_exists($role->getRoleName(), $this->mappedRoleNames)) {
			
			foreach($this->mappedRoleNames[$parentName] as $subRoleName) {
				$subRoles[] = new Role($subRoleName);
			}
			
		}
		
		return $subRoles;

	}
	
	/**
	 * build the map which maps all sub-roles on any level to any of their parent roles
	 * 
	 * @param array $hierarchy
	 */
	private function buildRoleMap() {
		
		$this->mappedRoleNames = [];

		foreach ($this->hierarchy as $parent => $roles) {

			$roles = array_map('strtolower', $roles);
			$this->mappedRoleNames[$parent] = $roles;
			$additionalRoles = $roles;
			$foundParent = [];

			// check for every role, whether there are other roles below it

			while ($role = array_shift($additionalRoles)) {

				if (!isset($this->hierarchy[$role])) {
					
					// role has no further sub-roles defined

					continue;
				}
		
				$foundParent[] = $role;
		
				foreach ($this->hierarchy[$role] as $roleToAdd) {
					$this->mappedRoleNames[$parent][] = $roleToAdd;
				}

				foreach (array_diff($this->hierarchy[$role], $foundParent) as $roleToAdd) {

					$additionalRoles[] = $roleToAdd;

				}
			}
		
			$this->mappedRoleNames[$parent] = array_unique($this->mappedRoleNames[$parent]);

		}
		
	}
	
}