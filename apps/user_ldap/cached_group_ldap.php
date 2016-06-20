<?php

namespace OCA\user_ldap;

use OCA\user_ldap\GROUP_LDAP;
use OC\Cernbox\LDAP\LDAPDatabase;
use OC\Cernbox\Storage\EosUtil;
use OCA\user_ldap\lib\Access;
use OC\Cernbox\LDAP\LDAPCacheManager;

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
		$cachedGroups = LDAPCacheManager::getUserEGroups($uid);
		if(array_search($gid, $cachedGroups) !== false)
		{
			return true;
		}
		
		return EosUtil::isMemberOfEGroup($uid, $gid);
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
		/*$groups = LDAPDatabase::fetchUserGroups($uid);
		$all = [];
		foreach($groups as $group)
		{
			$all[] = $group['group_cn'];
		}
		
		return $all;*/
		return LDAPCacheManager::getUserEGroups($uid);
	}
	
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) 
	{
		/*$users = LDAPDatabase::fetchGroupMembers($gid, '%'.$search.'%', $limit);
		$all = [];
		foreach($users as $user)
		{
			$all[] = $user['user_cn'];
		}
		
		return $all;*/
		return [];
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
		
		$onRedis = LDAPCacheManager::getAllCachedGroups();
		
		$all = array_merge($all, $onRedis);
		$all = array_unique($all);
		
		return $all;
	}
	
	public function groupExists($gid) 
	{
		return LDAPCacheManager::groupExists($gid);
	}
}