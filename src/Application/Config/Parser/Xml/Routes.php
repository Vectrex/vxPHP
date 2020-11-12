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
use vxPHP\Routing\Route;

class Routes implements XmlParserInterface
{
    /**
     * @var \StdClass
     */
    protected $site;

    /**
     * @var string
     */
    protected $nodeName;

    /**
     * Parsing of routes requires a script name configured
     * in the site settings
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->site = $config->site;
        $this->nodeName = 'route';
    }

    /**
     * @param \DOMNode $node
     * @return array
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): array
    {
        if ($this->site === null) {
            throw new \RuntimeException('Cannot parse route configuration. Site configuration must be parsed first.');
        }

        $routes = [];

        $scriptName = $node->getAttribute('script');

        if(!$scriptName) {
            $scriptName = $this->site->root_document ?? 'index.php';
        }

        $redirect = $node->getAttribute('default_redirect');

        if(!array_key_exists($scriptName, $routes)) {
            $routes[$scriptName] = [];
        }

        foreach($node->getElementsByTagName($this->nodeName) as $routeNode) {

            $parameters = [
                'redirect' => $redirect
            ];

            // get route id

            $routeId = $routeNode->getAttribute('id');

            if($routeId === null || trim($routeId) === '') {
                throw new ConfigException('Route with missing or invalid id found.');
            }

            // read optional controller

            if(($controller = $routeNode->getAttribute('controller'))) {

                // clean path delimiters, prepend leading backslash, replace slashes with backslashes, apply ucfirst to all namespaces

                $namespaces = explode('\\', ltrim(str_replace('/', '\\', $controller), '/\\'));

                if(count($namespaces) && $namespaces[0]) {
                    $parameters['controller'] = '\\Controller\\'. implode('\\', array_map('ucfirst', $namespaces)) . 'Controller';
                }
                else {
                    throw new ConfigException(sprintf("Controller string '%s' cannot be parsed.", (string) $controller));
                }
            }

            // read optional controller method

            if(($method = $routeNode->getAttribute('method'))) {
                $parameters['method'] = $method;
            }

            // read optional allowed request methods

            if(($requestMethods = $routeNode->getAttribute('request_methods'))) {
                $allowedMethods	= Route::KNOWN_REQUEST_METHODS;
                $requestMethods	= preg_split('~\s*,\s*~', strtoupper($requestMethods));

                foreach($requestMethods as $requestMethod) {
                    if(!in_array($requestMethod, $allowedMethods, true)) {
                        throw new ConfigException(sprintf("Invalid request method '%s' for route '%s'.", $requestMethod, $routeId));
                    }
                }
                $parameters['requestMethods'] = $requestMethods;
            }

            if(($path = $routeNode->getAttribute('path'))) {
                $parameters['path'] = $path;
            }

            // extract optional authentication requirements

            if(($auth = $routeNode->getAttribute('auth'))) {

                $auth = strtolower(trim($auth));

                if($auth && ($authParameters = $routeNode->getAttribute('auth_parameters'))) {
                    $parameters['authParameters'] = trim($authParameters);
                }

                $parameters['auth'] = $auth;
            }

            if(isset($routes[$scriptName][$routeId])) {
                throw new ConfigException(sprintf("Route '%s' for script '%s' found more than once.", $routeId, $scriptName));
            }

            // look for placeholder specifications

            $parameters['placeholders'] = [];

            foreach ($routeNode->getElementsByTagName('placeholder') as $placeholderNode) {
                $name = $placeholderNode->getAttribute('name');
                if (!$name) {
                    throw new ConfigException(sprintf("Placeholder for route '%s' has no name attribute.", $routeId));
                }
                $parameters['placeholders'][$name] = [
                    'match' => $placeholderNode->getAttribute('match'),
                    'default' => $placeholderNode->getAttribute('default')
                ];
            }

            $route = new Route($routeId, $scriptName, $parameters);
            $routes[$scriptName][$route->getRouteId()] = $route;
        }

        return $routes;
    }
}