<?php

namespace OCA\user_ldap;

class LDapCache
{
	const LDAPKEY = 'ldapcache';
	
	private static function init()
	{
		if(!isset($GLOBALS[LDAPKEY]))
		{
				$GLOBALS[LDAPKEY] = [];			
		}
	}
	
	public static function getCacheData($key)
	{
		if(isset($GLOBALS[LDAPKEY][$key]))
			return $GLOBALS[LDAPKEY][$key];
		
		return false;
	}
	
	public static function findRecursivelyData($key, $cmd)
	{
		self::init();
		$len = strlen($key);
		for($i = $len - 2; $i > 0; $i--)
		{
			$tmp = substr($key, 0, $i);
			$searchQuey = str_replace('%key%', $tmp, $cmd);
			$data = self::getCacheData($searchQuey);
			if($data)
				return $data;
		}
		
		return false;
	}
	
	public static function setCacheData($key, $value)
	{
		self::init();
		$GLOBALS[LDAPKEY][$key] = $value;
	}
}