<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:02 PM
 */

namespace OC\CernBox\Storage\Eos;


class Command {

	public static function exec($cmd, $eosMGMURL = false) {

		if($eosMGMURL === false)
		{
			$eosMGMURL = Util::getEosMgmUrl();
		}

		$fullCmd = 'EOS_MGM_URL='.$eosMGMURL.' '.$cmd;

		$user = \OC::$server->getUserSession()->getUser()->getUID();

		// Keep requesting EOS while we get a 22 error code
		$retrivalAttempts = \OC::$server->getConfig()->getSystemValue('eos_cmd_retry_attempts', -1);
		$counter = 0;
		do
		{
			$result = null;
			$errcode = null;
			exec($fullCmd, $result, $errcode);
			$counter++;
		}
		while($errcode === 22 && $counter !== $retrivalAttempts);

		if($errcode === 0) {
			\OCP\Util::writeLog('EOSCMD', "luser: $user cmd:$cmd errcode:$errcode", \OCP\Util::WARN);
		} else {
			\OCP\Util::writeLog('EOSCMD', "luser: $user cmd:$cmd errcode:$errcode", \OCP\Util::ERROR);
		}
		return array($result, $errcode);
	}
}