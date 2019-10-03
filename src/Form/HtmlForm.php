<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Form;

use vxPHP\Form\Exception\HtmlFormException;

use vxPHP\Form\FormElement\CheckboxElement;
use vxPHP\Form\FormElement\FileInputElement;
use vxPHP\Form\FormElement\FormElement;
use vxPHP\Form\FormElement\ImageElement;
use vxPHP\Form\FormElement\InputElement;

use vxPHP\Http\Request;
use vxPHP\Http\ParameterBag;
use vxPHP\Application\Application;
use vxPHP\Session\Session;
use vxPHP\Security\Csrf\CsrfTokenManager;
use vxPHP\Security\Csrf\CsrfToken;

/**
 * Parent class for HTML forms
 *
 * @version 1.9.0 2019-10-03
 * @author Gregor Kofler
 *
 * @todo tie submit buttons to other elements of form; use $initFormValues?
 * @todo make spam detection working with multiple forms
 */

class HtmlForm
{
	public const CSRF_TOKEN_NAME = '_csrf_token';
	
	/**
	 * generated markup
	 * 
	 * @var string
	 */
	private $html;

	/**
	 * name of template file
	 * 
	 * @var string
	 */
	private $tplFile;
	
	/**
	 * contents of template file
	 * 
	 * @var string
	 */
	private $template;
	
	/**
	 * element which initiated the form submit
	 * 
	 * @var FormElement
	 */
	private $clickedSubmit;

	/**
	 * array holding all element instances assigned to form
	 * 
	 * @var FormElement[]
	 */
	private $elements = [];


    /**
     * array that keeps counters for multiple elements with same name
     *
     * @var array
     */
    private $formElementIndexes = [];
	/**
	 * values with which form elements are initialized
	 * 
	 * @var array
	 */
	private $initFormValues;
	
	/**
	 * array holding all errors evaluated by a validated form
	 *
	 * @var FormError[]
	 */
	private $formErrors = [];

	/**
	 * array holding all HTML snippets assigned to form
	 * 
	 * @var array $formErrors
	 */
	private $miscHtml = [];
	
	/**
	 * array holding all variables assigned to form
	 * 
	 * @var array
	 */
	private $vars = [];
	
	/**
	 * when set to TRUE a CSRF token
	 * will be added to the form upon rendering
	 * 
	 * @var boolean
	 */
	private $enableCsrfToken = true;

	
	/**
	 * when set to TRUE some mild countermeasures
	 * for spam blocking will be added to the form upon rendering
	 * 
	 * @var boolean
	 */
	private $enableAntiSpam = false;

	/**
	 * the active request
	 * used to set form method and default form action
	 * 
	 * @var Request
	 */
	private $request;

	/**
	 * the request data bound to the form 
	 * 
	 * @var ParameterBag
	 */
	private $requestValues;
	
	/**
	 * the form action
	 * 
	 * @var string
	 */
	private $action;
	
	/**
	 * the form enctype
	 *
	 * @var string
	 */
	private $encType;

	/**
	 * the form method
	 *
	 * @var string
	 */
	private $method;
	
	/**
	 * arbitrary form attributes
	 *
	 * @var array
	 */
	private $attributes = [];

	/**
	 * when set to TRUE placeholders
	 * for elements which were not assigned
	 * to the form will throw a HTMLFormException
	 * 
	 * @var boolean
	 */
	private $onlyAssignedElements = false;

	/**
	 * Constructor
	 *
	 * @param string $template filename
	 * @param string $action attribute
	 * @param string $method form method attribute
	 * @param string $encodingType
	 */
	public function __construct($template = null, $action = null, $method = 'POST', $encodingType = null)
    {
		$this->method = strtoupper($method);
		$this->encType = $encodingType;
		$this->tplFile = $template;

		$this->request	= Request::createFromGlobals();

		$this->setAction($action ?: $this->request->getRequestUri());
	}

	/**
	 * Create instance
	 * allows chaining
	 *
	 * @param string $template filename
	 * @param string $action attribute
	 * @param string $method request method attribute
	 * @param string $encType encoding type attribute
	 *
	 * @return HtmlForm
	 */

	public static function create($template = null, $action = null, $method = 'POST', $encType = null): HtmlForm
    {
		return new static($template, $action, $method, $encType);
	}

	/**
	 * initialize parameter bag
	 * parameter bag is either supplied, or depending on request method retrieved from request object
	 *
	 * @param ParameterBag $bag
	 * @return HtmlForm
	 *
	 */
	public function bindRequestParameters(ParameterBag $bag = null): HtmlForm
    {
		if($bag) {
			$this->requestValues = $bag;
		}

		else {
			if($this->request->getMethod() == 'GET') {
				$this->requestValues = $this->request->query;
			}
			else {
				$this->requestValues = $this->request->request;
			}
		}

		// set form element values

		foreach($this->elements as $name => $element) {
			if(is_array($element)) {
				$this->setElementArrayRequestValue($name);
			}
			else {
				$this->setElementRequestValue($element);
			}
		}

		return $this;
	}

