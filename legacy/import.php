<?php
/*
 *      import.php
 *      
 *      Copyright 2009 Gregor Kofler <office@gregorkofler.com>
 *      
 */

require_once '../boot.inc.php';

/* Reihenfolge der DB-Felder in der CSV Tabelle für die direkte Übernahme, Felder mit Underscore werden nicht benötigt */ 

$csv_fields = array(
	'Reference',
	'MaturityDate',
	'Altered',
	'LifeOffice',
	'Term',
	'SurrenderValue',
	'SumAssured',
	'AccruedBonus',
	'BonusDate',
	'Price',
	'_SVR',
	'Premium',
	'Frequency',
	'TotalPremium',
	'FMV',
	'PDR',
	'TBR',
	'_Guaranteed_Amount',
	'_Security_Rating',
	'PolicyNo',
	'LiveAssured',
	'ValuationDate'
);

$additional_db_fields = array(
	'MarketMaker',			// PPL, 1st, TEPPCO...
	'ContractNote',			// integer
	'SurrenderValueDate',	// null
	'BonusStructure',		// M|C
	'Visible',				// 0|1
	'KVID',					// integer
	'KaufDT',				// Datum
	'RVID',					// Integer
	'ResUntilDT',			// Datum
	'Comment',				// Varchar
	'TID'					// Varchar
);

if(!empty($_POST) && !empty($_FILES['csv'])) {
	$handle = fopen($_FILES['csv']['tmp_name'], 'r');

	$entries = extract_uploaded_csv($handle);

	fclose($handle);

	if(!empty($entries)) {
		add_additional_values(&$entries);
		if(!($conn = connect_db())) {
			die('DB-Error!');
		}
		if(!write_values_to_db($conn)) {
			die('Eintragen in DB nicht möglich!');
		}
		
	}
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">

<head>
	<title>Polizzenimport</title>
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
</head>

<body>
	<h1>CSV Datei für Übernahme der neuen Polizzen</h1>
	
	<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post" enctype="multipart/form-data">
		CSV Datei <input name="csv" type="file"><br>
		<input type="submit" name="submit_csv" value="Upload!">
	</form>
</body>
</html>

<?php
function connect_db() {
	global $GlobalCfg;
	
	if(!($conn = mysql_connect($GlobalCfg['db']['host'], $GlobalCfg['db']['username'], $GlobalCfg['db']['password']))) {
		return false;
	}
	if(!mysql_select_db($GlobalCfg['db']['db'], $conn)) {
		return false;
	}
	return $conn;
}

function extract_uploaded_csv($handle) {
	global $csv_fields;

	if(!$handle) {
		return false;
	}
	$filtered = array();

	while (($line = fgets($handle)) !== FALSE) {
		$line = explode(';', $line);

		if((int) $line[0]) {
			array_push($filtered, php4_array_combine($csv_fields, $line));
		}
	}
	return $filtered;
}

function php4_array_combine($k, $v) {
	$result = array();
	foreach($k as $kk => $vv) {
		$result[$vv] = $v[$kk]; 
	}
	return $result;
}

function add_additional_values($entries) {
	var_dump($entries);
}
?>