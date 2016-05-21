<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Orm;

interface NestedSetInterface {
	public function getFirstChild();
	public function getLastChild();
	public function getNextSibling();
	public function getPreviousSibling();
	public function getParent();
	public function getChildren();
	public function getDescendants();
	public function getAncestors();
	public function getLevel();
	public function isRoot();
	public function isLeaf();
	public function hasChildren();
	public function countChildren();
	public function hasSiblings();

	public static function getRootInstance();
}
