<?php

namespace OC\User;

/**
 * THIS CLASS EXTENDS ORIGINAL SESSION (lib/private/user/session.php) TO
 * AVOID CORE PATCHES. TO DISABLE IT (AND TO BE ABLE TO PERFORM AN UPSTREAM UPDATE)
 * GO TO lib/private/server.php. LOOK FOR THIS LINE:
 * 
 * 				$userSession = new \OC\User\CernboxSession($manager, $session);
 * 
 * AND CHANGE IT TO THIS
 * 
 * 				$userSession = new \OC\User\Session($manager, $session);
 */
class CernboxSession extends Session
{
	public function __construct(\OCP\IUserManager $manager, \OCP\ISession $session) 
	{
			parent::__construct($manager, $session);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\User\Session::login()
	 */
	public function login($uid, $password) {
		// HUGO we lower case the username to not having problems with LDAP case-insensitive username resolution
		// This happened because the LDAP server is case-insensitive and the id resolution via nscd is case sensitive
		// so the user is correclty authenticated but we reply with a wrong message saying the user should subscribe to a Linux e-group
		$uid = strtolower($uid);
	
		$this->getManager()->emit('\OC\User', 'preLogin', [$uid, $password]);
		$user = $this->getManager()->checkPassword($uid, $password);
		if ($user !== false) {
			if (!is_null($user)) {
				if ($user->isEnabled()) {
					$this->setUser($user);
					$this->setLoginName($uid);
					$this->getManager()->emit('\OC\User', 'postLogin', [$user, $password]);
	
					// HUGO if EOS storage is not enabled we let the user enter as a normal guy
					if (! \OC\Files\ObjectStore\EosUtil::getEosPrefix()) {
						return true;
					}
	
					return $this->setUpNewUser($uid);
				} else {
					return false;
				}
			}
		} else {
			return false;
		}
	}
	
	public function setUpNewUser($uid)
	{
		// HUGO check user has valid uid and gid
		// TODO cache uid in User class to speed the system
		$uidAndGid = \OC\Files\ObjectStore\EosUtil::getUidAndGid($uid);
		if($uidAndGid === false) {
			\OCP\Util::writeLog('EOSLOGIN', "user: $uid has not a valid uid", \OCP\Util::ERROR);
			$tmpl = new \OC_Template('', 'error', 'guest');
			$tmpl->assign('errors', [1 => ['error' => "Your account has no computing group assigned. <br> Please use the CERN Account Service to fix this.  You may also check out <a href=\"https://cern.service-now.com/service-portal/article.do?n=KB0002981\">CERNBOX FAQ</a> for additional information. <br> If the problem persists then please report it via CERN Service Portal."]]);
			$tmpl->printPage();
			\OCP\User::logout();
			exit();
			return false;
		}
		\OCP\Util::writeLog('EOSLOGIN', "user: $uid has valid uid", \OCP\Util::ERROR);
		
		// HUGO check user has valid homedir if not we create it
		$homedir = \OC\Files\ObjectStore\EosUtil::getEosPrefix() . substr($uid, 0, 1) . "/" . $uid . "/";
		//$meta = \OC\Files\ObjectStore\EosUtil::getFileByEosPath($homedir);
		
		$errorCode = -1;
		do
		{
			$errorCode = \OC\Files\ObjectStore\EosUtil::statFile($homedir);
			if($errorCode != \OC\Files\ObjectStore\EosUtil::STAT_FILE_EXIST)
			{
				usleep(500000); // Half a second delay between request (microseconds)
			}
		}
		while($errorCode != \OC\Files\ObjectStore\EosUtil::STAT_FILE_EXIST && $errorCode != \OC\Files\ObjectStore\EosUtil::STAT_FILE_NOT_EXIST);
		
		if($errorCode === \OC\Files\ObjectStore\EosUtil::STAT_FILE_EXIST) { // path exists so we let the user access the system
			\OCP\Util::writeLog('EOSLOGIN', "user: $uid has a valid homedir that is: $homedir", \OCP\Util::ERROR);
			
			$meta = \OC\Files\ObjectStore\EosUtil::getFolderContents($homedir, function(array &$data) use ($uid)
			{
				$data['storage'] = 'object::user:' . $uid;
			});
			
			return true;
		}
			
		\OCP\Util::writeLog('EOSLOGIN', "user: $uid does NOT have valid homedir that is:  $homedir", \OCP\Util::ERROR);
		// the path does not exists so we create it
		// create home dir
		$script_path = \OCP\Config::getSystemValue("eos_configure_new_homedir_script_path", false);
		$eosMGMURL = \OCP\Config::getSystemValue("eos_mgm_url", false);
		$eosPrefix = \OCP\Config::getSystemValue("eos_prefix", false);
		$eosRecycle = \OCP\Config::getSystemValue("eos_recycle_dir", false);
		
		if(!$script_path || !$eosMGMURL || !$eosPrefix || !$eosRecycle) {
			$this->displayEOSConfigureDirError();
			exit();
			return false;
		}
		
		$result = null;
		$errcode = null;
		
		$cmd2 = "/bin/bash $script_path " . $eosMGMURL . ' ' . $eosPrefix . ' ' . $eosRecycle . ' ' . $uid;
		exec($cmd2, $result, $errcode);
		if($errcode !== 0){
			\OCP\Util::writeLog('EOSLOGIN', "error running the script to create the homedir: $homedir for user: $uid$ CMD: $cmd2 errcode: $errcode", \OCP\Util::ERROR);
			$tmpl = new \OC_Template('', 'error', 'guest');
			$tmpl->assign('errors', [1 => ['error' => "Your account could not be created on the fly. <br> Please check if your account has a computing group defined on <a href=\"http://cern.ch/account\">http://cern.ch/account</a>. <br> This may be achieved by subscribing to Linux and Lxplus services. <br> If the problem persists then please report it via CERN Service Portal."]]);
			$tmpl->printPage();
			\OCP\User::logout();
			exit();
			return false;
		}
		\OCP\Util::writeLog('EOSLOGIN', "homedir: $homedir created for user: $uid", \OCP\Util::ERROR);
			
		// all good, let the user enter
		return true;
	}
	
	private function displayEOSConfigureDirError()
	{
		\OCP\Util::writeLog('EOSLOGIN', "cannot find script for creating users. check config.php", \OCP\Util::ERROR);
		$tmpl = new \OC_Template('', 'error', 'guest');
		$tmpl->assign('errors', [1 => ['error' => "Your account could not be created on the fly (internal script not found). <br> Please report via CERN Service Portal."]]);
		$tmpl->printPage();
		\OCP\User::logout();
	}
}