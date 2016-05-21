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
 * Represents an Accept-* header.
 *
 * An accept header is compound with a list of items,
 * sorted by descending quality.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class AcceptHeader {

	/**
	 * @var AcceptHeaderItem[]
	 */
	private $items = array();

	/**
	 * @var bool
	 */
	private $sorted = TRUE;

	/**
	 * Constructor.
	 *
	 * @param AcceptHeaderItem[] $items
	 */
	public function __construct(array $items) {

		foreach ($items as $item) {
			$this->add($item);
		}

	}

	/**
	 * Builds an AcceptHeader instance from a string.
	 *
	 * @param string $headerValue
	 *
	 * @return AcceptHeader
	 */
	public static function fromString($headerValue) {

		$index = 0;

		return new self(array_map(
			function ($itemValue) use (&$index) {
				$item = AcceptHeaderItem::fromString($itemValue);
				$item->setIndex($index++);
				return $item;
			},
			preg_split('/\s*(?:,*("[^"]+"),*|,*(\'[^\']+\'),*|,+)\s*/',
				$headerValue,
				0,
				PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
		)));
	}

	/**
	 * Returns header value's string representation.
	 *
	 * @return string
	 */
	public function __toString() {

		return implode(',', $this->items);

	}

	/**
	 * Tests if header has given value.
	 *
	 * @param string $value
	 *
	 * @return Boolean
	 */
	public function has($value) {

		return isset($this->items[$value]);

	}

	/**
	 * Returns given value's item, if exists.
	 *
	 * @param string $value
	 *
	 * @return AcceptHeaderItem|NULL
	 */
	public function get($value) {

		return isset($this->items[$value]) ? $this->items[$value] : NULL;

	}

	/**
	 * Adds an item.
	 *
	 * @param AcceptHeaderItem $item
	 *
	 * @return AcceptHeader
	 */
	public function add(AcceptHeaderItem $item) {

		$this->items[$item->getValue()] = $item;
		$this->sorted = FALSE;

		return $this;

	}

	/**
	 * Returns all items.
	 *
	 * @return AcceptHeaderItem[]
	 */
	public function all() {

		$this->sort();

		return $this->items;

	}

	/**
	 * Filters items on their value using given regex.
	 *
	 * @param string $pattern
	 *
	 * @return AcceptHeader
	 */
	public function filter($pattern) {

		return new self(array_filter($this->items, function (AcceptHeaderItem $item) use ($pattern) {
			return preg_match($pattern, $item->getValue());
		}));
	}

	/**
	 * Returns first item.
	 *
	 * @return AcceptHeaderItem|NULL
	 */
	public function first() {

		$this->sort();

		return !empty($this->items) ? reset($this->items) : NULL;

	}

	/**
	 * Sorts items by descending quality
	 */
	private function sort() {

		if (!$this->sorted) {
			uasort($this->items, function ($a, $b) {
				$qA = $a->getQuality();
				$qB = $b->getQuality();

				if ($qA === $qB) {
					return $a->getIndex() > $b->getIndex() ? 1 : -1;
				}

				return $qA > $qB ? -1 : 1;
			});

			$this->sorted = TRUE;
		}
	}
}
