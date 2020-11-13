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

/**
 * Class Db
 * @package vxPHP\Application\Config\Parser\Xml
 * @deprecated replaced by Vxpdo
 */
class Db implements XmlParserInterface
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
     * @return \StdClass
     */
    public function parse(\DOMNode $node): \stdClass
    {
        $db = new \stdClass();

        $context = $this->isLocalhost ? 'local' : 'remote';
        $xpath = new \DOMXPath($node->ownerDocument);

        $d = $xpath->query("db_connection[@context='$context']", $node);

        if(!$d->length) {
            $d = $node->getElementsByTagName('db_connection');
        }

        if($d->length) {
            foreach($d->item(0)->childNodes as $childNode) {

                if($childNode->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                $v = trim($childNode->nodeValue);
                $k = $childNode->nodeName;

                $db->$k = $v;
            }
        }

        return $db;
    }
}