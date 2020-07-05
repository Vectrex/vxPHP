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

class MailSettings implements XmlParserInterface
{
    /**
     * @param \DOMNode $node
     * @return \StdClass
     * @throws ConfigException
     */
    public function parse(\DOMNode $node): \StdClass
    {
        $mail = new \StdClass;

        if(($mailer = $node->getElementsByTagName('mailer')->item(0))) {
            $mail->mailer = new \stdClass;

            if(!($class = $mailer->getAttribute('class'))) {
                throw new ConfigException('No mailer class specified.');
            }
            $mail->mailer->class = $class;

            foreach($mailer->childNodes as $childNode) {
                if($childNode->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }

                $mail->mailer->{$node->nodeName} = trim($node->nodeValue);
            }
        }

        return $mail;
    }
}