<?php

namespace OC\CernBox\LDAPCache\UserFetchers;

use OC\CernBox\LDAPCache\IUserFetcher;

/**
 * Scans all user home directories to get a list of users with a
 * home directory on EOS.
 *
 */
class EosFetcher implements IUserFetcher
{
	public function fetchUsers($priority)
	{
		// TODO: If needed, add support to scan the namespace from /eos/user
		$username = \OC::$server->getUserSession()->getUser()->getUID();
		$instanceManager = \OC::$server->getCernBoxEosInstanceManager();
		
		/*
		
		$eosBase = EosUtil::getEosPrefix();
		$start = ord('a');
		$end = ord('z') + 1;
		
		$allUsers = [];
		
		for($i = $start; $i < $end; $i++)
		{
			$char = chr($i);
			$tempEosPath = $eosBase . $char;
			
			$users = EosUtil::ls($tempEosPath);
			if($users !== FALSE)
			{
				$temp = [];
				foreach($users as $user)
				{
					$temp[$user] = ''; // Dummy val
				}
				$allUsers = array_merge($allUsers, $temp);
			}
		}
		
		return $allUsers;
		*/
		
		return [];
	}
}