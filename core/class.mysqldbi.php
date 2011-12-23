<?php
/**
 * mysqldbi
 * DB-Wrapper
 * facilitates mysqli functionality
 * 
 * @extends mysqli
 * 
 * @version 4.6.2 2011-12-23
 * @author Gregor Kofler
 * 
 * @todo execute is "ambiguous" as deprecated alias for mysqli_stmt_execute
 */

class Mysqldbi extends mysqli {
	private	$host,
			$user,
			$pass,
			$dbname;

	private $handleErrors	= true,
			$logErrors		= true,
			$logtype		= null;

	private $lastErrno,
			$lastError;

	private	$createField	= 'firstCreated',
			$updateField	= 'lastUpdated',
			$sortField		= 'CustomSort',
			$primaryKeys;

	private	$queryString;

	private $preparedQueryString;
	private $statement;	

	public	$queryResult;
	public	$numRows,
			$affectedRows;

	public function __construct($dbname = '', $dbhost = '', $dbuser = '', $dbpass = '') {
		if(!isset($GLOBALS['config'])) {
			throw new Exception('Missing config object!');
		}
		$c = &$GLOBALS['config'];
		if($dbname == '' &&(!isset($c->db) || !isset($c->db->name))) {
			throw new Exception('Database credentials not configured!');
		}
		
		$this->logtype	= isset($c->db->logtype) && strtolower($c->db->logtype) == 'xml' ? 'xml' : 'plain';

		$this->host		= $dbhost != '' ? $dbhost : $c->db->host;
		$this->user		= $dbuser != '' ? $dbuser : $c->db->user;
		$this->pass		= $dbpass != '' ? $dbpass : $c->db->pass;
		$this->dbname	= $dbname != '' ? $dbname : $c->db->name;
		
		@parent::__construct($this->host,$this->user,$this->pass, $this->dbname);
		if (mysqli_connect_errno() !== 0) {
			throw new MysqldbiException(sprintf('Mysqldbi Error: %s (%s)', mysqli_connect_error(), mysqli_connect_errno()));
		}

		if(defined('DEFAULT_ENCODING') && strtolower(DEFAULT_ENCODING) == 'utf-8') {
			$this->execute('set names \'utf8\'');
		}
	}
	
	public function __destruct() {
		$this->close();
	}

	public function setLogErrors($state) {
		$this->logError = !!$state;
	}

	/**
	 * execute (select) query
	 * 
	 * @param string $querystrstring
	 * @param bool $processRessource, process ressource and return result as array
	 * @param mixed $callbacks, callback functions to be executed prior to result returning 
  	 * @return mixed result
	 **/
	public function doQuery($querystr, $processRessource = false, $callbacks = null) {
		$this->queryString = $querystr;

		if(!$this->queryResult = @$this->query($querystr)) {
			$this->setError();
			return false;
		}
		$this->numRows = $this->queryResult->num_rows;

		if(!$processRessource) {
			return true;
		}
		
		$result = array();
		while($r = $this->queryResult->fetch_assoc()) {
			foreach((array)$callbacks as $c) {
				$r = array_map($c, $r);
			}
			$result[] = $r;
		}
		return $result; 
	}

