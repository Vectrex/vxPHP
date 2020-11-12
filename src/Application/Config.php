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

use vxPHP\Application\Exception\ConfigException;
use vxPHP\Webpage\Menu\Menu;

/**
 * Config
 * creates a configuration singleton by parsing an XML configuration
 * file
 *
 * @version 3.0.0 2020-07-07
 */
class Config {
	/**
	 * @var \stdClass
	 */
	public $site;

	/**
	 * db settings
	 * will be replaced by vxpdo settings
	 *
	 * @deprecated
	 * @var \stdClass
	 */
	public $db;

	/**
	 * vxpdo settings
	 *
	 * @var array
	 */
	public $vxpdo;

	/**
	 * @var \stdClass
	 */
	public $mail;

	/**
	 * @var \stdClass
	 */
	public $binaries;

	/**
	 * @var array
	 */
	public $paths;

	/**
	 * @var array
	 */
	public $routes;

	/**
	 * @var Menu[]
	 */
	public $menus;

	/**
	 * @var array
	 */
	public $server;

	/**
	 * @var array
	 *
	 * holds configuration of services
	 */
	public $services;

	/**
	 * @var array
	 *
	 * holds all configured plugins (event subscribers)
	 */
	public	$plugins;

	/**
	 * @var \stdClass
	 *
	 * holds configuration for templating
	 */
	public	$templating;

	/**
	 * @var boolean
	 */
	public $isLocalhost;

	/**
	 * holds sections of config file which are parsed
	 *
 	 * @var array
	 */
	private	$sections;

	/**
	 * a list of already processed XML files
	 * any XML file can only be parsed once
	 * avoids circular references but (currently) also disallows the
	 * re-use of XML "snippets"
	 *
	 * @var array
	 */
	private $parsedXmlFiles = [];
	/**
	 * create config instance
	 * if section is specified, only certain sections of the config file are parsed
	 *
	 * @param string $xmlFile
	 * @param array $sections
	 * @throws ConfigException
	 */
	public function __construct($xmlFile, array $sections = [])
    {
		$this->sections	= $sections;
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
	private function dumpXmlErrors($xmlFile): void
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
	private function includeIncludes(\DOMDocument $doc, $filepath): void
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
	 * @throws ConfigException
	 * @return void
	 */
	private function parseConfig(\DOMDocument $config): void
    {
		try {

			// determine server context, missing SERVER_ADDR assumes localhost/CLI

			$this->isLocalhost = Application::runsLocally();

			$rootNode = $config->firstChild;

            $sections = [];

            // collect all top-level node names and allow parsing of specific sections

			foreach($rootNode->childNodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE && (empty($this->sections) || in_array($node->nodeName, $this->sections, true))) {
                    if(!array_key_exists($node->nodeName, $sections)) {
                        $sections[$node->nodeName] = [];
                    }
                    $sections[$node->nodeName][] = $node;
                }
            }

			$namespace = __NAMESPACE__ . '\\Config\\Parser\\Xml\\';

			$keys = array_keys($sections);
			$sections = array_values($sections);
			$faultySections = [];

			while($section = array_shift($keys)) {
			    $nodes = array_shift($sections);

                $className = $namespace . ucfirst(preg_replace_callback('/_([a-z])/', static function($match) { return strtoupper($match[1]); }, $section));
                $parser = new $className($this);

                /**
                 * work around deprecated pages configuration
                 */
                if($section === 'pages') {
                    $section = 'routes';
                }

                foreach($nodes as $node) {
                    try {
                        $result = $parser->parse($node);
                    }
                    catch(\RuntimeException $e) {
                        /*
                         * a RuntimeException may occur when a certain section
                         * requires other already parsed sections. In this case
                         * a section is queued at the end again; if this section encounters
                         * a RuntimeException a second time the parsing is cancelled to
                         * avoid a potentially endless loop (e.g. parsing menus without any
                         * routes configured)
                         */
                        if(!in_array($section, $faultySections, true)) {
                            $faultySections[] = $section;
                            $keys[] = $section;
                            $sections[] = $nodes;
                            break;
                        }
                        throw new \RuntimeException($e->getMessage());
                    }

                    if($result instanceof \stdClass) {
                        $this->$section = $result;
                    }
                    if(is_array($result)) {
                        $this->$section = array_merge($this->$section ?? [], $result);
                    }
                }
			}
		}

		catch(ConfigException $e) {
			throw $e;
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
	public function getPaths($access = 'rw'): array
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
