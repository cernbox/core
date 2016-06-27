<?php

require_once '../base.php';

use \OC\Cernbox\LDAP\LDAPCacheManager;

if($argc < 2)
{
	echo 'Usage: php priorityupdater.php <priority [1,2,3]>' . PHP_EOL;
	exit();
}

$access = LDAPCacheManager::getAccessInstance();

$priorityUpdater = new \OC\Cernbox\LDAP\LDAPGroupMappingUpdater($access);
$priorityUpdater->setPriority(intval($argv[1]));
LDAPCacheManager::executeRefresh($priorityUpdater); 