<?php

namespace OC\CernBox\Backends;


use Guzzle\Http\Client;
use OC\Group\Backend;
use OCP\GroupInterface;

class LDAPGroupBackend implements GroupInterface {
	protected $hostname;
	protected $config;
	protected $port;
	protected $bindUsername;
	protected $bindPassword;
	protected $baseDN;
	protected $isVersion3;
	protected $groupBackend;
	protected $matchFilter;
	protected $searchFilter;
	protected $searchAttrs;
	protected $displayNameAttr;
	protected $uidAttr;
	protected $cboxgroupdBaseURL;
	protected $cboxgroupdSecret;

	public function __construct() {
		$this->logger = \OC::$server->getLogger();
		$this->config = \OC::$server->getConfig();

		$this->hostname = $this->config->getSystemValue("cbox.ldap.group.hostname", "localhost");
		$this->port = $this->config->getSystemValue("cbox.ldap.group.port", 389);
		$this->bindUsername = $this->config->getSystemValue("cbox.ldap.group.bindusername", "admin");
		$this->bindPassword = $this->config->getSystemValue("cbox.ldap.group.bindpassword", "admin");
		$this->baseDN = $this->config->getSystemValue("cbox.ldap.group.basedn", "dc=example,dc=org");
		$this->isVersion3 = $this->config->getSystemValue("cbox.ldap.group.version3", false);
		$this->matchFilter = $this->config->getSystemValue("cbox.ldap.group.matchfilter", "(&(objectClass=group)(objectClass=top)(cn=%s))");
		$this->searchFilter = $this->config->getSystemValue("cbox.ldap.group.searchfilter", "(&(objectClass=group)(objectClass=top)(cn=*%s*))");
		$this->searchAttrs = $this->config->getSystemValue("cbox.ldap.group.searchattrs", ["uid", "mail", "gecos"]);
		$this->displayNameAttr = $this->config->getSystemValue("cbox.ldap.group.displaynameattr", "gecos");
		$this->uidAttr = $this->config->getSystemValue("cbox.ldap.group.uidattr", "uid");
		$this->mailAttr = $this->config->getSystemValue("cbox.ldap.group.mailattr", "mail");
		$this->cboxgroupdSecret = $this->config->getSystemValue("cbox.ldap.group.cboxgroupd.secret", "change me !!!");
		$this->cboxgroupdBaseURL = $this->config->getSystemValue("cbox.ldap.group.cboxgroupd.baseurl", "http://localhost:2002/api/v1/membership");

		$this->logger->info(sprintf("hostname=%s port=%d bindUsername=%s baseDN=%s matchFilter=%s searchFilter=%s searchAttrs=%s displayNameAttr=%s uidAttr=%s",
			$this->hostname,
			$this->port,
			$this->bindUsername,
			$this->baseDN,
			$this->matchFilter,
			$this->searchFilter,
			implode(',', $this->searchAttrs),
			$this->displayNameAttr,
			$this->uidAttr));
	}


	protected function getLink() {
		$ds = ldap_connect($this->hostname, $this->port);
		if (!$ds) {
			throw new \Exception("ldap connection does not work");
		}
		if ($this->isVersion3) {
			ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
		}
		if(!empty($this->username) && !empty($this->bindPassword)) {
			$bindOK = ldap_bind($ds, $this->bindUsername, $this->bindPassword);
			if (!$bindOK) {
				throw new \Exception("ldap binding does not work");
			}
		}
		return $ds;
	}

	public function isVisibleForScope($scope) {
		return true;
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
		// less that 2 chars is going to put a lot of load on LDAP
		if (strlen($search) < 2) {
			return $gids;
		}

		$this->logger->info("search=$search limit=$limit");
		$ldapLink = $this->getLink();
		$sr = ldap_search($ldapLink, $this->baseDN,str_replace('%s', $search, $this->searchFilter), $this->searchAttrs);
		$this->logger->info(sprintf("number of entries returned: %d", ldap_count_entries($ldapLink, $sr)));

		$info = ldap_get_entries($ldapLink, $sr);

		for ($i = 0; $i < $info["count"]; $i++) {
			$this->logger->info(sprintf("dn=%s", $info[$i]["dn"]));
			$this->logger->info(sprintf("cn=%s", $info[$i][$this->uidAttr][0]));
			$this->logger->info(sprintf("displayName=%s", $info[$i][$this->displayNameAttr][0]));
			$gids[] = $info[$i][$this->uidAttr][0];
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

	protected function getSupportedActions() {
		return Backend::COUNT_USERS;
	}

	protected function getGroup($gid) {
		$ldapLink = $this->getLink();
		$search = sprintf($this->matchFilter, $gid);
		$this->logger->info("filter=$search");
		$sr = ldap_search($ldapLink, $this->baseDN, $search, $this->searchAttrs);
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
		$group = array(
			"gid" => $info[0][$this->uidAttr][0],
			"display_name" => $info[0][$this->displayNameAttr][0], // TODO(labkode) get displayName attr
		);
		return $group;
	}
}
