<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Routing;

use vxPHP\User\UserInterface;

/**
 * A route authenticator supports the router by checking whether
 * a user is allowed to invoke the controller configured for a route
 * 
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.2.1, 2021-11-28
 * 
 */
interface RouteAuthenticatorInterface
{
	/**
	 * checks whether user fulfills route authentication requirements
	 *  
	 * @param Route $route
	 * @param UserInterface $user
	 * @return boolean
	 */
	public function authenticate(Route $route, UserInterface $user): bool;

    /**
     * handles an authentication violation
     *
     * @param Route $route
     * @return mixed
     */
	public function handleViolation(Route $route);
}