	/**
	 * set submission method
	 * 'GET' and 'POST' are the only allowed values
	 *
	 * @param string $method
	 * @return HtmlForm
	 * @throws HtmlFormException
	 *
	 */
	public function setMethod($method): HtmlForm
    {
		$method = strtoupper($method);
		if($method !== 'GET' && $method !== 'POST') {
			throw new HtmlFormException(sprintf("Invalid form method: '%s'.", $method), HtmlFormException::INVALID_METHOD);
		}
		$this->method = $method;

		return $this;
	}

	/**
	 * set form action
	 * @todo auto convert action to nice uri and vice versa
	 *
	 * @param string $action
	 * @return HtmlForm
	 *
	 */
	public function setAction($action): HtmlForm
    {
		$this->action = htmlspecialchars($action, ENT_QUOTES);
		return $this;
	}

	/**
	 * set encoding type of form
	 * 'application/x-www-form-urlencoded' and 'multipart/form-data' are the only allowed values
	 *
	 * @param string $type
	 * @return HtmlForm
	 * @throws HtmlFormException
	 *
	 */
	public function setEncType($type): HtmlForm
    {
		$type = strtolower($type);

		if($type !== 'application/x-www-form-urlencoded' && $type !== 'multipart/form-data' && !empty($type)) {
			throw new HtmlFormException(sprintf("Invalid form enctype: '%s'.", $type), HtmlFormException::INVALID_ENCTYPE);
		}

		$this->encType = $type;

		return $this;
	}

    /**
     * set miscellaneous attribute of form
     *
     * @param string $attr
     * @param string $value
     * @return HtmlForm
     * @throws HtmlFormException
     */
	public function setAttribute($attr, $value): HtmlForm
    {
		$attr = strtolower($attr);

		switch($attr) {
			case 'action':
				$this->setAction($value);
				return $this;

			case 'enctype':
				$this->setEncType($value);
				return $this;

			case 'method':
				$this->setMethod($value);
				return $this;

			default:
				$this->attributes[$attr] = $value;
		}

		return $this;
	}
	
	/**
	 * get an attribute, if the attribute was not set previously a
	 * default value can be supplied
	 * 
	 * @param string $attr
	 * @param string $default
	 * @return string
	 */
	public function getAttribute($attr, $default = null): string
    {
		return ($attr && array_key_exists($attr, $this->attributes)) ? $this->attributes[$attr] : $default;
	}

    /**
     * sets sevaral form attributes stored in associative array
     *
     * @param array $attrs
     * @return HtmlForm
     * @throws HtmlFormException
     */
	public function setAttributes(array $attrs): HtmlForm
    {
        foreach($attrs as $k => $v) {
			$this->setAttribute($k, $v);
		}

		return $this;
	}

	/**
	 * Returns FormElement which submitted form, result is cached
	 * 
	 * @return FormElement
	 */
	public function getSubmittingElement(): FormElement
    {
		// cache submitting element

		if(!empty($this->clickedSubmit)) {
			return $this->clickedSubmit;
		}

		if($this->requestValues === null) {
			return null;
		}

		foreach($this->elements as $name => $e) {

			if(is_array($e)) {

				// parse one-dimensional arrays

				foreach($this->requestValues->keys() as $k) {

					if(preg_match('/^' . $name . '\\[(.*?)\\]$/', $k, $m)) {

						if(isset($this->elements[$name][$m[1]]) && $this->elements[$name][$m[1]]->canSubmit()) {

							$this->clickedSubmit = $this->elements[$name][$m[1]];
							return $this->clickedSubmit;

						}
					}

				}

				foreach($e as $k => $ee) {

					if(
						$ee instanceof ImageElement &&
						($arr = $this->requestValues->get($name . '_x')) &&
						isset($arr[$k])
					) {
						$this->clickedSubmit = $ee;
						return $ee;
					}

					else if(
						$ee->canSubmit() &&
						($arr = $this->requestValues->get($name)) &&
						isset($arr[$k])
					) {
						$this->clickedSubmit = $ee;
						return $ee;
					}
				}
			}

			else if($e instanceof ImageElement && $this->requestValues->get($name . '_x') !== null) {
				$this->clickedSubmit = $e;
				return $e;
			}
			else if($e->canSubmit() && $this->requestValues->get($name) !== null) {
				$this->clickedSubmit = $e;
				return $e;
			}

		}
	}

	/**
	 * checks whether form was submitted by element with $name
	 *
	 * @param string $name, name of element
	 * @return boolean result
	 */
	public function wasSubmittedByName($name): bool
    {
		if($this->getSubmittingElement() !== null) {
			return $this->getSubmittingElement()->getName() === $name;
		}

		return false;
	}

