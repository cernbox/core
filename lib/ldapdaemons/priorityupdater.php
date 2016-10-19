<?php

require_once '../base.php';

use \OC\CernBox\LDAPCache\LDAPCacheManager;

if($argc < 2)
{
	echo 'Usage: php priorityupdater.php <priority [1,2,3]>' . PHP_EOL;
	exit();
}

$access = LDAPCacheManager::getAccessInstance();

$priorityUpdater = new \OC\CernBox\LDAPCache\LDAPGroupMappingUpdater($access);
$priorityUpdater->setPriority(intval($argv[1]));
LDAPCacheManager::executeRefresh($priorityUpdater); 