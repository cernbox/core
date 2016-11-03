<?php

namespace OC\CernBox\LDAP;

use OCA\User_LDAP\Access;
use OCA\User_LDAP\User_LDAP;
use OCP\IConfig;

class CachedUser extends User_LDAP {
	private $util;
	private $ldapSQLDatabase;

	public function __construct(Access $access, IConfig $ocConfig) {
		$this->util = new Util();
		$this->ldapSQLDatabase = new CachedDatabase();
		parent::__construct($access, $ocConfig);
	}

	public function canChangeAvatar($uid) {
		return false;
	}

	public function getUsers($search = '', $limit = 10, $offset = 0) {
		$searchParams = $this->util->getSearchParams();
		if(strpos($searchParams, 'a') !== false) {
			$ldapUsers = $this->ldapSQLDatabase->getAllUsersMatchingCriteria($search,
				['cn', 'displayname'], ['cn'], $limit);
		} else {
			$ldapUsers = $this->ldapSQLDatabase->getPrimaryUsersMatchingCriteria($search,
				['cn', 'displayname'], ['cn'], $limit);
		}
		return $ldapUsers;
	}

	public function userExistsOnLDAP($user) {
		$user = $this->ldapSQLDatabase->getUserByCN($user);
		return $user && count($user) > 0;
	}

	public function userExists($uid) {
		return $this->userExistsOnLDAP($uid);
	}


	public function deleteUser($uid) {
		// nop
	}

	public function getDisplayName($uid) {
		$user = $this->ldapSQLDatabase->getUserByCN($uid);
		if($user) {
			return $user[0]['displayname'];
		} else {
			return false;
		}
	}

	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		$searchParams = $this->util->getSearchParams();
		if(strpos($searchParams, 'a') !== false) {
			$ldapUsers = $this->ldapSQLDatabase->getAllUsersMatchingCriteria($search,
				['cn', 'displayname'], ['cn', 'displayname'], $limit);
		} else {
			$ldapUsers = $this->ldapSQLDatabase->getPrimaryUsersMatchingCriteria($search,
				['cn', 'displayname'], ['cn', 'displayname'], $limit);
		}

		$result = array();
		foreach($ldapUsers as $user) {
			$result[$user['cn']] = $user['displayname'];
		}
		return $result;
	}

	public function implementsActions($actions) {
		return (bool)((\OC_User_Backend::CHECK_PASSWORD
				| \OC_User_Backend::GET_HOME
				| \OC_User_Backend::GET_DISPLAYNAME
				| \OC_User_Backend::SET_DISPLAYNAME
				| \OC_User_Backend::PROVIDE_AVATAR
				| \OC_User_Backend::COUNT_USERS)
				& $actions);
	}


	public function countUsers() {
		$this->ldapSQLDatabase->getNumberOfUsersMatchingCriteria();
	}


}