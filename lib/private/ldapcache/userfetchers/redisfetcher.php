<?php

namespace OC\LDAPCache\UserFetchers;

use OC\LDAPCache\IUserFetcher;
use OC\LDAPCache\LDAPCacheManager;

class RedisFetcher implements IUserFetcher
{
	public function fetchUsers($priority)
	{
		$key = LDAPCacheManager::REDIS_KEY_USERS_REFRESH . strval($priority);
		return \OC\Files\ObjectStore\Redis::readHashFromCacheMap($key);
	}
}