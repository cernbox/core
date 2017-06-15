<?php
namespace OC\Files\ObjectStore;

class EosCmd {

	public static function exec($cmd, $eosMGMURL = false) {
		
		if($eosMGMURL === false)
		{
			$eosMGMURL = EosUtil::getEosMgmUrl();
		}
		
		$conTimeout = \OC::$server->getConfig()->getSystemValue('eos_maxcontimeout', 10);
		$conRetry = \OC::$server->getConfig()->getSystemValue('eos_maxconretry', 5);
		$reqTimeout = \OC::$server->getConfig()->getSystemValue('eos_reqtimeout', 60);
		$fullCmd = "XRD_CONNECTIONWINDOW=$conTimeout XRD_CONNECTIONRETRY=$conRetry XRD_REQUESTTIMEOUT=$reqTimeout EOS_MGM_URL=".$eosMGMURL.' '.$cmd;
		
		$user = \OCP\User::getUser();
		// Keep requesting EOS while we get a 22 error code
		
		$result = null;
		$errcode = null;
		exec($fullCmd, $result, $errcode);
		
		if($errcode === 22)
		{
			$retrivalAttempts = \OC::$server->getConfig()->getSystemValue('eos_cmd_retry_attempts', 3);
			$infinite = $retrivalAttempts < 0;
			$counter = 1;
			do
			{
				// Sleep for half a second
				usleep(500000);
				
				//Retry the command
				$result = null;
				$errcode = null;
				exec($fullCmd, $result, $errcode);
				
				$counter++;
			}
			while($errcode === 22 && ($infinite || $counter < $retrivalAttempts));
		}
		
		if($errcode === 0) {
			\OCP\Util::writeLog('EOSCMD', "luser: $user cmd:$fullCmd errcode:$errcode", \OCP\Util::WARN);
		} else {
			\OCP\Util::writeLog('EOSCMD', "luser: $user cmd:$fullCmd errcode:$errcode", \OCP\Util::ERROR);
		}
		return array($result, $errcode);
	}
}
