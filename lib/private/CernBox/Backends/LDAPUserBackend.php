<?php

namespace OC\CernBox\Backends;

use OC\User\Backend;
use OCP\IUserBackend;
use OCP\UserInterface;

class LDAPUserBackend implements UserInterface, IUserBackend {
	private $hostname = 'localhost';
	private $port = 686;
	private $bindUsername = 'test';
	private $bindPassword = 'test';
	private $baseDN;
	private $isVersion3 = false;

	private $logger;

	public function __construct($hostname, $port, $bindUsername, $bindPassword, $baseDN, $isVersion3) {
		$this->logger = \OC::$server->getLogger();

		if($hostname) {
			$this->hostname = $hostname;
		}
		if($port) {
			$this->port = $port;
		}
		if($bindUsername) {
			$this->bindUsername = $bindUsername;
		}
		if($bindPassword) {
			$this->bindPassword = $bindPassword;
		}
		if($baseDN) {
			$this->baseDN = $baseDN;
		}
		$this->isVersion3 = $isVersion3;

		$this->logger->info(sprintf("hostname=%s port=%d bindUsername=%s bindPassword=%s baseDN=%s",
			$this->hostname,
			$this->port,
			$this->bindUsername,
			'xxxx',
			$this->baseDN));

		// add the group backend
		\OC::$server->getGroupManager()->clearBackends();
		\OC::$server->getGroupManager()->addBackend(
			new LDAPGroupBackend(
				\OC::$server->getConfig()->getSystemValue('ldap_group_backend.hostname'),
				\OC::$server->getConfig()->getSystemValue('ldap_group_backend.port'),
				\OC::$server->getConfig()->getSystemValue('ldap_group_backend.dn'),
				\OC::$server->getConfig()->getSystemValue('ldap_group_backend.cboxgroupd.secret'),
				\OC::$server->getConfig()->getSystemValue('ldap_group_backend.cboxgroupd.baseurl'))
		);
	}

	private function getLink() {
		$ds = ldap_connect($this->hostname, $this->port);
		if (!$ds) {
			throw new \Exception("ldap connection does not work");
		}
	        if($this->isVersion3) {
			ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
		}	
		$bindOK = ldap_bind($ds, $this->bindUsername, $this->bindPassword);
		if (!$bindOK) {
			throw new \Exception("ldap binding does not work");
		}
		return $ds;
	}

	public function getBackendName() {
		return 'CernBoxLDAPUserBackend';
	}

	public function implementsActions($actions) {
		return (bool)($this->getSupportedActions() & $actions);
	}

	public function deleteUser($uid) {
		// nop
	}

	private function getUser($uid) {
		$ldapLink = $this->getLink();
		$search =  sprintf("(&(objectClass=user)(samaccountname=%s))", $uid);
		$this->logger->info("filter=$search");
		$sr = ldap_search($ldapLink, $this->baseDN,  $search, ["cn", "mail", "displayName"]);
		if($sr === null) {
			$error = ldap_error($ldapLink);
			$this->logger->error($error);
			throw new Exception($error);
		}

		$count = ldap_count_entries($ldapLink, $sr);
		if ($count == 0) {
			return false;
		}

		$info = ldap_get_entries($ldapLink, $sr);
		$user = array(
			"dn" => $info[0]["dn"],
			"uid" => $info[0]["cn"][0],
			"display_name" => $info[0]["displayname"][0], // TODO(labkode) get displayName attr
			"email" => $info[0]["mail"][0],
		);
		return $user;
	}

	public function getUsers($search = '', $limit = null, $offset = null) {
		$uids = [];

		$search = trim($search);
		// less that 3 chars is going to put a lot of load on LDAP
		if(strlen($search) <3) {
			return $uids;
		}

		$this->logger->info("search=$search limit=$limit");
		$ldapLink = $this->getLink();
		$sr = ldap_search($ldapLink, $this->baseDN, sprintf("(&(objectClass=user)(samaccountname=%s*))", $search), ["cn", "mail", "displayName"]);
		$this->logger->info(sprintf("number of entries returned: %d", ldap_count_entries($ldapLink, $sr)));

		$info = ldap_get_entries($ldapLink, $sr);

		for ($i = 0; $i < $info["count"]; $i++) {
			$this->logger->info(sprintf("dn=%s", $info[$i]["dn"]));
			$this->logger->info(sprintf("cn=%s", $info[$i]["cn"][0]));
			$this->logger->info(sprintf("mail=%s", $info[$i]["mail"][0]));
			$this->logger->info(sprintf("displayName=%s", $info[$i]["displayName"][0]));
			$uids[] = $info[$i]["cn"][0];
		}
		return $uids;
	}

	public function userExists($uid) {
		$user = $this->getUser($uid);
		if ($user === false) {
			return false;
		} else {
			return true;
		}
	}

	public function getDisplayName($uid) {
		$user = $this->getUser($uid);
		if ($user === false) {
			return false;
		}
		return $user['display_name'];
	}

	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		$map = array();

		$search = trim($search);

		// less that 3 chars is going to put a lot of load on LDAP
		if(strlen($search) <3) {
			return $map;
		}

		$ldapLink = $this->getLink();
		$sr = ldap_search($ldapLink, $this->baseDN, sprintf("(&(objectClass=user)(displayName=%s*))", $search), ["cn", "mail", "displayName"]);
		$this->logger->info(sprintf("number of entries returned: %d", ldap_count_entries($ldapLink, $sr)));

		$info = ldap_get_entries($ldapLink, $sr);

		for ($i = 0; $i < $info["count"]; $i++) {
			$this->logger->info(sprintf("dn=%s", $info[$i]["dn"]));
			$this->logger->info(sprintf("cn=%s", $info[$i]["cn"][0]));
			$this->logger->info(sprintf("email=%s", $info[$i]["email"][0]));
			$this->logger->info(sprintf("displayName=%s", $info[$i]["displayName"][0]));
			$map[$info[$i]["cn"][0]] = $info[$i]["displayName"][0];
		}

		return $map;
	}

	public function hasUserListings() {
		return true;
	}

	/**
	 * Check if the password is correct
	 *
	 * @param string $uid The username
	 * @param string $password The password
	 * @return string
	 *
	 * Check if the password is correct without logging in the user
	 * returns the user id or false
	 */
	// NOT IN INTERFACE
	public function checkPassword($uid, $password) {
		if (empty($password)) {
			return false;
		}
		if (empty($uid)) {
			return false;
		}
		$user = $this->getUser($uid);
		if ($user === false) {
			return false;
		}
		$ldapLink = $this->getLink();
		$dn = $user["dn"];
		$bindOK = ldap_bind($ldapLink, $dn, $password);
		if (!$bindOK) {
			return false;
		}
		return $user['uid'];
	}

	// NOT IN INTERFACE
	public function getHome() {
		return false;
	}

	private function getSupportedActions() {
		return Backend::COUNT_USERS | Backend::GET_DISPLAYNAME | Backend::GET_HOME | Backend::CHECK_PASSWORD;
	}
}
