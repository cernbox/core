<?php
namespace OC\Files\ObjectStore;

class EosCmd {

	public static function exec($cmd) {
		$user = \OCP\User::getUser();
		$result = null;
		$errcode = null;
		exec($cmd, $result, $errcode);
		if($errcode === 0) {
			\OCP\Util::writeLog('EOSCMD', "luser: $user cmd:$cmd errcode:$errcode", \OCP\Util::WARN);
		} else {
			\OCP\Util::writeLog('EOSCMD', "luser: $user cmd:$cmd errcode:$errcode", \OCP\Util::ERROR);
		}
		return array($result, $errcode);
	}
}
