<?php

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