<?php

namespace OC\CernBox\Backends;


use OC\Group\Backend;
use OCP\GroupInterface;

class GroupBackend implements GroupInterface {

	private $logger;
	private $groups = array();

	public function __construct() {
		$this->logger = \OC::$server->getLogger();
		$groups = \OC::$server->getConfig()->getSystemValue("local_group_backend");
		if(!$groups) {
			$this->groups = array();
		} else {
			$this->groups = $groups;
		}
	}
	
	public function isVisibleForScope($scope) {
		return true;
	}

	public function implementsActions($actions) {
		return (bool)($this->getSupportedActions() & $actions);
	}

	public function inGroup($uid, $gid) {
		foreach($this->groups as $group) {
			foreach($group['users'] as $user) {
				if($group['gid'] === $gid && $user === $uid) {
					return true;
				}
			}
		}
		return false;
	}

	public function getUserGroups($uid) {
		$groups = array();
		foreach($this->groups as $group) {
			foreach($group['users'] as $user) {
				if($user === $uid) {
					$groups[] = $group['gid'];
				}
			}
		}
		return $groups;
	}

	public function getGroups($search = '', $limit = -1, $offset = 0) {
		$groups = array();
		foreach($this->groups as $group) {
			if(strpos($group['gid'], $search) !== false) {
				$groups[] = $group['gid'];
			}
		}
		return $groups;
	}

	public function groupExists($gid) {
		foreach($this->groups as $group) {
			if($group['gid'] === $gid) {
				return true;
			}
		}
		return false;
	}

	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		$users = array();
		foreach($this->groups as $group) {
			foreach($group['users'] as $user) {
				if(strpos($user, $search) !== false) {
					$users[] = $user;
				}
			}
		}
		return $users;
	}

	private function getSupportedActions() {
		return Backend::COUNT_USERS;
	}
}
