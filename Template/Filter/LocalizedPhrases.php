<?php

namespace vxPHP\Template\Filter;

use vxPHP\Application\Application;

/**
 * parses local page locale expressions
 * {!word} becomes lookup value of locale.terms
 *
 * @author Gregor Kofler
 *
 */
class LocalizedPhrases extends SimpleTemplateFilter implements SimpleTemplateFilterInterface {

	/**
	 * (non-PHPdoc)
	 *
	 * @see \vxPHP\SimpleTemplate\Filter\SimpleTemplateFilterInterface::parse()
	 *
	 */
	public function apply(&$templateString) {

		$application	= Application::getInstance();
		$config			= $application->getConfig();

		$locale = $application->getCurrentLocale();

		// without locale replace placeholders with their identifier

		if(empty($locale)) {

			$templateString = preg_replace(
				array(
					'@\{![a-z0-9_]+\}@i',
					'@\{![a-z0-9_]+:(.*?)\}@i'
				),
				array(
					'',
					'$1'
				),
				$templateString
			);
			return;

		}

		$this->getPhrases($locale);

		$templateString = preg_replace_callback(
			'@\{!([a-z0-9_]+)(:(.*?))?\}@i',
			array($this, 'translatePhraseCallback'),
			$templateString
		);
	}

	/**
	 * @todo locales handling in Application class
	 *
	 * @param unknown $matches
	 */
	private function translatePhraseCallback($matches) {

		return 'xxxx';

		if(!empty($GLOBALS['phrases'][$config->site->current_locale][$matches[1]])) {
			return $GLOBALS['phrases'][$config->site->current_locale][$matches[1]];
		}

		if(isset($matches[3])) {
			return $this->storePhrase($matches[3], $matches[1]);
		}
		else {
			return $this->storePhrase($matches[1]);
		}
	}

	private function getPhrases($locale) {

		if(
		!isset($GLOBALS['phrases'][$locale]) &&
		file_exists((defined('LOCALE_PATH') ? LOCALE_PATH : '').$locale.'.phrases')
		) {
			$GLOBALS['phrases'][$locale] = parse_ini_file(LOCALE_PATH.$locale.'.phrases');
		}
	}


}
