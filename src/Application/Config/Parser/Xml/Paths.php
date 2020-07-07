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

class Paths implements XmlParserInterface
{

    /**
     * @param \DOMNode $node
     * @return array
     */
    public function parse(\DOMNode $node): array
    {
        $paths = [];

        foreach($node->getElementsByTagName('path') as $path) {

            $id = $path->getAttribute('id');
            $subdir = $path->getAttribute('subdir') ?: '';

            if(!$id || !$subdir) {
                continue;
            }

            $subdir = '/' . trim($subdir, '/') . '/';

            // additional attributes are currently ignored

            $paths[$id] = ['subdir' => $subdir];
        }

        return $paths;
    }
}