    /**
     * renders complete form markup
     *
     * @throws HtmlFormException
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public function render(): ?string
    {
		if($this->loadTemplate())	{

			$this
				->primeTemplate()
				->insertFormFields()
				->insertErrorMessages()
				->insertFormStart()
				->insertFormEnd()
				->cleanupHtml()
			;

			return $this->html;

		}
	}

	/**
	 * deliver all valid form values
	 *
	 * @param boolean $getSubmits,deliver submit buttons when TRUE, defaults to FALSE
	 * @return ValuesBag
     *
     * @throws HtmlFormException
	 */
	public function getValidFormValues($getSubmits = false): ValuesBag
    {
		if(is_null($this->requestValues)) {

			throw new HtmlFormException('Values can not be evaluated. No request bound.', HtmlFormException::NO_REQUEST_BOUND);

		}

		$tmp = new ValuesBag();

		foreach($this->elements as $name => $e) {

			if(is_array($e)) {

				$vals = [];

				foreach($e as $ndx => $elem) {

					if(!$elem->isValid()) {
						continue;
					}

					// @todo since elements in an array are all of same type they don't need to be checked individually
					
					if($elem->canSubmit() && !$getSubmits) {
						continue;
					}

					if($elem instanceof CheckboxElement && !in_array($elem->getValue(), $this->requestValues->get($name, []))) {
						continue;
					}

					$vals[$ndx] = $elem->getModifiedValue();
				}

				$tmp->set($name, $vals);

			}

			else {

				if(
                    ($e->canSubmit() && !$getSubmits) ||
					!$e->isValid() ||
                    ($e instanceof CheckboxElement && !$this->requestValues->get($name))
				) {
					continue;
				}

				$tmp->set($name, $e->getModifiedValue());

			}
		}

		return $tmp;
	}

	/**
	 * sets initial form values stored in associative array
	 * values will only be applied to elements with previously declared value NULL
	 * checkbox elements will be checked when their value equals form value
	 *
	 * @param array $values
	 * @return HtmlForm
	 *
	 */
	public function setInitFormValues(array $values): HtmlForm
    {
		$this->initFormValues = $values;

		foreach($values as $name => $value) {
			if(isset($this->elements[$name]) && is_object($this->elements[$name])) {

				if($this->elements[$name] instanceof CheckboxElement) {
					if(empty($this->requestValues)) {
						$this->elements[$name]->setChecked($this->elements[$name]->getValue() == $value);
					}
					else {
						$this->elements[$name]->setChecked(!is_null($this->requestValues->get($name)));
					}
				}

				else if(is_null($this->elements[$name]->getValue())) {
					$this->elements[$name]->setValue($value);
				}
			}
		}

		return $this;
	}

	/**
	 * retrieve form errors
	 * $result is either false if no error is found, or array with FormErrors
	 *
	 * @return FormError[] | boolean
	 */
	public function getFormErrors()
    {
		if(!count($this->formErrors)) {
			return false;
		}

		return $this->formErrors;

	}

    /**
     * returns error texts extracted from template
     * might come in handy with XHR functionality
     * returns NULL if extraction fails or template is missing
     * if $keys is set, only error texts for given element names are extracted
     *
     * @param array $keys
     * @return array $error_texts
     * @throws HtmlFormException
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	public function getErrorTexts(array $keys = []): ?array
    {
		if($this->loadTemplate()) {

			$pattern = empty($keys) ? '.*?' : implode('|', $keys);

			preg_match_all("/\{\s*error_({$pattern}):(.*?)\}/", $this->template, $hits);

			if(!empty($hits[1]) && !empty($hits[2]) && count($hits[1]) === count($hits[2])) {
				return (array_combine($hits[1], array_map('strip_tags', $hits[2])));
			}
		}
	}

	/**
	 * validate form by checking validity of each form element
	 * error flags are stored in HtmlForm::formErrors
	 * will throw a HtmlFormException when CSRF tokens are enabled and the check fails
	 *
	 * @return HtmlForm
	 * @throws HtmlFormException
	 */
	public function validate(): HtmlForm
    {
		// check whether a CSRF token was tainted
		
		if($this->enableCsrfToken && !$this->checkCsrfToken()) {

			throw new HtmlFormException('CSRF token mismatch.', HtmlFormException::CSRF_TOKEN_MISMATCH);

		}

		$this->formErrors = [];

		foreach($this->elements as $name => $e) {
			
			if(is_array($e)) {
				foreach($e as $ndx => $elem) {
				    if(!$elem->isValid()) {
                        if (!isset($this->formErrors[$name])) {
                            $this->formErrors[$name] = [];
                        }
                        $this->formErrors[$name][$ndx] = new FormError($elem->getValidationErrorMessage());
                    }
				}
			}

			else if(!$e->isValid()) {
				$this->formErrors[$name] = new FormError($e->getValidationErrorMessage());
			}
		}

		return $this;
	}

	/**
	 * initialize a miscellaneous template variable
	 * array values allow "dynamic" loops an if-else constructs
	 *
	 * @param string $name name of variable
	 * @param mixed $value value of variable
	 * @return HtmlForm
	 *
	 */
	public function initVar($name, $value): HtmlForm
    {
		$this->vars[$name] = $value;
		return $this;
	}

