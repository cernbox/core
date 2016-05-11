<?php
namespace OC\Files\ObjectStore;

class EosCmd {

	public static function exec($cmd, $eosMGMURL = false) {
		
		if($eosMGMURL === false)
		{
			$eosMGMURL = EosUtil::getEosMgmUrl();
		}
		
		$fullCmd = 'EOS_MGM_URL='.$eosMGMURL.' '.$cmd;
		
		$user = \OCP\User::getUser();
		$result = null;
		$errcode = null;
		exec($fullCmd, $result, $errcode);
		if($errcode === 0) {
			\OCP\Util::writeLog('EOSCMD', "luser: $user cmd:$cmd errcode:$errcode", \OCP\Util::WARN);
		} else {
			\OCP\Util::writeLog('EOSCMD', "luser: $user cmd:$cmd errcode:$errcode", \OCP\Util::ERROR);
		}
		return array($result, $errcode);
	}
}
