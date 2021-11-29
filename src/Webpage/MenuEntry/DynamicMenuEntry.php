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
	public function __construct (string $path, array $attributes = [])
    {
		parent::__construct ($path, $attributes);
	}

	public function setPath ($path): self
    {
		$this->path = $path;
		return $this;
	}

	public function setAttributes (array $attributes): self
    {
		foreach($attributes as $attr => $value) {
			$this->attributes->$attr = $value;
		}
		return $this;
	}
}
