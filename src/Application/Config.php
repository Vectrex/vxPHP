<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Application;

use vxPHP\Application\Config\Parser\XmlParserInterface;
use vxPHP\Application\Exception\ConfigException;
use vxPHP\Webpage\Menu\Menu;

/**
 * Config
 * creates a configuration singleton by parsing an XML configuration
 * file
 *
 * @version 3.2.1 2021-12-01
 */
class Config {
	/**
	 * @var \stdClass|null
     */
	public ?\stdClass $site = null;

	/**
	 * db settings
	 * will be replaced by vxpdo settings
	 *
	 * @deprecated
	 * @var \stdClass|null
     */
	public ?\stdClass $db = null;

	/**
	 * vxpdo settings
	 *
	 * @var array|null
     */
	public ?array $vxpdo = null;

	/**
	 * @var \stdClass|null
     */
	public ?\stdClass $mail = null;

	/**
	 * @var \stdClass|null
     */
	public ?\stdClass $binaries = null;

	/**
	 * @var array|null
     */
	public ?array $paths = null;

	/**
	 * @var array|null
     */
	public ?array $routes = null;

	/**
	 * @var Menu[]
	 */
	public ?array $menus = null;

	/**
	 * @var array|null
     */
	public ?array $server = null;

	/**
	 * @var array|null
     *
	 * holds configuration of services
	 */
	public ?array $services = null;

	/**
	 * @var array|null
     *
	 * holds all configured plugins (event subscribers)
	 */
	public	?array $plugins = null;

	/**
	 * @var \stdClass|null
     *
	 * holds configuration for templating
	 */
	public	?\stdClass $templating = null;

	/**
	 * @var boolean
	 */
	public ?bool $isLocalhost = null;

    /**
     * @var array
     */
    private array $parserClasses = [];

	/**
	 * a list of already processed XML files
	 * any XML file can only be parsed once
	 * avoids circular references but (currently) also disallows the
	 * re-use of XML "snippets"
	 *
	 * @var array
	 */
	private array $parsedXmlFiles = [];

    /**
     * create config instance
     * possible options are currently
     * parsers: array of additional XmlParserInterfaces to parse custom config settings
     *
     * @param string $xmlFile
     * @param array $options
     * @throws ConfigException|\ReflectionException
     */
	public function __construct(string $xmlFile, array $options = [])
    {
		$xmlFile = realpath($xmlFile);

		$previousUseErrors = libxml_use_internal_errors(true);

		$config = new \DOMDocument();

		if(!$config->load($xmlFile, LIBXML_NOCDATA)) {
			$this->dumpXmlErrors($xmlFile);
			exit();
		}

		// skip any comments

		while($config->firstChild instanceof \DOMComment) {
			$config->removeChild($config->firstChild);
		}

		if('config' !== $config->firstChild->nodeName) {
			throw new ConfigException(sprintf("No 'config' root element found in %s.", $xmlFile));
		}

		// recursively add all includes to main document

		$this->includeIncludes($config, $xmlFile);

		// set up default parsers

        foreach (glob (__DIR__ . '/Config/Parser/Xml/*.php') as $filename) {
            $className = basename($filename, '.php');
            $sectionName = substr(preg_replace_callback('/([A-Z])/', static function ($match) { return '_' . strtolower($match[1]); }, $className), 1);
            $this->parserClasses[$sectionName] = __NAMESPACE__ . '\\Config\\Parser\\Xml\\' . $className;
        }

        // add custom parsers

        foreach ($options['parsers'] ?? [] as $section => $classname) {
            $reflectionClass = new \ReflectionClass($classname);

            if (!$reflectionClass->implementsInterface(XmlParserInterface::class)) {
                throw new \InvalidArgumentException(sprintf("Class '%s' does not implement XmlParserInterface.", $classname));
            }

            $this->parserClasses[strtolower($section)] = $classname;
        }

        $this->parseConfig($config);
		$this->getServerConfig();

		libxml_use_internal_errors($previousUseErrors);
	}

	/**
	 * Create formatted output of XML errors
	 *
	 * @param string $xmlFile
	 * @throws ConfigException
	 */
	private function dumpXmlErrors(string $xmlFile): void
    {
		$severity = [LIBXML_ERR_WARNING => 'Warning', LIBXML_ERR_ERROR => 'Error', LIBXML_ERR_FATAL => 'Fatal'];
		$errors = [];

		foreach(libxml_get_errors() as $error) {
			$errors[] = sprintf('Row %d, column %d: %s (%d) %s', $error->line, $error->column, $severity[$error->level], $error->code, $error->message);
		}

		throw new ConfigException(sprintf("Could not parse XML configuration in '%s'.\n\n%s", $xmlFile, implode("\n", $errors)));
	}

