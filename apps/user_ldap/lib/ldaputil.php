<?php

namespace OCA\user_ldap\lib;

class LDAPUtil
{
	const LDAP_USERS_BASE_DN = 'ou=users,ou=organic units,dc=cern,dc=ch';
	const LDAP_GROUPS_BASE_DN = 'ou=e-groups,ou=Workgroups,ou=cern,ou=ch';
	
	public static function getEGroupBaseDN()
	{
		return self::LDAP_GROUPS_BASE_DN;
	}
	
	public static function getUserBaseDN()
	{
		return self::LDAP_USERS_BASE_DN;
	}
	
	public static function getUserDN($userCN)
	{
		return ('cn=' . $userCN . ',' . self::LDAP_USERS_BASE_DN);
	}
	
	public static function getUserCNFromDN($dn)
	{
		$tokens = explode(',', $dn);
		foreach($tokens as $token)
		{
			$innerTokens = explode('=', $token);
			if(strcmp(strtolower($innerTokens[0]), 'cn') === 0)
			{
				return $innerTokens[1];
			}
		}
		
		return null;
	}
	
	public static function getGroupDN($groupCN)
	{
		return ('cn=' . $groupCN . ',' . self::LDAP_GROUPS_BASE_DN);
	}
	
	public static function getGroupCNFromDN($dn)
	{
		$tokens = explode(',', $dn);
		foreach($tokens as $token)
		{
			$innerTokens = explode('=', $token);
			if(strcmp(strtolower($innerTokens[0]), 'cn') === 0)
			{
				return $innerTokens[1];
			}
		}
		
		return null;
	}
	
	public static function setSearchParams($params)
	{
		$GLOBALS['ldapsearchparam'] = $params;
	}
	
	public static function getSearchParams()
	{
		if(isset($GLOBALS['ldapsearchparam']))
			return $GLOBALS['ldapsearchparam'];
		return '';
	}
}