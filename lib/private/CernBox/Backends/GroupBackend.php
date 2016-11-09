<?php

namespace OC\CernBox\Backends;


use OC\Group\Backend;
use OCP\GroupInterface;

class GroupBackend implements GroupInterface {

	private $groups = array();

	public function __construct() {
		$this->groups = array(
			array(
				'gid' => 'cernbox-admins',
				'users' => array('labradorsvc', 'gonzalhu'),
			),
			array(
				'gid' => 'cernbox-project-labradortestproject-readers',
				'users' => array('labradorsvc'),
			),
		);
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
					$users[] = $users;
				}
			}
		}
		return $users;
	}

	private function getSupportedActions() {
		return Backend::COUNT_USERS;
	}
}