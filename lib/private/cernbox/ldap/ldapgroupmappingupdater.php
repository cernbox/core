<?php

namespace OC\Cernbox\LDAP;

use \OC\Cernbox\Storage\EosUtil;
use \OC\Cernbox\Storage\Redis;
use \OC\Cernbox\LDAP\LDAPCacheManager;
use \OC\Cernbox\LDAP\LDAPUpdater;

class LDAPGroupMappingUpdater extends LDAPUpdater
{
	const CACHE_VALIDITY_TIME = 1800; // Half a hour
	
	private static $userFetchers = 
	[
			'\OC\LDAPCache\UserFetchers\RedisFetcher',
			'\OC\LDAPCache\UserFetchers\RedisFetcher',
			'\OC\LDAPCache\UserFetchers\EosFetcher',
			'\OC\LDAPCache\UserFetchers\FileFetcher'
	];
	
	private $priority;
	private $redisKey;
	
	public function __contruct(&$ldapAccess)
	{
		parent::__construct($ldapAccess);
	}
	
	public function setPriority($priority)
	{
		$this->priority = $priority;
		$this->redisKey = LDAPCacheManager::REDIS_KEY_USERS_REFRESH . strval($this->priority);
	}
	
	private function getUsedEGroups()
	{
		return LDAPCacheManager::getAllCachedGroups();
	}
	
	private function getUsersToUpdate()
	{
		$fetcherStr = self::$userFetchers[$this->priority - 1];
		$fetcher = new $fetcherStr();
		return $fetcher->fetchUsers($this->priority);
	}
	
	private function userNeedsUpdate($user)
	{
		$time = LDAPCacheManager::getUserExpirationTime($user);
		$priorityRefreshRate = self::CACHE_VALIDITY_TIME;
		return (($time + $priorityRefreshRate) < time());
	}
	
	public function fetchData()
	{
		$groups = $this->getUsedEGroups();
		$users = $this->getUsersToUpdate();
		
		foreach($users as $user => $dummy)
		{
			if($this->userNeedsUpdate($user))
			{
				$this->ldapData[$user] = [];
				foreach($groups as $group)
				{
					$this->ldapData[$user][] = [$group['share_with'], strval(EosUtil::isMemberOfEGroup($user, $group['share_with']))];
				}
				
				LDAPCacheManager::setUserExpirationTime($user, time());
			}
		}
	}
	
	public function fillCache()
	{
		foreach($this->ldapData as $user => $cache)
		{
			Redis::deleteFromCacheMap($this->redisKey, $user);
			LDAPCacheManager::setUserEGroups($user, $cache);
		}
	}
}