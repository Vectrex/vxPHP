<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * with minor adaptations lifted from Symfony's HttpFoundation classes
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */

namespace vxPHP\Http;

/**
 * Represents an Accept-* header item.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class AcceptHeaderItem
{
    private string $value;
    private float $quality = 1.0;
    private int $index = 0;
    private array $attributes = [];

    public function __construct(string $value, array $attributes = [])
    {
        $this->value = $value;
        foreach ($attributes as $name => $v) {
            $this->setAttribute($name, $v);
        }
    }

    /**
     * Builds an AcceptHeaderInstance instance from a string.
     *
     * @param string $itemValue
     *
     * @return self
     */
    public static function fromString(string $itemValue): self
    {
        $parts = HeaderUtils::split($itemValue, ';=');

        $part = array_shift($parts);
        $attributes = HeaderUtils::combine($parts);

        return new self($part[0], $attributes);
    }

    /**
     * Returns header value's string representation.
     *
     * @return string
     */
    public function __toString()
    {
        $string = $this->value.($this->quality < 1 ? ';q='.$this->quality : '');
        if (\count($this->attributes) > 0) {
            $string .= '; '.HeaderUtils::toString($this->attributes, ';');
        }

        return $string;
    }

    /**
     * Set the item value.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Returns the item value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Set the item quality.
     *
     * @param float $quality
     *
     * @return $this
     */
    public function setQuality(float $quality): self
    {
        $this->quality = $quality;

        return $this;
    }

    /**
     * Returns the item quality.
     *
     * @return float
     */
    public function getQuality(): float
    {
        return $this->quality;
    }

    /**
     * Set the item index.
     *
     * @param int $index
     *
     * @return $this
     */
    public function setIndex(int $index): self
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Returns the item index.
     *
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Tests if an attribute exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Returns an attribute by its name.
     *
     * @param string $name
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Returns all attributes.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set an attribute.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return $this
     */
    public function setAttribute(string $name, mixed $value): self
    {
        if ('q' === $name) {
            $this->quality = (float) $value;
        } else {
            $this->attributes[$name] = (string) $value;
        }

        return $this;
    }
}
