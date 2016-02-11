<?php
/**
 * @author Arthur Schiwon <blizzz@owncloud.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\User;

use OC\Hooks\Emitter;
use OCP\IUserSession;

/**
 * Class Session
 *
 * Hooks available in scope \OC\User:
 * - preSetPassword(\OC\User\User $user, string $password, string $recoverPassword)
 * - postSetPassword(\OC\User\User $user, string $password, string $recoverPassword)
 * - preDelete(\OC\User\User $user)
 * - postDelete(\OC\User\User $user)
 * - preCreateUser(string $uid, string $password)
 * - postCreateUser(\OC\User\User $user)
 * - preLogin(string $user, string $password)
 * - postLogin(\OC\User\User $user, string $password)
 * - preRememberedLogin(string $uid)
 * - postRememberedLogin(\OC\User\User $user)
 * - logout()
 *
 * @package OC\User
 */
class Session implements IUserSession, Emitter {
	/**
	 * @var \OC\User\Manager $manager
	 */
	private $manager;

	/**
	 * @var \OC\Session\Session $session
	 */
	private $session;

	/**
	 * @var \OC\User\User $activeUser
	 */
	protected $activeUser;

	/**
	 * @param \OCP\IUserManager $manager
	 * @param \OCP\ISession $session
	 */
	public function __construct(\OCP\IUserManager $manager, \OCP\ISession $session) {
		$this->manager = $manager;
		$this->session = $session;
	}

	/**
	 * @param string $scope
	 * @param string $method
	 * @param callable $callback
	 */
	public function listen($scope, $method, callable $callback) {
		$this->manager->listen($scope, $method, $callback);
	}

	/**
	 * @param string $scope optional
	 * @param string $method optional
	 * @param callable $callback optional
	 */
	public function removeListener($scope = null, $method = null, callable $callback = null) {
		$this->manager->removeListener($scope, $method, $callback);
	}

	/**
	 * get the manager object
	 *
	 * @return \OC\User\Manager
	 */
	public function getManager() {
		return $this->manager;
	}

	/**
	 * get the session object
	 *
	 * @return \OCP\ISession
	 */
	public function getSession() {
		return $this->session;
	}

	/**
	 * set the session object
	 *
	 * @param \OCP\ISession $session
	 */
	public function setSession(\OCP\ISession $session) {
		if ($this->session instanceof \OCP\ISession) {
			$this->session->close();
		}
		$this->session = $session;
		$this->activeUser = null;
	}

	/**
	 * set the currently active user
	 *
	 * @param \OC\User\User|null $user
	 */
	public function setUser($user) {
		if (is_null($user)) {
			$this->session->remove('user_id');
		} else {
			$this->session->set('user_id', $user->getUID());
		}
		$this->activeUser = $user;
	}

	/**
	 * get the current active user
	 *
	 * @return \OCP\IUser|null Current user, otherwise null
	 */
	public function getUser() {
		// FIXME: This is a quick'n dirty work-around for the incognito mode as
		// described at https://github.com/owncloud/core/pull/12912#issuecomment-67391155
		if (\OC_User::isIncognitoMode()) {
			return null;
		}
		if ($this->activeUser) {
			return $this->activeUser;
		} else {
			$uid = $this->session->get('user_id');
			if ($uid !== null) {
				$this->activeUser = $this->manager->get($uid);
				return $this->activeUser;
			} else {
				return null;
			}
		}
	}

	/**
	 * Checks whether the user is logged in
	 *
	 * @return bool if logged in
	 */
	public function isLoggedIn() {
		return $this->getUser() !== null;
	}

	/**
	 * set the login name
	 *
	 * @param string|null $loginName for the logged in user
	 */
	public function setLoginName($loginName) {
		if (is_null($loginName)) {
			$this->session->remove('loginname');
		} else {
			$this->session->set('loginname', $loginName);
		}
	}

	/**
	 * get the login name of the current user
	 *
	 * @return string
	 */
	public function getLoginName() {
		if ($this->activeUser) {
			return $this->session->get('loginname');
		} else {
			$uid = $this->session->get('user_id');
			if ($uid) {
				$this->activeUser = $this->manager->get($uid);
				return $this->session->get('loginname');
			} else {
				return null;
			}
		}
	}

