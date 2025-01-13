<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Service;

/**
 * interface to allow simple dependency injection
 *
 * @author Gregor Kofler
 * @version 0.1.0 2015-07-05
 *
 */
interface ServiceInterface
{
    public function setParameters(array $parameters);
}