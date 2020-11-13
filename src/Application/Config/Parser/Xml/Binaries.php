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
use vxPHP\Application\Config\Parser\XmlParserInterface;
use vxPHP\Application\Exception\ConfigException;

class Binaries implements XmlParserInterface
{
    /**
     * @var bool
     */
    private $isLocalhost;

    public function __construct(Config $config)
    {
        $this->isLocalhost = $config->isLocalhost;
    }

    /**
     * @param \DOMNode $node
     * @return \stdClass
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): \stdClass
    {
        $binaries = new \stdClass;

        $context = $this->isLocalhost ? 'local' : 'remote';

        $xpath = new \DOMXPath($node->ownerDocument);

        $e = $xpath->query("executables[@context='$context']", $node);

        if(!$e->length) {
            $e = $xpath->query('executables', $node);
        }

        if($e->length) {

            $p = $e->item(0)->getElementsByTagName('path');

            if(empty($p)) {
                throw new ConfigException('Malformed "site.ini.xml"! Missing path for binaries.');
            }

            $binaries->path = rtrim($p->item(0)->nodeValue, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            foreach($e->item(0)->getElementsByTagName('executable') as $v) {

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