	/**
	 * executes a prepared (select) query and returns result in array
	 * a previously executed statement is stored for (immediate) reuse
	 *
	 * @param string $statement, the query statement; when null re-use previous statement
	 * @param array $parameters, array with parameters; datatype of parameter determines binding
	 * @return array $result
	 */
	public function doPreparedQuery($statement, array $parameters = array(), $callbacks = null) {
		if(empty($statement) && empty($this->preparedQueryString)) {
			return array();
		}

		if(!empty($statement) && $this->preparedQueryString != $statement) {
			$this->preparedQueryString = $statement;
			$this->statement = $this->prepare($statement);
		}

		$type = '';
		$paramByRef = array();

		foreach($parameters as $k => $v) {
			switch (gettype($v)) {
				case 'integer':	$type .= 'i'; break;
				case 'double':	$type .= 'd'; break;
				case 'string':	$type .= 's'; break;
				default:		throw new MysqldbiException('Invalid datatypes for prepared query! Only string, integer and double are allowed.');
			}
			$paramByRef[$k] = &$parameters[$k];
		}

		array_unshift($paramByRef, $type);

		if(!empty($parameters)) {
			if(!@call_user_func_array(array($this->statement, 'bind_param'), $paramByRef)) {
				$this->setError(true, array_merge((array) $type, $parameters));
				return FALSE;
			}
		}
		if(!$this->statement->execute()) {
			$this->setError(true, array_merge((array) $type, $parameters));
			return FALSE;
		}

		$result = array();
		while($r = $this->statement->fetch_assoc()) {
			foreach((array)$callbacks as $c) {
				$r = array_map($c, $r);
			}
			$result[] = $r;
		}
		$this->numRows = count($result);
		return $result;
	}
	
	/**
	 * execute non-select query (UPDATE, INSERT, ...)
	 * 
	 * @param string querystring
	 * @param boolean split multiple SQL commands before processing
  	 * @return bool status
	 **/
	public function execute($querystr, $multi = false) {
		if($multi) {
			$queries = explode(';', $querystr);
		}
		else {
			$queries = array($querystr);
		}

		foreach($queries as $this->queryString) {
    		if(!$this->queryResult = @$this->query($this->queryString)) {
				$this->setError();
				return false;
			}
		}
		$this->affectedRows = $this->affected_rows;
		return true;
	}

	/**
	 * executes a prepared non-select query (UPDATE, INSERT, ...)
	 * a previously executed statement is stored for (immediate) reuse
	 *
	 * @param string $statement, the query statement
	 * @param array $parameters, array with parameters; datatype of parameter determines binding
	 * @return bool $status
	 */
	public function preparedExecute($statement, array $parameters = array()) {

		if($this->preparedQueryString !=$statement) {
			$this->preparedQueryString = $statement;
			$this->statement = $this->prepare($statement);
		}

		$type = '';
		$paramByRef = array();

		foreach($parameters as $k => $v) {
			switch (gettype($v)) {
				case 'integer':	$type .= 'i'; break;
				case 'double':	$type .= 'd'; break;
				case 'string':	$type .= 's'; break;
				default:		throw new Exception('Invalid datatypes for prepared query! Only string, integer and double are allowed.');
			}
			$paramByRef[$k] = &$parameters[$k];
		}

		array_unshift($paramByRef, $type);

		if(!empty($parameters)) {
			if(!@call_user_func_array(array($this->statement, 'bind_param'), $paramByRef)) {
				$this->setError(true, array_merge((array) $type, $parameters));
				return false;
			}
		}
		if(!$this->statement->execute()) {
			$this->setError(true); return false;
		}

		$this->affectedRows = $this->statement->affected_rows;
		return true;
	}

	
	/**
	 * insert record in specified table
	 * 
	 * @param string table
	 * @param array Data
	 * @return int insert id | bool false
	 */
	public function insertRecord($table, $insert) {
		if(!$this->doQuery("SELECT * FROM $table LIMIT 1")) {
			return FALSE;
		}

		$insert = array_change_key_case($insert, CASE_LOWER);
		
		$fields = $this->queryResult->fetch_fields();
		
	    foreach($fields as $f) {

	    	$name = strtolower($f->name);

			if (isset($insert[$name])) {
				$names[] = $name;
				$v = $this->maskField($f, $insert[$name]);

				if($v === FALSE) {
					return FALSE;
				}

				$values[] = $v;
			}

			else if($name == $this->updateField) {
				$names[]	= $this->updateField;
				$values[]	= 'NULL';
			}

			else if($name == $this->createField) {
				$names[]	= $this->createField;
				$values[]	= 'NOW()';
			}
		}

		$sqlnames	= implode(',',$names);
		$sqlvalues	= implode(',',$values);

		if(!$this->execute("INSERT INTO $table ($sqlnames) VALUES ($sqlvalues)")) {
			return FALSE;
		}

		return $this->insert_id;
	}

