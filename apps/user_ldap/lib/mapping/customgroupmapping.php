<?php

namespace OCA\User_LDAP\Mapping;

use OCA\user_ldap\lib\LDAPUtil;
use OC\Cache\LDAPDatabase;

class CustomGroupMapping extends GroupMapping 
{
	public function getDNByName($name)
	{
		return LDAPUtil::getGroupDN($name);
	}
	
	public function setDNbyUUID($fdn, $uuid)
	{
		// Do nothing. Original function would store it on DB
	}
	
	public function getNameByDN($fdn)
	{
		return LDAPUtil::getGroupCNFromDN($fdn);
	}
	
	public function getNamesBySearch($search)
	{
		$names = LDAPDatabase::fetchGroupsData($search);
		$result = [];
		foreach($names as $name)
		{
			$result = $name['cn'];
		}
	
		return $result;
	}
	
	public function getNameByUUID($uuid)
	{
		return $uuid;
	}
	
	public function getList($offset = null, $limit = null)
	{
		$realLimit = $limit;
		if($offset != null)
			$realLimit += $offset;
	
			$result = LDAPDatabase::fetchGroupsData('', ['cn'], ['cn'], $limit);
			$list = [];
			$start = ($offset == null? 0 : $offset);
			$end = ($limit == null? count($result) : $limit);
			for($i = $start; $i < $end; $i++)
			{
				$cn = $result[$i]['cn'];
				$token = [];
				$token['dn'] = LDAPUtil::getGroupDN($cn);
				$token['name'] = $cn;
				$token['uuid'] = $cn;
				$list[] = $token;
			}
	
			return $list;
	}
	
	public function map($fdn, $name, $uuid)
	{
		// Do nothing
	}
	
	public function clear()
	{
		// Do nothing
	}
}