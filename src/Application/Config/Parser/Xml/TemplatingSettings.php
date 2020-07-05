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

class TemplatingSettings implements XmlParserInterface
{
    /**
     * @param \DOMNode $node
     * @return \StdClass
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): \StdClass
    {
        $templating = new \StdClass;
        $templating->filters = [];

        $xpath = new \DOMXPath($node->ownerDocument);

        foreach($xpath->query("filters/filter", $node) as $filter) {

            $id = $filter->getAttribute('id');
            $class = $filter->getAttribute('class');

            if(!$id) {
                throw new ConfigException('Templating filter without id found.');
            }

            if(!$class)	{
                throw new ConfigException(sprintf("No class for templating filter '%s' configured.", $id));
            }

            if(isset($templating->filters[$id])) {
                throw new ConfigException(sprintf("Templating filter '%s' has already been defined.", $id));
            }

            // clean path delimiters, prepend leading backslash, and replace slashes with backslashes

            $class = '\\' . ltrim(str_replace('/', '\\', $class), '/\\');

            // store parsed information

            $templating->filters[$id] = [
                'class' => $class,
                'parameters' => $filter->getAttribute('parameters')
            ];
        }

        return $templating;
    }
}