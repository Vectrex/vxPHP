<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Template;

use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Application\Application;
use vxPHP\Template\Filter\SimpleTemplateFilterInterface;
use vxPHP\Template\Filter\ImageCache;
use vxPHP\Template\Filter\AnchorHref;
use vxPHP\Template\Filter\LocalizedPhrases;
use vxPHP\Application\Locale\Locale;

/**
 * A simple templating system
 *
 * @author Gregor Kofler
 * @version 2.0.1 2019-01-12
 *
 */

class SimpleTemplate {

    /**
     * the actual PHP content which will be passed
     * to the output buffer
     *
     * @var TemplateBuffer
     */
	private $bufferInstance;

    /**
     * @var string
     *
     * the processed template string
     */
	private $contents;

    /**
     * @var Locale
     */
	private $locale;
	
    /**
     * store for added custom filters with addFilter()
     *
     * @var SimpleTemplateFilterInterface []
     */
	private $filters = [];
	
    /**
     * keeps instances of pre-configured filters
     * will be applied before any added custom filters
     *
     * @var SimpleTemplateFilterInterface []
     */
	private $defaultFilters;

    /**
     * ids of blocked filters
     * allows turning of configured filters temporarily
     *
     * @var array
     */
    private $blockedFilters = [];

    /**
     * @var boolean
     */
	private $ignoreLocales;

    /**
     * name of a parent template found in <!-- extend: ... -->
     *
     * @var string
     */
	private $parentTemplateFilename;

    /**
     * the regular expression employed to search for an "extend@..." directive
     *
     * @var string
     */
	private $extendRex = '~<!--\s*\{\s*extend:\s*([\w./-]+)\s*@\s*([\w-]+)\s*\}\s*-->~';

    /**
     * initialize template based on $file
     * if $file is omitted the content can be set with setRawContents() later
     *
     * @param string $file
     * @throws SimpleTemplateException
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public function __construct($file = null) {

		$application = Application::getInstance();
        $this->bufferInstance = new TemplateBuffer();

		if($file) {
			$path = $application->getRootPath() . (defined('TPL_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(TPL_PATH, '/')) : '');

			if (!file_exists($path . $file)) {
				throw new SimpleTemplateException(sprintf("Template file '%s' does not exist.", $path . $file), SimpleTemplateException::TEMPLATE_FILE_DOES_NOT_EXIST);
			}

			$this->setRawContents(file_get_contents($path . $file));

		}
		
		$this->locale = $application->getCurrentLocale();

	}

    /**
     * static method to allow method chaining
     *
     * @param string $file
     * @return SimpleTemplate
     * @throws SimpleTemplateException
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public static function create($file = null) {

		return new static($file);

	}

	/**
	 * set or overwrite the raw contents of the template
	 * 
	 * @param string $contents
	 * @return SimpleTemplate
	 */
	public function setRawContents($contents) {

		$this->bufferInstance->__rawContents = (string) $contents;
		return $this;

	}
	
	/**
	 * check whether template file contains PHP code
	 * does this by searching for opening PHP tags:
	 * <? <?= <?php
	 * no checks for ASP notation are applied 
	 *
	 * @return boolean
	 */
	public function containsPHP() {

		return 1 === preg_match('~\<\?(?:=|php\s|\s)~', $this->bufferInstance->__rawContents);

	}

	/**
	 * return the plain file content of template file
	 *
	 * @return string
	 */
	public function getRawContents() {

		return $this->bufferInstance->__rawContents;

	}

    /**
     * get filename of parent template
     *
     * @return string
     */
	public function getParentTemplateFilename() {

		if(empty($this->parentTemplateFilename)) {
		
			if(preg_match($this->extendRex, $this->bufferInstance->__rawContents, $matches)) {

				$this->parentTemplateFilename = $matches[1];

			}
		}

		return $this->parentTemplateFilename;
		
	}

    /**
     * explicitly insert template at $blockName position
     *
     * @param SimpleTemplate $childTemplate
     * @param string $blockName
     * @return SimpleTemplate
     * @throws SimpleTemplateException
     */
	public function insertTemplateAt(SimpleTemplate $childTemplate, $blockName) {

		$blockRegExp = '~<!--\s*\{\s*block\s*:\s*' . $blockName . '\s*\}\s*-->~';

		if(preg_match($blockRegExp, $this->bufferInstance->__rawContents)) {

			$this->bufferInstance->__rawContents = preg_replace($blockRegExp, $childTemplate->getRawContents(), $this->bufferInstance->__rawContents);

		}

		else {
			throw new SimpleTemplateException(sprintf("Could not insert child template at '%s'.", $blockName), SimpleTemplateException::TEMPLATE_INVALID_NESTING);
		}

		return $this;
	}