	/**
	 * add custom error and force error message in template
	 *
	 * @param string $errorName
	 * @param mixed $errorNameIndex
     * @param string message
     *
 	 * @return HtmlForm
	 */
	public function setError($errorName, $errorNameIndex = null, $message = null): HtmlForm
    {
		if($errorNameIndex === null) {
			$this->formErrors[$errorName] = new FormError($message);
		}
		else {
			$this->formErrors[$errorName][$errorNameIndex] = new FormError($message);
		}

		return $this;
	}

	/**
	 * get all elements of form
	 * 
	 * @return array 
	 */
	public function getElements(): array
    {
		return $this->elements;
	}
	
	/**
	 * get one or more elements by name
	 *
     * @param string $name of element or elements
	 * @return FormElement|array
     * @throws \InvalidArgumentException
	 */
	public function getElementsByName($name) {

		if(isset($this->elements[$name])) {
			return $this->elements[$name];
		}
		
		throw new \InvalidArgumentException(sprintf("Unknown form element '%s'", $name));

	}

	/**
	 * add form element to form
	 *
	 * @param FormElement $element
	 * @throws HtmlFormException
	 * @return HtmlForm
	 */
	public function addElement(FormElement $element): HtmlForm
    {
		if(!empty($this->elements[$element->getName()])) {
			
			throw new HtmlFormException(sprintf("Element '%s' already assigned.", $element->getName()));
			
		}

		$this->elements[$element->getName()] = $element;
		$element->setForm($this);

		return $this;
	}

	/**
	 * add several form elements stored in array to form
	 * 
	 * the elements have to be of same type and have to have the same
	 * name or an empty name
	 *
	 * @param array FormElement[]
	 * @throws HtmlFormException
	 * @return HtmlForm
	 *
	 */
	public function addElementArray(array $elements): HtmlForm
    {
		if(count($elements)) {
			
			$firstElement = array_shift($elements);
			$name = $firstElement->getName();

			// remove any array indexes from name

			$name = preg_replace('~\[\w*\]$~i', '', $name);

			if(!empty($this->elements[$name])) {

				throw new HtmlFormException(sprintf("Element '%s' already assigned.", $name));

			}

			$arrayName = $name . '[]';
			$firstElement
				->setName($arrayName)
				->setForm($this)
			;

			$this->elements[$name] = [$firstElement];

			foreach($elements as $e) {
				
				if(get_class($e) !== get_class($firstElement)) {

					throw new HtmlFormException(sprintf("Class mismatch of form elements array. Expected '%s', found '%s'.", get_class($firstElement), get_class($e)));

				}
				
				// check whether names of element arrays match

				$nameToCheck = preg_replace('~\[\w*\]$~i', '', $e->getName());

				if($nameToCheck && $nameToCheck !== $name) {
					
					throw new HtmlFormException(sprintf("Name mismatch of form elements array. Expected '%s' or empty name, found '%s'.", $name, $e->getName()));

				}

				$e
					->setName($arrayName)
					->setForm($this)
				;

				$this->elements[$name][] = $e;
			}

		}

		return $this;
	}

	/**
	 * disallows placeholders for unassigned elements
	 * 
	 * @return HtmlForm
	 */
	public function allowOnlyAssignedElements(): HtmlForm
    {
		$this->onlyAssignedElements = true;
		return $this;
	}

	/**
	 * allows placeholders for unassigned elements
	 * 
	 * @return HtmlForm
	 */
	public function allowUnassignedElements(): HtmlForm
    {
        $this->onlyAssignedElements = false;
		return $this;
	}

	private function setElementRequestValue(FormElement $e): void
    {
		if($this->requestValues === null) {
			return;
		}

		$name = $e->getName();

		// flagging of checkboxes

		if($e instanceof CheckboxElement) {
			$e->setChecked((bool) $this->requestValues->get($name));
		}

		// don't handle file input elements

		else if($e instanceof FileInputElement) {

        }

		else {
			if(($value = $this->requestValues->get($name)) !== null) {
				$e->setValue($value);
			}
			elseif(isset($this->initFormValues[$name]) && $e->getValue() === null) {
				$e->setValue($this->initFormValues[$name]);
			}
		}
	}

	private function setElementArrayRequestValue($name): void
    {
		$values = $this->requestValues->get($name, []);

		foreach($this->elements[$name] as $k => $e) {

			if($e instanceof CheckboxElement) {
				$e->setChecked(in_array($e->getValue(), $values));
			}

			else {
				if(isset($values[$k])) {
					$e->setValue($values[$k]);
				}
				elseif($e->getValue() === null || isset($this->initFormValues[$name][$k])) {
					$e->setValue($this->initFormValues[$name][$k]);
				}
			}
		}
	}

	/**
	 * add miscellaneous markup and text to form
	 *
	 * @param string $id
	 * @param string $value
	 *
	 * @return HtmlForm
	 */
	public function addMiscHtml($id, $value): HtmlForm
    {
		$this->miscHtml[$id] = $value;
		return $this;
	}

	/**
	 * disable CSRF token
	 * 
	 * no hidden form element with
	 * a CSRF token will be added when form is rendered
	 * 
	 * @return HtmlForm
	 */
	public function disableCsrfToken(): HtmlForm
    {
		$this->enableCsrfToken = false;
		return $this;
	}

