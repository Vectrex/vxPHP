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

use vxPHP\Application\Exception\ConfigException;

class PluginsSettings implements XmlParserInterface
{
    /**
     * @param \DOMNode $node
     * @return array
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): array
    {
        $plugins = [];

        foreach($node->getElementsByTagName('plugin') as $plugin) {

            if(!($id = $plugin->getAttribute('id'))) {
                throw new ConfigException('Plugin without id found.');
            }

            if(isset($plugins[$id])) {
                throw new ConfigException(sprintf("Plugin '%s' has already been defined.", $id));
            }

            if(!($class = $plugin->getAttribute('class'))) {
                throw new ConfigException(sprintf("No class for plugin '%s' configured.", $id));
            }

            // store parsed information

            $plugins[$id] = [

                // clean path delimiters, prepend leading backslash, and replace slashes with backslashes

                'class' => '\\' . ltrim(str_replace('/', '\\', $class), '/\\'),
                'parameters' => []
            ];

            foreach($plugin->getElementsByTagName('parameter') as $parameter) {

                $name = $parameter->getAttribute('name');
                $value = $parameter->getAttribute('value');

                if(!$name) {
                    throw new ConfigException(sprintf("A parameter for plugin '%s' has no name.", $id));
                }

                $plugins[$id]['parameters'][$name] = $value;
            }
        }

        return $plugins;
    }
}