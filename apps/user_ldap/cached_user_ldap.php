<?php

namespace OCA\user_ldap;

use OC\Cernbox\LDAP\LDAPDatabase;
use OCA\user_ldap\lib\LDAPUtil;
use OCA\user_ldap\lib\Access;
use OCP\IConfig;

/**
 * Wrapper class for ldap access using CERNBox's ldap database cache
 */
class CACHED_USER_LDAP extends USER_LDAP
{
	/**
	 * @param \OCA\user_ldap\lib\Access $access
	 * @param \OCP\IConfig $ocConfig
	 */
	public function __construct(Access $access, IConfig $ocConfig) 
	{
		parent::__construct($access, $ocConfig);
	}
	
	public function canChangeAvatar($uid) 
	{
		return false;
	}
	
	public function getUsers($search = '', $limit = 10, $offset = 0, $searchParams = null) 
	{
		$searchParams = LDAPUtil::getSearchParams();
		
		if(strpos($searchParams, 'a') !== FALSE)
		{
			$ldap_users = LDAPDatabase::fetchUsersData('%'.$search.'%', ['cn', 'displayname'], ['cn'], $limit);
		}
		else 
		{
			$ldap_users = LDAPDatabase::fetchPrimaryUsersData('%'.$search.'%', ['cn', 'displayname'], ['cn'], $limit);
		}
		
		return $ldap_users;
	}
	
	public function userExistsOnLDAP($user) 
	{
		$user = LDAPDatabase::getUserData($user);
		return ($user && count($user) > 0);
	}
	
	public function userExists($uid) 
	{
		return $this->userExistsOnLDAP($uid);
	}
	
	public function deleteUser($uid) 
	{
	}
	
	public function getDisplayName($uid) 
	{
		$user = LDAPDatabase::getUserData($uid, ['cn'],['displayname']);
		if($user)
		{
			return $user[0]['displayname'];
		}
		
		return false;
	}
	
	public function getDisplayNames($search = '', $limit = null, $offset = null, $searchParams = null) 
	{
		$searchParams = LDAPUtil::getSearchParams();
		
		if(strpos($searchParams, 'a') !== FALSE)
		{
			$ldap_users = \OC\Cache\LDAPDatabase::fetchUsersData('%'.$search.'%', ['cn', 'displayname'], ['cn', 'displayname'], $limit);
		}
		else
		{
			$ldap_users = \OC\Cache\LDAPDatabase::fetchPrimaryUsersData('%'.$search.'%', ['cn', 'displayname'], ['cn', 'displayname'], $limit);
		}
		
		$users = [];
		foreach($ldap_users as $token)
		{
			$users[$token['cn']] = $token['displayname'];
		}
		
		return $users;
	}
	
	public function implementsActions($actions) {
		return (bool)((\OC_User_Backend::CHECK_PASSWORD
				| \OC_User_Backend::GET_HOME
				| \OC_User_Backend::GET_DISPLAYNAME
				| \OC_User_Backend::SET_DISPLAYNAME
				| \OC_User_Backend::PROVIDE_AVATAR
				| \OC_User_Backend::COUNT_USERS)
				& $actions);
	}
	
	public function setDisplayName($userId, $displayName) 
	{
		return true;
	}
	
	public function countUsers() 
	{
		return LDAPDatabase::countNumberOfUsers();
	}
	
	
}