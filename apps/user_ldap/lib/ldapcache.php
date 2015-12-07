<?php

namespace OCA\user_ldap\lib;

class LDapCache
{
	const LDAPKEY = 'ldapcache';
	
	private static function init()
	{
		if(!isset($GLOBALS[self::LDAPKEY]))
		{
				$GLOBALS[self::LDAPKEY] = [];			
		}
	}
	
	public static function getCacheData($key)
	{
		if(isset($GLOBALS[self::LDAPKEY][$key]))
			return $GLOBALS[self::LDAPKEY][$key];
		
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
		$GLOBALS[self::LDAPKEY][$key] = $value;
	}
}