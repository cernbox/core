<?php

namespace OC\CernBox\Backends;


use Guzzle\Http\Client;

/**
 * Class UnixLDAPGroupBackend
 *
 * @package OC\CernBox\Backends
 * This class extends the LDAPGroupBackends and includes unix groups
 * into query results. This is needed to allow sharing with unix groups.
 */ class UnixLDAPGroupBackend extends LDAPGroupBackend {
	private $unixBaseDN;

	public function __construct() {
		$this->unixBaseDN = \OC::$server->getConfig()->getSystemValue("cbox.ldap.group.unixbasedn", "OU=unix,OU=Workgroups,DC=cern,DC=ch");
		parent::__construct();
	}

	protected function getLink() {
		return parent::getLink(); // TODO: Change the autogenerated stub
	}

	public function implementsActions($actions) {
		return parent::implementsActions($actions); // TODO: Change the autogenerated stub
	}

	public function inGroup($uid, $gid) {
		return parent::inGroup($uid, $gid); // TODO: Change the autogenerated gstub
	}

	public function getUserGroups($uid) {
		$egroups = parent::getUserGroups($uid); // TODO: Change the autogenerated stub
		$client = new Client();
		$request = $client->createRequest("GET", sprintf("%s/usercomputinggroups/%s", $this->cboxgroupdBaseURL, $uid), null, null);
		$request->addHeader("Authorization", "Bearer " . $this->cboxgroupdSecret);
		$response = $client->send($request);
		if ($response->getStatusCode() == 200) {
			$json = $response->json();
			$unixGroups = [];
			foreach($json as $unixGroup) {
				$unixGroups[]  = "g:" . $unixGroup;
			}
			return array_merge($egroups, $unixGroups);
		} else {
			throw new \Exception('req to cboxgroupd failed');
		}
	}

	public function getGroups($search = '', $limit = -1, $offset = 0) {
		$gids = parent::getGroups($search, $limit, $offset); // TODO: Change the autogenerated stub

		if(strpos($search, "g:") === 0) {
			$search = str_replace("g:", "", $search);
			$this->logger->info(sprintf("dn=%s search=%s limit=%s", $this->unixBaseDN, $search, $limit));
			$ldapLink = $this->getLink();
			$sr = ldap_search($ldapLink, $this->unixBaseDN, sprintf($this->searchFilter, $search), $this->searchAttrs);
			$this->logger->info(sprintf("number of entries returned: %d", ldap_count_entries($ldapLink, $sr)));

			$info = ldap_get_entries($ldapLink, $sr);

			for ($i = 0; $i < $info["count"]; $i++) {
				$this->logger->info(sprintf("dn=%s", $info[$i]["dn"]));
				$this->logger->info(sprintf("cn=%s", $info[$i][$this->uidAttr][0]));
				$this->logger->info(sprintf("displayName=%s", $info[$i][$this->displayNameAttr][0]));
				$gids[] = "g:" . $info[$i][$this->uidAttr][0];
			}
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
		$users = parent::usersInGroup($gid, $search, $limit, $offset); // TODO: Change the autogenerated stub
		$client = new Client();
		$request = $client->createRequest("GET", sprintf("%s/usersincomputinggroup/%s", $this->cboxgroupdBaseURL, $gid), null, null);
		$request->addHeader("Authorization", "Bearer " . $this->cboxgroupdSecret);
		$response = $client->send($request);
		if ($response->getStatusCode() == 200) {
			$json = $response->json();
			return array_merge($users, $json);
		} else {
			throw new \Exception('req to cboxgroupd failed');
		}
	}

	protected function getSupportedActions() {
		return parent::getSupportedActions(); // TODO: Change the autogenerated stub
	}

	protected function getGroup($gid) {
		$egroup = parent::getGroup($gid);
		if($egroup) {
			return $egroup;
		}

		$group = false;
		if(strpos($gid, "g:") === 0) {
			$gid = str_replace("g:", "", $gid);
			$ldapLink = $this->getLink();
			$search = sprintf($this->matchFilter, $gid);
			$this->logger->info("filter=$search");
			$sr = ldap_search($ldapLink, $this->unixBaseDN, $search, $this->searchAttrs);
			if ($sr === false) {
				$error = ldap_error($ldapLink);
				$this->logger->error($error);
				throw new \Exception($error);
			}

			$count = ldap_count_entries($ldapLink, $sr);
			if ($count == 0) {
				return false;
			}

			$info = ldap_get_entries($ldapLink, $sr);
			$displayName = sprintf("%s (unix)", $info[0][$this->displayNameAttr][0]);
			$group = array(
				"gid" => "g:" . $info[0][$this->uidAttr][0],
				"display_name" => $displayName,
			);
		}
		return $group;
	}
}
