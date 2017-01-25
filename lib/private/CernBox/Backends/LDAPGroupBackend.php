<?php

namespace OC\CernBox\Backends;


use OC\Group\Backend;
use OCP\GroupInterface;

class GroupBackend implements GroupInterface {

	private $hostname = 'localhost';
	private $port = 686;
	private $bindUsername = 'test';
	private $bindPassword = 'test';
	private $baseDN;

	public function __construct($hostname, $port, $bindUsername, $bindPassword, $baseDN) {
		$this->logger = \OC::$server->getLogger();

		if ($hostname) {
			$this->hostname = $hostname;
		}
		if ($port) {
			$this->port = $port;
		}
		if ($bindUsername) {
			$this->bindUsername = $bindUsername;
		}
		if ($bindPassword) {
			$this->bindPassword = $bindPassword;
		}
		if ($baseDN) {
			$this->baseDN = $baseDN;
		}

		$this->logger->info(sprintf("hostname=%s port=%d bindUsername=%s bindPassword=%s baseDN=%s",
			$this->hostname,
			$this->port,
			$this->bindUsername,
			'xxxx',
			$this->baseDN));
	}

	private function getLink() {
		$ds = ldap_connect($this->hostname, $this->port);
		if (!$ds) {
			throw new \Exception("ldap connection does not work");
		}
		$bindOK = ldap_bind($ds, $this->bindUsername, $this->bindPassword);
		if (!$bindOK) {
			throw new \Exception("ldap binding does not work");
		}
		return $ds;
	}

	public function implementsActions($actions) {
		return (bool)($this->getSupportedActions() & $actions);
	}

	public function inGroup($uid, $gid) {
	}

	public function getUserGroups($uid) {
	}

	public function getGroups($search = '', $limit = -1, $offset = 0) {
		$gids = [];

		$search = trim($search);
		// less that 3 chars is going to put a lot of load on LDAP
		if(strlen($search) <3) {
			return $uids;
		}

		$this->logger->info("search=$search limit=$limit");
		$ldapLink = $this->getLink();
		$sr = ldap_search($ldapLink, $this->baseDN, sprintf("(&(objectClass=group)(objectClass=top)(cn=%s*))", $search), ["cn", "mail", "displayName"]);
		$this->logger->info(sprintf("number of entries returned: %d", ldap_count_entries($ldapLink, $sr)));

		$info = ldap_get_entries($ldapLink, $sr);

		for ($i = 0; $i < $info["count"]; $i++) {
			$this->logger->info(sprintf("dn=%s", $info[$i]["dn"]));
			$this->logger->info(sprintf("cn=%s", $info[$i]["cn"][0]));
			$this->logger->info(sprintf("mail=%s", $info[$i]["mail"][0]));
			$this->logger->info(sprintf("displayName=%s", $info[$i]["displayName"][0]));
			$gids[] = $info[$i]["cn"][0];
		}
		return $gids;
	}

	public function groupExists($gid) {
		$group = $this->getGroup($gid);
		if($group === false) {
			return false;
		}
		return true;
	}

	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
	}

	private function getSupportedActions() {
		return Backend::COUNT_USERS;
	}

	private function getGroup($gid) {
		$ldapLink = $this->getLink();
		$search =  sprintf("(&(objectClass=group)(objectClass=top)(cn=%s))", $gid);
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
		$group = array(
			"gid" => $info[0]["cn"][0],
			"display_name" => $info[0]["displayname"][0], // TODO(labkode) get displayName attr
			"email" => $info[0]["mail"][0],
		);
		return $group;
	}
}