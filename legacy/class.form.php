<?php
/**
 * Template Engine for Forms
 * @version 1.1.0 2011-10-13
 * @author Gregor Kofler
 * 
 * @todo tie submit buttons to other elements of form; use $initFormValues?
 * @todo make addAntiSpam working with multiple forms
 */

class Form {

	private		$html,
				$tplFile,
				$template,
				$clickedSubmit,
				$elemHtml		= array(),
				$initFormValues	= array(),
				$formValues		= array(),
				$elemTypes		= array(),
				$elemAttribs	= array(),
				$formErrors		= array(),
				$miscHtml		= array(),
				$rules			= array(),
				$vars 			= array(),
				$allowApcUpload	= false;
	
	protected	$action,
				$type,
				$method,
				$attributes = array(),
				$error,
				$submitIndex;

	/**
	 * Constructor
	 * @param string template filename
	 * @param string action attribute
	 * @param string submit method
	 * @param string encoding type
	 * @param string css class
	 * @param string misc string
	 */
	public function __construct($template, $action = false, $method = 'POST', $type = false, $css = false, $miscstr = false) {
		$this->action	= $action ? $action : $_SERVER['REQUEST_URI'];
		$this->method	= strtoupper($method);
		$this->type		= $type;
		$this->css		= $css;
		$this->miscstr	= $miscstr;
		$this->tplFile	= $template;
	}

	public function setMethod($method) {
		$method = strtoupper($method);
		if($method != 'GET' && $method != 'POST') {
			throw new FormException("Invalid form method: $method");
		}
		$this->method = $method; 
	}

	public function setAction($action) {
		$this->action = $action;
	}

	public function setEncType($type) {
		$method = strtolower($type);
		if($type != 'application/x-www-form-urlencoded' && $type != 'multipart/form-data' && !empty($type)) {
			throw new FormException("Invalid form enctype: $type");
		}
		$this->type = $type;
	}

	public function setAttribute($attr, $value) {
		$this->attributes[$attr] = $value;
	}

	/**
	 * Returns name of submit button if submission happened, otherwise false
	 * if submitted value is array, form::submitIndex is populated
	 */
	public function getSubmit() {
		if(!empty($this->clickedSubmit)) { return $this->clickedSubmit; }

		if($this->method == 'POST' && $_SERVER['REQUEST_METHOD'] == 'POST') {
			$req = &$_POST;
		}
		elseif($this->method == 'GET' && $_SERVER['REQUEST_METHOD'] == 'GET') {
			$req = &$GLOBALS['config']->_get;
		}
		else {
			return false;
		}

		foreach($this->elemAttribs as $k => $v) {
			if($this->elemTypes[$k] == 'button' && $v['type'] == 'submit') {
				if(isset($req[$k])) {
					$this->submitIndex = is_array($req[$k]) ? array_shift(array_keys($req[$k])) : null;
					$this->clickedSubmit = $k;
					return $k;
				}
				// needed for submits via XHR, since arrays are returned as plain text
				foreach(array_keys($req) as $i) {
					if(preg_match("/^$k\[(.*?)\]$/", $i, $m)) {
						$this->submitIndex = $m[1];
						return $k;
					}
				}
			}
			
			if(isset($v['type'])) {
				if($v['type'] == 'image') {
					if(isset($req[$k])) {
						$this->submitIndex = is_array($req[$k]) ? array_shift(array_keys($req[$k])) : null;
						$this->clickedSubmit = $k;
						return $k;
					}
					if(isset($req[$k.'_x']) && isset($req[$k.'_y'])) {
						$this->submitIndex = null;
						$this->clickedSubmit = $k;
						return $k;
					}
					// needed for submits via XHR, since arrays are returned as plain text
					foreach(array_keys($req) as $i) {
						if(preg_match("/^$k\[(.*?)\]$/", $i, $m)) {
							$this->submitIndex = $m[1];
							return $k;
						}
					}
				}
				
				if($v['type'] == 'submit' || $v['type'] == 'button') {
					if(isset($req[$k])) {
						$this->submitIndex = is_array($req[$k]) ? array_shift(array_keys($req[$k])) : null;
						$this->clickedSubmit = $k;
						return $k;
					}
					// needed for submits via XHR, since arrays are returned as plain text
					foreach(array_keys($req) as $i) {
						if(preg_match("/^$k\[(.*?)\]$/", $i, $m)) {
							$this->submitIndex = $m[1];
							return $k;
						}
					}
				}
			}
		}
	}

