<?php
namespace OC\Files\ObjectStore;

class EosCmd {

	public static function exec($cmd) {
		$user = \OCP\User::getUser();
		$result = null;
		$errcode = null;
		exec($cmd, $result, $errcode);
		if($errcode === 0) {
			\OCP\Util::writeLog('EOS', "loggeduser: $user cmd:$cmd errcode:$errcode", \OCP\Util::WARN);
		} else {
			\OCP\Util::writeLog('EOS', "logggeduser: $user cmd:$cmd errcode:$errcode", \OCP\Util::ERROR);
		}
		return array($result, $errcode);
	}
}
