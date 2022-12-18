<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Template\Filter;

/**
 * @author Gregor Kofler
 * @version 0.1.1 2022-11-25
 */
abstract class SimpleTemplateFilter
{
    /**
     * @var array
     */
    protected array $parameters;

	public function __construct($parameters = [])
    {
	    $this->parameters = $parameters;
	}

	public static function create(array $paramters = []): SimpleTemplateFilter
    {
		return new static($paramters);
	}

	abstract public function apply(&$templateString);
}
