<?php
/**
 * Template Engine for Forms
 * @version 1.1.10 2012-05-21
 * @author Gregor Kofler
 * 
 * @todo tie submit buttons to other elements of form; use $initFormValues?
 * @todo make addAntiSpam working with multiple forms
 * @todo relocate date and time validation to more appropriate class
 */

require_once 'class.formelement.php';

class HtmlForm {

	private		$html,
				$tplFile,
				$template,
				$clickedSubmit,
				$elements		= array(),
				$initFormValues	= array(),
				$formErrors		= array(),
				$miscHtml		= array(),
				$vars 			= array(),
				$allowApcUpload	= FALSE,

				$request,
				$action,
				$type,
				$method,
				$attributes = array(),
				$error,
				$submitIndex;

	/**
	 * Constructor
	 * 
	 * @param string $template filename
	 * @param string $action attribute
	 * @param string $submit method
	 * @param string $encoding type
	 * @param string $css class
	 * @param string $misc string
	 */
	public function __construct($template = NULL, $action = FALSE, $method = 'POST', $type = FALSE, $css = FALSE) {
		$this->method	= strtoupper($method);
		$this->type		= $type;
		$this->css		= $css;
		$this->tplFile	= $template;

		$this->setAction($action ? $action : $_SERVER['REQUEST_URI']);
		$this->setRequestValues();
	}

	/**
	 * set submission method
	 * 'GET' and 'POST' are the only allowed values
	 * 
	 * @param string $method
	 * @throws HtmlFormException
	 */
	public function setMethod($method) {
		$method = strtoupper($method);
		if($method != 'GET' && $method != 'POST') {
			throw new HtmlFormException("Invalid form method: $method");
		}
		$this->method = $method;
		$this->setRequestValues(); 
	}

	/**
	 * set form action
	 * 
	 * @param string $action
	 */
	public function setAction($action) {
		$this->action = htmlspecialchars($action, ENT_QUOTES);
	}

	/**
	 * set encoding type of form
	 * 'application/x-www-form-urlencoded' and 'multipart/form-data' are the only allowed values
	 * 
	 * @param string $method
	 * @throws HtmlFormException
	 */
	public function setEncType($type) {
		$method = strtolower($type);
		if($type != 'application/x-www-form-urlencoded' && $type != 'multipart/form-data' && !empty($type)) {
			throw new HtmlFormException("Invalid form enctype: $type");
		}
		$this->type = $type;
	}

	/**
	 * set miscellaneous attribute of form
	 * 
	 * @param string $attr
	 * @param string $value
	 */
	public function setAttribute($attr, $value) {
		$attr = strtolower($attr);

		switch($attr) {
			case 'action':
				$this->setAction($value);
				return;
			case 'enctype':
				$this->setEncType($value);
				return;
			case 'method':
				$this->setMethod($value);
				return;
			default:
				$this->attributes[$attr] = $value;		
		}
	}

	/**
	 * sets sevaral form attributes stored in associative array
	 * 
	 * @param array $attrs
	 */
	public function setAttributes(array $attrs) {
		foreach($attrs as $k => $v) {
			$this->setAttribute($k, $v);
		}
	}

	/**
	 * Returns FormElement which submitted form and stores it in self::clickedSubmit
	 * if submitted value is array, self::submitIndex is populated
	 */
	public function getSubmittingElement() {

		if(!empty($this->clickedSubmit)) {
			return $this->clickedSubmit;
		}

		$this->submitIndex = NULL;

		foreach($this->elements as $name => $e) {
			if($e instanceof ImageElement && isset($this->request["{$this->request[$name]}_x"])) {
				$this->clickedSubmit = $e;
				return $e;
			}
			else if($e instanceof FormElement && $e->canSubmit() && isset($this->request[$name])) {
				$this->clickedSubmit = $e;
				return $e;
			}
				
			if(is_array($e)) {

				// needed for submits via XHR, since arrays are returned as plain text
				foreach(array_keys($this->request) as $req) {
					if(preg_match("/^$name\[(.*?)\]$/", $req, $m)) {
						if(isset($this->elements[$name][$m[1]]) && $this->elements[$name][$m[1]]->canSubmit()) {
							$this->submitIndex = $m[1];
							$this->clickedSubmit = $e;
							return $e;
						}
					}
				}
				
				foreach($e as $k => $ee) {
					if($ee instanceof ImageElement && isset($this->request["{$this->request[$name]}_x"][$k])) {
						$this->clickedSubmit = $ee;
						$this->submitIndex = $k;
						return $ee;
					}
					else if($ee instanceof FormElement && $ee->canSubmit() && isset($this->request[$name][$k])) {
						$this->clickedSubmit = $ee;
						$this->submitIndex = $k;
						return $ee;
					}
				}
			}
		}
	}

