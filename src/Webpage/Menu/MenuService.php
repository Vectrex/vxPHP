<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Webpage\Menu;

use vxPHP\Service\Service;

/**
 * parent class for services generating dynamic menu entries 
 * 
 * @author Gregor Kofler
 * @version 0.1.0 2016-07-25
 */
abstract class MenuService extends Service implements MenuServiceInterface {
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Webpage\Menu\MenuServiceInterface::appendMenuEntries()
	 */
	public abstract function appendMenuEntries(Menu $menu);

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Service\Service::setParameters()
	 */
	public function setParameters(array $parameters) {
	}
}