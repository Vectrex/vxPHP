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

class Vxpdo implements XmlParserInterface
{
    /**
     * @param \DOMNode $node
     * @return \StdClass[]
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): array
    {
        $vxpdo = [];

        foreach($node->getElementsByTagName('datasource') as $datasource) {

            $name = $datasource->getAttribute('name') ?: 'default';

            if(array_key_exists($name,  $vxpdo)) {
                throw new ConfigException(sprintf("Datasource '%s' declared twice.", $name));
            }

            $config = [
                'driver' => null,
                'dsn' => null,
                'host' => null,
                'port' => null,
                'user' => null,
                'password' => null,
                'dbname' => null,
            ];

            foreach($datasource->childNodes as $childNode) {

                if($childNode->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                if(array_key_exists($childNode->nodeName, $config)) {
                    $config[$childNode->nodeName] = trim($childNode->nodeValue);
                }
            }

            $vxpdo[$name] = (object) $config;
        }

        return $vxpdo;
    }
}