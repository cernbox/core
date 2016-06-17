<?php

namespace OC\Cernbox\LDAP\UserFetchers;

use OC\Cernbox\LDAP\IUserFetcher;

final class FileFetcher implements IUserFetcher 
{
	/**
	 * {@inheritDoc}
	 * @see \OC\LDAPCache\IUserFetcher::fetchUsers()
	 */
	public function fetchUsers($priority) 
	{
		$pathToFile = rtrim(\OC::$SERVERROOT, '/') . '/userlist.txt';
		$handle = fopen($pathToFile, 'r');
		$users = [];
		if($handle)
		{
			while(($user = fgets($handle)) !== FALSE)
			{
				$user = trim($user);
				$users[$user] = '';
			}
		
			fclose($handle);
		}
		else
		{
			\OCP\Util::writeLog('LDAP CACHE', 'FileFetcher: Could not get user list from ' . $pathToFile, \OCP\Util::ERROR);
		}
		
		return $users;
	}
}