	/**
	 * update record in specified table
	 * 
	 * @param string table
	 * @param mixed id (dzt. nur primary Key)
	 * @param array newData
	 * @return bool Result
	 **/
	public function updateRecord($table, $id, $update) {
		if(!$this->doQuery("SELECT * FROM $table LIMIT 1")) {
			return FALSE;
		}

		$update = array_change_key_case($update, CASE_LOWER);

		$fields = $this->queryResult->fetch_fields();

	    foreach($fields as $f) {
	    	$name = strtolower($f->name);

	    	if($f->flags & MYSQLI_PRI_KEY_FLAG) {
	    		$primaryKey = $name;
	    	}

			if (isset($update[$name])) {
				$v = $this->maskField($f, $update[$name]);

				if($v === FALSE) {
					return FALSE;
				}

				$parm[] = "$name=$v";
			}

			else if($name == $this->updateField) {
				$parm[]	= $this->updateField.'=NULL';
			}
		}

		if(empty($parm)) {
			return NULL;
		}

		if(!is_array($id)) {
			return $this->execute("UPDATE $table SET ".implode(',', $parm)." WHERE $primaryKey = $id");
		}

		foreach($id as $k => $v) {
			$where[] = "$k = $v";
		}
		return $this->execute("UPDATE $table SET ".implode(',', $parm)." WHERE ".implode(' AND ', $where));
	}

	/**
	 * delete record
	 * 
	 * @param string $table
	 * @param mixed $id for identifying records; can be numeric primary key or associative array
	 * @param bool $usePreparedStatement; use prepared statement to allow various mixed datatypes as $id
	 * 
	 * @return int affected_rows
	 **/
	public function deleteRecord($table, $id, $usePreparedStatement = false) {
		if(!$usePreparedStatement) {
			if(!is_array($id)) {
		    	$where = "{$this->getPrimaryKey($table)} = $id";
			}
			else {
				foreach($id as $key => $val) {
					$fields[] = "$key=$val";
				}
				$where = implode(' and ', $fields);
			}
			return $this->execute("delete from $table where $where");
		}

		if(!is_array($id)) {
			return $this->preparedExecute("DELETE FROM $table WHERE {$this->getPrimaryKey($table)} = ?", (int) $id);
		}
		else {
			foreach($id as $key => $val) {
				$fields[] = "$key=?";
				$vals[] = $val;
			}
			$where = implode(' AND ', $fields);
			return $this->preparedExecute("DELETE FROM $table WHERE $where", $vals);
		}
	}

	/**
	 * retrieve possible values of ENUM or SET column
	 * 
	 * @param string table
	 * @param string column
	 * @return array Values | bool error
	 */
	public function getEnumValues($table, $column) {
		if(!$this->doQuery("SHOW COLUMNS FROM $table LIKE '$column'")) { return false; }

		$rec = $this->queryResult->fetch_assoc();
		$val = explode("','", preg_replace("/(set|enum)\('(.+?)'\)/", "\\2", $rec['Type']));

		if(empty($val))	{ return false; }
		return $val;
	}

	
	/**
	 * check whether table exists
	 * 
	 * @param string table
	 * @return bool result
	 */
	public function tableExists($table) {
		$this->doQuery("SHOW TABLES LIKE '$table'");
		return !!$this->numRows;
	}

	/**
	 * check whether a column in table exists
	 * 
	 * @param string table
	 * @param string column
	 * @return bool result
	 */
	public function columnExists($table, $column) {
		$this->doQuery("SHOW COLUMNS FROM $table LIKE '$column'");
		return !!$this->numRows;
	}

	/**
	 * retrieve default value of specified column
	 * @param string table
	 * @param string column
	 * @return string
	 */
	 public function getDefaultFieldValue($table, $column) {
		if(!$this->doQuery("SHOW COLUMNS FROM $table LIKE '$column'")) { return false; }
		$rec = $this->queryResult->fetch_assoc();
		return $rec['Default'];
	 }