    /**
     * assign value to variable, which is then available within template
     *
     * @param string | array $var
     * @param mixed $value
     * @return SimpleTemplate
     * @throws SimpleTemplateException
     */
	public function assign($var, $value = null) {

        $invalidProperties = ['__rawContents'];

		if(is_array($var)) {
			foreach($var as $k => $v) {
			    if(in_array($k, $invalidProperties)) {
			        throw new SimpleTemplateException("Tried to assign invalid property '%s'", $k);
                }
				$this->bufferInstance->$k = $v;
			}

			return $this;
		}

        if(in_array($var, $invalidProperties)) {
            throw new SimpleTemplateException("Tried to assign invalid property '%s'", $var);
        }

		$this->bufferInstance->$var = $value;

		return $this;
	}

	/**
	 * appends filter to filter queue
	 *
	 * @param SimpleTemplateFilterInterface $filter
	 * @return SimpleTemplate
	 */
	public function addFilter(SimpleTemplateFilterInterface $filter) {

		array_push($this->filters, $filter);

		return $this;

	}

    /**
     * block a pre-configured filter
     *
     * @param string $filterId
     * @return $this
     */
	public function blockFilter($filterId) {

	    if(!in_array($filterId, $this->blockedFilters)) {
	        $this->blockedFilters[] = $filterId;
        }

        return $this;

    }

    /**
     * output parsed template
     *
     * @param SimpleTemplateFilterInterface []
     * @return string
     * @throws SimpleTemplateException
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public function display($defaultFilters = null) {

		$this->extend();

		$this->fillBuffer();

		// check whether pre-configured filters are already in place
		
		if(is_null($defaultFilters)) {

			// add default filters

			$this->defaultFilters = [
				new AnchorHref(),
				new ImageCache(),
				// new AssetsPath()
			];

			if(!$this->ignoreLocales) {
                $this->defaultFilters[] = new LocalizedPhrases();
			}

		}

		else {

		    $this->defaultFilters = $defaultFilters;

            if(!$this->ignoreLocales) {

                // check whether adding a localization filter is necessary

                $found = false;

                foreach($this->defaultFilters as $filter) {

                    if($filter instanceof LocalizedPhrases) {
                        $found = true;
                        break;
                    }

                }

                if(!$found) {
                    $this->defaultFilters[] = new LocalizedPhrases();
                }
            }

        }

        // add configured filters

        if($templatingConfig = Application::getInstance()->getConfig()->templating) {

            foreach($templatingConfig->filters as $id => $filter) {

                if(!in_array($id, $this->blockedFilters)) {

                    // load class file

                    $instance = new $filter['class']();

                    // check whether instance implements FilterInterface

                    if (!$instance instanceof SimpleTemplateFilterInterface) {
                        throw new SimpleTemplateException(sprintf("Template filter '%s' (class %s) does not implement the SimpleTemplateFilterInterface.", $id, $filter['class']));
                    }

                    $this->defaultFilters[] = $instance;

                }
            }
        }

        $this->applyFilters();

		return $this->contents;
	}

    /**
     * allow extension of a parent template with current template
     *
     * searches in current rawContents for
     * <!-- { extend: parent_template.php @ content_block } -->
     * and in template to extend for
     * <!-- { block: content_block } -->
     *
     * current rawContents is then replaced by parent rawContents with current rawContents filled in
     *
     * @throws SimpleTemplateException
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	private function extend() {

		if(preg_match($this->extendRex, $this->bufferInstance->__rawContents, $matches)) {

			$blockRegExp = '~<!--\s*\{\s*block\s*:\s*' . $matches[2] . '\s*\}\s*-->~';

			$extendedContent = file_get_contents(Application::getInstance()->getRootPath() . (defined('TPL_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(TPL_PATH, '/')) : '') . $matches[1]);

			if(preg_match($blockRegExp, $extendedContent)) {

				$this->bufferInstance->__rawContents = preg_replace(
					$blockRegExp,
					preg_replace(
						$this->extendRex,
						'',
						$this->bufferInstance->__rawContents
					),
					$extendedContent
				);

			}

			else {
				throw new SimpleTemplateException("Could not extend with '{$matches[1]}' at '{$matches[2]}'.", SimpleTemplateException::TEMPLATE_INVALID_NESTING);
			}
		}
	}

	/**
	 * applies all stacked filters to template before output
	 */
	private function applyFilters() {

		// handle default and pre-configured filters first

		foreach($this->defaultFilters as $f) {
			$f->apply($this->contents);
		}

		// handle added custom filters last

		foreach($this->filters as $f) {
			$f->apply($this->contents);
		}

	}

	/**
	 * fetches template file and evals content
	 * immediate output supressed by output buffering
	 */
	private function fillBuffer() {

	    // wrap bufferInstance in closure to allow the use of $this in template

	    $closure = function($outer) {

            ob_start();

            /* @deprecated use $this when accessing assigned variables */

            $tpl = $this;

            eval('?>' . $this->__rawContents);
            $outer->contents = ob_get_contents();
            ob_end_clean();

        };

	    $boundClosure = $closure->bindTo($this->bufferInstance);
	    $boundClosure($this);

	}

}
