<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Debug;

use vxPHP\Http\Exception\HttpException;
use vxPHP\Http\Response;
use vxPHP\Application\Config;
use vxPHP\Application\Application;
use vxPHP\Template\SimpleTemplate;

/**
 * custom error handling and debugging functionality
 * 
 * @author Gregor Kofler
 * @version 0.1.0 2015-03-22
 */
class ExceptionHandler {

	private $debug;
	private $charset;
	
	/**
	 * @var ErrorHandler
	 */
	private static $handler;
	
	private function __construct($debug, $charset) {

		$this->debug	= $debug;
		$this->charset	= $charset;

	}

	/**
	 * register custom exception handler
	 * 
	 * @param string $debug
	 * @param string $charset
	 * @throws \RuntimeException
	 */
	public static function register($debug = TRUE, $charset = 'UTF-8') {

		if(self::$handler) {
			throw new \RuntimeException('Exception handler already registered.');
		}
		
		self::$handler = new static($debug, $charset);
			
		set_exception_handler(array(self::$handler, 'handle'));

		return self::$handler;

	}

	/**
	 * handle exception
	 * 
	 * @param \Exception $e
	 */
	public function handle(\Exception $e) {

		try {
			ob_clean();
			$this->createResponse($e)->send();
		}
		
		catch(\Exception $e) {
			printf('Exception thrown when handling exception (%s: %s)', get_class($e), $e->getMessage());
			exit();
		}

	}

	/**
	 * create response
	 * with a HttpException status code and headers are considered
	 * other exceptions default to status code 500
	 * a error_docs/error_{status_code}.php template is parsed, when found
	 * otherwise the exception data is decorated and dumped
	 * 
	 * @param \Exception $e
	 * @return \vxPHP\Http\Response
	 */
	protected function createResponse(\Exception $e) {

		if($e instanceof HttpException) {
			$status		= $e->getStatusCode();
			$headers	= $e->getHeaders();
		}
		else {
			$status		= Response::HTTP_INTERNAL_SERVER_ERROR;
			$headers	= array();
		}

		$config = Application::getInstance()->getConfig(); 

		if(isset($config->paths['tpl_path'])) {
			$path = ($config->paths['tpl_path']['absolute'] ? '' : rtrim(Application::getInstance()->getRootPath(), DIRECTORY_SEPARATOR)) . $config->paths['tpl_path']['subdir'];

			if(file_exists($path . 'error_docs' . DIRECTORY_SEPARATOR . 'error_' . $status . '.php')) {
				$tpl = SimpleTemplate::create('error_docs' . DIRECTORY_SEPARATOR . 'error_' . $status . '.php');
				$content = $tpl
					->assign('exception', $e)
					->assign('status', $status)
					->display();
			}
			
			else {
				$content = $this->decorateException($e, $status);
			}
		}

		else {
			$content = $this->decorateException($e, $status);
		}

		return new Response($content, $status, $headers);
	}

	/**
	 * generate simple formatted output of exception
	 * 
	 * @param \Exception $e
	 * @param integer $status
	 * 
	 * @return string
	 */
	protected function decorateException(\Exception $e, $status) {

		$headerTpl = '
		<!DOCTYPE html>
		<html>
			<head>
				<title>Error %d</title>
				<meta http-equiv="content-type" content="text/html; charset=UTF-8">
			</head>
			<body>
				<table>
					<tr>
						<th colspan="4">%s</th>
					</tr>
					<tr>
						<th></th>
						<th>File</th>
						<th>Line</th>
						<th>Function</th>
					</tr>
		';

		$footerTpl = '				
				</table>
			</body>
		</html>';

		$rowTpl = '<tr><td>%d</td><td>%s</td><td>%d</td><td>%s</td></tr>';

		$content = sprintf($headerTpl, $status, $e->getMessage());

		foreach($e->getTrace() as $ndx => $level) {
			
			// skip row with error handler

			if($ndx) {
				$content .= sprintf(
					$rowTpl,
					$ndx,
					$level['file'],
					$level['line'],
					(isset($level['class']) ? ($level['class'] . $level['type'] . $level['function']) : $level['function']) . '()'
				);
			}
		}

		$content .= $footerTpl;

		return $content;

	}
}
