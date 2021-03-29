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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Application\Application;
use vxPHP\Template\Filter\SimpleTemplateFilterInterface;
use vxPHP\Template\Filter\ImageCache;
use vxPHP\Template\Filter\AnchorHref;
use vxPHP\Template\Filter\LocalizedPhrases;
use vxPHP\Template\Filter\Spaceless;

/**
 * A simple templating system
 *
 * @author Gregor Kofler
 * @version 2.3.1 2021-03-29
 *
 */

class SimpleTemplate
{
    /**
     * template buffers which hold a part of the template each
     *
     * @var TemplateBuffer
     */
	private $bufferInstance;

    /**
     * the raw string of the template contents
     * will split into one or more template buffer instances
     *
     * @var string
     */
    private $rawContents;

    /**
     * associative array of blocks
     *
     * @var array
     */
    private $blocks;

    /**
     * @var string
     *
     * the processed template string
     */
	private $contents;

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
	protected const EXTEND_REX = '~<!--\s*{\s*extend:\s*([\w./-]+)\s*@\s*([\w-]+)\s*}\s*-->~';

    /**
     * the regular expression format to search for "block" directives
     */
    protected const BLOCK_REX_FORMAT = '~<!--\s*{\s*block\s*:\s*%s\s*}\s*-->~';