	/**
	 * Deliver complete form HTML
	 */
	public function getHtml() {
		if(!$this->loadTemplate())	{ return false; }
		
		$this->primeTemplate();
		$this->insertFormFields();

		SimpleTemplate::parseTemplateLinks($this->html);
		SimpleTemplate::parseTemplateLocales($this->html);

		$this->insertErrorMessages();
		$this->cleanupHtml();
		
		$link = $this->action;
		if(defined('USE_MOD_REWRITE') && USE_MOD_REWRITE) {
			$link = SimpleTemplate::link2Canonical($this->action);
		}
		
		return implode('', array(
			"<form action='{$this->sanitizeValue($link)}' method='{$this->method}'",
			($this->type	? " enctype='{$this->type}'" : ''),
			($this->css		? " class='{$this->css}'" : ''),
			($this->miscstr	? " {$this->miscstr}" : '').'>',
			(isset($this->antiSpam) ? $this->antiSpam : ''),
			$this->html,
			'</form>'
		));
	}

	/**
	 * Force value of element regardless of $_POST/$_GET
	 *
	 * @param string name of element
	 * @param mixed value
	 * @param mixed index if element array
	 */
	public function forceElemValue($name, $value, $ndx = null) {
		if(!isset($this->elemTypes[$name])) { return; }

		if(is_array($this->elemTypes[$name])) {
			if($ndx != null && isset($this->elemTypes[$name][$ndx])) {
				$this->formValues[$name][$ndx] = $value;
				return;
			}
			$this->formValues[$name] = array_combine(
				array_keys($this->elemTypes[$name]),
				array_fill(0, count($this->elemTypes[$name]),
				$value));
			return;
		}

		$this->formValues[$name] = $value;
	}
	
	/**
	 * Deliver valid Form-Values
	 * @param bool also deliver submit buttons, default false 
	 * @return array valid Entries
	 */
	public function getValidFormValues($getSubmits = false) {
		$tmp = array();
		foreach($this->formValues as $k => $v) {
			if(!$getSubmits) {
				if(isset($this->elemAttribs[$k]['type']) && $this->elemAttribs[$k]['type'] == 'submit') { continue; }
				if($this->elemTypes[$k] == 'image') { continue; }
			}
			if(is_array($v)) {
				foreach($v as $kk => $vv) {
					if(empty($this->formErrors[$k][$kk])) { $tmp[$k][$kk] = $vv; }	
				}
			}
			else {
				if(empty($this->formErrors[$k])) { $tmp[$k] = $v; }
			}	
		}
		return $tmp;
	}

	/**
	 * Resets request-generated form values with the initial ones
	 * @return void
	 */
	public function resetFormValues() {
		$this->formValues = $this->initFormValues;
	}

	/**
	 * Sets form values initial form values
	 * @param array initial values of form elements 
	 * @return void
	 */
	public function setInitFormValues($values) {
		if(is_array($values)) {
			$this->initFormValues = $values;
		}
	}

	/**
	 * Deliver Errors
	 * @return mixed true if no error or array with errors 
	 */
	public function getFormErrors() {
		if(count($this->formErrors) === 0) {
			return false;
		}

		else {
			return $this->formErrors;
		}
	}

	/**
	 * Returns error texts extracted from template
	 * might come in handy with the XHR functionality
	 * returns null if extraction fails or template is missing
	 * 
	 * @param array keys; get only texts for specified fields  
	 * @return array error name => error text
	 */
	public function getErrorTexts(array $keys = null) {
		if(!$this->loadTemplate()) { return null; }
		
		$pattern = empty($keys) ? '.*?' : implode('|', $keys);
		
		preg_match_all("/\{\s*error_({$pattern}):(.*?)\}/", $this->template, $hits);
		
		if(empty($hits[1]) || empty($hits[2]) || count($hits[1]) != count($hits[2])) { return null; }
		
		return (array_combine($hits[1], array_map('strip_tags', $hits[2])));
	}
	
	/**
	 * Return error state of specified field
	 * @return bool presence of error
	 */
	public function getElementError($ndx) {
		return(!empty($this->formErrors[$ndx]));
	}

