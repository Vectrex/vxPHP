<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Webpage\MenuEntry;

class DynamicMenuEntry extends MenuEntry
{
	public function __construct($path, $attributes)
    {
		parent::__construct($path, $attributes);
	}

	public function setPath($path): DynamicMenuEntry
    {
		$this->path = $path;
		return $this;
	}

	public function setAttributes(Array $attributes): DynamicMenuEntry
    {
		foreach($attributes as $attr => $value) {
			$this->attributes->$attr = $value;
		}
		return $this;
	}
}
