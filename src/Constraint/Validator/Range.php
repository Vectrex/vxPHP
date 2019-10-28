<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Constraint\Validator;


use vxPHP\Constraint\AbstractConstraint;
use vxPHP\Constraint\ConstraintInterface;

/**
 * check whether a float or numeric value is within a given range
 * if $options['exclusive'] is truthy the bounds are not considered
 * valid; the value is strictly checked to be a valid float or
 * integer or a string representing either; scientific notation is
 * not supported
 *
 * @version 0.1.0 2018-04-29
 * @author Gregor Kofler
 */
class Range extends AbstractConstraint
{

    /**
     * @var bool
     */
    private $exclusive;

    /**
     * @var float
     */
    private $min;

    /**
     * @var float
     */
    private $max;

    /**
     * Range constructor.
     * @param $min
     * @param $max
     * @param array $options
     */
    public function __construct($min, $max, $options = [])
    {
        $this->min = $min;
        $this->max = $max;
        $this->exclusive = array_key_exists('exclusive', $options) && (bool) $options['exclusive'];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \vxPHP\Constraint\ConstraintInterface::validate()
     */
    public function validate($value): bool
    {
        if(!(is_int($value) || is_float($value)) && !preg_match('/^[+-]?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/', $value)) {
            return false;
        }

        if($this->exclusive) {
            return ($value > $this->min) && ($value < $this->max);
        }

        return ($value >= $this->min) && ($value <= $this->max);
    }
}