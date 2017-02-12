<?php

namespace vxPHP\User;

use vxPHP\User\RoleHierarchy;

/**
 * Represents the custom role hierarchy used with vxWeb
 *
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.1.0 2017-02-12
 */
class vxWebRoleHierarchy extends RoleHierarchy {

	const AUTH_SUPERADMIN		= 1;
	const AUTH_PRIVILEGED		= 16;
	const AUTH_OBSERVE_TABLE	= 256;
	const AUTH_OBSERVE_ROW		= 4096;
	
	private $roles = [
			'superadmin' => ['privileged'],
			'privileged' => ['observe_table'],
			'observe_table' => ['observe_row']
	];
	
	/**
	 *
	 * {@inheritdoc}
	 *
	 * @see \vxPHP\User\RoleHierarchy::__construct()
	 */
	public function __construct() {
		
		parent::__construct ($this->roles);

	}
}