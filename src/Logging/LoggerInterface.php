<?php

namespace vxPHP\Logging;

interface LoggerInterface {
	public function setLogFile($path);
	public function writeLogEntry();
}