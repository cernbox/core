<?php

namespace OC\CernBox\LDAP;

use OCA\User_LDAP\Access;
use OCA\User_LDAP\Group_LDAP;


class CachedGroup extends Group_LDAP {
	private $instanceManager;
	private $ldapSQLDatabase;

	public function __construct(Access $access) {
		$this->ldapSQLDatabase = new CachedDatabase();
		$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
		parent::__construct($access);
	}

	public function inGroup($uid, $gid) {
		$cachedGroups = $this->ldapSQLDatabase->getUserGroupsMatchingCriteria($uid);
		if(array_search($gid, $cachedGroups) !== false) {
			return true;
		}
		return $this->instanceManager->isUserMemberOfGroup($uid, $gid);
	}

	public function getUserPrimaryGroupIDs($dn) {
		return false;
	}

	public function getUsersInPrimaryGroup($groupDN, $search = '', $limit = -1, $offset = 0) {
		return array();
	}

	public function getUserPrimaryGroup($dn) {
		return false;
	}

	public function getUserGroups($uid) {
		$groups = $this->ldapSQLDatabase->getUserGroupsMatchingCriteria($uid);
		return array_map(function ($group) {
			return $group['group_cn'];
		}, $groups);
	}

	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		$users = $this->ldapSQLDatabase->getGroupMembersMatchingCriteria($gid, $search);
		return array_map(function ($user) {
			return $user['user_cn'];
		}, $users);
	}

	public function countUsersInGroup($gid, $search = '') {
		return count($this->usersInGroup($gid, $search));
	}

	public function getGroups($search = '', $limit = -1, $offset = 0) {
		$groups = $this->ldapSQLDatabase->getAllGroupsMatchingCriteria($search, ['cn'], ['cn'], $limit);
		return array_map(function ($group) {
			return $group['group_cn'];
		}, $groups);
	}

	public function groupExists($gid) {
		$group = $this->ldapSQLDatabase->getGroupByCN($gid);
		return ($group && count($group) > 0);
	}
}