    /**
     * initialize template based on $file
     * if $file is omitted the content can be set with setRawContents() later
     *
     * @param string $file
     * @throws SimpleTemplateException
     * @throws ApplicationException
     */
	public function __construct($file = null)
    {
        $this->bufferInstance = new TemplateBuffer();

        $this->defaultFilters = [
            strtolower(AnchorHref::class) => new AnchorHref(),
            strtolower(ImageCache::class) => new ImageCache(),
            strtolower(LocalizedPhrases::class) => new LocalizedPhrases(),
            strtolower(Spaceless::class) => new Spaceless()
            // new AssetsPath()
        ];

		if ($file) {
		    if (file_exists($file)) {
                $this->setRawContents(file_get_contents($file));
            }
		    else {
                $application = Application::getInstance();
                $path = $application->getRootPath() . (defined('TPL_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(TPL_PATH, '/')) : '');

                if (!file_exists($path . $file)) {
                    throw new SimpleTemplateException(sprintf("Template file '%s' does not exist.", $path . $file), SimpleTemplateException::TEMPLATE_FILE_DOES_NOT_EXIST);
                }

                $this->setRawContents(file_get_contents($path . $file));
            }
		}
	}

    /**
     * static method to allow method chaining
     *
     * @param string $file
     * @return SimpleTemplate
     * @throws SimpleTemplateException
     * @throws ApplicationException
     */
	public static function create($file = null): self
    {
		return new static($file);
	}

    /**
     * set or overwrite the raw contents of the template
     *
     * @param string $contents
     * @return SimpleTemplate
     * @throws SimpleTemplateException
     */
	public function setRawContents(string $contents): self
    {
        $this->rawContents = $contents;
        $this->extractBlocks ();
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
	public function containsPHP(): bool
    {
		return 1 === preg_match('~<\?(?:=|php\s|\s)~', $this->rawContents);
	}

	/**
	 * return the plain file content of template file
	 *
	 * @return string
	 */
	public function getRawContents(): string
    {
        return $this->rawContents;
	}

    /**
     * get filename of parent template
     *
     * @return string
     */
	public function getParentTemplateFilename(): ?string
    {
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
	public function insertTemplateAt(SimpleTemplate $childTemplate, string $blockName): self
    {
		$blockRegExp = sprintf(self::BLOCK_REX_FORMAT, $blockName);

		if(preg_match($blockRegExp, $this->rawContents)) {
			$this->setRawContents(preg_replace($blockRegExp, $childTemplate->getRawContents(), $this->rawContents));
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
	public function assign($var, $value = null): self
    {
		if(is_array($var)) {
			foreach($var as $k => $v) {
			    if(false === preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $k)) {
			        throw new SimpleTemplateException("Invalid property name '%s'", $k);
                }

			    if(in_array($k, TemplateBuffer::INVALID_PROPERTIES, true)) {
			        throw new SimpleTemplateException("Tried to assign invalid property '%s'", $k);
                }
				$this->bufferInstance->$k = $v;
			}

			return $this;
		}

        if(false === preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $var)) {
            throw new SimpleTemplateException("Invalid property name '%s'", $var);
        }
        if(in_array($var, TemplateBuffer::INVALID_PROPERTIES, true)) {
            throw new SimpleTemplateException("Tried to assign invalid property '%s'", $var);
        }

		$this->bufferInstance->$var = $value;

		return $this;
	}

    /**
     * works as SimpleTemplate::assign() but will escape the value
     * before assigning it; will only handle values that can
     * be converted to strings
     *
     * @param string | array $var
     * @param mixed $value
     * @return $this
     * @throws SimpleTemplateException
     */
	public function assignString($var, $value = null): self
    {
        if(is_array($var)) {
            foreach($var as $k => $v) {
                if(false === preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $k)) {
                    throw new SimpleTemplateException("Invalid property name '%s'", $k);
                }
                if(in_array($k, TemplateBuffer::INVALID_PROPERTIES, true)) {
                    throw new SimpleTemplateException("Tried to assign invalid property '%s'", $k);
                }
                if(is_scalar($v)) {
                    $this->bufferInstance->$k = htmlspecialchars((string) $v);
                }
                else if(is_object($v) && method_exists($v, '__toString')) {
                    $this->bufferInstance->$k = htmlspecialchars($v->__toString());
                }
                else {
                    throw new SimpleTemplateException(sprintf("String value can not be evaluated for property '%s'", $k));
                }
            }
            return $this;
        }

        if(false === preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $var)) {
            throw new SimpleTemplateException("Invalid property name '%s'", $var);
        }
        if(in_array($var, TemplateBuffer::INVALID_PROPERTIES, true)) {
            throw new SimpleTemplateException("Tried to assign invalid property '%s'", $var);
        }
        if(is_scalar($value)) {
            $this->bufferInstance->$var = htmlspecialchars((string) $value);
        }
        else if(is_object($value) && method_exists($value, '__toString')) {
            $this->bufferInstance->$var = htmlspecialchars($value->__toString());
        }
        else {
            throw new SimpleTemplateException(sprintf("String value can not be evaluated for property '%s'.", $var));
        }

        return $this;
    }

    /**
     * appends filter to filter queue
     *
     * @param SimpleTemplateFilterInterface $filter
     * @return SimpleTemplate
     * @throws SimpleTemplateException
     */
	public function addFilter(SimpleTemplateFilterInterface $filter): self
    {
        if(array_key_exists(strtolower(get_class($filter)), $this->defaultFilters)) {
            throw new SimpleTemplateException(sprintf("Filter class '%s' is already configured as default filter.", get_class($filter)));
        }

        $this->filters[strtolower(get_class($filter))] = $filter;
		return $this;
	}

    /**
     * block a pre-configured filter
     *
     * @param string $filterId
     * @return $this
     */
	public function blockFilter(string $filterId): self
    {
	    if(!in_array($filterId, $this->blockedFilters, true)) {
	        $this->blockedFilters[] = $filterId;
        }

        return $this;
    }

    /**
     * output parsed template
     *
     * if an array of filters is passed it will replace any previously
     * configured or added filters including the default filters
     *
     * @param SimpleTemplateFilterInterface []
     * @return string
     * @throws SimpleTemplateException
     * @throws ApplicationException
     */
	public function display(array $filters = null): string
    {
		$this->extend();
		$this->fillBuffer();

		if(null !== $filters) {

		    // won't check for duplicate filters

		    foreach($filters as $filter) {
		        if(!$filter instanceof SimpleTemplateFilterInterface) {
		            throw new SimpleTemplateException('Tried to apply a filter which does not implement the SimpleTemplateFilterInterface.');
                }
            }

            $this->applyFilters($filters);
		    $this->removeEmptyBlocks();
            return $this->contents;
		}

		// get default filters and added filters

        $filtersToApply = array_merge($this->defaultFilters, $this->filters);

        // add configured filters

        try {
            $templatingConfig = Application::getInstance()->getConfig()->templating;
        }
		catch (ApplicationException $e) {
		    $templatingConfig = null;
        }

        if($templatingConfig) {
            foreach($templatingConfig->filters as $id => $filter) {

                if(!in_array($id, $this->blockedFilters, true)) {

                    // load class file

                    $instance = new $filter['class']();

                    // check whether instance implements FilterInterface

                    if (!$instance instanceof SimpleTemplateFilterInterface) {
                        throw new SimpleTemplateException(sprintf("Template filter '%s' (class %s) does not implement the SimpleTemplateFilterInterface.", $id, get_class($filter)));
                    }

                    if(array_key_exists(strtolower(get_class($instance)), $filtersToApply)) {
                        throw new SimpleTemplateException("Template filter '%s' (class %s) has been already set.", $id, get_class($filter));
                    }

                    $filtersToApply[strtolower(get_class($instance))] = $instance;
                }
            }
        }

        $this->applyFilters($filtersToApply);
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
     * @throws ApplicationException
     */
	private function extend(): void
    {
        if ($this->parentTemplateFilename) {
            if (file_exists($this->parentTemplateFilename)) {
                $path = $this->parentTemplateFilename;
            }
            else {
                $path = Application::getInstance()->getRootPath() . (defined('TPL_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(TPL_PATH, '/')) : '') . $this->parentTemplateFilename;

                if (!file_exists($path)) {
                    throw new SimpleTemplateException(sprintf("Parent template file '%s' not assigned.", $path), SimpleTemplateException::TEMPLATE_FILE_DOES_NOT_EXIST);
                }
            }

            $this->contents = file_get_contents($path);

            foreach ($this->blocks as $blockName => $markup) {
                $blockRegExp = '~<!--\s*\{\s*block\s*:\s*' . $blockName . '\s*\}\s*-->~';

                if (preg_match($blockRegExp, $this->contents)) {
                    $this->contents = preg_replace($blockRegExp, $markup, $this->contents);
                }
            }
        }
        else {
            $this->contents = implode('', $this->blocks);
        }
	}

    /**
     * apply filters to template before output
     *
     * @param SimpleTemplateFilterInterface[] $filters
     */
	private function applyFilters (array $filters): void
    {
        foreach ($filters as $filter) {
            $filter->apply($this->contents);
        }
	}

    /**
     * remove any orphaned <!-- { block: ... } --> comments
     */
	private function removeEmptyBlocks (): void
    {
        $this->contents = preg_replace ('~<!--\s*{\s*block\s*:\s*.*?\s*}\s*-->~', '', $this->contents);
    }

	/**
	 * fetches template file and evaluates content
	 * immediate output suppressed by output buffering
	 */
	private function fillBuffer(): void
    {
	    // wrap bufferInstance in closure to allow the use of $this in template

        $this->bufferInstance->__rawContents = $this->contents;

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

    /**
     * extract all blocks of the template
     * all blocks must point to the same parent template
     *
     * @throws SimpleTemplateException
     */
	private function extractBlocks (): void
    {
        preg_match_all(self::EXTEND_REX, ltrim($this->rawContents), $matches, PREG_OFFSET_CAPTURE);

        // blocks got identified

        if ($matches[0]) {

            // first extend is preceeded by chars

            if ($matches[0][0][1]) {
                throw new SimpleTemplateException('First extend directive preceeded by non-whitespace characters.');
            }

            // check for single parent template name

            if (count(array_unique(array_column($matches[1], 0))) !== 1) {
                throw new SimpleTemplateException('No support of multiple parent templates.');
            }

            $this->parentTemplateFilename = $matches[1][0][0];

            $blocks = array_filter(preg_split (self::EXTEND_REX, $this->rawContents), 'trim');

            if (count($matches[2]) !== count($blocks)) {
                throw new SimpleTemplateException('Mismatch of block markers and block contents. Block contents must not be empty.');
            }

            $this->blocks = array_combine(array_column($matches[2], 0), array_map('trim', $blocks));
        }

        // no blocks

        else {
            $this->blocks = [trim($this->rawContents)];
        }
    }
}