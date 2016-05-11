<?php

include('../base.php');

use \OC\LDAPCache\LDAPCacheManager;

$access = LDAPCacheManager::getAccessInstance();

$groupUpdater = new \OC\LDAPCache\DatabaseCache\LDAPGroupUpdater($access);
LDAPCacheManager::executeRefresh($groupUpdater);

$userUpdater = new \OC\LDAPCache\DatabaseCache\LDAPUserUpdater($access);
LDAPCacheManager::executeRefresh($userUpdater);