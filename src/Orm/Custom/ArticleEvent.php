<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Orm\Custom;

use vxPHP\Observer\Event;
use vxPHP\Observer\PublisherInterface;

class ArticleEvent extends Event {
	
	const BEFORE_ARTICLE_SAVE		= 'ArticleEvent.beforeArticleSave';
	const AFTER_ARTICLE_SAVE		= 'ArticleEvent.afterArticleSave';
	const BEFORE_ARTICLE_DELETE		= 'ArticleEvent.beforeArticleDelete';
	const AFTER_ARTICLE_DELETE		= 'ArticleEvent.afterArticleDelete';

	public function __construct($eventName, PublisherInterface $publisher) {
	
		// optional event type specific stuff happens here

		parent::__construct ($eventName, $publisher);

	}
	
	
}