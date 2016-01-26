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
	
	public static function getGroupDN($groupCN)
	{
		return ('cn=' . $groupCN . ',' . self::LDAP_GROUPS_BASE_DN);
	}
	
	public static function setSearchParams($params)
	{
		$GLOBALS['ldapsearchparam'] = $params;
	}
	
	public static function getSearchParams()
	{
		return $GLOBALS['ldapsearchparam'];
	}
	
	private static function dumpArray($handle, $array, $index = 0)
	{
		$tabs = "";
		for($z = 0; $z < $index; $z++)
		{
			$tabs .= "\t";
		}
	
		if(is_array($array))
		{
			$keys = array_keys($array);
			$i = 0;
			foreach($array as $a)
			{
				if(is_array($a))
				{
					fwrite($handle, $tabs . " " . $keys[$i] . ":" . PHP_EOL);
					self::dumpArray($handle, $a, $index + 1);
				}
				else fwrite($handle, $tabs . " " . $keys[$i] . ": " . $a . PHP_EOL);
	
				$i++;
			}
		}
	}
	
	public static function log($file, array $msgs)
	{
		$handle = fopen($file, 'a+');
	
		foreach($msgs as $msg)
		{
			if(is_array($msg))
				self::dumpArray($handle, $msg);
				else
					fwrite($handle, $msg . PHP_EOL);
		}
	
		fwrite($handle, PHP_EOL);
		fflush($handle);
		fclose($handle);
	}
}