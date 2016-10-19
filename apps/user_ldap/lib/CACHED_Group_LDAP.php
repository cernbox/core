<?php

namespace OCA\User_LDAP;

use OC\CernBox\LDAPCache\LDAPDatabase;
use OC\CernBox\LDAPCache\LDAPCacheManager;
use OCA\User_LDAP\Access;

/**
 * Wrapper class for ldap access using CERNBox's ldap database cache
 */
class CACHED_Group_LDAP extends Group_LDAP
{
	public function __construct(Access $access) 
	{
		parent::__construct($access);
	}
	
	public function inGroup($uid, $gid) 
	{
		$cachedGroups = LDAPCacheManager::getUserEGroups($uid);
		if(array_search($gid, $cachedGroups) !== false)
		{
			return true;
		}
		
		return \OC::$server->getCernBoxEosUtil()->userIsMemberOfEgroup($uid, $gid);
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
			$all[] = $group['group_cn'];
		}
		
		return $all;
	}
	
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) 
	{
		$users = LDAPDatabase::fetchGroupMembers($gid, '%'.$search.'%', $limit);
		$all = [];
		foreach($users as $user)
		{
			$all[] = $user['user_cn'];
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
			$all[] = $group['cn'];
		}
		
		return $all;
	}
	
	public function groupExists($gid) 
	{
		$groupData = LDAPDatabase::getGroupData($gid);
		return ($groupData && count($groupData) > 0);
	}
}