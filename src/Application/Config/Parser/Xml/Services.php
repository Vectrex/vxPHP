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

use vxPHP\Application\Config\Parser\ParserTrait;
use vxPHP\Application\Exception\ConfigException;
use vxPHP\Application\Config\Parser\XmlParserInterface;

class Services implements XmlParserInterface
{
    use ParserTrait;
    /**
     * @param \DOMNode $node
     * @return array
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): array
    {
        $services = [];

        foreach($node->getElementsByTagName('service') as $service) {

            if(!($id = $service->getAttribute('id'))) {
                throw new ConfigException('Service without id found.');
            }

            if(isset($services[$id])) {
                throw new ConfigException(sprintf("Service '%s' has already been defined.", $id));
            }

            if(!($class = $service->getAttribute('class'))) {
                throw new ConfigException(sprintf("No class for service '%s' configured.", $id));
            }

            // store parsed information

            $services[$id] = [

                // clean path delimiters, prepend leading backslash, and replace slashes with backslashes

                'class' => '\\' . ltrim(str_replace('/', '\\', $class), '/\\'),
                'parameters' => []
            ];

            foreach($service->getElementsByTagName('parameter') as $parameter) {

                $name = $parameter->getAttribute('name');
                $value = $this->parseAttributeValue(trim($parameter->getAttribute('value')));

                if(!$name) {
                    throw new ConfigException(sprintf("A parameter for service '%s' has no name.", $id));
                }

                $services[$id]['parameters'][$name] = $value;
            }
        }

        return $services;
    }
}