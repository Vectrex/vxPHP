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
use vxPHP\Application\Exception\ConfigException;
use vxPHP\Controller\Controller;

/**
 * Class TemplateBuffer
 * this wraps the raw contents of a template which is then rendered by
 * capturing the output buffer
 *
 * @author Gregor Kofler
 * @version 1.0.0 2019-01-07
 *
 * @package vxPHP\Template
 */
class TemplateBuffer
{
    /**
     * @var string
     *
     * the unprocessed template string
     */
    public $__rawContents = '';

    /**
     * include another template file
     * does only path handling
     * included files are within the same scope as the including file
     *
     * @param string $templateFilename
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
    public function includeFile($templateFilename) {

        /* @deprecated use $this when accessing assigned variables */

        $tpl = $this;

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
     * @param string $methodName
     * @param array $constructorArguments
     *
     * @return string
     * @throws ConfigException
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
    public function includeControllerResponse($controllerPath, $methodName = null, array $constructorArguments = null) {

        $namespaces = explode('\\', ltrim(str_replace('/', '\\', $controllerPath), '/\\'));

        if(count($namespaces) && $namespaces[0]) {
            $controller = '\\Controller\\'. implode('\\', array_map('ucfirst', $namespaces)) . 'Controller';
        }

        else {
            throw new ConfigException(sprintf("Controller string '%s' cannot be parsed.", $controllerPath));
        }


        // get instance and set method which will be called in render() method of controller

        $controllerClass = Application::getInstance()->getApplicationNamespace() . $controller;

        if(!$constructorArguments) {

            /**
             * @var Controller
             */
            $instance = new $controllerClass();

        }

        else {

            $instance = new $controllerClass(...$constructorArguments);

        }

        if($methodName) {

            return $instance->setExecutedMethod($methodName)->render();

        }
        else {

            return $instance->setExecutedMethod('execute')->render();

        }

    }

}