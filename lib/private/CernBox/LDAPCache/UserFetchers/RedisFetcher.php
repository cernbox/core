<?php

namespace OC\LDAPCache\UserFetchers;

use OC\CernBox\LDAPCache\IUserFetcher;
use OC\CernBox\LDAPCache\LDAPCacheManager;

class RedisFetcher implements IUserFetcher
{
	public function fetchUsers($priority)
	{
		$key = LDAPCacheManager::REDIS_KEY_USERS_REFRESH . strval($priority);
		return \OC\CernBox\Drivers\Redis::readHashFromCacheMap($key);
	}
}