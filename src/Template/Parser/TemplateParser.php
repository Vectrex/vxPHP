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

abstract class TemplateParser implements ParserInterface {
	private $source;

	public function setSource($source) {
		$this->source = $source;
	}

	abstract public function parse();
}
