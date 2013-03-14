<?php

namespace vxPHP\Orm\Custom\Exception;

class ArticleException extends \Exception {
	const ARTICLE_DOES_NOT_EXIST	= 1;
	const ARTICLE_HEADLINE_NOT_SET	= 2;
	const ARTICLE_CATEGORY_NOT_SET	= 3;
}
