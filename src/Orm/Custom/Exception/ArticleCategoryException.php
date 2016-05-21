<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Orm\Custom\Exception;

class ArticleCategoryException extends \Exception {
	const ARTICLECATEGORY_NOT_SAVED						= 1;
	const ARTICLECATEGORY_NOT_NESTED					= 2;
	const ARTICLECATEGORY_DOES_NOT_EXIST				= 3;
	const ARTICLECATEGORY_SORT_CALLBACK_NOT_CALLABLE	= 4;
}