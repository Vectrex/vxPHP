<?php
/*
if (!@include_once __DIR__ . '/../vendor/autoload.php') {
	if (!@include_once __DIR__ . '/../../../autoload.php') {
		trigger_error("Unable to load dependencies", E_USER_ERROR);
	}
}
*/

require_once __DIR__ . '/../src/Autoload/Psr4.php';

$loader = new vxPHP\Autoload\Psr4();
$loader->register();

$loader->addPrefix('vxPHP', __DIR__ . '/../src');
$loader->addPrefix('vxPHP\\Tests', __DIR__ . '/../tests');
