<?php

namespace OC\CernBox\Backends;


use Guzzle\Http\Client;
use OC\Group\Backend;
use OCP\AppFramework\Http\DataResponse;
use OCP\GroupInterface;

class LDAPGroupBackend implements GroupInterface {

	private $hostname = 'localhost';
	private $port = 389;
	private $baseDN;

	private $cboxgroupdBaseURL = 'http://localhost:2002/api/v1/membership';
	private $cboxgroupdSecret = 'change me !!!';

	public function __construct($hostname, $port, $baseDN, $cboxgroupdSecret, $cboxgroupdBaseURL) {
		$this->logger = \OC::$server->getLogger();

		if ($hostname) {
			$this->hostname = $hostname;
		}
		if ($port) {
			$this->port = $port;
		}
		if ($baseDN) {
			$this->baseDN = $baseDN;
		}
		if($cboxgroupdSecret) {
			$this->cboxgroupdSecret = $cboxgroupdSecret;
		}
		if($cboxgroupdBaseURL) {
			$this->cboxgroupdBaseURL = $cboxgroupdBaseURL;
		}
	}

	private function getLink() {
		$ds = ldap_connect($this->hostname, $this->port);
		if (!$ds) {
			throw new \Exception("ldap connection does not work");
		}
		return $ds;
	}

	public function implementsActions($actions) {
		return (bool)($this->getSupportedActions() & $actions);
	}

	public function inGroup($uid, $gid) {
		$userGroups = $this->getUserGroups($uid);
		foreach($userGroups as $userGroup) {
			if($gid === $userGroup) {
				return true;
			}
		}
		return false;
	}

	public function getUserGroups($uid) {
		$client = new Client();
		$request = $client->createRequest("GET", sprintf("%s/usergroups/%s", $this->cboxgroupdBaseURL, $uid), null, null);
		$request->addHeader("Authorization", "Bearer " . $this->cboxgroupdSecret);
		$response = $client->send($request);
		if ($response->getStatusCode() == 200) {
			$json = $response->json();
			return $json;
		} else {
			throw new \Exception('req to cboxgroupd failed');
		}
	}

	public function getGroups($search = '', $limit = -1, $offset = 0) {
		$gids = [];

		$search = trim($search);
		// less that 3 chars is going to put a lot of load on LDAP
		if (strlen($search) < 3) {
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
		if ($group === false) {
			return false;
		}
		return true;
	}

	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		$client = new Client();
		$request = $client->createRequest("GET", sprintf("%s/usersingroup/%s", $this->cboxgroupdBaseURL, $gid), null, null);
		$request->addHeader("Authorization", "Bearer " . $this->cboxgroupdSecret);
		$response = $client->send($request);
		if ($response->getStatusCode() == 200) {
			$json = $response->json();
			return $json;
		} else {
			throw new \Exception('req to cboxgroupd failed');
		}
	}

	private function getSupportedActions() {
		return Backend::COUNT_USERS;
	}

	private function getGroup($gid) {
		$ldapLink = $this->getLink();
		$search = sprintf("(&(objectClass=group)(objectClass=top)(cn=%s))", $gid);
		$this->logger->info("filter=$search");
		$sr = ldap_search($ldapLink, $this->baseDN, $search, ["cn", "mail", "displayName"]);
		if ($sr === null) {
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