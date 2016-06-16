<?php

namespace OC\Cernbox\LDAP\UserFetchers;

use \OC\Cernbox\Storage\EosUtil;
use \OC\Cernbox\LDAP\IUserFetcher;

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
				$temp = [];
				foreach($users as $user)
				{
					$temp[$user] = ''; // Dummy val
				}
				$allUsers = array_merge($allUsers, $temp);
			}
		}
		
		return $allUsers;
	}
}