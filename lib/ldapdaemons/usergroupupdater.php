<?php

include('../base.php');

use \OC\CernBox\LDAPCache\LDAPCacheManager;

$access = LDAPCacheManager::getAccessInstance();

$groupUpdater = new \OC\CernBox\LDAPCache\DatabaseCache\LDAPGroupUpdater($access);
LDAPCacheManager::executeRefresh($groupUpdater);

$userUpdater = new \OC\CernBox\LDAPCache\DatabaseCache\LDAPUserUpdater($access);
LDAPCacheManager::executeRefresh($userUpdater);