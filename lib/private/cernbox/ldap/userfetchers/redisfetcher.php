<?php

namespace OC\Cernbox\LDAP\UserFetchers;

use OC\Cernbox\LDAP\IUserFetcher;
use OC\Cernbox\LDAP\LDAPCacheManager;

class RedisFetcher implements IUserFetcher
{
	public function fetchUsers($priority)
	{
		$key = LDAPCacheManager::REDIS_KEY_USERS_REFRESH . strval($priority);
		return \OC\Cernbox\Storage\Redis::readHashFromCacheMap($key);
	}
}