	/**
	 * Return (unchecked) value of specified field
	 * @return element value
	 */
	public function getElementValue($ndx) {
		return $this->formValues[$ndx];
	}

	/**
	 * Validate formValues with supplied rules
	 * rules are regular expressions
	 */
	public function validate() {
		foreach($this->formValues as $k => $v) {

			if(!isset($this->rules[$k])) { continue; }

			 // check array of fields
			if(is_array($v)) {
				if(count($v) == 0) {
					$this->formErrors[$k] = true;
				}
				foreach($v as $kk => $vv) {
					if(!preg_match($this->rules[$k], $vv)) {
						$this->formErrors[$k][$kk] = true;
					}
				}
			}

			 // check single field
			else {
				if(!preg_match($this->rules[$k], $v)) {
					$this->formErrors[$k] = true;
				}
			}
		}
	}

	/**
	 * Initialize a template variable
	 * array values allow "dynamic" loops an if-else constructs
	 * 
	 * @param string varname
	 * @param mixed value
	 */
	public function initVar($name, $val) {
		$this->vars[$name] = $val;
	}

	/**
	 * Add validation rule
	 * 
	 * @param string elementName
	 * @param string regEx	
	 */
	public function addRule($name, $rex) {
		$this->rules[$name] = $rex;
	}

	/**
	 * Add custom error and force error message in Template
	 * 
	 * @param string errorName
	 */
	public function setError($err, $ndx = null) {
		if($ndx === null) {
			$this->formErrors[$err] = true;
			return;
		}
		$this->formErrors[$err][$ndx] = true;
	}

	/**
	 * Add Dropdown
	 * 
	 * @param string name
	 * @param array possible values
	 * @param mixed init value: (associative) array forces element array
	 * @param string css class
	 * @param array css classes for options
	 * @param bool disabled
	 */
	public function addDropDown($name, $list, $value = null, $class = false, $optionClass = false, $disabled = false) {

		if(!isset($value)) {
			$value = isset($this->initFormValues[$name]) ? $this->initFormValues[$name] : -1;
		}
		$this->initElemValue($name, $value, 'dropdown');

		$this->elemAttribs[$name] = array(
			'list'			=> $list,
			'class'			=> $class,
			'optionClass'	=> $optionClass,
			'disabled'		=> $disabled
		);
	}

	/**
	 * Add Selectbox
	 * 
	 * @param string name
	 * @param array possible values
	 * @param mixed init value: array forces multiple select
	 * @param int size
	 * @param string css class
	 * @param array css classes for options
	 * @param bool disabled
	 */
	public function addSelectbox($name, $list, $value = null, $size = 5, $class = false, $optionClass = false, $disabled = false) {

		if(!isset($value)) {
			$value = isset($this->initFormValues[$name]) ? $this->initFormValues[$name] : -1;
		}
		$this->initElemValue($name, $value, 'selectbox');

		$this->elemAttribs[$name] = array(
			'list'			=> $list,
			'size'			=> $size,
			'class'			=> $class,
			'optionClass'	=> $optionClass,
			'disabled'		=> $disabled
		);
	}

	/**
	 * Add Input Field
	 * 
	 * @param string name
	 * @param mixed init value: (associative) array forces element array
	 * @param string type
	 * @param int max length
	 * @param string css class
	 * @param bool disabled
	 */
	public function addInput($name, $value = null, $type = null, $maxlen = 40, $class = false, $disabled = false) {
		
		$type = isset($type) ? strtolower($type) : null;
		
		if(!isset($value)) {
			$value = isset($this->initFormValues[$name]) ? $this->initFormValues[$name] : '';
		}

		$this->initElemValue($name, $value, 'input', $type !== 'submit');

		$this->elemAttribs[$name] = array(
			'type'		=> $type,
			'maxlen'	=> $maxlen,
			'class'		=> $type == 'submit' ? ($class ? "submit $class" : 'submit') : ($class ? $class : ''),
			'disabled'	=> $disabled
		);
	}

	/**
	 * Add Button Element
	 * 
	 * @param string name
	 * @param mixed init value: (associative) array forces element array
	 * @param string type (defaults to submit)
	 * @param string css class
	 * @param bool disabled
	 * @param string innerHTML
	 */
	public function addButton($name, $value, $type = 'submit', $class = false, $disabled = false, $markup = NULL) {
		
		$this->initElemValue($name, $value, 'button');

		$this->elemAttribs[$name] = array(
			'type'		=> strtolower($type),
			'class'		=> $class ? "button $class" : $class,
			'disabled'	=> $disabled,
			'markup'	=> $markup
		);
	}

