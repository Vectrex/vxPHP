<?php

namespace vxPHP\Util;

/**
 * Collection of regular expressions used througout the framework
 * class merely provides "namespace"
 * 
 * @version 0.2.4 2011-09-01
 * @author Gregor Kofler
 */

/*
			'!'.
            '(([^:/?#"<>]+):)?'. // 2. Scheme
            '(//([^/?#"<>]*))?'. // 4. Authority
            '([^?#"<>]*)'.       // 5. Path
            '(\?([^#"<>]*))?'.   // 7. Query
            '(#([^"<>]*))?'.     // 8. Fragment
            '!';
*/

$prot	= '((?:https?|ftp):\/\/)'		; // protocol
$host	= '([^\/:\'"<>\\x00-\\x20]+)'	; // server/host
$port	= '(:(\d{1,5}))?'				; // port
$ruri	= '(\/[^#\'"<>\\x00-\\x20]*)?'	; // request uri
$ploc	= '(\#([^\'"<>\\x00-\\x20]*))?'	; // location in web page

define('tmp_REX_URI_PROT',		"$prot$host$port$ruri$ploc");
define('tmp_REX_URI', 			"\s*(|$prot?$host$port$ruri$ploc)\s*");

$qtext			= '[^\\x0d\\x22\\x5c\\x80-\\xff]';
$dtext			= '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
$atom			= '[^\\x00-\\x20"(),.:;<>@\\x5b-\\x5d\\x7f-\\xff]+';
//$atom			= '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]';
$atom_umlaut	= '(?:[^\\x00-\\x20"(),.:;<>@\\x5b-\\x5d\\x7f-\\xff]|[äöüÄÖÜ])+';
$quoted_pair	= '\\x5c[\\x00-\\x7f]';
	
$domain_literal	= "\\x5b(?:$dtext|$quoted_pair)*\\x5d";
$quoted_string	= "\\x22(?:$qtext|$quoted_pair)*\\x22";
$domain_ref		= $atom_umlaut;
$sub_domain		= "(?:$domain_ref|$domain_literal)";
$word			= "(?:$atom|$quoted_string)";

//now a two-part domain identifier is required (not conforming to RFC822)
$domain			= "$sub_domain(?:\\x2e$sub_domain)+";	// "$sub_domain(\\x2e$sub_domain)*"

//capturing parantheses added
$local_part		= "$word(?:\\x2e$word)*";

define('tmp_REX_EMAIL', "($local_part)@($domain)");

class Rex {
	// numeric
	const INT_EXCL_NULL				= '/^[1-9]{1}[0-9]*$/';
	const INT						= '/^[0-9]+$/';
	const INT_EXCL_NULL_AND_NEW		= '/^(?:[1-9]{1}[0-9]*|new)$/i';
	const SIGNED_INT				= '/^-?[0-9]+$/';
	const EMPTY_OR_INT				= '/^[0-9]*$/';
	const EMPTY_OR_INT_EXCL_NULL	= '/^(|[1-9]{1}[0-9]*)$/';
	const FLOAT						= '/^[0-9]+((,|\.){1}[0-9]+)?$/';
	const EMPTY_OR_FLOAT			= '/^(|([0-9]+((,|\.){1}[0-9]+)?))$/';
	const NOT_EMPTY_TEXT			= '/\w+/';
	const DECIMAL					= '/^(\+|-)?([1-9]\d{0,2}(\.\d{3})*(,\d+)?|[1-9]\d{0,2}((\'|,)\d{3})*(\.\d+)?|\d+([,.]\d+)?)$/';
	const EMPTY_OR_DECIMAL			= '/^(|(\+|-)?([1-9]\d{0,2}(\.\d{3})*(,\d+)?|[1-9]\d{0,2}((\'|,)\d{3})*(\.\d+)?|\d+([,.]\d+)?))$/';

	// date and time
	const DATE_DE					= '/^((((31\.(0?[13578]|1[02]))|((29|30)\.(0?[1,3-9]|1[0-2])))\.(1[6-9]|[2-9]\d)?\d{2})|(29\.0?2\.(((1[6-9]|[2-9]\d)?(0[48]|[2468][048]|[13579][26])|((16|[2468][048]|[3579][26])00))))|(0?[1-9]|1\d|2[0-8])\.((0?[1-9])|(1[0-2]))\.((1[6-9]|[2-9]\d)?\d{2}))$/';
	const HOUR_MIN					= '/^(2[0-3]|[0-1]?\d):[0-5]?\d$/';
	const HOUR_MIN_SEC				= '/^(2[0-3]|[0-1]?\d):[0-5]?\d:[0-5]?\d$/';

	// other
	const MD5_HASH					= '/^[0-9a-f]{32}$/i';
	const URI						= tmp_REX_URI;
	const URI_STRICT				= tmp_REX_URI_PROT;
//	const URI						= '\s*(|(https?):\/\/)(\w+[\w\-]*\.)+([a-z]{2,6}){1}(\/{1}([\w%.-~]+([?]{1}[\w\-=&%]+)?)?)*\s*'
//	const URI_STRICT				= '(https?:\/\/)((\w+[\w\-]*\.)+([a-z]{2,6}){1}(\/{1}([\w%.-~]+([?]{1}[\w\-=&%]+)?)?)*)';
	const EMAIL						= tmp_REX_EMAIL;
	const ALIAS_OR_INT				= '/^([1-9]{1}[0-9]*|[a-z0-9_]+)$/i';
	const IMAGE_MIMETYPE			= '~^image/(png|gif|jpeg)$~';
}
?>