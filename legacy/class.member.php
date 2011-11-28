<?php
/**
 * Member
 * @version 0.0.6 2007-11-07
 */
class member {

	public function __construct() {
	}

	static function purgeMembers() {
	 	global $db;
	}

	static function getAdminEmails($notification = null, $exclude = true) {
		global $db;

		$w[]	= $notification === null ? '1=1' : '(Notifications & '.constant($notification).' != 0)';

		if($exclude && (isset($_SESSION['aid']))) {
			$w[]	= 	'adminID != '.$_SESSION['aid'];
		}

		$db->query(' select Email from admins where '.implode(' and ', $w));

		$mails = array();
		while($rec = $db->queryResult->fetch_assoc()) { $mails[] = $rec['Email']; }
		return $mails;
	}

	static function notifyAdmin($mailCode, $varData = array(), $adminEmails = null) {
		global $db;

		return;
		
		if(empty($adminEmails)) {
			$adminEmails = self::getAdminEmails($mailCode);
		}
		else {
			$adminEmails = (array) $adminEmails;
		}
		
		if(count($adminEmails) < 1) {
			return true;
		}

		$db->query("select Subject, AdminText from mails where Code='$mailCode'");

		$mailArray = $db->queryResult->fetch_assoc();
		if(empty($mailArray['AdminText'])) {
			return true;
		}
		array_walk($mailArray, array('self','insertMailFields'), $varData);

		$m = new Email();

		$m->setReceiver($adminEmails);
		$m->setSubject(defined('DEFAULT_MAIL_SUBJECT_PREFIX') ? DEFAULT_MAIL_SUBJECT_PREFIX : ''.$mailArray['Subject']);
		$m->setMailText($mailArray['AdminText']);

		return $m->send();
	}

	static function notifyMember($mailCode, $email, $varData = array()) {
		global $db;

		$db->query('select Subject, Text, Signature, Attachment from mails where Code="'.$mailCode.'"');
		$mailArray	= $db->queryResult->fetch_assoc(); 
		if(empty($mailArray['Text'])) {
			return true;
		}
		array_walk($mailArray, array('self','insertMailFields'), $varData);

		$m = new sendMail();

		$m->setReceiver	($email);
		$m->setSubject	($mailArray['Subject']);
		$m->setMailText	($mailArray['Text']);
		$m->setSig		($mailArray['Signature']);
		
		if(!empty($mailArray['Attachment'])) {
			$attachments = explode(',', $mailArray['Attachment']);
			foreach($attachments as $a) {
				$m->addAttachment($a);
			}
		}

		if($m->send()) {
			self::logNotification($mailCode, $email);
			return true;
		}
		return false;
	}

	static function insertMailFields(&$arrayVal, $arrayKey, $newVals) {
		$fields = array_keys($newVals);
		foreach ($newVals as $key => $val) {
			$arrayVal = str_replace('{'.$key.'}', $val, $arrayVal);
		}
	}

	static function logNotification($code, $receiver) {
	}
}
?>
