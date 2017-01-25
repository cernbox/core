<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 1/25/17
 * Time: 9:14 AM
 */

namespace OC\CernBox\Backends;


use OC\User\Session;
use OC_Template;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ISession;
use OCP\IUserManager;

class UserSession extends Session {

	private $logger;
	private $instanceManager;

	/**
	 * UserSession constructor.
	 */
	public function __construct(IUserManager $manager, ISession $session, ITimeFactory $timeFacory, $tokenProvider, IConfig $config) {
		$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
		$this->logger = \OC::$server->getLogger();
		parent::__construct($manager, $session, $timeFacory, $tokenProvider, $config);
	}

	protected function prepareUserLogin() {
		// is Eos storage is not enabled we don't do anything.
		if(!\OC::$server->getConfig()->getSystemValue('eosstore')) {
			parent::prepareUserLogin();
			return;
		}

		// Refresh the token
		\OC::$server->getCsrfTokenManager()->refreshToken();

		//we need to pass the user name, which may differ from login name
		$user = $this->getUser()->getUID();

		$pair = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($user);
		if ($pair === false) { // user does not an uid and gid
			$this->logout();
			die("Your account could not be created on the fly. <br> Please check if your account has a computing group defined on <a href=\"http://cern.ch/account\">http://cern.ch/account</a>. <br> This may be achieved by subscribing to Linux and Lxplus services. <br> If the problem persists then please report it via CERN Service Portal.");
			return;
		}

		$ok = $this->instanceManager->createHome($user);
		if (!$ok) {
			$this->logout();
			die("Your account could not be created on the fly (internal script not found). <br> Please report via CERN Service Portal.");
			return;
		}
	}
}
