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

use vxPHP\Service\ServiceInterface;

/**
 * Interface for services generating menu entries
 * 
 * @author Gregor Kofler
 * @version 0.1.0 2016-07-25
 */
interface MenuServiceInterface extends ServiceInterface
{
	/**
	 * append menu entries to a menu passed into method
	 * 
	 * @param Menu $menu
	 */
	public function appendMenuEntries(Menu $menu);
}