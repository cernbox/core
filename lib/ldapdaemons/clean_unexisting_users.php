<?php

include('../base.php');


function printUsage() {
	echo 'Usage: php -f clean_unexisting_users.php <inform|delete>' . PHP_EOL;
	exit(1);
}

if($argc < 2)
{
	printUsage();
}

$action = $argv[1];
if ($action !== 'inform' && $action !== 'delete') {
	printUsage();
}

$eosFetcher = new OC\LDAPCache\UserFetchers\EosFetcher();
$usersFromEos = $eosFetcher->fetchUsers(0);
$cnt = count($usersFromEos);
if($cnt > 0) {
	$usersToBeFixed = array();
	foreach($usersFromEos as $username => $dummy) {
		$stmt = "select distinct user_cn from cernbox_ldap_group_members where user_cn='$username' and (select count(1) from cernbox_ldap_users where cn='$username') = 0;";
		$result = \OC_DB::prepare($stmt)->execute()->fetchAll();
		if (count($result) > 0) {
			$usersToBeFixed[] = $result[0]['user_cn'];
		}
	}

	if ($action === 'inform') {
		array_walk($usersToBeFixed, function ($value, $key) {
			echo "[$key] $value\n";
		});
	} else {
		array_walk($usersToBeFixed, function ($value, $key) {
			$deleteStmt = "delete from cernbox_ldap_group_members where user_cn='$value' and (select count(1) from cernbox_ldap_users where cn='$value') = 0;";
			try {
				\OC_DB::prepare($deleteStmt)->execute();
			} catch(\Exception $e) {
				echo "[$key] $value DELETION_FAILED\n";
			} 
			echo "[$key] $value DELETION_OK\n";
		});
	}
} else {
	echo "Couldn't fetch users from EOS homedir scanning ... check /var/log/owncloud.log to investigate the problem\n";
	exit(1);
}
