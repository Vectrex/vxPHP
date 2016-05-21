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

class ArticleException extends \Exception {
	const ARTICLE_DOES_NOT_EXIST	= 1;
	const ARTICLE_HEADLINE_NOT_SET	= 2;
	const ARTICLE_CATEGORY_NOT_SET	= 3;
}