	/**
	 * retrieve primary key field(s) of specified table
	 * @param string table
	 * @param bool force re-analyzing of table
	 * @return string
	 */
	 public function getPrimaryKey($table, $force = false) {
	 	if(isset($this->primaryKeys[$table]) && !$force) {
	 		return $this->primaryKeys[$table];
	 	}

	 	$pri = array();

	 	if(!$this->doQuery("SHOW COLUMNS FROM $table")) { return false; }
	 	while($rec = $this->queryResult->fetch_assoc()) {
	 		if($rec['Key'] == 'PRI') {
	 			array_push($pri, $rec['Field']);
	 		}
	 	}
	 	switch (count($pri)) {
	 		case 0:
	 			$this->primaryKeys[$table] = null;
	 			return null;
	 		case 1:
	 			$this->primaryKeys[$table] = $pri[0];
	 			return $pri[0];
	 		default:
	 			$this->primaryKeys[$table] = $pri; 
	 			return $pri;
	 	}
	 }
	 
	 /**
	  * Sort row by $sortField up or down
	  * 
	  * @param string table
	  * @param integer id
	  * @param string direction 'up' | 'dn'
	  * @param string filter additional attribute incorporated in sort
	  */
	public function customSort($table, $id, $direction, $filter = null) {

		$dirOp		= $direction == 'up' ? '<' : '>';
		$dirSort	= $direction == 'up' ? 'desc' : '';

		$sql =	'start transaction;';

		if(!empty($filter)) {
			$sql .=	"select {$this->sortField}, $filter from $table where {$table}ID = $id into @oldPos, @filter;";
			$sql .=	"select {$this->sortField}, {$table}ID from $table where {$this->sortField} $dirOp @oldPos and $filter = @filter order by CustomSort $dirSort limit 1 into @newPos, @swappedID;";
		}
		else {
			$sql .=	"select {$this->sortField} from $table where {$table}ID = $id into @oldPos;";
			$sql .=	"select {$this->sortField}, {$table}ID from $table where {$this->sortField} $dirOp @oldPos order by CustomSort $dirSort limit 1 into @newPos, @swappedID;";
		}

		$sql .= "update $table set {$this->sortField} = @oldPos where @swappedID is not null and {$table}ID = @swappedID;";
		$sql .= "update $table set {$this->sortField} = @newPos where @swappedID is not null and {$table}ID = $id;";
		$sql .= 'commit';

		$this->execute($sql, true);
	}

	/**
	 * Wrapper for real_escape_string
	 * @param string string to escape
	 * @return string escaped string
	 */
	public function escapeString($arg) {
		return $this->real_escape_string($arg);
	}

	/**
	 * Re-formats date strings depending on locale to yyyy-mm-dd
	 * does not check validity of date
	 * 
	 * @param string datestring
	 * @param string locale "us" | "iso" | "de"
	 * 
	 * @return string reformated datestring 
	 */
	public function formatDate($dat, $locale = null) {

		if(empty($dat)){ return ''; }
		
		$locale = $locale == null ? (!defined('SITE_LOCALE') ? 'de' : SITE_LOCALE) : $locale;

		$tmp = preg_split('/( |\.|\/|-)/', $dat);

		switch($locale) {
			case 'de':
				$tmp[2] = substr(date('Y'), 0, 4-strlen($tmp[2])).$tmp[2];
				return $tmp[2].'-'.$tmp[1].'-'.$tmp[0];
			case 'us':
				$tmp[2] = substr(date('Y'), 0, 4-strlen($tmp[2])).$tmp[2];
				return $tmp[2].'-'.$tmp[0].'-'.$tmp[1];
			case 'iso':
				$tmp[0] = substr(date('Y'), 0, 4-strlen($tmp[0])).$tmp[0];
				return $tmp[0].'-'.$tmp[1].'-'.$tmp[2];
			default:
				return '';
		}
	}

