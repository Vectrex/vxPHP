<?php
/**
 * html
 * simple class for generating HTML tags
 * 
 * @version 1.3.0, 2007-09-15 
 */

define ('EXTERNALLINKGFX', IMG_SITE_PATH.'external.gif');

if(!defined('REX_EMAIL')) {
	$qtext			= '[^\\x0d\\x22\\x5c\\x80-\\xff]';
	$dtext			= '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
	$atom			= '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+';
	$quoted_pair	= '\\x5c[\\x00-\\x7f]';
	
	$domain_literal	= "\\x5b($dtext|$quoted_pair)*\\x5d";
	$quoted_string	= "\\x22($qtext|$quoted_pair)*\\x22";
	$domain_ref		= $atom;
	$sub_domain		= "($domain_ref|$domain_literal)";
	$word			= "($atom|$quoted_string)";
	$domain			= "$sub_domain(\\x2e$sub_domain)*";
	$local_part		= "$word(\\x2e$word)*";
	$addr_spec		= "$local_part\\x40$domain";

	define('REX_EMAIL', $addr_spec);
}

if(!defined('REX_URI')) {
	define('REX_URI', '\s*(|(http|https):\/\/)(\w{1,}[\w\-]{0,}\.){1,}([a-z]{2,6}){1}(\/{1}([\w%.-]{1,}([?]{1}[\w\-=&%]{1,}){0,1}){0,1}){0,}\s*');
}

class html {
	/**
	 * Image-Tag erzeugen
	 * @param string src Quelldatei
	 * @param string alt ALT-Attribut-Text
	 * @param string title TITLE-Attribut-Text
	 * @param string class CSS-Klasse
	 * @param boolean timestamp optionaler Parameter an der Source, der Reload erzwingt
	 */
	static function img($src, $alt = null, $title = null, $class = null, $timestamp = false) {
		if(empty($alt)) {
			$alt = explode('.', basename($alt));
			array_pop($alt);
			$alt = implode('.', $alt);
		}
		$html = '<img src="'.$src.($timestamp ? '?'.filemtime($src) : '').'" alt="'.$alt.'"';
		$html .= empty($title) ? '' : ' title="'.$title.'"';
		$html .= empty($class) ? '>' : ' class="'.$class.'">';
		return $html;
	}

	/**
	 * Anchor-Tag erzeugen
	 * @param string link URI oder relativer Link
	 * @param string text Text des Links
	 * @param string img Image, welches als Link dient
	 * @param string class CSS-Klasse
	 * @param string miscstr frei belegbarer String f�r weitere Attribute, Event-Handler
	 */
	static function a($link, $text = '', $img = '', $class = false, $miscstr = false) {
		if (empty($link)) { return false; }

		if(self::checkMail($link)) {
			$enc = 'mailto:';
			$len = strlen($link);
			for($i = 0; $i < $len; $i++) {
				$enc .= rand(0,1) ? '&#x'.dechex(ord($link[$i])).';' : '&#'.ord($link[$i]).';';
			}
			$link = $enc;
		}
		else {
			if(defined('USE_MOD_REWRITE') && USE_MOD_REWRITE) {
				$link = self::link2Canonical($link);
			}
			$link = htmlspecialchars($link);
		}

		$text =		($text == '' && $img == '') ? preg_replace('/^(\s*(ftp:\/\/|http(s?):\/\/|mailto:))/i', '', $link) : $text;

		$htmlSrc =	self::checkExternal($link) ? '<img src="'.EXTERNALLINKGFX.'" alt="">&nbsp;' : '';
		$htmlSrc .=	'<a '.($class ? 'class="'.$class.'" ' : '').'href="'.$link.'"';
		$htmlSrc .=	$miscstr ? ' '.$miscstr.'>' : '>';
		$htmlSrc .=	$img != '' ? '<img src="'.$img.'" alt="'.$text.'">' : $text;
		$htmlSrc .=	'</a>';
		return $htmlSrc;
	}

	/**
	 * Schema:
	 * {language}/{page}/{mode}:{id}
	 */
	static function link2Canonical ($link) {
		return preg_replace(
			array(
				'/[&\?]{1}page=(\w+)/i',
				'/[&\?]{1}mode=(\w+)/i',
				'/[&\?]{1}id=(\d+)/i'),
			array(
				'/page_$1',
				'/$1',
				'~$1'),
			$link);
	}

