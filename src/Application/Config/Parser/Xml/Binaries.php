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

use vxPHP\Application\Config\Parser\XmlParserInterface;
use vxPHP\Application\Exception\ConfigException;

class Binaries implements XmlParserInterface
{
    /**
     * @param \DOMNode $node
     * @return \stdClass
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): \stdClass
    {
        $binaries = new \stdClass;
        $xpath = new \DOMXPath($node->ownerDocument);
        $e = $xpath->query('executables', $node);

        if($e->length) {

            $p = $e->item(0)?->getElementsByTagName('path');

            if($p === null) {
                throw new ConfigException('Malformed "site.ini.xml"! Missing path for binaries.');
            }

            $binaries->path = rtrim($p->item(0)->nodeValue, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            foreach($e->item(0)?->getElementsByTagName('executable') as $v) {

                if(!($id = $v->getAttribute('id'))) {
                    throw new ConfigException('Binary without id found.');
                }

                foreach($v->attributes as $attr) {
                    $binaries->executables[$id][$attr->nodeName] = $attr->nodeValue;
                }
            }
        }

        return $binaries;
    }
}