	/**
	 * Strips decimal strings from everything but decimal point and negative prefix 
	 * @param string decimal
	 * @return float stripped decimal  
	 */
	public function formatDecimal($dec) {
		if(trim($dec) == ''){
			return null;
		}
		if(substr($dec, 0, 1) == '+') { $dec = substr($dec, 1); }

		if(preg_match('/^\-?\d+([,.]\d+)?$/', $dec)) {
			return (float) (str_replace(',', '.', $dec));
		}
		if(preg_match('/^\-?[1-9]\d{0,2}((,|\')\d{3})*(\.\d+)?$/', $dec)) {
			return (float) (str_replace(array(',', '\''), array('', ''), $dec));
		}
		if(preg_match('/^\-?[1-9]\d{0,2}(\.\d{3})*(,\d+)?$/', $dec)) {
			return (float) (str_replace(array('.', ','), array('', '.'), $dec));
		}
		return (float) $dec;
	}

	/**
	 * Escapes or returns valid default values for a field
	 *
	 * @param object $field
	 * @param mixed $value
	 * @return mixed $value
	 */
	protected function maskField($f, $v)	{
		if(	$f->type === MYSQLI_TYPE_DATE || 
			$f->type === MYSQLI_TYPE_TIME || 
			$f->type === MYSQLI_TYPE_DATETIME || 
			$f->type === MYSQLI_TYPE_TIMESTAMP || 
			$f->type === MYSQLI_TYPE_YEAR || 
			$f->type === MYSQLI_TYPE_NEWDATE
			){ 
			$v = ($v === '') ? 'NULL' : "'$v'";
		}
		else if(
			$f->type === MYSQLI_TYPE_TINY ||
			$f->type === MYSQLI_TYPE_SHORT ||
			$f->type === MYSQLI_TYPE_LONG ||
			$f->type === MYSQLI_TYPE_FLOAT || 
			$f->type === MYSQLI_TYPE_DOUBLE || 
			$f->type === MYSQLI_TYPE_NULL ||
			$f->type === MYSQLI_TYPE_LONGLONG ||
			$f->type === MYSQLI_TYPE_INT24 ||
			$f->type === MYSQLI_TYPE_DECIMAL ||
			(defined('MYSQLI_TYPE_BIT') && $f->type === MYSQLI_TYPE_BIT) ||
			(defined('MYSQLI_TYPE_NEWDECIMAL') && $f->type === MYSQLI_TYPE_NEWDECIMAL)
			) {
			if(is_bool($v)) {
				return $v ? 1 : 0;
			}
			if(!empty($v) && !is_numeric($v) && strtoupper($v) !== 'NULL') {
				return false;
			}
			$v = ($v === '') ? 'NULL' : $v;
		}
		else {
			$v = "'".$this->escapeString(htmlspecialchars_decode($v, ENT_QUOTES))."'";
		}
		return $v;
	}
	
	/**
	 * disable error handling
	 */
	public function disableErrorHandling() {
		$this->handleErrors = false;
	}

	/**
	 * enable error handling
	 */
	public function enableErrorHandling() {
		$this->handleErrors = true;
	}
	
	
	/**
	 * store error in $error array
	 * @param boolean $wasPrepared
	 */
	protected function setError($wasPrepared = FALSE){
		if(!$this->handleErrors) {
			return;
		}

		$this->lastErrno = $this->errno;
 		$this->lastError = $this->error;

 		if(func_num_args() > 1 && is_array(func_get_arg(1))) {
 			$parameters = func_get_arg(1);
 			$this->logQuery($wasPrepared, $parameters);
 		}
 		else {
 			$this->logQuery($wasPrepared);
 		}

 		throw new MysqldbiException(sprintf('Mysqldbi Error: %s (%s)', $this->lastError, $this->lastErrno));
	}

