<?php

/**
 * mysqldb
 * 1.3
 * 2006-12-30
 */

class mysqldb {
	var $conn;
	var $host;
	var $user;
	var $pass;
	var $dbname;
	var $db;			/* mySQL DB-Ressource */

	var $displayError = false;
	var $error;			/* array mit errno und error */
	var $lastErrno;
	var $lastError;
	var $lastQueryRes;	/* letzte Query Ressource */
	var $lastQueryString;

	var $createField = 'firstCreated';
	var $updateField = 'lastUpdated';

	function mysqldb($dbname = DBNAME, $dbhost = DBHOST, $dbuser = DBUSER, $dbpass = DBPASS) {
		$this->host			= $dbhost;
		$this->user			= $dbuser;
		$this->pass			= $dbpass;
		$this->open();
		$this->selectDb($dbname);
		$this->displayError	= false;
	}

	/**
	 * Wrapper für mysql_connect()
	 * @return bool
	 */
	function open(){
		if(!$this->conn = @mysql_connect($this->host,$this->user,$this->pass)) {
      		$this->setError();
			return false;
		}
    	return $this->conn;
	}

	/**
	 * Wrapper für mysql_close()
	 * @return bool
	 */
	function close(){
		if(!$this->conn) { return false; }
    	return mysql_close($this->conn);
	}

	/**
	 * Wrapper für mysql_select_db()
	 * @param string $dbname
	 * @return bool
	 */
	function selectDb($dbname = null) {
	    if(!($dbname || $this->dbname)) { return false; }
	    if($dbname) {
			$this->dbname = $dbname;
			if(!$this->db = @mysql_select_db($this->dbname,$this->conn)){
	      		$this->setError();
				return false;
	    	}
			return $this->db;
		}
	}

	/**
	 * Fehler in $error-Array ablegen
	 * @private
	 */
	function setError(){
		if(!$this->db) {
			$this->error[] = array('errno' => null, 'error' => 'Kein DB-Handler.');
    	}
    	else {
			$this->error[] = array('errno' => mysql_errno($this->conn), 'error' => mysql_error($this->conn));
		}
		$this->lastErrno = $this->error[count($this->error)-1]['errno'];
 		$this->lastError = $this->error[count($this->error)-1]['error'];

		if($this->displayError) { echo $this->lastErrno, ': ', $this->lastError; }
		$this->logQuery();
	}

	/**
	 * Query-Fehler loggen, wenn keine Bildschirmausgabe
	 */
	function logQuery() {
		if ($this->displayError == true) { return true; }
		if ($handle = fopen('mysql.log', 'a+b', true)) {
		return fwrite($handle,
			"-- ".date('Y-m-d H:i:s')." ----\r\n".
			$this->lastQueryString."\r\n\r\n".
			$_SERVER['REMOTE_ADDR'].' -- '.
			$_SERVER['REQUEST_URI']."\r\n".
			$this->lastErrno.': '.$this->lastError.';'.
			"\r\n---------------------------\r\n\r\n");
		}
	}

	/**
	 * misc SQL-Query
	 * @param string $querystr
  	 * @return= result id | FALSE
	 **/
	function query($querystr){
		if(!$this->db) { return false; }
		
		$this->lastQueryString = $querystr;
    	if(!$this->lastQueryRes = @mysql_query($this->lastQueryString, $this->conn)) {
			$this->setError();
			return false;
		}
		return $this->lastQueryRes;
	}

	/**
	 * execute batch of query-commands
	 * @param string $querystr
  	 * @return= result id | FALSE
	 **/
	function execute($querystr){
		if(!$this->db) { return false; }
		$queries = explode(';', $querystr);
		foreach($queries as $this->lastQueryString) {
    		if(!$this->lastQueryRes = @mysql_query($this->lastQueryString, $this->conn)) {
				$this->setError();
				return false;
			}
		}
		return $this->lastQueryRes;
	}

	/**
	 * Neuanlage eines Datensatzes
	 * @param string $table
	 * @param array $insert
	 * @return insert id
	 */
	function insertRecord($table, $insert) {
		if(!$rset = $this->query('SELECT * FROM '.$table.' LIMIT 1')) { return false; }

	    while ($fieldObj = mysql_fetch_field($rset)) {
			if (isset($insert[$fieldObj->name])) {
				$names[] = $fieldObj->name;
				if($fieldObj->type == 'date' || $fieldObj->type == 'time' || $fieldObj->type == 'datetime') {
					$values[] = ($insert[$fieldObj->name] == '') ? 'NULL' : '"'.$insert[$fieldObj->name].'"';
				}
				else if($fieldObj->type == 'int' || $fieldObj->type == 'real') {
					if(!empty($insert[$fieldObj->name]) && !is_numeric($insert[$fieldObj->name]) && $insert[$fieldObj->name] != 'NULL') { return (false); }
					$values[] = ($insert[$fieldObj->name] == '') ? 'NULL' : $insert[$fieldObj->name];
				}
				else {
					$values[] = '"'.mysql_real_escape_string($insert[$fieldObj->name]).'"';
				}
			}

			else if($fieldObj->name == $this->updateField) {
				$names[]	= $this->updateField;
				$values[]	= 'NULL';
			}
			else if($fieldObj->name == $this->createField) {
				$names[]	= $this->createField;
				$values[]	= 'NOW()';
			}
		}

		$sqlnames	= implode(',',$names);
		$sqlvalues	= implode(',',$values);
		if(!$this->query('insert into '.$table.' ('.$sqlnames.') values ('.$sqlvalues.')')) { return false; }
		return mysql_insert_id();
	}

