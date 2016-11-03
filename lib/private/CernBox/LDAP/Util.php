<?php

namespace OC\CernBox\LDAP;

class Util {
	private $ldapUsersBaseDN;
	private $ldapGroupsBaseDN;
	private $searchParams;

	public function __construct() {
		// TODO(labkode): put this in config file like cernbox.ldapuserbasedn
		$this->ldapUsersBaseDN = 'ou=users,ou=organic units,dc=cern,dc=ch';
		$this->ldapGroupsBaseDN = 'ou=e-groups,ou=Workgroups,ou=cern,ou=ch';
	}

	public function getLDAPUsersBaseDN() {
		return $this->ldapUsersBaseDN;
	}

	public function getLDAPGroupsBaseDN() {
		return $this->ldapGroupsBaseDN;
	}

	public function getUserDN($userCN) {
		$userDN = "cn=$userCN," . $this->ldapUsersBaseDN;
		return $userDN;
	}

	public function getUserCN($userDN) {
		$tokens = explode(",", $userDN);
		foreach($tokens as $token) {
			list($key, $value) = explode("=", $token);
			if(strtolower($key) === 'cn') {
				return $value;
			}
		}
		return null;
	}

	public function getGroupDN($groupCN) {
		$groupDN = "cn=$groupCN," . $this->ldapGroupsBaseDN;
		return $groupDN;
	}

	public function getGroupCN($groupDN) {
		$tokens = explode(",", $groupDN);
		foreach($tokens as $token) {
			list($key, $value) = explode("=", $token);
			if(strtolower($key) === 'cn') {
				return $value;
			}
		}
		return null;
	}

	public function setSearchParams(array $params) {
		$this->searchParams = $params;
	}

	public function getSearchParams() {
		return $this->searchParams;
	}
}