<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Application\Config\Parser\Xml;

use vxPHP\Application\Config;

/**
 * Class Pages
 * @package vxPHP\Application\Config\Parser\Xml
 * @deprecated will be replaced by routes
 */
class Pages extends Routes
{
    /**
     * Parsing of routes requires a script name configured
     * in the site settings
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->site = $config->site;
        $this->nodeName = 'page';
    }
}