<?php

namespace OC\CernBox\Backends;

use OC\User\Backend;
use OCP\IUserBackend;
use OCP\UserInterface;

class UserBackend implements UserInterface, IUserBackend {
	private $users = array();

	public function __construct() {
		$this->users = array(
			array(
				'uid' => 'labradorsvc',
				'password' => 'testor',
				'display_name' => 'Labrador Service Account'
			),
			array(
				'uid' => 'ourense',
				'password' => 'test',
				'display_name' => 'Ourense Is Good'
			),
		);

		// add the group backend
		\OC::$server->getGroupManager()->clearBackends();
		\OC::$server->getGroupManager()->addBackend(new GroupBackend());
	}

	public function getBackendName() {
		return 'CernBoxUserBackend';
	}

	public function implementsActions($actions) {
		return (bool)($this->getSupportedActions() & $actions);
	}

	public function deleteUser($uid) {
		// nop
	}

	public function getUsers($search = '', $limit = null, $offset = null) {
		$uids = array();
		foreach($this->users as $user) {
			if (
				strpos($user['uid'], $search) !== false ||
				strpos($user['display_name'], $search) !== false
			) {
				$uids[] = $user['uid'];
			}
		}
		return $uids;
	}

	public function userExists($uid) {
		foreach($this->users as $user) {
			if ($user['uid'] === $uid) {
				return true;
			}
		}
		return false;
	}

	public function getDisplayName($uid) {
		foreach($this->users as $user) {
			if($user['uid'] === $uid) {
				return $user['display_name'];
			}
		}
		return false;
	}

	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		$map = array();
		foreach($this->users as $user) {
			if ( strpos($user['display_name'], $search) !== false ) {
				$map[$user['uid']] = $user['display_name'];
			}
		}
		return $map;
	}

	public function hasUserListings() {
		return true;
	}

	/**
	 * Check if the password is correct
	 * @param string $uid The username
	 * @param string $password The password
	 * @return string
	 *
	 * Check if the password is correct without logging in the user
	 * returns the user id or false
	 */
	// NOT IN INTERFACE
	public function checkPassword($uid, $password) {
		foreach($this->users as $user) {
			if($user['uid'] === $uid && $user['password'] === $password) {
				return $user['uid'];
			}
		}
		return false;
	}

	// NOT IN INTERFACE
	public function getHome() {
		return false;
	}

	private function getSupportedActions() {
		return Backend::COUNT_USERS | Backend::GET_DISPLAYNAME | Backend::GET_HOME | Backend::CHECK_PASSWORD;
	}
}
