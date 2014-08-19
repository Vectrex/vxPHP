<?php

namespace vxPHP\File\Exception;

class MetaFolderException extends \Exception {
	const METAFOLDER_DOES_NOT_EXIST	= 1;
	const METAFOLDER_ALREADY_EXISTS	= 2;
	const ID_OR_PATH_REQUIRED		= 3;
	const NO_ROOT_FOLDER_FOUND		= 4;
}

?>