	/**
	 * Datensatz in $table updaten
	 * @param string $table
	 * @param mixed $id (dzt. nur primary Key)
	 * @param array $newData
	 * @return int affected_rows | bool false
	 **/
	function updateRecord ($table, $id, $update) {
		if(!$rset = $this->query('SELECT * FROM '.$table.' LIMIT 1')) { return false; }

	    while ($fieldObj = mysql_fetch_field($rset)) {
	    	if($fieldObj->primary_key == 1) { $primaryKey = $fieldObj->name; }
			if (isset($update[$fieldObj->name])) {
				if($fieldObj->type == 'date' || $fieldObj->type == 'time') {
					$value = ($update[$fieldObj->name] == '') ? 'NULL' : '"'.$update[$fieldObj->name].'"';
				}
				else if($fieldObj->type == 'int' || $fieldObj->type == 'real') {
					if(!empty($update[$fieldObj->name]) && !is_numeric($update[$fieldObj->name]) && $update[$fieldObj->name] != 'NULL') { return (false); }
					$value = ($update[$fieldObj->name] == '') ? 'NULL' : $update[$fieldObj->name];
				}
				else {
					$value = '"'.mysql_real_escape_string($update[$fieldObj->name]).'"';
				}
				$parm[] = $fieldObj->name.'='.$value;
			}
			else if($fieldObj->name == $this->updateField) {
				$parm[]	= $this->updateField.'=NULL';
			}
		}
		if(!$this->query('update '.$table.' set '.implode(",", $parm).' where '.$primaryKey.'='.$id)) { return false; }
		$rows = mysql_affected_rows($this->conn);
		if($rows == -1) {
			$this->setError();
			return false;
		}
		return $rows;
	}

	/**
	 * Datensatz in $table löschen
	 * @param string $table
	 * @param mixed $id
	 * @return int affected_rows
	 **/
	function deleteRecord($table, $id) {
		if(!$this->db) { return false; }

		if(!is_array($id)) {
			if(!$rset = $this->query('SELECT * FROM '.$table.' LIMIT 1')) { return false; }
	    	while ($fieldObj = mysql_fetch_field($rset)) {
	    		if($fieldObj->primary_key == 1) { $primaryKey = $fieldObj->name; break; }
	    	}
	    	$where = $primaryKey.'='.$id;
		}
		else {
			foreach($id as $key => $val) {
				$fields[] = $key.'='.$val;
			}
			$where = implode(' and ', $fields);
		}
		if(!$this->query('delete from '.$table.' where '.$where)) { return false; }
		$rows = mysql_affected_rows($this->conn);
		if($rows == -1) {
			$this->setError();
			return false;
		}
		return $rows;
	}
	/**
	 * Default-Array aus Tabelle erzeugen
	 * @param string $table
	 * @return array fields | false
	 */
	function createInitRecord($table) {
		$record = array();
		if(!$rset = $this->query('SELECT * FROM '.$table.' LIMIT 1')) { return false; }
		while($fieldObj = mysql_fetch_field($rset)) {
			if($fieldObj->type == 'int' || $fieldObj->type == 'real')	{ $record[$fieldObj->name] = 0; }
			else														{ $record[$fieldObj->name] = ''; }
		}
		return $record;
	}

	/**
	 * Mögliche Werte eines ENUM oder SET-Feldes ermitteln
	 * @return array
	 * @param string Tabelle
	 * @param string Spalte
	 */
	 function getEnumValues($table, $column) {

		if(!$this->db) { return false; }

	 	$rset = $this->query('SHOW COLUMNS FROM '.$table.' LIKE "'.$column.'"');

	 	if(mysql_num_rows($rset) < 1)	{ return false; }

		$rec = mysql_fetch_assoc($rset);
		$val = explode("','", preg_replace("/(set|enum)\('(.+?)'\)/", "\\2", $rec['Type']));

		if(empty($val))	{ return false; }
		return $val;
	 }

	/**
	 * Defaultwert eines Feldes ermitteln
	 * @return string
	 * @param string Tabelle
	 * @param string Spalte
	 */
	 function getDefaultFieldValue($table, $column) {

		if(!$this->db) { return false; }

	 	$rset = $db->query('SHOW COLUMNS FROM '.$table.' LIKE "'.$column.'"');

	 	if(mysql_num_rows($rset) < 1)	{ return false; }

		$rec = mysql_fetch_assoc($rset);
		return $rec['Default'];
	 }
	 
	 /**
	  * Zeile einer Tabelle mittels Feld ´CustomSort´ nach oben sortieren
	  * @param string table
	  * @param integer id
	  * @param string direction Richtung 'up' | 'dn'
	  * @param string filter zusätzliches Attribut innerhalb dessen sortiert wird
	  */
	function customSort($table, $id, $direction, $filter = null) {

		$dirOp		= $direction == 'up' ? '<' : '>';
		$dirSort	= $direction == 'up' ? 'desc' : '';

		$sql =	'start transaction;';

		if(!empty($filter)) {
			$sql .=	"select CustomSort, $filter from $table where {$table}ID = $id into @oldPos, @filter;";
			$sql .=	"select CustomSort, {$table}ID from $table where CustomSort $dirOp @oldPos and $filter = @filter order by CustomSort $dirSort limit 1 into @newPos, @swappedID;";
		}
		else {
			$sql .=	"select CustomSort from $table where {$table}ID = $id into @oldPos;";
			$sql .=	"select CustomSort, {$table}ID from $table where CustomSort $dirOp @oldPos order by CustomSort $dirSort limit 1 into @newPos, @swappedID;";
		}

		$sql .= "update $table set CustomSort = @oldPos where @swappedID is not null and {$table}ID = @swappedID;";
		$sql .= "update $table set CustomSort = @newPos where @swappedID is not null and {$table}ID = $id;";
		$sql .= 'commit';

		$this->execute($sql);
	}
}
?>