	/**
	 * log query errors
	 * @param boolean $wasPrepared
	 */
	protected function logQuery($wasPrepared) {
		if (!$this->logErrors) {
			return;
		}

		$logfile	= rtrim($_SERVER['DOCUMENT_ROOT'], '/').(defined('MYSQL_LOG_PATH') ? MYSQL_LOG_PATH : '/').'mysql.log'.($this->logtype == 'xml' ? '.xml' : '');

		/*
		 * logfile XML style
		 */
		if($this->logtype == 'xml') {
			if(!file_exists($logfile) || filesize($logfile) == 0) {
				$logtext = array('<?xml version="1.0" standalone="yes" ?>', '<error_log>');
			}
			else {
				$logtext = file($logfile);
				array_pop($logtext);
			}
			
			$err = array_reverse(debug_backtrace());
			array_pop($err);
			$i = 0;
			foreach($err as $v) {
				$trace[] = "\t\t<file level='$i'>".basename($v['file'])."</file>";
				$trace[] = "\t\t<line level='".$i++."'>{$v['line']}</line>";
			}
			
			$logtext = array_merge($logtext, array(
					"<error>\n",
					"\t<datetime>".date('Y-m-d H:i:s')."</datetime>\n",
			));
			if($wasPrepared) {
				$logtext = array_merge($logtext, array(
					"\t<statement_string>\n",
					"\t\t<![CDATA[\n", $this->preparedQueryString, "\n\t\t]]>\n",
					"\t</statement_string>\n",
				));
				if(func_num_args() > 1) {
					$parameters = func_get_arg(1);
					$types = trim(preg_replace('//u', ',', array_shift($parameters)), ',');
					$logtext = array_merge($logtext, array(
						"\t<bound_parameters>\n",
						"\t\t<types>$types</types>\n",
						"\t\t<parameter>".implode("</parameter>\n\t\t<parameter>", $parameters)."</parameter>\n",
					"\t</bound_parameters>\n",
					));
				}
			}
			else {
				$logtext = array_merge($logtext, array(
					"\t<querystring>\n",
					"\t\t<![CDATA[\n", $this->queryString, "\n\t\t]]>\n",
					"\t</querystring>\n",
				));
			}
			$logtext = array_merge($logtext, array(
					"\t<remote_addr>".$_SERVER['REMOTE_ADDR']."</remote_addr>\n",
					"\t<request_uri>".htmlspecialchars($_SERVER['REQUEST_URI'])."</request_uri>\n",
					"\t<error_no>{$this->lastErrno}</error_no>\n",
					"\t<error_msg>{$this->lastError}</error_msg>\n",
					"\t<backtrace>\n".implode("\n", $trace)."\n\t</backtrace>\n",
					"</error>\n"
			));
			array_push($logtext, "</error_log>");

			$tmp = tempnam(defined('MYSQL_LOG_PATH') ? MYSQL_LOG_PATH : '', 'tmp_');
	
			if ($tmp !== false && ($handle = fopen($tmp, 'w'))) {
				flock($handle, LOCK_EX);
				fwrite($handle, implode('', $logtext));
				flock($handle, LOCK_UN);
				fclose($handle);
				if(file_exists($logfile)) {
					@unlink($logfile);
				}
				if(!@rename($tmp, $logfile)) {
					unlink($tmp);
				}
				else {
					chmod($logfile, 0777);
				}
			}
			return;
		}

		/*
		 * logfile plain style
		 */
		if ($handle = fopen($logfile, 'a+b', true)) {
			$err = array_reverse(debug_backtrace());
			array_pop($err);
			foreach($err as $v) {
				$trace[] = basename($v['file']).":line ".$v['line'];
			}

			$err = array(
				date('Y-m-d H:i:s').' '.str_repeat('-', 52),
				'',
				$this->queryString,
				'',
				$_SERVER['REMOTE_ADDR'].' -- '.$_SERVER['REQUEST_URI'],
				$this->lastErrno.': '.$this->lastError,
				'',
				'Backtrace: '.implode(' => ', $trace),
				''
			);
			return fwrite($handle,
				implode("\r\n", array_map(array($this, 'formatLogCallback'), $err))
			);
		}
	}