	/**
	 * checks whether form was submitted by element with $name
	 * 
	 * @param string $name, name of element
	 * @return boolean result
	 */
	public function wasSubmittedByName($name) {
		if(!is_null($this->getSubmittingElement())) {
			return $this->getSubmittingElement()->getName() === $name;
		}
		return FALSE;
	}

	/**
	 * renders complete form markup
	 */
	public function render() {
		if($this->loadTemplate())	{
		
			$this->primeTemplate();
			$this->insertFormFields();
	
			SimpleTemplate::parseTemplateLinks($this->html);
			SimpleTemplate::parseTemplateLocales($this->html);
	
			$this->insertErrorMessages();
			$this->cleanupHtml();
			
			$link = defined('USE_MOD_REWRITE') && USE_MOD_REWRITE ? SimpleTemplate::link2Canonical($this->action) : $this->action;
			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			
			return implode('', array(
				"<form action='$link' method='{$this->method}'",
				($this->type ? " enctype='{$this->type}'" : ''),
				($this->css	? " class='{$this->css}'" : ' '),
				implode(' ', $attr),
				'>',
				(isset($this->antiSpam) ? $this->antiSpam : ''),
				$this->html,
				'</form>'
			));
		}
	}

	/**
	 * deliver all valid form values
	 * 
	 * @param boolean $getSubmits,deliver submit buttons when TRUE, defaults to FALSE 
	 * @return array valid_form_values
	 */
	public function getValidFormValues($getSubmits = FALSE) {

		$tmp = array();

		foreach($this->elements as $name => $e) {
			if($e instanceof FormElement) {
				if(
					$e->canSubmit() && !$getSubmits ||
					!$e->isValid() ||
					$e instanceof CheckboxElement && !isset($this->request[$name])
				) {
					continue;
				}
				$tmp[$name] = $e->getFilteredValue();
			}

			else {
				$tmp[$name] = array();
				foreach($e as $ndx => $elem) {
					if(
						$elem->canSubmit() && !$getSubmits ||
						!$elem->isValid() ||
						$elem instanceof CheckboxElement && !isset($this->request[$name][$ndx])
					) {
						continue;
					}
					$tmp[$name][$ndx] = $elem->getFilteredValue();					
				}
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
	 * @return void
	 */
	public function setInitFormValues(array $values) {
		$this->initFormValues = $values;
		
		foreach($values as $name => $value) {
			if(isset($this->elements[$name]) && $this->elements[$name] instanceof FormElement) {
				if($this->elements[$name] instanceof CheckboxElement) {
					if(empty($this->request)) {
						$this->elements[$name]->setChecked($this->elements[$name]->getValue() == $value);
					}
					else {
						$this->elements[$name]->setChecked(isset($this->request[$name]));
					}
				}
				else if(is_null($this->elements[$name]->getValue())) {
					$this->elements[$name]->setValue($value);
				}
			}
		}
	}

	/**
	 * retrieve form errors
	 * $result is either FALSE if no error found, or array with errors
	 * 
	 * @return $result 
	 */
	public function getFormErrors() {
		if(count($this->formErrors) === 0) {
			return FALSE;
		}

		else {
			return $this->formErrors;
		}
	}

	/**
	 * returns error texts extracted from template
	 * might come in handy with XHR functionality
	 * returns NULL if extraction fails or template is missing
	 * if $keys is set, only error texts for given element names are extracted
	 * 
	 * @param array $keys  
	 * @return array $error_texts
	 */
	public function getErrorTexts(array $keys = array()) {
		if($this->loadTemplate()) {
			$pattern = empty($keys) ? '.*?' : implode('|', $keys);
			preg_match_all("/\{\s*error_({$pattern}):(.*?)\}/", $this->template, $hits);
			if(!empty($hits[1]) && !empty($hits[2]) && count($hits[1]) == count($hits[2])) {
				return (array_combine($hits[1], array_map('strip_tags', $hits[2])));
			}
		}
	}

	/**
	 * validate form by checking validity of each form element
	 * error flags are stored in HtmlForm::formErrors
	 */
	public function validate() {

		$this->formErrors = array();

		foreach($this->elements as $name => $e) {
			if($e instanceof FormElement) {
				if(!$e->isValid()) {
					$this->formErrors[$name] = TRUE;
				}
			}

			else {
				foreach($e as $ndx => $elem) {
					if(!$elem->isValid()) {
						if(!isset($this->formErrors[$name])) {
							$this->formErrors[$name] = array();
						}
						$this->formErrors[$name][$ndx] = TRUE;
					}
				}
			}
		}
	}

	/**
	 * initialize a miscellaneous template variable
	 * array values allow "dynamic" loops an if-else constructs
	 * 
	 * @param string $name_of_var
	 * @param mixed $value_of_var
	 */
	public function initVar($name, $val) {
		$this->vars[$name] = $val;
	}

	/**
	 * add custom error and force error message in template
	 * 
	 * @param string $error_name
	 * @param mixed $error_name_index
	 */
	public function setError($err, $ndx = NULL) {
		if(is_null($ndx)) {
			$this->formErrors[$err] = TRUE;
		}
		else {
			$this->formErrors[$err][$ndx] = TRUE;
		}
	}

	/**
	 * add form element to form
	 * 
	 * @param FormElement $e
	 */
	public function addElement(FormElement $e) {
		$this->elements[$e->getName()] = $e;
		$this->setElementRequestValue($e);
	} 

	/**
	 * add several form elements stored in array to form
	 * 
	 * @param array $e
	 */
	public function addElementArray(array $e) {
		if(count($e)) {
			$elems = array_values($e);
			$name = $elems[0]->getName();
			$name = preg_replace('~\[\w+\]$~i', '', $name);
			$this->elements[$name] = $e;
			$this->setElementArrayRequestValue($name);
		}
	} 

	private function setElementRequestValue(FormElement $e) {
		$name = $e->getName();

		// checkboxes are only affected by request, if request is set all

		if($e instanceof CheckboxElement && !empty($this->request)) {
			$e->setChecked(isset($this->request[$name]));
		}

		else {
			if(isset($this->request[$name])) {
				$e->setValue($this->request[$name]);
			}
			elseif(isset($this->initFormValues[$name]) && is_null($e->getValue())) {
				$e->setValue($this->initFormValues[$name]);
			}
		}
	}

	private function setElementArrayRequestValue($name) {
		foreach($this->elements[$name] as $k => $e) {
	
			if($e instanceof CheckboxElement) {
				$e->setChecked(isset($this->request[$name][$k]));
			}
			else {
				if(isset($this->request[$name][$k])) {
					$e->setValue($this->request[$name][$k]);
				}
				elseif(is_null($e->getValue()) || isset($this->initFormValues[$name][$k])) {
					$e->setValue($this->initFormValues[$name][$k]);
				}
			}
		}
	}

	/**
	 * add miscellaneous markup and text to form
	 * 
	 * @param string $markup_id
	 * @param mixed $markup_value
	 */
	public function addMiscHtml($id, $value) {
		$this->miscHtml[$id] = $value;
	}

	/**
	 * enable APC upload if supported by server
	 */
	public function enableApcUpload() {
		$this->allowApcUpload = $GLOBALS['config']->server['apc_on'];
	}

	/**
	 * disable APC upload if supported by server
	 */
	public function disableApcUpload() {
		$this->allowApcUpload = FALSE;
	}

	/**
	 * add anti spam elements
	 */
	public function addAntiSpam() {
		$secret = md5(uniqid(null, true));
		$label = md5($secret);

		$_SESSION['antiSpamTimer'][$secret]	= microtime(true);
		
		$e = new InputElement('verify', NULL);
		$e->setAttribute('type', 'hidden');

		$this->addElement($e);
		$e->setValue($secret);

		$this->antiSpam =	"
			<div>{$e->render()}
				<span style='display:none;'>
					<label for='confirm_entry_$label'>Leave this field empty!</label>
					<input id='confirm_entry_$label' name='confirm_entry_$label' value=''>
				</span>
			</div>";
	}
	
	/**
	 * check for spam
	 * 
	 * @return boolean $spam_detected
	 */
	public function detectSpam(array $fields = array(), $threshold = 3) {
		if(
			!isset($this->request['verify']) ||
			!isset($_SESSION['antiSpamTimer'][$this->request['verify']]) ||
			(microtime(true) - $_SESSION['antiSpamTimer'][$this->request['verify']] < 1)
		) {
			return TRUE;
		}

		$label = md5($this->request['verify']);

		if(!isset($this->request["confirm_entry_$label"]) || $this->request["confirm_entry_$label"] !== '') {
			return TRUE;
		}

		foreach($fields as $f) {
			if(preg_match_all('~<\s*a\s+href\s*\=\s*(\\\\*"|\\\\*\'){0,1}http://~i', $this->request[$f], $tmp) > $threshold) {
				return TRUE;
			}
			if(preg_match('~\[\s*url.*?\]~i', $this->request[$f])) {
				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * remove form element
	 * when $name indicates an array of elements,
	 * a single element can be picked by declaring $index_of_element_in_array
	 * 
	 * @param $name_of_element
	 * @param $index_of_element_in_array
	 */
	public function removeElementByName($name, $ndx = NULL) {
		if($ndx !== NULL) {
			unset($this->elements[$name][$ndx]);
		}
		else {
			unset($this->elements[$name]);
		}
	}

	/**
	 * remove miscellaneous markup and text to form
	 * when $id indicates an array of snippets,
	 * a single snippet can be picked by declaring $index_of_markup_in_array
	 * 
	 * @param string $markup_id
	 * @param mixed $index_of_markup_in_array
	 */
	public function removeHtmlByName($id, $ndx = NULL) {
		if(!is_null($ndx) && isset($this->miscHtml[$id][$ndx])) {
			unset($this->miscHtml[$id][$ndx]);
		}
		else if(isset($this->miscHtml[$id])) {
			unset($this->miscHtml[$id]);
		}
	}

	/**
	 * depending on form method
	 * $_POST or $_GET values are retrieved for further processing
	 */
	private function setRequestValues() {
		if($this->method == 'GET') {
			$this->request = array_map('urldecode', $GLOBALS['config']->_get);
		}
		else {
			$this->request = &$_POST;
		}
	}

	/**
	 * load template
	 * 
	 * @return $success
	 * @throws HtmlFormException
	 */
	private function loadTemplate() {

		if(!empty($this->template)) {
			return TRUE;
		}

		$path = $_SERVER['DOCUMENT_ROOT'].(defined('FORMTEMPLATES_PATH') ? FORMTEMPLATES_PATH : '');

		if(!file_exists($path.$this->tplFile)) {
			throw new HtmlFormException("Template file '$path{$this->tplFile}' does not exist.");
		}
		$this->template = @file_get_contents($path.$this->tplFile);
		return TRUE;
	}

	/**
	 * prepare template
	 * 
	 * interprets pseudo tags
	 * {loop $i} .. {end_loop}
	 * {if(cond)} .. {else} .. {end_if} 
	 * {html:$string}
	 */
	private function primeTemplate() {

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
	}

	/*
	 * unroll loops
	 */
	private function doLoop($tpl) {
		$stack = $this->loopRecursion($tpl, 0);
		$tpl = $this->unrollLoops($stack);
		return $tpl;
	}
	
	private function parseLoopVar($tpl, $counters) {
		foreach($counters as $c => $v) {
			$tpl = preg_replace('~\044'.$c.'~', $v, $tpl);
		}
		return $tpl;
	}

	private function unrollLoops($stack, $counters = array()) {

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

	private function loopRecursion($tpl, $level) {
		$stack = array();

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
					return array($right, $left);
				}
				$stack[count($stack) - 1]['right'] = $left;
				return array($right, $stack);
			}

			else {
				list($right, $inner) = $this->loopRecursion($right, $level + 1);

				$stack[] = array(
					'lvl' => $level,
					'left' => $left,
					'loopVar' => $matches[3],
					'ndx' => $matches[5],
					'inner' => $inner
				);
			}
			$tpl = $right;
		}
	}

	/*
	 * insert vars
	 */
	private function doInsertVars($tpl) {
		foreach($this->vars as $k => $v) {
			if(is_array($v)) {
				foreach($v as $kk => $vv) {
					$tpl = preg_replace("~\{\s*\044$k\[$kk\]\s*\}~i", $vv, $tpl);
				}
			}
			else {
				$tpl = preg_replace("~\{\s*\044$k\s*\}~i", $v, $tpl);
			}
		}
		return $tpl;
	}
	
	/*
	 * handle {if (cond)} .. {else} .. {end_if}
	 */
	private function doIfElseEndif($tpl) {

		$stack = array();
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
							($stack[$nesting - 1]['parentCond'] === FALSE)
						) {
							$left = '';
							$parentCond = FALSE;
							$cond = NULL;
						}
						else {
							$parentCond = TRUE;
							$cond = $this->evalCondition($matches[3]);
						}
					}
					else {
						$cond = $this->evalCondition($matches[3]);
					} 

					if(!empty($stack[$nesting]['left'])) {
						$left = $stack[$nesting]['left'].$left;
					}

					$stack[$nesting] = array(
						'left' => $left,
						'condition' => $cond,
						'parentCond' => isset($parentCond) ? $parentCond : NULL,
						'else' => FALSE);
					++$nesting;
					break;
			}
			$tpl = $right;
		}

		// append last right
		return empty($stack[0]['left']) ? $tpl : $stack[0]['left'].$right;
	}

