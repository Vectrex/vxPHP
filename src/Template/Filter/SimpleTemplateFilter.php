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
 * @version 0.1.2 2026-06-11
 */
abstract class SimpleTemplateFilter
{
    public function __construct(protected array $parameters = [])  {}

    public static function create(array $parameters = []): SimpleTemplateFilter
    {
        return new static($parameters);
    }

    abstract public function apply(&$templateString);
}
