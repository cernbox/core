<?php

namespace OC\CernBox\LDAP;


use OCP\Util;

class CachedDatabase {
	/**
	 * @var \OCP\IDBConnection
	 */
	private $con;
	private $usersTable = 'cernbox_ldap_users';
	private $groupsTable = 'cernbox_ldap_groups';
	private $groupMembersTable = 'cernbox_ldap_group_members';

	public function __construct() {
		$this->con = \OC::$server->getDatabaseConnection();
	}

	public function getAllUsersMatchingCriteria($search = "",
								array $searchAttributes = ['cn'],
								array $attributesToRetrieve = ['cn'],
								$limit = null)
	{
		// $attributesToRetrieve => cn,displayname
		$attributesToRetrieve = implode(",", $attributesToRetrieve);

		// $likes => cn LIKE %labrador% OR displayname LIKE %labrador%
		$likes = array();
		foreach($searchAttributes as $searchAttribute) {
			$likes[] = $searchAttribute . " LIKE %$search%";
		}
		$likes = implode(" OR ", $likes);
		$data = $this->con->executeQuery("SELECT $attributesToRetrieve FROM ? WHERE $likes LIMIT ?", array($this->usersTable, $limit))->fetchAll();
		return $data;
	}

	public function getPrimaryUsersMatchingCriteria($search = "",
								array $searchAttributes,
								array $attributesToRetrieve,
								$limit = null)
	{
		$attributesToRetrieve = implode(",", $attributesToRetrieve);
		$likes = array();
		foreach($searchAttributes as $searchAttribute) {
			$likes[] = $searchAttribute . " LIKE %$search%";
		}
		$likes = implode(" OR ", $likes);
		$data = $this->con->executeQuery("SELECT $attributesToRetrieve FROM ? WHERE $likes AND employeetype=? LIMIT ? ", array($this->usersTable, 'Primary', $limit))->fetchAll();
		return $data;
	}

	public function getUserByCN($cn)
	{
		$data = $this->con->executeQuery("SELECT * FROM ? WHERE cn=?", array($this->usersTable, $cn))->fetchAll();
		if(count($data) === 0) {
			return null;
		} else {
			return $data[0]; //if there are more users with same cn (unprovable) we return the first one.
		}
	}

	public function getAllGroupsMatchingCriteria($search = "",
								array $searchAttributes = ['cn'],
								array $attributesToRetrieve = ['cn'],
								$limit = null)
	{
		// $attributesToRetrieve => cn,displayname
		$attributesToRetrieve = implode(",", $attributesToRetrieve);

		// $likes => cn LIKE %labrador% OR displayname LIKE %labrador%
		$likes = array();
		foreach($searchAttributes as $searchAttribute) {
			$likes[] = $searchAttribute . " LIKE %$search%";
		}
		$likes = implode(" OR ", $likes);
		$data = $this->con->executeQuery("SELECT $attributesToRetrieve FROM ? WHERE $likes LIMIT ?", array($this->groupsTable, $limit))->fetchAll();
		return $data;
	}

	public function getGroupByCN($cn)
	{
		$data = $this->con->executeQuery("SELECT * FROM ? WHERE cn=?", array($this->groupsTable, $cn))->fetchAll();
		if(count($data) === 0) {
			return null;
		} else {
			return $data[0]; //if there are more users with same cn (unprovable) we return the first one.
		}
	}

	public function getNumberOfUsersMatchingCriteria($search = '')
	{
		$data = $this->con->executeQuery("SELECT COUNT(*) FROM ? WHERE cn LIKE %?%", array($this->usersTable, $$search))->fetchAll();
		return $data['COUNT(*)'];
	}

	public function getNumberOfGroupsMatchingCriteria($search = '')
	{
		$data = $this->con->executeQuery("SELECT COUNT(*) FROM ? WHERE cn LIKE %?%", array($this->groupsTable, $$search))->fetchAll();
		return $data['COUNT(*)'];
	}

	public function getGroupMembersMatchingCriteria($group, $search = '')
	{
		try {
			if (empty($search)) {
				$data = $this->con->executeQuery("SELECT * FROM ? WHERE group_cn = ?", array($this->groupMembersTable, $group))->fetchAll();
				return $data;
			} else {
				// $likes => cn LIKE %labrador% OR displayname LIKE %labrador%
				$likes = array();
				$likes[] = "user_cn LIKE %$search%";
				$likes = implode(" OR ", $likes);
				$data = $this->con->executeQuery("SELECT * FROM ? WHERE group_cn = ? AND $likes", array($this->groupMembersTable, $group))->fetchAll();
				return $data;
			}
		} catch (\Exception $e) {
			Util::writeLog('user_ldap', 'Could not fetch users from group ' . $group . ' from database: ' . $e->getMessage(), Util::ERROR);
			return false;
		}
	}

	public function getUserGroupsMatchingCriteria($user, $search = '') {
		try {
			if (empty($search)) {
				$data = $this->con->executeQuery("SELECT * FROM ? WHERE user_cn = ?", array($this->groupMembersTable, $user))->fetchAll();
				return $data;
			} else {
				// $likes => cn LIKE %labrador% OR displayname LIKE %labrador%
				$likes = array();
				$likes[] = "group_cn LIKE %$search%";
				$likes = implode(" OR ", $likes);
				$data = $this->con->executeQuery("SELECT * FROM ? WHERE group_cn = ? AND $likes", array($this->groupMembersTable, $user))->fetchAll();
				return $data;
			}
		} catch (\Exception $e) {
			Util::writeLog('user_ldap', 'Could not fetch groups from user ' . $user. ' from database: ' . $e->getMessage(), Util::ERROR);
			return false;
		}
	}

	public function isInGroup($user, $group) {
		$data = $this->con->executeQuery("SELECT * FROM ? WHERE user_cn = ? AND group_cn=?", array($this->groupMembersTable, $user, $group))->fetchAll();
		return $data && true;
	}
}