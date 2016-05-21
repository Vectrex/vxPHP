<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Template\Parser;

use vxPHP\Template\Parser\TemplateParser;

class HtmlTemplateParser extends TemplateParser {

	public function parse() {
		return $this->source;
	}
}