	private function evalCondition($cond) {
		if(!preg_match('/(.*?)\s*(==|!=|<|>|<=|>=)\s*(.*)/i', $cond, $terms) || count($terms) != 4) { return null; }

		if(preg_match('/\044(.*)/', $terms[1], $tmp)) { $terms[1] = isset($this->vars[$tmp[1]]) ? $this->vars[$tmp[1]] : null; }
		if(preg_match('/\044(.*)/', $terms[3], $tmp)) { $terms[3] = isset($this->vars[$tmp[1]]) ? $this->vars[$tmp[1]] : null; }
		
		switch ($terms[2]) {
			case '==':	return $terms[1] == $terms[3];
			case '!=':	return $terms[1] != $terms[3];
			case '<':	return $terms[1] < $terms[3];
			case '>':	return $terms[1] > $terms[3];
			case '<=':	return $terms[1] <= $terms[3];
			case '>=':	return $terms[1] >= $terms[3];
		}
		return null;
	}

	/**
	 * insert form fields into template
	 */
	private function insertFormFields() {
/*		$this->html = preg_replace_callback(
			'~<\s*(dropdown|input|image|button|textarea|options|checkbox|selectbox):(\w+)(\s+.*?)*\s*\/>~i',
			array($this, 'insertFieldsCallbackNew'),
			$this->template);
*/		
		$this->html = preg_replace_callback(
			'/\{(dropdown|input|image|button|textarea|options|checkbox|selectbox):(\w+)(\s+.*?)*\}/i',
			array($this, 'insertFieldsCallback'),
			$this->template);
	}

