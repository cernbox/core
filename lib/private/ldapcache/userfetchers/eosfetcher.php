<?php

namespace OC\LDAPCache\UserFetchers;

use \OC\Files\ObjectStore\EosUtil;
use \OC\LDAPCache\IUserFetcher;

class EosFetcher implements IUserFetcher
{
	public function fetchUsers($priority)
	{
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
				$allUsers = array_merge($allUsers, $users);
			}
		}
		
		return $allUsers;
	}
}