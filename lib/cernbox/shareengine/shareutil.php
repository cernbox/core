<?php

namespace OC\Cernbox\ShareEngine;

final class ShareUtil 
{
	public static function calcTokenHash($token)
	{
		$hash = 0;
		$tokenLen = strlen($token);
		for($i = 0; $i < $tokenLen; $i++)
		{
			$hash += ord($token[$i]);
		}
		
		return ($hash % 100);
	}
	
	public static function parseAcl($aclString)
	{
		$acls = explode(',', $aclString);
		$map = [];
		foreach($acls as $acl)
		{
			$parts = explode(':', $acl);
			$map[$parts[1]] = [$parts[2], $parts[0]];
		}
		
		return $map;
	}
	
	public static function buildAcl($aclMap)
	{
		$acl = '';
		foreach($aclMap as $user => $part)
		{
			$acl .= ($part[1] . ':' . $user . ':' . $part[0]);
		}
		
		return $acl;
	}
}