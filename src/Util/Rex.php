<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Util;

/**
 * Collection of regular expressions used througout the framework
 * class merely provides "namespace"
 *
 * @version 0.2.8 2025-01-13
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

// URI

$prot = '((?:https?|ftp):\/\/)'; // protocol
$host = '([^\/:\'"<>\\x00-\\x20]+)'; // server/host
$port = '(:(\d{1,5}))?'; // port
$ruri = '(\/[^#\'"<>\\x00-\\x20]*)?'; // request uri
$ploc = '(\#([^\'"<>\\x00-\\x20]*))?'; // location in web page

define('tmp_URI_PROT', '/^' . $prot . $host . $port . $ruri . $ploc . '$/');
define('tmp_EMPTY_OR_URI_PROT', '/^(?:|' . $prot . $host . $port . $ruri . $ploc . ')$/');
define('tmp_URI', '/^' . $prot . '?' . $host . $port . $ruri . $ploc . '$/');
define('tmp_EMPTY_OR_URI', '/^(?:|' . $prot . '?' . $host . $port . $ruri . $ploc . ')$/');

// Email

$qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
$dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
$atom = '[^\\x00-\\x20"(),.:;<>@\\x5b-\\x5d\\x7f-\\xff]+';
$atom_umlaut = '(?:[^\\x00-\\x20"(),.:;<>@\\x5b-\\x5d\\x7f-\\xff]|[äöüÄÖÜ])+';
$quoted_pair = '\\x5c[\\x00-\\x7f]';

$domain_literal = "\\x5b(?:$dtext|$quoted_pair)*\\x5d";
$quoted_string = "\\x22(?:$qtext|$quoted_pair)*\\x22";
$domain_ref = $atom_umlaut;
$sub_domain = "(?:$domain_ref|$domain_literal)";
$word = "(?:$atom|$quoted_string)";

//now a two-part domain identifier is required (not conforming to RFC822)
$domain = "$sub_domain(?:\\x2e$sub_domain)+";    // "$sub_domain(\\x2e$sub_domain)*"

//capturing parantheses added
$local_part = "$word(?:\\x2e$word)*";

// put everything together
define('tmp_EMAIL', '/^(' . $local_part . ')@(' . $domain . ')$/');
define('tmp_EMPTY_OR_EMAIL', '/^(?:|(' . $local_part . ')@(' . $domain . '))$/');

// date

$dateDe = '((((31\.(0?[13578]|1[02]))|((29|30)\.(0?[1,3-9]|1[0-2])))\.(1[6-9]|[2-9]\d)?\d{2})|(29\.0?2\.(((1[6-9]|[2-9]\d)?(0[48]|[2468][048]|[13579][26])|((16|[2468][048]|[3579][26])00))))|(0?[1-9]|1\d|2[0-8])\.((0?[1-9])|(1[0-2]))\.((1[6-9]|[2-9]\d)?\d{2}))';
$dateIso = '(((19|20)([2468][048]|[13579][26]|0[48])|2000)-02-29|((19|20)[0-9]{2}-(0[469]|11)-(0[1-9]|[12][0-9]|30)|(19|20)[0-9]{2}-(0[13578]|1[02])-(0[1-9]|[12][0-9]|3[01])|(19|20)[0-9]{2}-02-(0[1-9]|1[0-9]|2[0-8])))';

define('tmp_DATE_DE', '/^' . $dateDe . '$/');
define('tmp_DATE_ISO', '/^' . $dateIso . '$/');
define('tmp_EMPTY_OR_DATE_DE', '/^(?:|' . $dateDe . ')$/');
define('tmp_EMPTY_OR_DATE_ISO', '/^(?:|' . $dateIso . ')$/');

class Rex
{
    // numeric
    public const string INT_EXCL_NULL_AND_NEW = '/^(?:[1-9][0-9]*|new)$/i';
    public const string INT_EXCL_NULL = '/^[1-9][0-9]*$/';
    public const string EMPTY_OR_INT_EXCL_NULL = '/^(?:|[1-9][0-9]*)$/';
    public const string INT = '/^[0-9]+$/';
    public const string EMPTY_OR_INT = '/^[0-9]*$/';
    public const string SIGNED_INT = '/^-?[0-9]+$/';
    public const string EMPY_OR_SIGNED_INT = '/^(?:|-?[0-9]+)$/';
    public const string FLOAT = '/^[0-9]+(?:[,\.][0-9]+)?$/';
    public const string EMPTY_OR_FLOAT = '/^(?:|[0-9]+(?:[,\.][0-9]+)?)$/';
    public const string DECIMAL = '/^[+-]?([1-9]\d{0,2}(\.\d{3})*(,\d+)?|[1-9]\d{0,2}((\'|,)\d{3})*(\.\d+)?|\d+([,\.]\d+)?)$/';
    public const string EMPTY_OR_DECIMAL = '/^(|[+-]?([1-9]\d{0,2}(\.\d{3})*(,\d+)?|[1-9]\d{0,2}((\'|,)\d{3})*(\.\d+)?|\d+([,\.]\d+)?))$/';

    // date and time

    // matches [d]d.[m]m.yyyy including leap years from 1600-01-01 to 2099-12-31
    public const string DATE_DE = tmp_DATE_DE;
    public const string EMPTY_OR_DATE_DE = tmp_EMPTY_OR_DATE_DE;

    // matches yyyy-mm-dd including leap years from 1900-01-01 to 2099-12-31
    public const string DATE_ISO = tmp_DATE_ISO;
    public const string EMPTY_OR_DATE_ISO = tmp_EMPTY_OR_DATE_ISO;

    // matches [h]h:[m]m between 00:00 and 23:59
    public const string HOUR_MIN = '/^(?:2[0-3]|[0-1]?\d):[0-5]?\d$/';
    public const string EMPTY_OR_HOUR_MIN = '/^(?:|(?:2[0-3]|[0-1]?\d):[0-5]?\d)$/';

    // matches [h]h:[m]m:[s]s between 00:00:00 and 23:59:59
    public const string HOUR_MIN_SEC = '/^(?:2[0-3]|[0-1]?\d):[0-5]?\d:[0-5]?\d$/';
    public const string EMPTY_OR_HOUR_MIN_SEC = '/^(?:|(?:2[0-3]|[0-1]?\d):[0-5]?\d:[0-5]?\d)$/';

    // URL and email

    // will ignore the protocol part
    public const string URI = tmp_URI;
    public const string EMPTY_OR_URI = tmp_EMPTY_OR_URI;

    // will require a protocol
    public const string URI_STRICT = tmp_URI_PROT;
    public const string EMPTY_OR_URI_STRICT = tmp_EMPTY_OR_URI_PROT;

    public const string EMAIL = tmp_EMAIL;
    public const string EMPTY_OR_EMAIL = tmp_EMPTY_OR_EMAIL;

    //	public const string URI						= '\s*(|(https?):\/\/)(\w+[\w\-]*\.)+([a-z]{2,6}){1}(\/{1}([\w%.-~]+([?]{1}[\w\-=&%]+)?)?)*\s*'
    //	public const string URI_STRICT				= '(https?:\/\/)((\w+[\w\-]*\.)+([a-z]{2,6}){1}(\/{1}([\w%.-~]+([?]{1}[\w\-=&%]+)?)?)*)';

    // other

    public const string NOT_EMPTY_TEXT = '/\w+/';
    public const string MD5_HASH = '/^[0-9a-fA-F]{32}$/';
    public const string IMAGE_MIMETYPE = '~^image/(?:png|gif|jpeg|webp)$~';
    public const string ALIAS_OR_INT = '/^(?:[1-9][0-9]*|[A-Za-z0-9_]+)$/';
}
