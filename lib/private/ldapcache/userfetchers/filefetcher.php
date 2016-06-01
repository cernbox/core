<?php

namespace OC\LDAPCache\UserFetchers;

use OC\LDAPCache\IUserFetcher;

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
				$users[] = $user;
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