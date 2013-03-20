<?php
/**
 * htmlForm
 * 1.33
 * 2007-03-04
 */

class htmlForm {

	var $html;
	var $action;
	var $type;
	var $miscstr;

	var $template;
	var $emptyReplace = '';

	var $errstr;


	/**
	 * Konstruktor
	 */
	function htmlForm($action, $method, $type = '', $miscstr = '') {
		$this->action	= $action;
		$this->method	= $method;
		$this->type		= $type;
		$this->miscstr	= $miscstr;
		$this->errstr	= '';
	}

	/**
	 * Templatefile laden
	 */
	function loadTemplate($tfile) {
		if(!file_exists($tfile)) {
			$this->errstr = 'Template file does not exist.';
			return false;
		}
		$this->template = @file_get_contents($tfile);
		return true;
	}

	/**
	 * Formular-HTML abholen
	 */
	function getForm($empty = null) {
		if(!empty($this->errstr)) {
			return false;
		}

		if(!empty($empty)) {
			$this->emptyReplace = $empty;
		}

		$html = preg_replace('~\{\/?(html|link|input|select|textarea).*\}~i', $this->emptyReplace, $this->template);
		return '
			<form action="'.$this->action.'" method="'.$this->method.'"'.(!empty($this->enctype) ? ' enctype="'.$this->enctype.'"' : '').(!empty($this->miscstr) ? ' '.$this->miscstr : '').'>
				'.$html.'
			</form>
		';
	}

	/**
	 * Formularfeld oder Hyperlink in Template einfügen
	 * wird $name angegeben, werden alle Platzhalter mit $name ersetzt, sonst nur der erste gefundene
	 * @mixed html:		string - einfaches Replace, array(string implode_str, array entries)
	 * @string type:	html | link | input | select | textarea
	 */
	function insertField($html, $type, $name = null) {
		if(!empty($this->errstr)) {
			return false;
		}
		if(is_array($html)) {
			list($implode_str, $entries) = $html;
			if(!is_array($entries)) {
				return false;
			}
			$html = implode($implode_str, $entries);
		}

		if(empty($name)) {
			$this->template = preg_replace("~\{$type.*\}~i", $html, $this->template, 1);
		}
		else {
			$this->template = preg_replace("~\{$type:\s*$name\}~i", $html, $this->template);
		}
		return true;
	}

