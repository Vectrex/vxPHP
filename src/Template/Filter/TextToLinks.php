<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Template\Filter;

use vxPHP\Util\Rex;
use vxPHP\Application\Application;

/**
 * URLs in http://subdomain.domain.tld form and emails are wrapped in anchor tags
 * emails are slightly "obfuscated"
 *
 * @author Gregor Kofler
 */
class TextToLinks extends SimpleTemplateFilter implements SimpleTemplateFilterInterface
{
    /**
     * indicates, whether the protocol is displayed in link texts
     *
     * @var boolean
     */
	private	$showProtocol;

    /**
     * current encoding of web site, returned by Application
     *
     * @var string
     */
    private $encoding;

    /**
     * (non-PHPdoc)
     *
     * @param $templateString
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @see SimpleTemplateFilterInterface::apply()
     */
	public function apply(&$templateString): void
    {
		$this->encoding = strtoupper(Application::getInstance()->getConfig()->site->default_encoding);

		$templateString = preg_replace_callback(
			'~(^|\s|>|(?:<a [^>]*?>.*?))'.Rex::URI_STRICT.'(<|\s|$)~i',
			array($this, 'urlAnchors'),
			$templateString
		);

		$templateString = preg_replace_callback(
			'~(<a [^>]*?>.*?|)('.Rex::EMAIL.')([^<]*</a>|)~i',
			array($this, 'obfuscatedMailAnchors'),
			$templateString
		);
	}

	/**
	 * enable or disable display of protocol in link texts
	 *
	 * @param boolean $showProtocol
	 */
	public function setShowProtocol($showProtocol)
    {
		$this->showProtocol = $showProtocol;
	}

	private function urlAnchors($matches)
    {
		if(substr($matches[1], 0, 2) == '<a') {
			return $matches[0];
		}

		return
			$matches[1] .
			'<a class="link_http" href="' . $matches[2] . $matches[3] . $matches[6] . '">' .
			($this->showProtocol ? $matches[2] : '') . $matches[3] . $matches[6] .
			'</a>' .
			$matches[9];
	}

	private function obfuscatedMailAnchors($matches)
    {
		if($matches[1] !== '' || $matches[5] !== '') {
			return $matches[0];
		}

		$pref = 'mailto:';
		$text = '';
		$href = '';

		$len = strlen($pref);

		for($i = 0; $i < $len; ++$i) {
			$href .= random_int(0, 1) ? '&#x'.dechex(ord($pref[$i])).';' : '&#'.ord($pref[$i]).';';
		}

		$len = mb_strlen($matches[2], $this->encoding);

		for($i = 0; $i < $len; ++$i) {
			$t = mb_substr($matches[2], $i, 1, $this->encoding);
			if(ord($t) > 127) {
				$text .= $t;
			}
			else {
				$text .= random_int(0, 1) ? '&#x'.dechex(ord($t)).';' : '&#'.ord($t).';';
			}
		}
		$href .= $text;

		return '<a href="' . $href . '">' . $text . '</a>';
	}
}