	/**
	 * enable CSRF token
	 * 
	 * a hidden form element with
	 * a CSRF token will be added when form is rendered
	 * 
	 * @return HtmlForm
	 */
	public function enableCsrfToken(): HtmlForm
    {
		$this->enableCsrfToken = true;
		return $this;
	}
	
	/**
	 * disable spam countermeasures
	 * 
	 * @return HtmlForm
	 */
	public function disableAntiSpam(): HtmlForm
    {
		$this->enableAntiSpam = false;
		return $this;
	}

	/**
	 * enable spam countermeasures
	 * 
	 * @return HtmlForm
	 */
	public function enableAntiSpam(): HtmlForm
    {
		$this->enableAntiSpam = true;
		return $this;
	}

    /**
     * render CSRF token element
     * the token will use the form action as id
     *
     * @return string
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @throws \vxPHP\Template\Exception\SimpleTemplateException
     */
	private function renderCsrfToken(): string
    {
		$tokenManager = new CsrfTokenManager();
		$token = $tokenManager->refreshToken('_' . $this->action . '_');

		$e = new InputElement(self::CSRF_TOKEN_NAME, $token->getValue());
		$e->setAttribute('type', 'hidden');
		
		return $e->render();
	}
	
	/**
	 * check whether a CSRF token remained untainted
	 * compares the stored token with the request value
	 * 
	 * @return bool
	 */
	private function checkCsrfToken(): bool
    {
		$tokenManager = new CsrfTokenManager();

		$token = new CsrfToken(
			'_' . $this->action . '_',
			$this->requestValues->get(self::CSRF_TOKEN_NAME)
		);

		return $tokenManager->isTokenValid($token);
	}

