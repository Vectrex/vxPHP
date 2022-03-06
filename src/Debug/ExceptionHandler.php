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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Http\Exception\HttpException;
use vxPHP\Http\Response;
use vxPHP\Application\Application;
use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Template\SimpleTemplate;

/**
 * custom error handling and debugging functionality
 * 
 * @author Gregor Kofler
 * @version 0.2.0 2022-03-06
 */
class ExceptionHandler
{
	/**
	 * @var ExceptionHandler|null
     */
	private static ?ExceptionHandler $handler = null;
	
	private function __construct() {}

    /**
     * register custom exception handler
     *
     * @return ExceptionHandler
     */
	public static function register(): ExceptionHandler
    {
		if(self::$handler) {
			throw new \RuntimeException('Exception handler already registered.');
		}
		
		self::$handler = new static();
			
		set_exception_handler([self::$handler, 'handle']);

		return self::$handler;
	}

	/**
	 * handle exception
	 * 
	 * @param \Throwable $e
	 */
	public function handle(\Throwable $e): void
    {
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
     * with an HttpException status code and headers are considered
     * other exceptions default to status code 500
     * error_docs/error_{status_code}.php template is parsed, when found
     * otherwise the exception data is decorated and dumped
     *
     * @param \Exception $e
     * @return \vxPHP\Http\Response
     * @throws ApplicationException
     * @throws SimpleTemplateException
     */
	protected function createResponse(\Exception $e): Response
    {
		if($e instanceof HttpException) {
			$status = $e->getStatusCode();
			$headers = $e->getHeaders();
		}
		else {
			$status = Response::HTTP_INTERNAL_SERVER_ERROR;
			$headers = [];
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
	protected function decorateException(\Exception $e, int $status): string
    {
		$headerTpl = '
		<!DOCTYPE html>
		<html lang="en">
			<head>
				<title>%s</title>
				<meta http-equiv="content-type" content="text/html; charset=UTF-8">
			</head>
			<body>
				<table>
					<tr>
						<th colspan="4">%s: %s</th>
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

		$content = sprintf($headerTpl, $e->getMessage(), get_class($e), $e->getMessage());

		foreach($e->getTrace() as $ndx => $level) {
			
            $content .= sprintf(
                $rowTpl,
                $ndx,
                $level['file'],
                $level['line'],
                (isset($level['class']) ? ($level['class'] . $level['type'] . $level['function']) : $level['function']) . '()'
            );
		}

		$content .= $footerTpl;

		return $content;
	}
}
