<?php

namespace OC\CernBox\LDAP;

use OCA\User_LDAP\Access;
use OCA\User_LDAP\Connection;
use OCA\User_LDAP\FilesystemHelper;
use \OCA\User_LDAP\Helper;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\LogWrapper;
use OCA\User_LDAP\User\Manager;
use OCP\Image;

class CacheManager {
	const REDIS_KEY_USER_CACHE_EXPIRATION = 'users_cache_validity';
	const REDIS_KEY_GROUP_MAPPING_CACHE = 'group_mapping_';
	const REDIS_KEY_USERS_REFRESH = 'ldap_refresh_queue_';

	/**
	 * @var Access
	 */
	private $ldapAccess;

	private $redis;

	// copy/paste from ./apps/user_ldap/appinfo/app.php
	public function __construct() {
		$this->redis = \OC::$server->getCernBoxRedis();
		$helper = new Helper();
		$configPrefixes = $helper->getServerConfigurationPrefixes(true);
		$ldapWrapper = new LDAP();
		$ocConfig = \OC::$server->getConfig();
		if(count($configPrefixes) === 1) {
			$dbc = \OC::$server->getDatabaseConnection();
			$userManager = new Manager($ocConfig,
				new FilesystemHelper(),
				new LogWrapper(),
				\OC::$server->getAvatarManager(),
				new Image(),
				$dbc,
				\OC::$server->getUserManager()
			);
			$connector = new Connection($ldapWrapper, $configPrefixes[0]);
			$ldapAccess = new Access($connector, $ldapWrapper, $userManager);
			$this->ldapAccess = $ldapAccess;
		}
	}

	public function getLDAPAccess() {
		return $this->ldapAccess ? $this->ldapAccess : false;
	}

	public function executeRefresh($ldapUpdater)
	{
		$ldapUpdater->fetchData();
		$ldapUpdater->fillCache();
	}

	public function updateUserCacheRefresh()
	{
		$user = \OC::$server->getUserSession()->getUser();
		if(!$user) {
			return false;
		}

		$userId = $user->getUID();

		$url = $_SERVER['REQUEST_URI'];
		if($url && !empty($url))
		{
			$redisKey = self::REDIS_KEY_USERS_REFRESH;
			if(strpos($url, 'heartbeat') !== false) {
				$redisKey .= '2';
			} else {
				$redisKey .= '1';
			}

			$this->redis->writeToCacheMap($redisKey, $userId, ''); // '' = dummy value
		}
	}

	public function getUserExpirationTime($user)
	{
		return intval($this->redis->readFromCacheMap(self::REDIS_KEY_USER_CACHE_EXPIRATION, $user));
	}

	public function setUserExpirationTime($user, $time)
	{
		$this->redis->writeToCacheMap(self::REDIS_KEY_USER_CACHE_EXPIRATION, $user, $time);
	}

	// TODO CHECK IF MANY TOP-LEVEL ENTRIES IN REDIS DOWNGRADE THE PERFORMANCE
	public function setUserEGroups($user, $groups)
	{
		$key = self::REDIS_KEY_GROUP_MAPPING_CACHE . $user;
		foreach($groups as $membership)
		{
			$this->redis->writeToCacheMap($key, $membership[0], $membership[1]);
		}
	}

	public function addUserEGroup($user, $group, $membership)
	{
		$key = self::REDIS_KEY_GROUP_MAPPING_CACHE . $user;
		$this->redis->writeToCacheMap($key, $group, $membership);
	}

	/**
	 *
	 * @param string $user
	 * @param string $group
	 *
	 * @return string membership (either "1" or "") or FALSE if the group is not in the cache
	 */
	public function getUserMembershipToGroup($user, $group)
	{
		$redisKey = self::REDIS_KEY_GROUP_MAPPING_CACHE . $user;
		return $this->redis->readFromCacheMap($redisKey, $group);
	}

	public function getUserEGroups($user)
	{
		$redisKey = self::REDIS_KEY_GROUP_MAPPING_CACHE . $user;
		$all = $this->redis->readHashFromCacheMap($redisKey);
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