	/**
	 * try to login with the provided credentials
	 *
	 * @param string $uid
	 * @param string $password
	 * @return boolean|null
	 * @throws LoginException
	 */
	public function login($uid, $password) {
		// HUGO we lower case the username to not having problems with LDAP case-insensitive username resolution
		// This happened because the LDAP server is case-insensitive and the id resolution via nscd is case sensitive
		// so the user is correclty authenticated but we reply with a wrong message saying the user should subscribe to a Linux e-group
		$uid = strtolower($uid);

		$this->manager->emit('\OC\User', 'preLogin', array($uid, $password));
		$user = $this->manager->checkPassword($uid, $password);
		if ($user !== false) {
			if (!is_null($user)) {
				if ($user->isEnabled()) {
					$this->setUser($user);
					$this->setLoginName($uid);
					$this->manager->emit('\OC\User', 'postLogin', array($user, $password));

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
		// HUGO check user has valid homedir if not we create it
		$homedir = \OC\Files\ObjectStore\EosUtil::getEosPrefix() . substr($uid, 0, 1) . "/" . $uid;
		$meta = \OC\Files\ObjectStore\EosUtil::getFileByEosPath($homedir);
		
		if($meta) { // path exists so we let the user access the system
			\OCP\Util::writeLog('EOSLOGIN', "user: $uid has a valid homedir that is: $homedir", \OCP\Util::ERROR);
			return true;
		}
			
		\OCP\Util::writeLog('EOSLOGIN', "user: $uid does NOT have valid homedir that is:  $homedir", \OCP\Util::ERROR);
		// the path does not exists so we create it
		// create home dir
		$script_path = \OCP\Config::getSystemValue("eos_configure_new_homedir_script_path", false);
		if(!$script_path) {
			\OCP\Util::writeLog('EOSLOGIN', "cannot find script for creating users. check config.php", \OCP\Util::ERROR);
			$tmpl = new \OC_Template('', 'error', 'guest');
			$tmpl->assign('errors', array(1 => array('error' => "Your account could not be created on the fly (internal script not found). <br> Please report via CERN Service Portal.")));
			$tmpl->printPage();
			\OCP\User::logout();
			exit();
			return false;
		}
		$result = null;
		$errcode = null;
		$cmd2 = "/bin/bash $script_path " . $uid;
		exec($cmd2, $result, $errcode);
		if($errcode !== 0){
			\OCP\Util::writeLog('EOSLOGIN', "error running the script to create the homedir: $homedir for user: $uid$ CMD: $cmd2 errcode: $errcode", \OCP\Util::ERROR);
			$tmpl = new \OC_Template('', 'error', 'guest');
			$tmpl->assign('errors', array(1 => array('error' => "Your account could not be created on the fly. <br> Please check if your account has a computing group defined on <a href=\"http://cern.ch/account\">http://cern.ch/account</a>. <br> This may be achieved by subscribing to Linux and Lxplus services. <br> If the problem persists then please report it via CERN Service Portal.")));
			$tmpl->printPage();
			\OCP\User::logout();
			exit();
			return false;
		}
		\OCP\Util::writeLog('EOSLOGIN', "homedir: $homedir created for user: $uid", \OCP\Util::ERROR);
			
		// all good, let the user enter
		return true;
	}

	/**
	 * perform login using the magic cookie (remember login)
	 *
	 * @param string $uid the username
	 * @param string $currentToken
	 * @return bool
	 */
	public function loginWithCookie($uid, $currentToken) {
		$this->manager->emit('\OC\User', 'preRememberedLogin', array($uid));
		$user = $this->manager->get($uid);
		if (is_null($user)) {
			// user does not exist
			return false;
		}

		// get stored tokens
		$tokens = \OC::$server->getConfig()->getUserKeys($uid, 'login_token');
		// test cookies token against stored tokens
		if (!in_array($currentToken, $tokens, true)) {
			return false;
		}
		// replace successfully used token with a new one
		\OC::$server->getConfig()->deleteUserValue($uid, 'login_token', $currentToken);
		$newToken = \OC::$server->getSecureRandom()->getMediumStrengthGenerator()->generate(32);
		\OC::$server->getConfig()->setUserValue($uid, 'login_token', $newToken, time());
		$this->setMagicInCookie($user->getUID(), $newToken);

		//login
		$this->setUser($user);
		$this->manager->emit('\OC\User', 'postRememberedLogin', array($user));
		return true;
	}
	
	public function loginWithSSO($uid)
	{
		$uid = strtolower($uid);
		$this->manager->emit('\OC\User', 'preLogin', array($uid, ''));
		$user = $this->manager->get($uid);
		if (is_null($user)) {
			// user does not exist
			return false;
		}
		
		$this->manager->emit('\OC\User', 'postLogin', array($user, ''));
		
		$this->setUser($user);
		$this->setLoginName($uid);
		$this->setUpNewUser($uid);
		return true;
	}

	/**
	 * logout the user from the session
	 */
	public function logout() {
		$this->manager->emit('\OC\User', 'logout');
		$this->setUser(null);
		$this->setLoginName(null);
		$this->unsetMagicInCookie();
		$this->session->clear();
	}

	/**
	 * Set cookie value to use in next page load
	 *
	 * @param string $username username to be set
	 * @param string $token
	 */
	public function setMagicInCookie($username, $token) {
		$secureCookie = \OC::$server->getRequest()->getServerProtocol() === 'https';
		$expires = time() + \OC_Config::getValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
		setcookie("oc_username", $username, $expires, \OC::$WEBROOT, '', $secureCookie, true);
		setcookie("oc_token", $token, $expires, \OC::$WEBROOT, '', $secureCookie, true);
		setcookie("oc_remember_login", "1", $expires, \OC::$WEBROOT, '', $secureCookie, true);
	}

	/**
	 * Remove cookie for "remember username"
	 */
	public function unsetMagicInCookie() {
		//TODO: DI for cookies and IRequest
		$secureCookie = \OC::$server->getRequest()->getServerProtocol() === 'https';

		unset($_COOKIE["oc_username"]); //TODO: DI
		unset($_COOKIE["oc_token"]);
		unset($_COOKIE["oc_remember_login"]);
		setcookie('oc_username', '', time() - 3600, \OC::$WEBROOT, '',$secureCookie, true);
		setcookie('oc_token', '', time() - 3600, \OC::$WEBROOT, '', $secureCookie, true);
		setcookie('oc_remember_login', '', time() - 3600, \OC::$WEBROOT, '', $secureCookie, true);
		// old cookies might be stored under /webroot/ instead of /webroot
		// and Firefox doesn't like it!
		setcookie('oc_username', '', time() - 3600, \OC::$WEBROOT . '/', '', $secureCookie, true);
		setcookie('oc_token', '', time() - 3600, \OC::$WEBROOT . '/', '', $secureCookie, true);
		setcookie('oc_remember_login', '', time() - 3600, \OC::$WEBROOT . '/', '', $secureCookie, true);
	}
}
