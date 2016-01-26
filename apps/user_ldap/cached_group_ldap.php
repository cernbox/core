<?php

namespace OCA\user_ldap;

use OCA\user_ldap\GROUP_LDAP;
use OC\Cache\LDAPDatabase;

/**
 * Wrapper class for ldap access using CERNBox's ldap database cache
 */
class CACHED_GROUP_LDAP extends GROUP_LDAP
{
	public function __construct(Access $access) 
	{
		parent::__construct($access);
	}
	
	public function inGroup($uid, $gid) 
	{
		return LDAPDatabase::isInGroup($uid, $gid);
	}
	
	public function getUsersInPrimaryGroup($groupDN, $search = '', $limit = -1, $offset = 0)
	{
		return [];
	}
	
	public function getUserPrimaryGroupIDs($dn) 
	{
		return false;
	}
	
	public function countUsersInPrimaryGroup($groupDN, $search = '', $limit = -1, $offset = 0) 
	{
		return 0;	
	}
	
	public function getUserPrimaryGroup($dn) 
	{
		return false;	
	}
	
	public function getUserGroups($uid) 
	{
		$groups = LDAPDatabase::fetchUserGroups($uid);
		$all = [];
		foreach($groups as $group)
		{
			$all[] = $group;
		}
		
		return $all;
	}
	
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) 
	{
		$users = LDAPDatabase::fetchGroupMembers($gid, '%'.$search.'%', $limit);
		$all = [];
		foreach($users as $user)
		{
			$all[] = $user;
		}
		
		return $all;
	}
	
	public function countUsersInGroup($gid, $search = '') 
	{
		return count($this->usersInGroup($gid, $search));
	}
	
	public function getGroups($search = '', $limit = -1, $offset = 0) 
	{
		$groups = LDAPDatabase::fetchGroupsData('%'.$search.'%', ['cn'], ['cn'], $limit);
		$all = [];
		foreach($groups as $group)
		{
			$all[] = $group;
		}
		
		return $all;
	}
	
	public function groupExists($gid) 
	{
		$groupData = LDAPDatabase::getGroupData($gid);
		return ($groupData && count($groupData) > 0);
	}
}