	private function insertFieldsCallback($matches) {

		if(empty($this->elements[$matches[2]])) {
			return '';
		}

		if(is_array($this->elements[$matches[2]])) {
			$e = array_shift($this->elements[$matches[2]]);
			return $e->render();
		}
		else {
			return $this->elements[$matches[2]]->render();
		}
	}
	
	/**
	 * insert error messages into template
	 * placeholder for error messages are replaced
	 */
	private function insertErrorMessages() {

		$rex = '/\{(?:\w+\|)*error_%s(?:\|\w+)*:([^\}]+)\}/i';
		
		foreach($this->formErrors as $name => $v) {
			if(!is_array($v)) {
				$this->html = preg_replace(
					sprintf($rex, $name),
					'$1',
					$this->html);
			}
			else {
				foreach(array_keys($this->formErrors[$name]) as $ndx) {
					$this->html = preg_replace(
						sprintf($rex, $name),
						empty($this->formErrors[$name][$ndx]) ? '' : '$1',
						$this->html,
						1);
				}
			}
		}
	}

	/**
	 * cleanup HTML output
	 * removes any {} pairs
	 */
	private function cleanupHtml() {
		$this->html = preg_replace('/\{\s*(input:.*?|button:.*?|textarea:.*?|dropdown:.*?|checkbox:.*?|options:.*?|error_.*?|html:.*?|loop.*?|end_loop|\044.*?)\s*\}/i', '', $this->html);
	}

