<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Webpage;

use vxPHP\User\UserInterface;
use vxPHP\Webpage\Menu\Menu;

/**
 * A menu authenticator allows checking whether a menu or menu entries
 * within the menu are shown to a user; routes linked to menu entries
 * must be authenticated by a route authenticator
 *
 * @author Gregor Kofler, info@gregorkofler.com
 * @version 0.1.1, 2021-10-09
 *
 */
interface MenuAuthenticatorInterface
{
    /**
     * checks whether user fulfills menu or menu entry authentication
     * requirements
     * the result will indicate whether the menu is visible at all
     * the state of single menu entries within an otherwise visible menu
     * are stored with the menu entries
     *
     * @param Menu $menu
     * @param UserInterface $user
     * @return boolean user is authenticated to see the menu
     */
    public function authenticate(Menu $menu, UserInterface $user): bool;
}