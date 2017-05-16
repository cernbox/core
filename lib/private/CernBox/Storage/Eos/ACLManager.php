<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 10/20/16
 * Time: 2:12 PM
 */

namespace OC\CernBox\Storage\Eos;


/**
 * Class ACLManager
 *
 * @package OC\CernBox\Storage\Eos
 * This class manages the sys.acl Eos extended attribute to
 * grant access to other users and groups.
 */
class ACLManager {
	/**
	 * @var ACLEntry[]
	 */
	private $aclEntries;

	public function __construct($multipleEosSysAclEntry) {
		$this->aclEntries = array();
		$multipleEosSysAclEntry = (string)$multipleEosSysAclEntry;
		$grants = explode(",", $multipleEosSysAclEntry);
		foreach($grants as $grant) {
			$acl = new ACLEntry($grant);
			$this->aclEntries[] = $acl;
		}
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getUsers() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::USER_TYPE) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getUsersWithReadPermission() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::USER_TYPE
				&& $entry->hasReadPermission()) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getUsersWithReadAndWritePermissions() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::USER_TYPE
				&& $entry->hasWritePermission()) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getGroups() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::GROUP_TYPE) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getGroupsWithReadPermissions() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::GROUP_TYPE
				&& $entry->hasReadPermission()) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getGroupsWithReadAndWritePermission() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::GROUP_TYPE
				&& $entry->hasWritePermission()) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getUnixGroups() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::UNIX_TYPE) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getUnixGroupsWithReadPermissions() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::UNIX_TYPE
				&& $entry->hasReadPermission()) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @return ACLEntry[]
	 */
	public function getUnixGroupsWithReadAndWritePermission() {
		return array_map(function (ACLEntry $entry) {
			if($entry->getType() === ACLEntry::UNIX_TYPE
				&& $entry->hasWritePermission()) {
				return $entry;
			}
		}, $this->aclEntries);
	}

	/**
	 * @param $username
	 * @return bool|ACLEntry
	 */
	public function getUser($username) {
		$users = $this->getUsers();
		foreach($users as $user) {
			if($user->getGrantee() === $username) {
				return $user;
			}
		}
		return false;
	}

	public function getGroup($gid) {
		$groups = $this->getGroups();
		foreach($groups as $group) {
			if($group->getGrantee() === $gid) {
				return $group;
			}
		}
		return false;
	}

	public function getUnixGroup($gid) {
		$groups = $this->getUnixGroups();
		foreach($groups as $group) {
			if($group->getGrantee() === $gid) {
				return $group;
			}
		}
		return false;
	}

	/**
	 * Adds a new user to the sys.acl with $eosPermissions.
	 * If the entry already exists, it is overwritten.
	 * @param $grantee
	 * @param $eosPermissions
	 * @return bool
	 */
	public function addUser($grantee, $eosPermissions) {
		$this->deleteUser($grantee); // delete previous entry if existed
		$singleACL = implode(":", array(ACLEntry::USER_TYPE, $grantee, $eosPermissions));
		$aclEntry = new ACLEntry($singleACL);
		$this->aclEntries[] = $aclEntry;
		return true;
	}

	public function addGroup($grantee, $eosPermissions) {
		$this->deleteGroup($grantee); // delete previous entry if existed
		$singleACL = implode(":", array(ACLEntry::GROUP_TYPE, $grantee, $eosPermissions));
		$aclEntry = new ACLEntry($singleACL);
		$this->aclEntries[] = $aclEntry;
		return true;
	}

	public function addUnixGroup($grantee, $eosPermissions) {
		$this->deleteUnixGroup($grantee); // delete previous entry if existed
		$singleACL = implode(":", array(ACLEntry::UNIX_TYPE, $grantee, $eosPermissions));
		$aclEntry = new ACLEntry($singleACL);
		$this->aclEntries[] = $aclEntry;
		return true;
	}


	public function deleteUser($grantee) {
		$this->deleteGrantee($grantee);
	}

	public function deleteGroup($grantee) {
		$this->deleteGrantee($grantee);
	}

	public function deleteUnixGroup($grantee) {
		$this->deleteGrantee($grantee);
	}

	public function serializeToEos() {
		$entries = array();
		foreach($this->aclEntries as $entry) {
			$entries[] = $entry->serializeToEos();
		}
		return implode(",", $entries);
	}

	private function deleteGrantee($grantee) {
		foreach($this->aclEntries as $index => $entry) {
			if ($entry->getGrantee() === $grantee) {
				unset($this->aclEntries[$index]);
				$this->aclEntries = array_values($this->aclEntries); // re-index from 0
			}
		}
	}
}

