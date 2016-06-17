<?php

include('../base.php');

use \OC\Cernbox\LDAP\LDAPCacheManager;

$access = LDAPCacheManager::getAccessInstance();

$groupUpdater = new \OC\Cernbox\LDAP\DatabaseCache\LDAPGroupUpdater($access);
LDAPCacheManager::executeRefresh($groupUpdater);

$userUpdater = new \OC\Cernbox\LDAP\DatabaseCache\LDAPUserUpdater($access);
LDAPCacheManager::executeRefresh($userUpdater);