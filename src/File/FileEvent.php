<?php

namespace vxPHP\File;

use vxPHP\Observer\Event;
use vxPHP\Observer\PublisherInterface;

class FileEvent extends Event {
	
	const AFTER_METAFILE_CREATE		= 'FileEvent.afterMetafileCreate';
	const BEFORE_METAFILE_DELETE	= 'FileEvent.beforeMetafileDelete';

	public function __construct($eventName, PublisherInterface $publisher) {
	
		// optional event type specific stuff happens here

		parent::__construct ($eventName, $publisher);

	}
	
	
}