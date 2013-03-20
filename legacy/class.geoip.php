<?php
class GeoIP {
	public static function getCC($ip, $db) {
		if(!$db instanceof Mysqldbi) {
			return false;
		}
		if(!($ip = ip2long($ip))) {
			return false;
		}

		$rows = $db->doQuery("select CC from ip_to_country where IP_Start_Int <= $ip and IP_End_Int >= $ip", true);
		if(empty($rows)) {
			return false;
		}
		return $rows[0]['CC'];
	}
}
?>