	/**
	 * Add Input Image
	 * 
	 * @param string name
	 * @param string src
	 * @param mixed init value: (associative) array forces element array
	 * @param string css class
	 * @param bool disabled
	 */
	public function addImage($name, $src, $value = null, $class = false, $disabled = false) {

		if(!isset($value)) {
			$value = isset($this->initFormValues[$name]) ? $this->initFormValues[$name] : '';
		}

		$this->initElemValue($name, $value, 'image');
		
		$this->elemAttribs[$name] = array(
			'type'		=> 'image',
			'src'		=> $src,
			'class'		=> $class ? "image $class" : $class,
			'disabled'	=> $disabled
		);
	}
	
	/**
	 * Add Option Field
	 * 
	 * @param string name
	 * @param array possible values
	 * @param mixed init value: (associative) array forces element array
	 * @param string spacer: markup to seperate entries
	 * @param string css class
	 * @param bool disabled
	 */
	public function addOptions($name, $list, $value = null, $spacer = '&nbsp;', $class = false, $disabled = false) {

		if(!isset($value)) {
			$value = isset($this->initFormValues[$name]) ? $this->initFormValues[$name] : '';
		}

		$this->initElemValue($name, $value, 'options');
		
		$this->elemAttribs[$name] = array(
			'list'		=> $list,
			'spacer'	=> $spacer,
			'class'		=> $class,
			'disabled'	=> $disabled
		);
	}

	/**
	 * Add Checkbox
	 * 
	 * @param string name
	 * @param mixed init value: (associative) array forces element array
	 * @param mixed caption: string gives identical labels; (associative) array gives corresponding labels 
	 * @param string css class
	 * @param bool disabled
	 */
	public function addCheckBox($name, $value = null, $caption = '', $class = false, $disabled = false) {

		if(!isset($value)) {
			$value = isset($this->initFormValues[$name]) ? $this->initFormValues[$name] : false;
		}

		$this->initElemValue($name, $value, 'checkbox');

		$this->elemAttribs[$name] = array(
			'caption'	=> $caption,
			'class'		=> $class,
			'disabled'	=> $disabled
		);
	}

	/**
	 * Add Textarea
	 * 
	 * @param string name
	 * @param mixed init value: (associative) array forces element array
	 * @param int rows
	 * @param int columns
	 * @param string css style
	 * @param bool disabled
	 */
	public function addTextarea($name, $value = null, $rows = 10, $cols = 40, $class = false, $disabled = false) {

		if(!isset($value)) {
			$value = isset($this->initFormValues[$name]) ? $this->initFormValues[$name] : '';
		}

		$this->initElemValue($name, $value, 'textarea');
				
		$this->elemAttribs[$name] = array(
			'rows'		=> $rows,
			'cols'		=> $cols,
			'class'		=> $class,
			'disabled'	=> $disabled
		);
	}

	/**
	 * Add misc Markup and Text
	 * 
	 * @param string id
	 * @param mixed value
	 */
	public function addMiscHtml($id, $value) {
		$this->miscHtml[$id] = $value;
	}

	/**
	 * Enable APC upload if supported by server
	 */
	public function enableApcUpload() {
		$this->allowApcUpload = $GLOBALS['config']->server['apc_on'];
	}

	/**
	 * Disable APC upload if supported by server
	 */
	public function disableApcUpload() {
		$this->allowApcUpload = false;
	}

	/**
	 * Add anti spam elements
	 */
	public function addAntiSpam() {
		$secret = md5(uniqid(null, true));
		$label = md5($secret);

		$_SESSION['antiSpamTimer'][$secret]	= microtime(true);
		
		$this->addInput('verify', 0, 'hidden');
		$this->forceElemValue('verify', $secret);
		$this->buildElemHtml('verify');

		$this->antiSpam = $this->elemHtml['verify'];
		$this->antiSpam =	"
			<div>{$this->elemHtml['verify']}
				<span style='display:none;'>
					<label for='confirm_entry_$label'>Leave this field empty!</label>
					<input id='confirm_entry_$label' name='confirm_entry_$label' value=''>
				</span>
			</div>";
	}
	
