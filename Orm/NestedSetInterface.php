<?php
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