	/**
	 * Kombinationsschaltfläche
	 */
	function buildCBox($name, $liste, $selektiert = -1, $style = false, $optionStyle = null, $disabled = false, $miscstr = false) {
		$html =		'<select name="'.$name.'" size="1"'.(!$style ? '' : ' class="'.$style.'"');
		$html .=	!$miscstr	? '' : ' '.$miscstr;
		$html .=	!$disabled	? '>' : ' disabled>';
		$q = 0;
		if ($selektiert == -1) {
			foreach ($liste as $index => $wert) {
				$html .= '<option '.($optionStyle ? 'class="'.$optionStyle[$q].'" ' : '').'value="'.$index.'">'.htmlentities(stripslashes($wert));
				$q++;
			}
		}
		else {
			foreach ($liste as $index => $wert) {
				$html .= '<option '.($optionStyle ? 'class="'.$optionStyle[$q].'" ' : '').'value="'.$index.'"'.($index == $selektiert ? ' selected' : '').'>'.htmlentities(stripslashes($wert));
				$q++;
			}
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * Listenfeld
	 */
	function buildListField($name, $liste, $rows, $multiple = false, $selektiert = null, $style = null, $optionStyle = null, $disabled = false) {
		$html =		'<select name="'.$name.'" size='.$rows;
		$html .=	$multiple ? ' multiple' : '';
		$html .= 	(!$style ? '>' : ' class="'.$style.'"');
		$html .=	!$disabled	? '>' : ' disabled>';
		$q = 0;
		if (is_null($selektiert)) {
			foreach ($liste as $index => $wert) {
				$html .= '<option '.($optionStyle ? 'class='.$optionStyle[$q].' ' : '').'value='.$index.'>'.htmlentities(stripslashes($wert));
				$q++;
			}
		}
		else {
			settype($selektiert, 'array');
			foreach ($liste as $index => $wert) {
				$html .= '<option '.($optionStyle ? 'class='.$optionStyle[$q].' ' : '').'value='.$index.(in_array($index, $selektiert) ? ' selected' : '').'>'.htmlentities(stripslashes($wert));
				$q++;
			}
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * Eingabefeld
	 */
	function buildInput($name, $wert = '', $type = null, $maxlen = 0, $style = false, $disabled = false, $miscstr = false) {
		$type =		$type == null ?  null : strtolower($type);
		if($type == 'submit') {
			$style = 'submit'.($style !== false ? ' '.$style : '');
		}
		$html =		'<input name="'.$name.'"';
		$html .=	($type == null || $type == 'text' || $type == 'password') ? ' maxlength="'.($maxlen != 0 ? $maxlen : 40).'"' : '';
		$html .=	' value="'.($type == null ? htmlentities(stripslashes($wert)) : $wert).'"';
		$html .=	$type == null	? '' : ' type="'.$type.'"';
		$html .=	!$style			? '' : ' class="'.$style.'"';
		$html .=	!$miscstr		? '' : ' '.$miscstr;
		$html .=	!$disabled		? '>' : ' disabled>';
		return $html;
	}

	/**
	 * Checkbox
	 */
	function buildCheckBox($name, $value = 1, $checked = false, $caption = '', $style = false, $disabled = false, $miscstr = false) {
		$html =		'<input type="checkbox" name="'.$name.'" value="'.$value.'"';
		$html .=	!$checked	? '' : ' checked';
		$html .=	!$style		? ' class="ieCompat"' : ' class="ieCompat '.$style.'"';
		$html .=	!$miscstr	? '' : ' '.$miscstr;
		$html .=	!$disabled	? '>' : ' disabled>';
		$html .=	$caption;
		return $html;
	}

	/**
	 * Textarea
	 */
	function buildTextarea($name, $wert = '', $rows = 10, $cols = 40, $style = false) {
		$html =		'<textarea name="'.$name.'" rows='.$rows.' cols='.$cols;
		$html .=	!$style ? '>' : ' class="'.$style.'">';
		$html .=	htmlentities(stripslashes($wert)).'</textarea>';
		return $html;
	}

	/**
	 * Radiobuttons
	 */
	function buildRadio($name, $wert, $selected = null, $vertical = false, $style = false, $disabled = false, $miscstr = null) {
		$suffix =
		$html = '';
		foreach($wert as $k => $v) {
			$html .= '
				<input type=radio name="'.$name.'" value="'.$k.'"'.
				($style ? ' class="ieCompat '.$style.'"' : ' class="ieCompat"').
				($selected !== null && $k == $selected ? ' checked' : '');

				if(is_array($miscstr)) {
					$html .= ' '.array_shift($miscstr);
				}
				else if($miscstr !== null) {
					$html .= ' '.$miscstr;
				}
				$html .= !$disabled ? '' : ' disabled';
				$html .= '>'.$v.($vertical ? '<br>' : '');
		}
		return $html;
	}

	/**
	 * per JS generiertes Dropdownfeld,
	 * welches Vorselektion für ein zugeordnetes Listen-/Dropdownfeld ermöglicht
	 */
	function preselectCBox($affectedElem, $entries, $affectedEntries, $separatorHTML = 'br', $style = null, $prefix = 'preSelect_') {

		$elemValues		= '"'.implode('","', array_keys($entries)).'"';
		$elemText		= '"'.implode('","', array_values($entries)).'"';

		$js = '
		<script type="text/javascript">

			var affectedElementId	= "'.$affectedElem.'";
			var affectingPrefix		= "'.$prefix.'";

			var elemValues			= new Array('.$elemValues.');
			var elemText			= new Array('.$elemText.');
			var availableValues		= new Array();
			var availableTexts		= new Array();

//			if(window.addEventListener) {
//				window.addEventListener("load", createDropdownPreSelect, false);
//			}
//			else if(window.attachEvent) {
//		   		window.attachEvent("onload", createDropdownPreSelect);
//			}
			window.onload			= createDropdownPreSelect;

		';

		foreach($entries as $k => $v){
			if(!empty($affectedEntries[$k])) {
				$js .= "availableValues[$k]	= new Array('".implode("','",array_keys($affectedEntries[$k]))."');\r\n";
				$js .= "availableTexts[$k]	= new Array('".implode("','",array_values($affectedEntries[$k]))."');\r\n";
			}
		}

		$js .= '
			function createDropdownPreSelect() {
				var newSelect			= document.createElement("select");
				var eP					= document.getElementById(affectedElementId).parentNode;
				var affectedSelection	= document.getElementById(affectedElementId).value;
				var preselectSelection	= 0;
				var i, j;

			ende:
				for(i = 0; i < elemValues.length; i++) {
					for(j = 0; j < availableValues[elemValues[i]].length; j++) {
						if(availableValues[elemValues[i]][j] == affectedSelection) {
							preselectSelection	= elemValues[i];
							break ende;
						}
					}
				}

				newSelect.id	= affectingPrefix+affectedElementId;
				'.(!empty($style) ? "
				newSelect.className = '$style';
				" : '').'

				eP.insertBefore(newSelect, document.getElementById(affectedElementId));
				'.(!empty($separatorHTML) ? "
				var newSep = document.createElement('$separatorHTML');
				eP.insertBefore(newSep, document.getElementById(affectedElementId));
				" : '').'

				for(i = 0; i < elemValues.length; i++) {
					opt			= document.createElement("option");
					opt.value	= elemValues[i];
					if(opt.value == preselectSelection) {
						opt.selected = true;
					}
					opt.appendChild(document.createTextNode(elemText[i]));
			 		newSelect.appendChild(opt);
			 	}

				if (newSelect.addEventListener) {
			   		newSelect.addEventListener("change",onchangeHandler,true);
			   	}
				else if (newSelect.attachEvent) {
			   		newSelect.attachEvent("onchange",onchangeHandler);
			   	}
				onchangeHandler();
			}

			function onchangeHandler() {
				var sel			= document.getElementById(affectingPrefix+affectedElementId).value;
				var vals		= availableValues[sel];
				var texts		= availableTexts[sel];
				var nowSelected	= document.getElementById(affectedElementId).value;

				document.getElementById(affectedElementId).options.length = 0;

				for(i = 0; i < vals.length; i++) {
					opt			= document.createElement("option");
					opt.value	= vals[i];
					if(opt.value == nowSelected) {
						opt.selected = true;
					}
					opt.appendChild(document.createTextNode(texts[i]));
			 		document.getElementById(affectedElementId).appendChild(opt);
			 	}
			}
		</script>
		';
		return $js;
	}

	/**
	 * Datumseingabe prüfen (D[D].M[M].YYYY)
	 * liefert  false wenn Datum ungültig, sonst Datum im Format YYYY-MM-DD
	 * future-Flag erlaubt Kontrolle auf zukünftiges Datum
	 * @param string datum
	 * @param bool future
	 * @param bool override delivers date always as YYYY-MM-DD string 
	 * @return string datum
	 */
	function checkDateInput($datum, $future = false, $return = false) {
		if(!preg_match('=^\d{1,2}\.\d{1,2}\.\d{0,4}$=', $datum))	{ return false; }	//Format
		$tmp = explode('.', $datum);
		if(empty($tmp[2])) {
			$tmp[2] = date('Y');
		}
		else {
			$tmp[2] = strlen($tmp[2]) < 4 ? substr(date('Y'), 0, 4-strlen($tmp[2])).$tmp[2] : $tmp[2];
		}
		if(!checkdate($tmp[1],$tmp[0],$tmp[2])) 					{ return false; }	//Werte

		$tmp[0] = str_pad($tmp[0],2,'0',STR_PAD_LEFT);
		$tmp[1] = str_pad($tmp[1],2,'0',STR_PAD_LEFT);

		if($future) {
			if($tmp[2].$tmp[1].$tmp[0] < date('Ymd'))				{ return false; }	//Vergangenheit
		}
		if(defined('MSSQL_DATE') && MSSQL_DATE == true && $return == false) {
			return $tmp[2].'-'.$tmp[0].'-'.$tmp[1];
		}
		return $tmp[2].'-'.$tmp[1].'-'.$tmp[0];
	}

	/**
	 * Zeiteingabe prüfen ([h]h{:,/}[m]m)
	 * liefert  false wenn Uhrzeit ungültig, sonst Uhrzeit im Format hh:mm
	 */
	function checkTimeInput($uhrzeit) {
		$parts = preg_split('~[:,/\.]{1}~', $uhrzeit);
		if(count($parts) > 2) { return false; }
		if((!is_numeric($parts[0]) || intval($parts[0]) != $parts[0]) || $parts[0] < 0 || $parts[0] > 23 ) { return false; }
		if((!is_numeric($parts[1]) || intval($parts[1]) != $parts[1]) || $parts[1] < 0 || $parts[1] > 59 ) { return false; }
		return sprintf('%02d:%02d', $parts[0], $parts[1]);
	}

	/**
	 * Eingabefeld auf Integerwerte prüfen
	 * optional mit einer Bereichseinschränkung
	 */
	function checkIntInput($int, $min = null, $max = null) {
		if(!is_numeric($int) || intval($int) != $int) { return false; }
		if(!is_null($min)) {
			if($int < $min) { return false; }
		}
		if(!is_null($max)) {
			if($int > $max) { return false; }
		}
		return true;
	}
}
?>