    /**
     * render spam countermeasures
     *
     * @return string
     * @throws HtmlFormException
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @throws \vxPHP\Template\Exception\SimpleTemplateException
     */
	private function renderAntiSpam(): string
    {
		$secret = md5(uniqid(null, true));
		$label = md5($secret);
		
		Session::getSessionDataBag()->set('antiSpamTimer', [$secret => microtime(true)]);

		$e = new InputElement('verify', null);
		$e->setAttribute('type', 'hidden');

		$this->addElement($e);
		$e->setValue($secret);

		return sprintf("
			<div>%s
				<span style='display:none;'>
					<label for='confirm_entry_%s'>Leave this field empty!</label>
					<input id='confirm_entry_%s' name='confirm_entry_%s' value=''>
				</span>
			</div>", $e->render(), $label, $label, $label);
	}

	/**
	 * check for spam
	 *
     * @param array $fields names of fields to check against spam
     * @param int $threshold number of suspicious content which when exceeded will indicate spam
	 * @return boolean $spam_detected
	 */
	public function detectSpam(array $fields = [], $threshold = 3): bool
    {
		$verify	= $this->requestValues->get('verify');
		$timer	= Session::getSessionDataBag()->get('antiSpamTimer');

		if(
			!$verify ||
			!isset($timer[$verify]) ||
			(microtime(true) - $timer[$verify] < 1)
		) {
			return true;
		}

		$label = md5($verify);

		if($this->requestValues->get('confirm_entry_' . $label) === null || $this->requestValues->get('confirm_entry_' . $label) !== '') {
			return true;
		}

		foreach($fields as $f) {
			if(preg_match_all('~<\s*a\s+href\s*\=\s*(\\\\*"|\\\\*\'){0,1}http://~i', $this->requestValues->get($f), $tmp) > $threshold) {
				return true;
			}
			if(preg_match('~\[\s*url.*?\]~i', $this->requestValues->get($f))) {
				return true;
			}
		}
		return false;
	}

    /**
     * remove form element
     * when $name indicates an array of elements,
     * a single element can be picked by declaring an additional index
     *
     * @param string $name of element
     * @param int $index of element if an array of elements is handled
     * @return HtmlForm
     */
	public function removeElementByName($name, $index = null): HtmlForm
    {
		if($index !== null) {
			unset($this->elements[$name][$index]);
		}
		else {
			unset($this->elements[$name]);
		}
        return $this;
	}

    /**
     * remove miscellaneous markup and text to form
     * when $id indicates an array of snippets,
     * a single snippet can be picked by declaring an index
     *
     * @param string $id of markup
     * @param int $index of markup if an array of markups is handled
     * @return HtmlForm
     */
	public function removeHtmlByName($id, $index = null): HtmlForm
    {
		if(!is_null($index) && isset($this->miscHtml[$id][$index])) {
			unset($this->miscHtml[$id][$index]);
		}
		else if(isset($this->miscHtml[$id])) {
			unset($this->miscHtml[$id]);
		}
        return $this;
	}

    /**
     * load template
     *
     * @return bool $success
     * @throws HtmlFormException
     * @throws \vxPHP\Application\Exception\ApplicationException
     */
	private function loadTemplate(): bool
    {
		if(!empty($this->template)) {
			return true;
		}

		$path = Application::getInstance()->getRootPath() . (defined('FORMTEMPLATES_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(FORMTEMPLATES_PATH, '/')) : '');

		if(!file_exists($path . $this->tplFile)) {
			throw new HtmlFormException("Template file '$path{$this->tplFile}' does not exist.", HtmlFormException::TEMPLATE_FILE_NOT_FOUND);
		}

		$this->template = @file_get_contents($path . $this->tplFile);

		return true;
	}

	/**
	 * prepare template
	 *
	 * interprets pseudo tags
	 * {loop $i} .. {end_loop}
	 * {if(cond)} .. {else} .. {end_if}
	 * {html:$string}
	 * 
	 * @return HtmlForm
	 */
	private function primeTemplate(): HtmlForm
    {
		// unroll Loops {loop $counter} .. {end_loop}

		$this->template = $this->doLoop($this->template);

		// insert vars and loop counters

		$this->template = $this->doInsertVars($this->template);

		// {if (cond)} .. {else} .. {end_if}

		$this->template = $this->doIfElseEndif($this->template);

		// insert misc html
		foreach($this->miscHtml as $k => $v) {
			if(!is_array($v)) {
				$this->template = preg_replace("/\{html:$k\}/i", $v, $this->template);
			}
			else {
				foreach($v as $vv) {
					$this->template = preg_replace("/\{html:$k\}/i", $vv, $this->template, 1);
				}
			}
		}
		
		return $this;
	}

	/*
	 * unroll loops
	 */
	private function doLoop($tpl): string
    {
		$stack = $this->loopRecursion($tpl, 0);
		$tpl = $this->unrollLoops($stack);
		return $tpl;
	}

	private function parseLoopVar($tpl, $counters): string
    {
		foreach($counters as $c => $v) {
			$tpl = preg_replace('~\044'.$c.'~', $v, $tpl);
		}
		return $tpl;
	}

	private function unrollLoops($stack, $counters = []): string
    {
		$markup = '';

		foreach($stack as $s) {
			$left = $s['left'];
			$right = !empty($s['right']) ? $s['right'] : '';

			if(!isset($s['loopVar']) || !isset($this->vars[$s['loopVar']])) {
				$markup .= $left.$right;
				continue;
			}

			$inner = '';
			$counter = $this->vars[$s['loopVar']];

			if(!empty($s['ndx'])) {
				$ndxs = explode('][', trim($s['ndx'], '[]$'));
				foreach($ndxs as $n) {
					$counter = is_array($counter) && isset($counters[$n]) && isset($counter[$counters[$n]]) ? $counter[$counters[$n]] : 0;
				}
			}

			if(is_array($s['inner'])) {
				for($i = 0; $i < $counter; ++$i) {
					$counters[$s['loopVar']] = $i;
					$inner .= $this->unrollLoops($s['inner'], $counters);
				}
			}
			else {
				for($i = 0; $i < $counter; ++$i) {
					$counters[$s['loopVar']] = $i;
					$inner .= $this->parseLoopVar($s['inner'], $counters);
				}
			}

			$markup .= $left.$inner.$right;
		}

		return $markup;
	}

	private function loopRecursion($tpl, $level): ?array
    {

		$stack = [];

		while(true) {
			preg_match('~(.*?)\{(loop\s+\044([a-z0-9_]+)(?:\[(\d+)\]|((?:\[\044[a-z0-9_]+\])*))|end_loop)}(.*)~si', $tpl, $matches);

			if(count($matches) < 7) {
				$stack[]['left'] = $tpl;
				return $stack;
			}

			$left = $matches[1];
			$right = $matches[6];

			if($matches[2] === 'end_loop') {
				if(empty($stack)) {
					return [$right, $left];
				}
				$stack[count($stack) - 1]['right'] = $left;
				return [$right, $stack];
			}

            [$right, $inner] = $this->loopRecursion($right, $level + 1);

            $stack[] = [
                'lvl' => $level,
                'left' => $left,
                'loopVar' => $matches[3],
                'ndx' => $matches[5],
                'inner' => $inner
            ];

			$tpl = $right;
		}
	}

	private function doInsertVars($tpl): string
    {
		foreach($this->vars as $k => $v) {
			if(is_array($v)) {
				foreach($v as $kk => $vv) {
					$tpl = preg_replace('~\{\s*\\$' . $k .'\[' . $kk . '\]\s*\}~i', $vv, $tpl);
				}
			}
			else {
				$tpl = preg_replace('~\{\s*\\$' . $k .'\s*\}~i', $v, $tpl);
			}
		}
		return $tpl;
	}

	/*
	 * handle {if (cond)} .. {else} .. {end_if}
	 */
	private function doIfElseEndif($tpl): string
    {
		$stack = [];
		$nesting = 0;

		while(true) {
			preg_match('~(.*?)\{(if\s*\((.+?)\)\s*|else|end_if)\}(.*)~si', $tpl, $matches);

			if(count($matches) < 5) {
				break;
			}

			$right = $matches[4];
			$left = $matches[1];

			switch($matches[2]) {
				case 'else':
					$last = &$stack[$nesting-1];

					$last['else'] = true;

					if($last['condition'] && (!isset($last['parentCond']) || $last['parentCond'])) {
						$last['left'] .= $left;
					}
					break;

				case 'end_if':
					$last = &$stack[$nesting-1];

					if(((!$last['condition'] && $last['else']) || ($last['condition'] && !$last['else'])) && (!isset($last['parentCond']) || $last['parentCond'])) {
						$last['left'] .= $left;
					}

					--$nesting;

					if($nesting > 0) {
						$stack[$nesting-1]['left'] .= $stack[$nesting]['left'];
						$stack[$nesting]['left'] = '';
					}
					break;

				default:
					if($nesting > 0) {
						if(
							($stack[$nesting - 1]['condition'] && $stack[$nesting - 1]['else']) ||
							(!$stack[$nesting - 1]['condition'] && !$stack[$nesting - 1]['else']) ||
							($stack[$nesting - 1]['parentCond'] === false)
						) {
							$left = '';
							$parentCond = false;
							$cond = null;
						}
						else {
							$parentCond = true;
							$cond = $this->evalCondition($matches[3]);
						}
					}
					else {
						$cond = $this->evalCondition($matches[3]);
					}

					if(!empty($stack[$nesting]['left'])) {
						$left = $stack[$nesting]['left'].$left;
					}

					$stack[$nesting] = [
						'left' => $left,
						'condition' => $cond,
						'parentCond' => isset($parentCond) ?: null,
						'else' => false
					];
					++$nesting;
					break;
			}
			$tpl = $right;
		}

		// append last right

		return empty($stack[0]['left']) ? $tpl : $stack[0]['left'] . $right;
	}

	private function evalCondition($cond): ?bool
    {
		if(!preg_match('/(.*?)\s*(==|!=|<|>|<=|>=)\s*(.*)/', $cond, $terms) || count($terms) !== 4) {
		    return null;
		}
		if(preg_match('/\044(.*)/', $terms[1], $tmp)) {
		    $terms[1] = $this->vars[$tmp[1]] ?? null;
		}
		if(preg_match('/\044(.*)/', $terms[3], $tmp)) {
            $terms[3] = $this->vars[$tmp[1]] ?? null;
        }

		switch ($terms[2]) {
			case '==':
				return $terms[1] === $terms[3];

			case '!=':
				return $terms[1] !== $terms[3];

			case '<':
				return $terms[1] < $terms[3];

			case '>':
				return $terms[1] > $terms[3];

			case '<=':
				return $terms[1] <= $terms[3];

			case '>=':
				return $terms[1] >= $terms[3];
		}

		return null;
	}

	/**
	 * insert form fields into template
	 * 
	 * form fields are expected in the following form
	 * {element: <name> [ {attributes}]}
	 * 
	 * the optional attributes are provided as JSON encoded key-value pairs
     * future versions may observe *only* the "element" string and deprecate
     * specific element types
     *
	 * @return HtmlForm
	 */
	private function insertFormFields(): HtmlForm
    {
		$this->html = preg_replace_callback(

			'/\{\s*(dropdown|input|image|button|textarea|options|checkbox|selectbox|label|element):(\w+)(?:\s+(\{.*?\}))?\s*\}/i',

			function($matches) {

				// check whether element was assigned

				if(empty($this->elements[$matches[2]])) {

					if($this->onlyAssignedElements) {
						throw new HtmlFormException(sprintf("Element '%s' not assigned to form.", $matches[2]), HtmlFormException::INVALID_MARKUP);
					}

				}

				else {

                    // validate JSON attribute string

                    if (!empty($matches[3])) {

                        $attributes = json_decode($matches[3], true, 2);

                        if ($attributes === null) {
                            throw new HtmlFormException(sprintf("Could not parse JSON attributes for element '%s'.", $matches[2]), HtmlFormException::INVALID_MARKUP);
                        }

                        $attributes = array_change_key_case($attributes, CASE_LOWER);

                        if (isset($attributes['name'])) {
                            throw new HtmlFormException(sprintf("Attribute 'name' is not allowed with element '%s'.", $matches[2]), HtmlFormException::INVALID_MARKUP);
                        }

                    }

                    // insert rendered element or label

                    if (is_array($this->elements[$matches[2]])) {

                        if (!isset($this->formElementIndexes[$matches[2]])) {
                            $this->formElementIndexes[$matches[2]] = 0;
                        }

                        if ('label' === strtolower($matches[1])) {

                            // insert label, array index does not advance

                            $e = $this->elements[$matches[2]][$this->formElementIndexes[$matches[2]]]->getLabel();

                        } else {

                            // insert element, advance array index

                            $e = $this->elements[$matches[2]][$this->formElementIndexes[$matches[2]]++];

                        }

                    } else {

                        // insert element

                        $e = $this->elements[$matches[2]];

                        if ('label' === strtolower($matches[1])) {

                            // insert label

                            $e = $e->getLabel();

                        }
                    }

                    if($e) {

                        if (isset($attributes)) {
                            $e->setAttributes($attributes);
                        }

                        return $e->render();
                    }

                }
			},

			$this->template
		);
		
		return $this;

	}

    /**
     * allows an opening form tag at a custom position
     * searches for {form[ {attributes}]}
     * a maximum of one opening tag is allowed
     * the optional attributes are provided as JSON encoded key-value pairs,
     * these attributes override attributes set with setAttribute()
     * if no tag is found a <form> tag is prepended to the parsed HTML template
     *
     * @return HtmlForm
     * @throws HtmlFormException
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @throws \vxPHP\Template\Exception\SimpleTemplateException
     */
	private function insertFormStart(): HtmlForm
    {
		$this->html = preg_replace_callback(

			'/\{\s*form(?:\s+(\{.*?\}))?\s*\}/i',

			function($matches) {

				// validate JSON attribute string

				if(!empty($matches[1])) {

					$attributes = json_decode($matches[1], true, 2);

					if($attributes === null) {
						throw new HtmlFormException("Could not parse JSON attributes for '{form}'.", HtmlFormException::INVALID_MARKUP);
					}

					$attributes = array_change_key_case($attributes, CASE_LOWER);

					// check for not allowed attributes

					foreach(['method', 'action', 'type'] as $method) {
						if(in_array($method, $attributes, true)) {
							throw new HtmlFormException(sprintf("Attribute '%s' not allowed for '{form}'.", $method), HtmlFormException::INVALID_MARKUP);
						}
					}

					// override attributes which were set with HtmlForm::setAttribute()

					$attributes = array_merge($this->attributes, $attributes);

				}
				
				else {

					$attributes = $this->attributes;

				}

				$attr = [];
				
				foreach($attributes as $k => $v) {
					$attr[] = "$k='$v'";
				}

				// return <form ...> tag and CSRF token and antispam elements when applicable

				return sprintf(
					'<form action="%s" method="%s" %s %s>%s%s',
					$this->action,
					$this->method,
					$this->encType ? ( 'enctype="' . $this->encType . '"') : '',
					implode(' ', $attr),
					$this->enableAntiSpam ? $this->renderAntiSpam() : '',
					$this->enableCsrfToken ? $this->renderCsrfToken() : ''
				);

			},
			$this->html,
			-1,
			$count
		);

		// more than one opening tag

		if($count > 1) {
			throw new HtmlFormException("Found more than one opening {form} tag.", HtmlFormException::INVALID_MARKUP);
		}

		// no opening tag found

		if(!$count) {

			$attr = [];
	
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			
			$this->html = sprintf(
				'<form action="%s" method="%s" %s %s>%s%s%s',
				$this->action,
				$this->method,
				$this->encType ? ( 'enctype="' . $this->encType . '"') : '',
				implode(' ', $attr),
				$this->enableAntiSpam	? $this->renderAntiSpam() : '',
				$this->enableCsrfToken	? $this->renderCsrfToken() : '',
				$this->html
			);
		}
		
		return $this;
	}

	/**
	 * allows a closing form tag at a custom position
	 * searches for {end_form}
	 * a maximum of one closing tag is allowed
	 * if no tag is found a </form> tag is appended to the parsed HTML template
	 * 
	 * @return HtmlForm
	 * @throws HtmlFormException
	 */
	private function insertFormEnd(): HtmlForm
    {
		$this->html = preg_replace(
			'/\{\s*end_form\s*\}/i',
			'</form>',
			$this->html,
			-1,
			$count
		);

		// more than one closing tag found

		if($count > 1) {

			throw new HtmlFormException("Found more than one closing {end_form} tags.", HtmlFormException::INVALID_MARKUP);

		}

		// no closing tag

		if(!$count) {
			$this->html .= '</form>';
		}

		return $this;
	}

	/**
	 * insert error messages into template
	 * placeholder for error messages are replaced
	 * 
	 * @return HtmlForm
	 */
	private function insertErrorMessages(): HtmlForm
    {
		$rex = '/\{(?:\w+\|)*error_%s(?:\|\w+)*:([^\}]+)\}/i';

		foreach($this->formErrors as $name => $v) {
			if(!is_array($v)) {
				$this->html = preg_replace(
					sprintf($rex, $name),
					'$1',
					$this->html
				);
			}
			else {
				foreach(array_keys($this->formErrors[$name]) as $ndx) {
					$this->html = preg_replace(
						sprintf($rex, $name),
						empty($this->formErrors[$name][$ndx]) ? '' : '$1',
						$this->html,
						1
					);
				}
			}
		}
		
		return $this;
	}

	/**
	 * cleanup HTML output
	 * removes orphaned {} pairs
	 * with error messages, misc HTML blocks or variables
	 * 
	 * @return HtmlForm
	 */
	private function cleanupHtml(): HtmlForm
    {
		$this->html = preg_replace('/\{\s*(error_.*?|html:.*?|\044.*?)\s*\}/i', '', $this->html);
		return $this;
	}
}