	/**
	 * Check date input
	 * depending on SITE_LOCALE constant
	 * 
	 * @param string date
	 * @param bool future allow only future dates
	 * @param string locale override
	 * @return bool result
	 */
	static function checkDateInput($datum, $future = false, $locale = null) {
		$locale = $locale == null ? (!defined('SITE_LOCALE') ? 'de' : SITE_LOCALE) : $locale;
		switch($locale) {
			case 'de':

			case 'us':
				$rex = '\d{1,2}(\.|/|\-)\d{1,2}\1\d{0,4}';

				if(!preg_match('~^'.$rex.'$~', $datum, $matches))	{ return false; }

				$tmp	= explode($matches[1], $datum);
				$tmp[2]	= strlen($tmp[2]) < 4 ? substr(date('Y'), 0, 4-strlen($tmp[2])).$tmp[2] : $tmp[2];

				if($locale == 'de'){
					if(!checkdate($tmp[1],$tmp[0],$tmp[2])) 		{ return false; }
					break;
				}
				if(!checkdate($tmp[0],$tmp[1],$tmp[2])) 			{ return false; }
				break;

			default:
				$rex = '\d{2}(\d{2})?(\.|/|\-)\d{1,2}\2\d{1,2}';
				if(!preg_match('~^'.$rex.'$~', $datum, $matches))	{ return false; }

				$tmp = explode($matches[2], $datum);
				$tmp[0]	= strlen($tmp[0]) < 4 ? substr(date('Y'), 0, 4-strlen($tmp[0])).$tmp[0] : $tmp[0];

				if(!checkdate($tmp[1],$tmp[2],$tmp[0])) 			{ return false; }
		}

		if($future) {
			$dformat = '%04d%02d%02d'; 
			switch($locale){
				case 'de':
					if(sprintf($dformat, $tmp[2],$tmp[1],$tmp[0]) < date('Ymd'))	{ return false; }
					break;
				case 'us':
					if(sprintf($dformat, $tmp[2],$tmp[0],$tmp[1]) < date('Ymd'))	{ return false; }
					break;
				default:
					if(sprintf($dformat, $tmp[0],$tmp[1],$tmp[2]) < date('Ymd'))	{ return false; }
			}
		}
		return true;
	}

	/**
	 * Check time input (H[H]:[M]M)
	 * 
	 * @param string time
	 * @return bool result
	 */
	static function checkTimeInput($time) {
		if(!preg_match('=^\d{1,2}:\d{1,2}$=', $time))	{ return false; }	//Format
		$tmp = explode(':', $time);
		if((int) $tmp[0] > 23 || (int) $tmp[1] > 59)	{ return false; }	//Werte
		return true;
	}
}

class HtmlFormException extends Exception {
}
?>