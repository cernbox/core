<?php

namespace OC\Cernbox\LDAP;

abstract class LDAPUpdater
{
	protected $ldapAccessInstance;	
	protected $ldapData;
	
	protected $ldapFunc;
	protected $ldapFilter;
	protected $ldapAttr;

	public function __construct(&$ldapAccess)
	{
		$this->ldapAccessInstance = $ldapAccess;
		$this->ldapData = [];
	}
	
	protected function callLDAPMethod($limit = 2000)
	{
		if(!method_exists($this->ldapAccessInstance, $this->ldapFunc))
			throw new \Exception('Ldap updater: Cannot find function ' . $this->ldapFunc . ' in the Access instance');
		
			$lastReturn = 0;
			// We keep requesting new chunks of data until the number of retrieved entries is lower than the requested
			// number of entries
			do
			{
				$ldapDataCache = call_user_func_array([$this->ldapAccessInstance, $this->ldapFunc], [$this->ldapFilter, $this->ldapAttr, $limit, count($this->ldapData)]);
				$lastReturn = count($ldapDataCache);
				$this->ldapData = array_merge($this->ldapData, $ldapDataCache);
			}
			while($lastReturn >= $limit);
	}
	
	protected function sortLDAPArray(array $array)
	{
		$result = [];
		foreach($array as $token)
		{
			$key = $token['cn'][0];
			$innerArray = [];
			foreach($token as $keyy => $value)
			{
				if(count($value) > 1)
					$innerArray[$keyy] = $this->sortInnerLDAPArray($value);
					else
						$innerArray[$keyy] = $value[0];
			}
	
			$result[$key] = $innerArray;
		}
	
		return $result;
	}
	
	protected function sortInnerLDAPArray(array $array)
	{
		$result = [];
		foreach($array as $v)
		{
			$result[] = $v;
		}
	
		return $result;
	}
	
	public abstract function fillCache();
	public abstract function fetchData();
}
