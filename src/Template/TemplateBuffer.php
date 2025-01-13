<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Template;

use vxPHP\Application\Application;
use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Application\Exception\ConfigException;
use vxPHP\Controller\Controller;

/**
 * Class TemplateBuffer
 * this wraps the raw contents of a template which is then rendered by
 * capturing the output buffer
 *
 * @author Gregor Kofler
 * @version 1.1.1 2021-11-28
 *
 * @package vxPHP\Template
 */
class TemplateBuffer
{
    public const array INVALID_PROPERTIES = ['__rawContents'];

    /**
     * @var string
     *
     * the unprocessed template string
     */
    public string $__rawContents = '';

    /**
     * include another template file
     * does only path handling
     * included files are within the same scope as the including file
     *
     * @param string $templateFilename
     * @throws ApplicationException
     */
    public function includeFile(string $templateFilename): void
    {
        eval('?>' .
            file_get_contents(Application::getInstance()->getRootPath() .
                (defined('TPL_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(TPL_PATH, '/')) : '') .
                $templateFilename
            ));
    }

    /**
     * include controller output
     * $controllerPath is [path/to/controller/]name_of_controller
     * additional arguments can be passed on to the controller constructor
     *
     * @param string $controllerPath
     * @param string|null $methodName
     * @param array|null $constructorArguments
     *
     * @return string|null
     * @throws ApplicationException
     * @throws ConfigException
     */
    public function includeControllerResponse(string $controllerPath, ?string $methodName = null, ?array $constructorArguments = null): ?string
    {
        $namespaces = explode('\\', ltrim(str_replace('/', '\\', $controllerPath), '/\\'));

        if($namespaces[0]) {
            $controller = '\\Controller\\'. implode('\\', array_map('ucfirst', $namespaces)) . 'Controller';
        }

        else {
            throw new ConfigException(sprintf("Controller string '%s' cannot be parsed.", $controllerPath));
        }

        // get instance and set method which will be called in render() method of controller

        $controllerClass = Application::getInstance()->getApplicationNamespace() . $controller;

        if(!$constructorArguments) {
            /**
             * @var Controller $instance
             */
            $instance = new $controllerClass();
        }
        else {
            $instance = new $controllerClass(...$constructorArguments);
        }

        if($methodName) {
            return $instance->setExecutedMethod($methodName)->render();
        }
        return $instance->setExecutedMethod('execute')->render();
    }
}