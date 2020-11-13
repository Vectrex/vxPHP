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

class Site implements XmlParserInterface
{

    /**
     * @param \DOMNode $node
     * @return \StdClass
     */
    public function parse(\DOMNode $node): \StdClass
    {
        $site = new \StdClass;

        foreach($node->childNodes as $childNode) {

            if($childNode->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $v = trim($childNode->nodeValue);
            $k = $childNode->nodeName;

            if ('locales' === $k) {
                if (!isset($site->locales)) {
                    $site->locales = [];
                }

                foreach ($childNode->getElementsByTagName('locale') as $locale) {
                    $loc = $locale->getAttribute('value');
                    if ($loc && !in_array($loc, $site->locales, true)) {
                        $site->locales[] = $loc;
                    }
                    if ($loc && $locale->getAttribute('default') === '1') {
                        $site->default_locale = $loc;
                    }
                }
            } else {
                $site->$k = $v;
            }
        }

        return $site;
    }
}