	/**
	 * Check for spam
	 * 
	 * @return bool spam detected
	 */
	public function detectSpam($fields = array(), $threshold = 3) {
		if($this->method == 'GET') {
			$req = &$GLOBALS['config']->_get;
		}
		else {
			$req = &$_POST;
		}

		if(
			!isset($req['verify']) ||
			!isset($_SESSION['antiSpamTimer'][$req['verify']]) ||
			(microtime(true) - $_SESSION['antiSpamTimer'][$req['verify']] < 1)
		) {
			return true;
		}

		$label = md5($req['verify']);

		if(!isset($req["confirm_entry_$label"]) || $req["confirm_entry_$label"] !== '') {
			return true;
		}

		foreach($fields as $f) {
			if(preg_match_all('~<\s*a\s+href\s*\=\s*(\\\\*"|\\\\*\'){0,1}http://~i', $req[$f], $tmp) > $threshold) {
				return true;
			}
			if(preg_match('~\[\s*url.*?\]~i', $req[$f])) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Remove form element
	 * 
	 * @param name
	 * @param ndx
	 */
	public function removeElement($name, $ndx = null) {
		if($ndx !== null) {
			$this->initFormValues[$name][$ndx] = null;
			return;
		}
		unset($this->elemTypes[$name]);
	}

	/**
	 * Remove misc html
	 * 
	 * @param name
	 * @param ndx
	 */
	public function removeHtml($name, $ndx = null) {
		if($ndx !== null) {
			if(isset($this->miscHtml[$name][$ndx])) { $this->miscHtml[$name][$ndx] = ''; }
			return;
		}
		if(isset($this->miscHtml[$name])) { $this->miscHtml[$name] = ''; }
	}
	
	/**
	 * load template
	 * @return bool success
	 */
	private function loadTemplate() {
		if(!empty($this->template)) { return true; }

		$path = $_SERVER['DOCUMENT_ROOT'].(defined('FORMTEMPLATES_PATH') ? FORMTEMPLATES_PATH : '');

		if(!file_exists($path.$this->tplFile)) {
			$this->error = 'Template file does not exist.';
			return false;
		}
		$this->template = @file_get_contents($path.$this->tplFile);
		return true;
	}

	/**
	 * prepare template
	 * 
	 * interpreted Meta tags
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
	 * Insert form fields into template
	 */
	private function insertFormFields() {
		$this->html = preg_replace_callback(
			'~<\s*(dropdown|input|image|button|textarea|options|checkbox|selectbox):(\w+)(\s+.*?)*\s*/>~i',
			array($this, 'insertFieldsCallbackNew'),
			$this->template);
		
		$this->html = preg_replace_callback(
			'/\{(dropdown|input|image|button|textarea|options|checkbox|selectbox):(\w+)(\s+.*?)*\}/i',
			array($this, 'insertFieldsCallback'),
			$this->template);
	}

	private function insertFieldsCallbackNew($matches) {
		if(isset($this->elemTypes[$matches[2]])) {
			if(empty($this->elemHtml[$matches[2]])) {
				$this->buildElemHtml($matches[2]);
			}
			if(is_array($this->elemHtml[$matches[2]])) {
				return array_shift($this->elemHtml[$matches[2]]);
			}
			return $this->elemHtml[$matches[2]];
		}
	}
	
	private function insertFieldsCallback($matches) {
		if(isset($this->elemTypes[$matches[2]])) {
			if(empty($this->elemHtml[$matches[2]])) {
				$this->buildElemHtml($matches[2]);
			}
			if(is_array($this->elemHtml[$matches[2]])) {
				return array_shift($this->elemHtml[$matches[2]]);
			}
			return $this->elemHtml[$matches[2]];
		}
	}
	
	/**
	 * Insert error messages into template
	 * all form fields are checked, whether they need validation or not
	 * error templates are removed
	 */
	private function insertErrorMessages() {

		$rex = '/\{(?:\w+\|)*error_%s(?:\|\w+)*:([^\}]+)\}/i';
		foreach($this->formErrors as $k => $v) {
			if(!is_array($v)) {
				$this->html = preg_replace(
					sprintf($rex, $k),
					'$1',
					$this->html);
			}
			else {
				foreach(array_keys($this->formValues[$k]) as $kk) {
					$this->html = preg_replace(
						sprintf($rex, $k),
						empty($this->formErrors[$k][$kk]) ? '' : '$1',
						$this->html,
						1);
				}
			}
		}
	}

	/**
	 * Cleanup HTML output
	 * removes any {} pairs
	 */
	private function cleanupHtml() {
		$this->html = preg_replace('/\{\s*(input:.*?|button:.*?|textarea:.*?|dropdown:.*?|checkbox:.*?|options:.*?|error_.*?|html:.*?|loop.*?|end_loop|\044.*?)\s*\}/i', '', $this->html);
	}

	/**
	 * Initialize value of a form field
	 * value is either provided by
	 * $_GET | $_POST or
	 * the supplied default value
	 * 
	 * @param string fieldname
	 * @param mixed default value
	 * @param type type of element
	 * @param bool sanitize value before storing
	 */
	private function initElemValue($n, $v, $type, $clean = true) {
		
		$this->elemTypes[$n] = $type;

		if($this->method == 'GET') {
			$req = &$GLOBALS['config']->_get;
		}
		else {
			$req = &$_POST;
		}
		
		if($type == 'checkbox' && !empty($req)) {
			if(is_array($v)) {
				foreach($v as $kk => $vv) {
					$this->initFormValues[$n][$kk]	= $vv ? 1 : 0;
					$this->formValues[$n][$kk]		= !isset($req[$n][$kk]) ? 0 : 1;
				}
				return;
			}
			$this->initFormValues[$n]	= $v ? 1 : 0;
			$this->formValues[$n]		= !isset($req[$n]) ? 0 : 1;
			return;
		}

		if($clean) {
			$v = is_array($v) ? array_map(array($this, 'sanitizeValue'), $v) : $this->sanitizeValue($v);
		}
			
		if($type === 'selectbox' && !empty($req) && empty($req[$n])) {
			$this->initFormValues[$n]	= $v;
			$this->formValues[$n]		= array();
			return;
		}

		if(isset($req[$n])) {
			$this->initFormValues[$n] = $v;

			if(!is_array($req[$n])) {
				$this->formValues[$n] = $this->sanitizeValue($req[$n]);
			}
			else {
				if($type === 'selectbox') {
					$this->formValues[$n] = array_map(array($this, 'sanitizeValue'), $req[$n]);
				}
				else {
					$this->formValues[$n] = array_map(array($this, 'sanitizeValue'), $req[$n])+$v;
				}
			}
			return;
		}
		
		$this->initFormValues[$n]	= $v;
		$this->formValues[$n]		= $v;
	}
	
	private function sanitizeValue($v) {
		return is_null($v) ? null : htmlspecialchars($v, ENT_QUOTES);
	}

	/**
	 * Build HTML output of specified element
	 * 
	 * IMPORTANT NOTICE:
	 * If the values are stored in an array (which gives multiple elements),
	 * a value of NULL will produce an empty string, allowing the elimination of single elements
	 * currently doesn't work with list fields with multiple selects enabled
	 * 
	 * @param string name
	 */
	private function buildElemHtml($n) {
		static $apcUploadAdded = false;

		switch($this->elemTypes[$n]) {

			case 'input':
				$prefix = '';

				if($this->elemAttribs[$n]['type'] == 'file' && !$apcUploadAdded && $this->allowApcUpload) {
					$apcUploadAdded = true;
					if($GLOBALS['config']->server['apc_on']) {
						$prefix ='<span style="display: none;"><input type="hidden" name="APC_UPLOAD_PROGRESS" value="'.uniqid('apc_').'"></span>';
					}
				}

				$attr =
					($this->elemAttribs[$n]['type'] == null	? 'type="text"' : " type='{$this->elemAttribs[$n]['type']}'").
					($this->elemAttribs[$n]['type'] == null	|| $this->elemAttribs[$n]['type'] == 'text' || $this->elemAttribs[$n]['type'] == 'password' ? " maxlength='{$this->elemAttribs[$n]['maxlen']}'" : '').
					($this->elemAttribs[$n]['class'] ? " class='{$this->elemAttribs[$n]['class']}'" : '').
					($this->elemAttribs[$n]['disabled']	? ' disabled' : '');

				if(!is_array($this->formValues[$n])) {
					$this->elemHtml[$n] = "$prefix<input name='$n' value='{$this->formValues[$n]}' $attr>";
					return;
				}
				foreach($this->formValues[$n] as $k => $v) {
					$this->elemHtml[$n][$k] = is_null($v) ?
						$prefix :
						"$prefix<input name='{$n}[{$k}]' value='{$this->formValues[$n][$k]}' $attr>";
					$prefix = '';
				}
				return;
			
			case 'button':
				$attr =
					"type='{$this->elemAttribs[$n]['type']}'".
					($this->elemAttribs[$n]['class'] ? " class='{$this->elemAttribs[$n]['class']}'" : '').
					($this->elemAttribs[$n]['disabled']	? ' disabled' : '');

				if(!is_array($this->formValues[$n])) {
					$markup = isset($this->elemAttribs[$n]['markup']) ? $this->elemAttribs[$n]['markup'] : $this->formValues[$n];

					$this->elemHtml[$n] = "
						<button name='$n' value='{$this->formValues[$n]}' $attr>$markup</button>";
					return;
				}
				foreach($this->formValues[$n] as $k => $v) {
					$markup = isset($this->elemAttribs[$n]['markup']) ? $this->elemAttribs[$n]['markup'] : $this->formValues[$n][$k];

					$this->elemHtml[$n][$k] = is_null($v) ? '' : "
						<button name='{$n}[{$k}]' value='{$this->formValues[$n][$k]}' $attr>{$this->formValues[$n][$k]}</button>";
				}
				return;

			case 'image':
				$attr =
					"src='{$this->elemAttribs[$n]['src']}'".
					($this->elemAttribs[$n]['class'] ? " class='{$this->elemAttribs[$n]['class']}'" : '').
					($this->elemAttribs[$n]['disabled']	? ' disabled' : '');

				if(!is_array($this->formValues[$n])) {
					$this->elemHtml[$n] = "
						<input type='image' name='$n' value='{$this->formValues[$n]}' $attr>";
					return;
				}
				foreach($this->formValues[$n] as $k => $v) {
					$this->elemHtml[$n][$k] = is_null($v) ? '' : "
						<input type='image' name='{$n}[{$k}]' value='{$this->formValues[$n][$k]}' $attr>";
				}
				return;
				
			case 'checkbox':
				$attr = 
					($this->elemAttribs[$n]['class']	? " class='ieCompat {$this->elemAttribs[$n]['class']}'" : "class='ieCompat'").
					($this->elemAttribs[$n]['disabled']	? ' disabled' : '');
				
				if(!is_array($this->formValues[$n])) {
					$caption = is_array($this->formValues[$n]) ? array_shift($this->elemAttribs[$n]['caption']) : $this->elemAttribs[$n]['caption'];

					$this->elemHtml[$n] = "
						$caption<input type='checkbox' name='$n' value='1'".
						(empty($this->formValues[$n]) ? '' : ' checked')." $attr>";
					return;
				}

				$defaultCaption = !is_array($this->elemAttribs[$n]['caption']) ? $this->elemAttribs[$n]['caption'] : '';  

				foreach($this->formValues[$n] as $k => $v) {
					$caption = is_array($this->elemAttribs[$n]['caption']) && isset($this->elemAttribs[$n]['caption'][$k]) ? $this->elemAttribs[$n]['caption'][$k] : $defaultCaption;

					$this->elemHtml[$n][$k] = is_null($v) ? '' : "
						<input type='checkbox' name='{$n}[{$k}]' value='1'".
						($v != 0 ? ' checked' : '')." $attr>$caption";
				}
				return;

			case 'textarea':
				$attr =
 					"rows='{$this->elemAttribs[$n]['rows']}' cols='{$this->elemAttribs[$n]['cols']}'".		
					($this->elemAttribs[$n]['class']	? " class='{$this->elemAttribs[$n]['class']}'" : '').
					($this->elemAttribs[$n]['disabled']	? ' disabled' : '');
				
				if(!is_array($this->formValues[$n])) {
					$this->elemHtml[$n] = "<textarea name='$n' $attr>{$this->formValues[$n]}</textarea>";
					return;
				}
				foreach($this->formValues[$n] as $k => $v) {
					$this->elemHtml[$n][$k] = is_null($v) ? '' : "<textarea name='{$n}[{$k}]' $attr>{$this->formValues[$n][$k]}</textarea>";
				}
				return;

			case 'options':
				$attr =
					($this->elemAttribs[$n]['class'] ? " class='ieCompat {$this->elemAttribs[$n]['class']}'" : ' class="ieCompat"').
					($this->elemAttribs[$n]['disabled'] ? ' disabled' : '');
				
				if(!is_array($this->formValues[$n])) {
					foreach ($this->elemAttribs[$n]['list'] as $kk => $vv) {
						$h[] = "
							<input type='radio' name='$n' value='$kk' $attr".
							($kk == $this->formValues[$n] ? ' checked="checked"' : '').">$vv";
					}
					$this->elemHtml[$n] = implode($this->elemAttribs[$n]['spacer'], $h);
					return;
				}
				foreach($this->formValues[$n] as $k => $v) {
					if(is_null($v)) {
						$this->elemHtml[$n][$k] = '';
					}
					else {
						foreach ($this->elemAttribs[$n]['list'] as $kk => $vv) {
							$h[] = "
								<input type='radio' name='{$n}[{$k}]' value='$kk' $attr".
								($kk == $this->formValues[$n][$k] ? ' checked="checked"' : '').">$vv";
						}
						$this->elemHtml[$n][$k] = implode($this->elemAttribs[$n]['spacer'], $h);
					}
				}
				return;

			case 'selectbox':
				$attr =
					"size='{$this->elemAttribs[$n]['size']}'".
					($this->elemAttribs[$n]['class'] ? " class='{$this->elemAttribs[$n]['class']}'" : '').
					($this->elemAttribs[$n]['disabled'] ? ' disabled' : '');
				
				if(!is_array($this->formValues[$n])) {
					$this->elemHtml[$n] = "<select name='$n' $attr>";
					foreach ($this->elemAttribs[$n]['list'] as $k => $v) {
						$this->elemHtml[$n] .= '
							<option'.(isset($this->elemAttribs[$n]['optionClass'][$k]) ? " class='{$this->elemAttribs[$n]['optionClass'][$k]}'" : '').
							" value='$k'".($k == $this->formValues[$n] ? " selected" : '').">$v</option>";
					}
					$this->elemHtml[$n] .= '</select>';
					return;
				}

				$this->elemHtml[$n] = "<select name='{$n}[]' multiple='multiple' $attr>";
				foreach ($this->elemAttribs[$n]['list'] as $k => $v) {
					$this->elemHtml[$n] .= '
						<option'.(isset($this->elemAttribs[$n]['optionClass'][$k]) ? " class='{$this->elemAttribs[$n]['optionClass'][$k]}'" : '').
						" value='$k'".(in_array($k, $this->formValues[$n]) ? " selected" : '').">$v</option>";
				}
				$this->elemHtml[$n] .= '</select>';
				return;

			case 'dropdown':
				$attr =
					"size='1'".
					($this->elemAttribs[$n]['class'] ? " class='{$this->elemAttribs[$n]['class']}'" : '').
					($this->elemAttribs[$n]['disabled'] ? ' disabled' : '');

				if(!is_array($this->formValues[$n])) {
					$this->elemHtml[$n] = "<select name='$n' $attr>";
					foreach ($this->elemAttribs[$n]['list'] as $k => $v) {
						$this->elemHtml[$n] .= '
							<option'.(isset($this->elemAttribs[$n]['optionClass'][$k]) ? " class='{$this->elemAttribs[$n]['optionClass'][$k]}'" : '').
							" value='$k'".($k == $this->formValues[$n] ? " selected" : '').">$v</option>";
					}
					$this->elemHtml[$n] .= '</select>';
					return;
				}
				foreach($this->formValues[$n] as $k => $v) {
					if(is_null($v)) {
						$this->elemHtml[$n][$k] = '';
					}
					else { 
						$this->elemHtml[$n][$k] = "<select name='${n}[{$k}]' $attr>";
						foreach ($this->elemAttribs[$n]['list'] as $kk => $vv) {
							$this->elemHtml[$n][$k] .= '
								<option'.(isset($this->elemAttribs[$n]['optionClass'][$kk]) ? " class='{$this->elemAttribs[$n]['optionClass'][$kk]}'" : '').
								" value='$kk'".($kk == $this->formValues[$n][$k] ? " selected" : '').">$vv</option>";
						}
						$this->elemHtml[$n][$k] .= '</select>';
					}
				}
		}
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

class FormException extends Exception {
}
?>