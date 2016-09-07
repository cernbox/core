<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:02 PM
 */

namespace OC\CernBox\Storage\Eos;


use OC\OCS\Config;

class  Commander{


	private $retrievalAttempts;
	private $util;

	/**
	 * Commander constructor.
	 */
	public function __construct(\OCP\IConfig $config, \OC\CernBox\Storage\Eos\Util $util) {
		$this->util = $util;
		$this->retrievalAttempts = $config->getSystemValue('cernboxcliretryattempts');
	}

	public function exec($cmd, $eosMGMURL = false) {

		if($eosMGMURL === false)
		{
			$eosMGMURL = $this->util->getEosMgmUrl();
		}

		$fullCmd = 'EOS_MGM_URL='.$eosMGMURL.' '.$cmd;

		// Keep requesting EOS while we get a 22 error code
		$counter = 0;
		do
		{
			$result = null;
			$errcode = null;
			exec($fullCmd, $result, $errcode);
			$counter++;
		}
		while($errcode === 22 && $counter !== $this->retrievalAttempts);

		if($errcode === 0) {
			\OCP\Util::writeLog('EOSCMD', "cmd:$cmd errcode:$errcode", \OCP\Util::WARN);
		} else {
			\OCP\Util::writeLog('EOSCMD', "cmd:$cmd errcode:$errcode", \OCP\Util::ERROR);
		}
		return array($result, $errcode);
	}
}