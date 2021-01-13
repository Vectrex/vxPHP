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
use vxPHP\Application\Exception\ConfigException;
use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\DynamicMenuEntry;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Application\Config\Parser\XmlParserInterface;

class Menus implements XmlParserInterface
{
    /**
     * @var array
     */
    private $routes;

    /**
     * @var \StdClass
     */
    private $site;

    public function __construct(Config $config)
    {
        $this->site = $config->site;
        $this->routes = $config->routes;
    }

    /**
     * @param \DOMNode $node
     * @return array
     * @throws \RuntimeException
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): array
    {
        if ($this->site === null || $this->routes === null) {
            throw new \RuntimeException('Cannot parse menu configuration. Site and route configuration must be parsed first.');
        }

        $menus = [];

        foreach ((new \DOMXPath($node->ownerDocument))->query('menu', $node) as $menu) {

            $id = $menu->getAttribute('id') ?: Menu::DEFAULT_ID;

            if(isset($menus[$id])) {
                $this->appendMenuEntries($menu->childNodes, $menus[$id]);
            }
            else {
                $menus[$id] = $this->parseMenu($menu);
            }
        }

        return $menus;
    }

    /**
     * Parse XML menu entries and creates menu instance
     *
     * @param \DOMNode $menu
     * @return Menu
     * @throws ConfigException
     */
    private function parseMenu(\DOMNode $menu): Menu
    {
        $root = $menu->getAttribute('script');

        if(!$root) {
            if($this->site) {
                $root= $this->site->root_document ?: 'index.php';
            }
            else {
                $root= 'index.php';
            }
        }

        $type = $menu->getAttribute('type') === 'dynamic' ? 'dynamic' : 'static';
        $service = $menu->getAttribute('service') ?: null;
        $id = $menu->getAttribute('id') ?: null;

        if($type === 'dynamic' && !$service) {
            throw new ConfigException('A dynamic menu requires a configured service.');
        }

        $m = new Menu(
            $root,
            $id,
            $type,
            $service
        );

        if(($menuAuth = strtolower(trim($menu->getAttribute('auth'))))) {

            $m->setAuth($menuAuth);

            // if an auth level is defined, additional authentication parameters can be set

            if(($authParameters = $menu->getAttribute('auth_parameters'))) {
                $m->setAuthParameters($authParameters);
            }

        }

        $m->setDisplay(!($display = $menu->getAttribute('display')) || 'none' !== strtolower(trim($display)));

        foreach($menu->attributes as $attr) {
            $nodeName = $attr->nodeName;
            if(!in_array($nodeName, ['script', 'type', 'service', 'id', 'auth', 'auth_parameters', 'display'])) {
                $m->setAttribute($attr->nodeName, $attr->nodeValue);
            }
        }

        $this->appendMenuEntries($menu->childNodes, $m);

        return $m;
    }

    /**
     * append menu entries of configuration to a
     * previously created Menu instance
     *
     * @param \DOMNodeList $entries
     * @param Menu $menu
     * @throws ConfigException
     */
    private function appendMenuEntries(\DOMNodeList $entries, Menu $menu): void
    {
        foreach($entries as $entry) {

            if($entry->nodeType !== XML_ELEMENT_NODE || 'menuentry' !== $entry->nodeName) {
                continue;
            }

            // read additional attributes which are passed to menu entry constructor

            $attributes = [];

            foreach($entry->attributes as $attr) {

                $nodeName = $attr->nodeName;

                if(!in_array($nodeName, ['page', 'path', 'auth', 'auth_parameters', 'display'])) {
                    $attributes[$attr->nodeName] = $attr->nodeValue;
                }

            }

            if('menuentry' === $entry->nodeName) {

                $route = $entry->getAttribute('page') ?: $entry->getAttribute('route');
                $path = $entry->getAttribute('path');

                // menu entry comes with a path attribute (which can also link an external resource)

                if($path) {
                    $local = strpos($path, '/') !== 0 && !preg_match('~^[a-z]+://~', $path);
                    $e = new MenuEntry($path, $attributes, $local);
                }

                // menu entry comes with a page attribute, in this case the route path is used

                else if($route) {
                    if(!isset($this->routes[$menu->getScript()][$route])) {

                        throw new \RuntimeException(sprintf(
                            "No route for menu entry ('%s') found. Available routes for script '%s' are '%s'.",
                            $route,
                            $menu->getScript(),
                            empty($this->routes[$menu->getScript()]) ? 'none' : implode("', '", array_keys($this->routes[$menu->getScript()]))
                        ));

                    }

                    $e = new MenuEntry((string) $this->routes[$menu->getScript()][$route]->getPath(), $attributes, true);

                }

                else {
                    throw new ConfigException(sprintf("Menu entry with both route ('%s') and path ('%s') attribute found.", $route, $path));
                }

                // handle authentication settings of menu entry

                if(($auth = strtolower(trim($entry->getAttribute('auth'))))) {

                    // set optional authentication level

                    $e->setAuth($auth);

                    // if auth level is defined, additional authentication parameters can be set

                    if(($authParameters = $entry->getAttribute('auth_parameters'))) {
                        $e->setAuthParameters($authParameters);
                    }
                }

                $e->setDisplay(!($display = $entry->getAttribute('display')) || 'none' !== strtolower(trim($display)));

                $menu->appendEntry($e);

                $submenu = (new \DOMXPath($entry->ownerDocument))->query('menu', $entry);

                if($submenu->length) {
                    $e->appendMenu($this->parseMenu($submenu->item(0)));
                }
            }
            else if($entry->nodeName === 'menuentry_placeholder') {
                $e = new DynamicMenuEntry(null, $attributes);
                $menu->appendEntry($e);
            }
        }
    }
}