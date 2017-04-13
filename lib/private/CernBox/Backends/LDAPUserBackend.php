<?php

namespace OC\CernBox\Backends;

use OC\User\Backend;
use OCP\IUserBackend;
use OCP\UserInterface;

class LDAPUserBackend implements UserInterface, IUserBackend {
	/*
	 * @var \OCP\Config
	 */
	private $config;
	private $hostname;
	private $port;
	private $bindUsername;
	private $bindPassword;
	private $baseDN;
	private $isVersion3;
	private $groupBackend;
	private $matchFilter;
	private $searchFilter;
	private $searchAttrs;
	private $displayNameAttr;
	private $uidAttr;

	private $logger;

	public function __construct($hostname = "localhost", $port = 389, $bindUsername = "admin", $bindPassword = "admin", $baseDN = "dc=example,dc=org", $isVersion3 = true) {
		$this->logger = \OC::$server->getLogger();
		$this->config = \OC::$server->getConfig();

		$this->hostname = $this->config->getSystemValue("cbox.ldap.user.hostname", "localhost");
		$this->port = $this->config->getSystemValue("cbox.ldap.user.port", 389);
		$this->bindUsername = $this->config->getSystemValue("cbox.ldap.user.bindusername", "admin");
		$this->bindPassword = $this->config->getSystemValue("cbox.ldap.user.bindpassword", "admin");
		$this->baseDN = $this->config->getSystemValue("cbox.ldap.user.basedn", "dc=example,dc=org");
		$this->isVersion3 = $this->config->getSystemValue("cbox.ldap.user.version3", false);
		$this->groupBackend = $this->config->getSystemValue("cbox.ldap.user.groupbackend", "\\OC\\CernBox\\Backends\\GroupBackend");
		$this->matchFilter = $this->config->getSystemValue("cbox.ldap.user.matchfilter", "(&(objectClass=account)(uid=%s))");
		$this->searchFilter = $this->config->getSystemValue("cbox.ldap.user.searchfilter", "(&(objectClass=account)(uid=%s*))");
		$this->searchAttrs = $this->config->getSystemValue("cbox.ldap.user.searchattrs", ["uid", "mail", "gecos"]);
		$this->displayNameAttr = $this->config->getSystemValue("cbox.ldap.user.displaynameattr", "gecos");
		$this->uidAttr = $this->config->getSystemValue("cbox.ldap.user.uidattr", "uid");


		$this->logger->info(sprintf("hostname=%s port=%d bindUsername=%s bindPassword=%s baseDN=%s",
			$this->hostname,
			$this->port,
			$this->bindUsername,
			'xxxx',
			$this->baseDN));

		// add the group backend
		\OC::$server->getGroupManager()->clearBackends();
		\OC::$server->getGroupManager()->addBackend(new $this->groupBackend());
	}

	public function getBackendName() {
		return 'CernBoxLDAPUserBackend';
	}

	public function implementsActions($actions) {
		return (bool)($this->getSupportedActions() & $actions);
	}

	private function getSupportedActions() {
		return Backend::COUNT_USERS | Backend::GET_DISPLAYNAME | Backend::GET_HOME | Backend::CHECK_PASSWORD;
	}

	public function deleteUser($uid) {
		// nop
	}

	public function getUsers($search = '', $limit = null, $offset = null) {
		$uids = [];

		$search = trim($search);
		// less that 3 chars is going to put a lot of load on LDAP
		if (strlen($search) < 3) {
			return $uids;
		}

		$this->logger->info("search=$search limit=$limit");
		$ldapLink = $this->getLink();
		$sr = ldap_search($ldapLink, $this->baseDN, sprintf($this->searchFilter, $search), $this->searchAttrs);
		$this->logger->info(sprintf("number of entries returned: %d", ldap_count_entries($ldapLink, $sr)));
		$info = ldap_get_entries($ldapLink, $sr);
		for ($i = 0; $i < $info["count"]; $i++) {
			$this->logger->info(sprintf("dn=%s", $info[$i]["dn"]));
			$this->logger->info(sprintf("cn=%s", $info[$i]["cn"][0]));
			$this->logger->info(sprintf("mail=%s", $info[$i]["mail"][0]));
			$this->logger->info(sprintf("display_name=%s", $info[$i][$this->displayNameAttr][0]));
			$uids[] = $info[$i]["cn"][0];
		}
		return $uids;
	}

	private function getLink() {
		$ds = ldap_connect($this->hostname, $this->port);
		if (!$ds) {
			throw new \Exception("ldap connection does not work");
		}
		if ($this->isVersion3) {
			ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
		}
		$bindOK = ldap_bind($ds, $this->bindUsername, $this->bindPassword);
		if (!$bindOK) {
			throw new \Exception("ldap binding does not work");
		}
		return $ds;
	}

	public function userExists($uid) {
		$user = $this->getUser($uid);
		if ($user === false) {
			return false;
		} else {
			return true;
		}
	}

	private function getUser($uid) {
		$ldapLink = $this->getLink();
		$search = sprintf($this->matchFilter, $uid);
		$this->logger->info("filter=$search");
		$sr = ldap_search($ldapLink, $this->baseDN, $search, $this->searchAttrs);
		if ($sr === false) {
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
			"display_name" => $info[0][$this->displayNameAttr][0], // TODO(labkode) get gecos attr
			"email" => $info[0]["mail"][0],
		);
		return $user;
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
		if (strlen($search) < 3) {
			return $map;
		}

		$ldapLink = $this->getLink();
		$sr = ldap_search($ldapLink, $this->baseDN, sprintf("(&(objectClass=account)(" . $this->displayNameAttr . "=%s*))", $search), $this->searchAttrs);
		$this->logger->info(sprintf("number of entries returned: %d", ldap_count_entries($ldapLink, $sr)));

		$info = ldap_get_entries($ldapLink, $sr);

		for ($i = 0; $i < $info["count"]; $i++) {
			$this->logger->info(sprintf("dn=%s", $info[$i]["dn"]));
			$this->logger->info(sprintf("cn=%s", $info[$i]["cn"][0]));
			$this->logger->info(sprintf("email=%s", $info[$i]["email"][0]));
			$this->logger->info(sprintf("display_name=%s", $info[$i][$this->displayNameAttr][0]));
			$map[$info[$i]["cn"][0]] = $info[$i][$this->displayNameAttr][0];
		}

		return $map;
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
	public function hasUserListings() {
		return true;
	}

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

	public function getHome() {
		return false;
	}
}