	static function checkExternal($link) {
		return preg_match('/^(\s*(ftp:\/\/|http(s?):\/\/))/i', $link);
	}

	static function checkMail($link) {
		return preg_match('!^'.REX_EMAIL.'$!', $link);
	}
	
	static function checkUri($link) {
		return preg_match('!^'.REX_URI.'$!', $link);
	}

	static function text2link($text) {
		$rexHtml = '(http:\/\/|https:\/\/|ftp:\/\/|ftps:\/\/)([a-z]{1,}[\w\-]{0,}\.){1,}([a-z]{2,6}){1}(\/{1}[\w\.\-%]{1,}([?]{1}[\w\-=&%]{1,}){0,1}){0,}';
		$text = preg_replace(array('/(?<!\{)'.$rexHtml.'(?![|}])/i', '/'.REX_EMAIL.'/'), array("<a href='$0'>$0</a>", "<a href='mailto:$0'>$0</a>"), $text);
		$text = preg_replace('/\{(https?:\/\/.+)\|(.+)\}/i', "<a href='$1'>$2</a>", $text);
		return $text;
	}
	
	static function text2tags($text) {
		if(!defined('REX_PSEUDO_LI')) {
			define('REX_PSEUDO_LI', '(^|\n)\*{1}\s+([\w]+.*?)(?=\r?\n)');
		}
		if(!defined('REX_PSEUDO_UL')) {
			define('REX_PSEUDO_UL', '((\<li\>.*?\<\/li\>(\r?\n)*)+)');
		}
		$t = preg_replace('/'.REX_PSEUDO_LI.'/i', '<li>$2</li>', $text);

		return preg_replace('/'.REX_PSEUDO_UL.'/i', '<ul>$0</ul>', $t);
	}
	
	static function placeholder2link($text) {
		$rex = '\{\s*file_([\w-.]+\.(jpg|gif|png))\s*\|\s*([\w\s����,-.]+)\s*\}';
		$str = preg_replace('/'.$rex.'/i', '<img src="'.FILES_MEDIA_PATH.'$1" alt="$3">', $text);

		$rex = '\{\s*file_([\w-.]+)\s*\|\s*([\w\s����,-.]+)\s*\}';
		$str = preg_replace('/'.$rex.'/i', '<a href="'.FILES_MEDIA_PATH.'$1">$2</a>', $str);

		$rex = '\{\s*([a-z]+)_(\d+)\s*\|\s*([\w\s����,-.]+)\s*\}';
		return preg_replace('/'.$rex.'/i', '<a href="?page=$1&amp;id=$2">$3</a>', $str);
	}
	
	static function highlightText($text, $keyword) {
		return str_replace($keyword, '<span class="highlight">'.$keyword.'</span>', $text);
	}

	static function parseTemplateLinks(&$text) {
		if(empty($text)) { return; }
/*		$this->contents = preg_replace(
			array(
				'/<a(.*?)\s+href=("|\')\$([a-z0-9_.-]+)(.*?)\2(.*?)>/i',
				'/<img(.*?)\s+src=("|\')\$([a-z0-9_.-]+)\2(.*?)>/i'
			),
			array(
				"<a$1 href=$2".ROOT_DOCUMENT."?page=$3$4$2$5>",
				"<img$1 src=$2".IMG_SITE_PATH."$3$2$4>",
			),
			$this->contents);
*/
		$text = preg_replace_callback(
			'/<a(.*?)\s+href=("|\')\$([a-z0-9_.-]+)(.*?)\2(.*?)>/i',
			array('self', 'parseCallbackA'),
			$text);
		$text = preg_replace_callback(
			'/<img(.*?)\s+src=("|\')\$([a-z0-9_.-]+)\2(.*?)>/i',
			array('self', 'parseCallbackImg'),
			$text);
	}
	
	static function parseCallbackA($matches) {
		return "<a{$matches[1]} href={$matches[2]}".ROOT_DOCUMENT."?page={$matches[3]}{$matches[4]}{$matches[2]}{$matches[5]}>";
	}
	static function parseCallbackImg($matches) {
		return "<img{$matches[1]} src={$matches[2]}".IMG_SITE_PATH."{$matches[3]}{$matches[2]}{$matches[4]}>";
	}
}
?>