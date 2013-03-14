<?php

namespace vxPHP\File\Exception;

class FilesystemFileException extends \Exception {
	const	FILE_DOES_NOT_EXIST			= 1;
	const	FILE_RENAME_FAILED			= 2;
	const	FILE_DELETE_FAILED			= 3;
	const	METAFILE_CREATION_FAILED	= 4;
	const	METAFILE_ALREADY_EXISTS		= 5;
}
