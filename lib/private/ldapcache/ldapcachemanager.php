<?php

namespace OC\LDAPCache;

use \OC\Files\ObjectStore\Redis;

class LDAPCacheManager
{
	const REDIS_KEY_USER_CACHE_EXPIRATION = 'users_cache_validity';
	const REDIS_KEY_GROUP_MAPPING_CACHE = 'group_mapping_';
	const REDIS_KEY_USERS_REFRESH = 'ldap_refresh_queue_';
	
	public static function &getAccessInstance()
	{
		$helper = new \OCA\user_ldap\lib\Helper();
		$configPrefixes = $helper->getServerConfigurationPrefixes(true);
		$ldapWrapper = new \OCA\user_ldap\lib\LDAP();
		if(count($configPrefixes) === 1)
		{
			//avoid the proxy when there is only one LDAP server configured
			$dbc = \OC::$server->getDatabaseConnection();
			$userManager = new \OCA\user_ldap\lib\user\Manager(
					\OC::$server->getConfig(),
					new \OCA\user_ldap\lib\FilesystemHelper(),
					new \OCA\user_ldap\lib\LogWrapper(),
					\OC::$server->getAvatarManager(),
					new \OCP\Image(),
					$dbc);
			$connector = new \OCA\user_ldap\lib\Connection($ldapWrapper, $configPrefixes[0]);
			$ldapAccess = new \OCA\user_ldap\lib\Access($connector, $ldapWrapper, $userManager);
			
			return $ldapAccess;
		}
		
		return FALSE;
	}
	
	public static function executeRefresh(&$ldapUpdater)
	{
		\OC\Files\ObjectStore\EosUtil::setInternalScriptExecution(true);
		$ldapUpdater->fetchData();
		$ldapUpdater->fillCache();
	}
	
	public static function updateUserCacheRefresh()
	{
		$user = \OC::$server->getUserSession()->getUser();
		if(!$user)
		{
			return;	
		}
		
		$user = $user->getUID();
		
		$url = $_SERVER['REQUEST_URI'];
		if($url && !empty($url))
		{
			$redisKey = self::REDIS_KEY_USERS_REFRESH;
			if(strpos($url, 'heartbeat') !== FALSE)
			{
				$redisKey .= '2';
			}
			else
			{
				$redisKey .= '1';
			}
			
			Redis::writeToCacheMap($redisKey, $user, ''); // '' = dummy value
		}
	}
	
	public static function getUserExpirationTime($user)
	{
		return intval(Redis::readFromCacheMap(self::REDIS_KEY_USER_CACHE_EXPIRATION, $user));
	}
	
	public static function setUserExpirationTime($user, $time)
	{
		Redis::writeToCacheMap(self::REDIS_KEY_USER_CACHE_EXPIRATION, $user, $time);
	}
	
	// TODO CHECK IF MANY TOP-LEVEL ENTRIES IN REDIS DOWNGRADE THE PERFORMANCE
	public static function setUserEGroups($user, $groups)
	{
		$key = self::REDIS_KEY_GROUP_MAPPING_CACHE . $user;
		foreach($groups as $membership)
		{
			Redis::writeToCacheMap($key, $membership[0], $membership[1]);
		}
	}
	
	public static function getUserEGroups($user)
	{
		$redisKey = self::REDIS_KEY_GROUP_MAPPING_CACHE . $user;
		$all = Redis::readHashFromCacheMap($redisKey);
		$result = [];
		foreach($all as $group => $membership)
		{
			if($membership === '1')
			{
				$result[] = $group;
			}
		}
		
		return $result;
	}
}