	/**
	 * recursively include XML files in include tags
	 * to avoid circular references any file can only be included once
	 *
	 * @param \DOMDocument $doc
	 * @param string $filepath
	 * @throws ConfigException
	 */
	private function includeIncludes(\DOMDocument $doc, string $filepath): void
    {
		$path = rtrim(dirname(realpath($filepath)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(in_array($filepath, $this->parsedXmlFiles, true)) {
			throw new ConfigException(sprintf('File %s has already been used.', $filepath));
		}

		$this->parsedXmlFiles[] = $filepath;

		$includes = (new \DOMXPath($doc))->query('//include');

		foreach($includes as $node) {

			if(!empty($node->nodeValue)) {

				// load file

				$include = new \DOMDocument();

				if(!$include->load($path . $node->nodeValue, LIBXML_NOCDATA)) {

					$this->dumpXmlErrors($path . $node->nodeValue);
					exit();

				}

				//  recursively insert includes

				$this->includeIncludes($include, $path . $node->nodeValue);

				// import root node and descendants of include
				
				foreach($include->childNodes as $childNode) {

					if($childNode instanceOf \DOMComment) {
						continue;
					}

					$importedNode = $doc->importNode($childNode, true);
					break;

				}
				
				// check whether included file groups several elements under a config element

				if('config' !== $importedNode->nodeName) {

					// replace include element with imported root element

					$node->parentNode->replaceChild($importedNode, $node);

				}

				else {
					
					// append all child elements of imported root element
					
					while($importedNode->firstChild) {
						
						$node->parentNode->insertBefore($importedNode->firstChild, $node);
						
					}

					// delete include element
					
					$node->parentNode->removeChild($node);
				}
			}
		}
	}

	/**
	 * iterates through the top level nodes (sections) of the config
	 * file; if a method matching the section name is found this method
	 * is called to parse the section
	 *
	 * @param \DOMDocument $config
     * @return void
	 */
	private function parseConfig(\DOMDocument $config): void
    {
        // determine server context, missing SERVER_ADDR assumes localhost/CLI

        $this->isLocalhost = Application::runsLocally();

        $rootNode = $config->firstChild;

        $sections = [];

        // collect all top-level node names and allow parsing of specific sections

        foreach($rootNode->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                if(!array_key_exists($node->nodeName, $sections)) {
                    $sections[$node->nodeName] = [];
                }
                $sections[$node->nodeName][] = $node;
            }
        }

        $keys = array_keys($sections);
        $sections = array_values($sections);
        $faultySections = [];

        while($section = array_shift($keys)) {

            $nodes = array_shift($sections);

            if (array_key_exists($section, $this->parserClasses)) {
                $parser = new $this->parserClasses[$section]($this);

                foreach ($nodes as $node) {
                    try {
                        $result = $parser->parse($node);
                    } catch (\RuntimeException $e) {
                        /*
                        * a RuntimeException may occur when a certain section
                        * requires other already parsed sections. In this case
                        * a section is queued at the end again; if this section encounters
                        * a RuntimeException a second time the parsing is cancelled to
                        * avoid a potentially endless loop (e.g. parsing menus without any
                        * routes configured)
                        */
                        if (!in_array($section, $faultySections, true)) {
                            $faultySections[] = $section;
                            $keys[] = $section;
                            $sections[] = $nodes;
                            break;
                        }
                       throw new \RuntimeException($e->getMessage());
                    }

                    /**
                    * work around deprecated pages configuration and merge pages with routes
                    */
                    if($section === 'pages') {
                        $section = 'routes';
                    }
                    if ($result instanceof \stdClass) {
                        $this->$section = $result;
                    }
                    if (is_array($result)) {
                        $this->$section = array_merge($this->$section ?? [], $result);
                    }
                }
            }
        }
    }

	/**
	 * create constants for simple access to certain configuration settings
	 */
	public function createConst(): void
    {
		$properties = get_object_vars($this);

		if(isset($properties['db'])) {
			foreach($properties['db'] as $k => $v) {
				if(is_scalar($v)) {
					$k = strtoupper($k);
					if(!defined("DB$k")) { define("DB$k", $v); }
				}
			}
		}

		if(isset($properties['site'])) {
			foreach($properties['site'] as $k => $v) {
				if(is_scalar($v)) {
					$k = strtoupper($k);
					if(!defined($k)) { define($k, $v); }
				}
			}
		}

		if(isset($properties['paths'])) {
			foreach($properties['paths'] as $k => $v) {
				$k = strtoupper($k);
				if(!defined($k)) { define($k, $v['subdir']); }
			}
		}

		$locale = localeconv();

		foreach($locale as $k => $v) {
			$k = strtoupper($k);
			if(!defined($k) && !is_array($v)) { define($k, $v); }
		}
	}

	/**
	 * returns all paths matching access criteria
	 *
	 * @param string $access
	 * @return array
	 */
	public function getPaths(string $access = 'rw'): array
    {
		$paths = [];
		foreach($this->paths as $p) {
			if($p['access'] === $access) {
				$paths[] = $p;
			}
		}
		return $paths;
	}

	/**
	 * add particular information regarding server configuration, like PHP extensions
	 */
	private function getServerConfig(): void
    {
		$this->server['apc_on'] = extension_loaded('apc') && function_exists('apc_add') && ini_get('apc.enabled') && ini_get('apc.rfc1867');

		$fs = ini_get('upload_max_filesize');
		$suffix = strtoupper(substr($fs, -1));
		switch($suffix) {
			case 'K':
				$mult = 1024; break;
			case 'M':
				$mult = 1024*1024; break;
			case 'G':
				$mult = 1024*1024*1024; break;
			default:
				$mult = 0;
		}

		$this->server['max_upload_filesize'] = $mult ? (float) (substr($fs, 0, -1)) * $mult : (int) $fs;
	}
}