	protected function formatLogCallback($str) {
		return wordwrap($str, 72, "\r\n");
	}

	/**
	 * Administration and handling of a nested set
	 */
	public function getParentNodes($tbl, $id, $fields = null) {
		if(is_array($fields)) {
			for($i = 0; $i < count($fields); $i++) {
				$fields[$i] = 'a.'.$fields[$i];
			}
			$fields = implode(',', $fields);
		}
		else {
			$fields = 'a.*';
		}
		$pk = $this->getPrimaryKey($tbl);
		$sql = "
			select
				$fields
			from
				$tbl a, $tbl b
		    WHERE a.l <= b.l AND a.r >= b.r and b.$pk = $id and a.parent is not null";

		return($this->doQuery($sql));
	}

	public function getSubTree($tbl, $id = null, $fields = null) {
		if(is_array($fields)) {
			for($i = 0; $i < count($fields); $i++) {
				$fields[$i] = 'a.'.$fields[$i];
			}
			$fields = implode(',', $fields);
		}
		else {
			$fields = 'a.*';
		}
		
		$where = empty($id) ? 'a.level > 0' : "b.$pk = $id";
		$pk = $this->getPrimaryKey($tbl);
		$sql = "
			select distinct
				$fields
			from
				$tbl a, $tbl b
			where
				a.l BETWEEN b.l AND b.r and $where
			order by a.l";

		return($this->doQuery($sql));
	}

	/**
	 * overloads native prepare method
	 * 
	 * @param string $query
	 */
	public function prepare($query) {
		return new MysqldbiStatement($this, $query);
	}
	
	/**
	 * 
	 * create unique alias from string
	 * 
	 * @param string $text text to convert
	 * @param string $table name of entity
	 * @param int $id primary key of entity
	 * 
	 * @return string alias
	 */
	public function getAlias($text, $table, $id = NULL) {
		$replaceFrom	= array('~(ä|&auml;)~', '~(ö|&ouml;)~', '~(ü|&uuml;)~', '~(ß|&szlig;)~', '~\W+~', '~(^_+|_+$)~');
		$replaceTo		= array('ae', 'oe', 'ue', 'ss', '_', '');
		$alias			= preg_replace($replaceFrom, $replaceTo, strtolower($text));

		if(isset($id)) {
			if(($rows = $this->doPreparedQuery("SELECT {$table}ID FROM $table WHERE LOWER(Alias) = ? AND {$table}ID != ?", array($alias, (int) $id)))) {
				return $alias .= "_$id";
			}
		}
		else {
			$i = 1;
			while(($rows = $this->doPreparedQuery("SELECT {$table}ID FROM $table WHERE LOWER(Alias) = ?", array($alias)))) {
				$alias .= ++$i;
			}
		}

		return $alias;
	}
}

/**
 * extends prepared statements
 */
class MysqldbiStatement extends mysqli_stmt {
	
	protected $varsBound = false;
	protected $results;

	/**
	 * Constructor wrapper
	 *
	 * @param Mysqldbi $link;
	 * @param string $query; the query of the prepared statement
	 */
	public function __construct(Mysqldbi $link, $query) {
		parent::__construct($link, $query);
	}
	
	/**
	 * provides fetch_assoc() for prepared statements
	 *
	 * @return null or associative array
	 */
	public function fetch_assoc() {
		if(!$this->varsBound) {
			$meta = $this->result_metadata();
			while(($col = $meta->fetch_field())) {
				$varsToBind[] = &$this->results[$col->name];
			}
			call_user_func_array(array($this, 'bind_result'), $varsToBind);
			$this->varsBound = true;
		}

		if($this->fetch() === null) {return null; }

		foreach($this->results as $k => $v) {
			$r[$k] = $v;
		}
		return $r;
	}
}

class MysqldbiException extends Exception {
}
?>