<?php

/**
 * 
 * @param unknown $access
 * @param unknown $function
 * @param array $parameters
 * @param number $limit
 * @throws Exception
 */
function retrieveLDAPData(&$access, $function, array $parameters = ['cn'], $limit = 2000)
{
	if(!method_exists($access, $function))
		throw new Exception('Ldap database update: Cannot find function ' . $function . ' in the Access instance');
	
	$ldapData = [];
	$lastReturn = 0;
	do
	{
		$ldapDataCache = call_user_func_array([$access, $function], ['(cn=*)', $parameters, $limit, count($ldapData)]);
		$lastReturn = count($ldapDataCache);
		$ldapData = array_merge($ldapData, $ldapDataCache);
	}
	while($lastReturn >= $limit);
	
	return $ldapData;
}

/**
 * 
 * @param unknown $rawArray
 */
function simplifyArray($rawArray)
{
	$result = [];
	foreach($rawArray as $key => $value)
	{
		$temp = [];
		foreach($value as $keyy => $valuee)
		{
			if(count($valuee) > 1)
			{
				$temp[$keyy] = $valuee;	
			}
			else
			{
				$temp[$keyy] = $valuee[0];
			}
		}
			
		$result[] = $temp;
	}
	
	return $result;
}

/**
 * 
 * @param unknown $data
 * @param unknown $filename
 */
function dumpArrayToFile($data, $filename)
{
	$handle = fopen($filename, 'w');
	
	foreach($data as $token)
	{
		fwrite($handle, '-----------------' . PHP_EOL);
		foreach($token as $key => $value)
		{
			if(is_array($value))
			{
				fwrite($handle, $key . ':' . PHP_EOL);
				foreach($value as $val)
					fwrite($handle, '  ' . $val . PHP_EOL);
			}
			else
			{
				fwrite($handle, $key . '=' .$value.PHP_EOL);
			}
		}
	}
	
	fflush($handle);
	fclose($handle);
}

function insertIntoDatabase($tableName, array $data, array $parameters = ['cn'])
{
	$paramLen = count($parameters) - 1;
	$paramList = '(';
	for($i = 0; $i < $paramLen; $i++)
	{
		$paramList .= '?,';
	}
	
	$paramList .= '?)';
	
	foreach($data as $token)
	{
		$statement = \OC_DB::prepare('INSERT INTO ' . $tableName . ' VALUES ' . $paramList);
		$paramArray = [];
		foreach($parameters as $param)
		{
			if(isset($token[$param]))
				$paramArray[] = $token[$param];
			else
				$paramArray[] = NULL;
		}
		
		if(!$statement->execute($paramArray))
		{
			\OCP\Util::writeLog('CRON user_ldap', 'Could not insert a row', \OCP\Util::ERROR);
		}
	}
}

function getCNFromDN($dn)
{
	$tokenized = explode(',', $dn);
	return explode('=', $tokenized[0])[1];
}

try 
{
	require_once '../../lib/base.php';
	
	$helper = new \OCA\user_ldap\lib\Helper();
	$configPrefixes = $helper->getServerConfigurationPrefixes(true);
	$ldapWrapper = new \OCA\user_ldap\lib\LDAP();
	if(count($configPrefixes) === 1) 
	{
		//avoid the proxy when there is only one LDAP server configured
		$dbc = \OC::$server->getDatabaseConnection();
		$userManager = new \OCA\user_ldap\lib\user\Manager(
				\OC::$server->getConfig(),
				new OCA\user_ldap\lib\FilesystemHelper(),
				new OCA\user_ldap\lib\LogWrapper(),
				\OC::$server->getAvatarManager(),
				new \OCP\Image(),
				$dbc);
		$connector = new OCA\user_ldap\lib\Connection($ldapWrapper, $configPrefixes[0]);
		$ldapAccess = new OCA\user_ldap\lib\Access($connector, $ldapWrapper, $userManager);
		
		$ldapGroupBaseDN = $connector->ldapBaseGroups;
		
		//$groups = OCA\user_ldap\LDAPDatabase::fetchGroupsData();
		
		//$ldapGroups = simplifyArray(retrieveLDAPData($ldapAccess, 'searchGroups'));
		//dumpArrayToFile($ldapGroups, 'ldap_cron_groups.txt');
		//insertIntoDatabase('ldap_groups', $ldapGroups);
		//unset($ldapGroups);
		
		$ldapUsers = simplifyArray(retrieveLDAPData($ldapAccess, 'searchUsers', ['cn', 'uidNumber', 'displayName', 'memberOf', 'employeeType']));
		//insertIntoDatabase('ldap_users', $ldapUsers, ['cn', 'uidumber', 'displayname', 'employeetype']);
		//dumpArrayToFile($ldapUsers, 'ldap_cron_users.txt');
		
		$insertData = [];
		foreach($ldapUsers as $token)
		{
			$userGroups = [];
			$rawGroups = $token['memberof'];

			if(!is_array($rawGroups) || count($rawGroups) < 1)
				continue;
			
			foreach($rawGroups as $group)
			{
				// Filter non e-groups
				if(strpos($rawGroups, $ldapGroupBaseDN) == FALSE)
					continue;
				
				$groupCN = getCNFromDN($group);
				if(!$groupCN || $groupCN == NULL || empty($groupCN))
					continue;
				
				$userGroups['user_cn'] = $token['cn'];
				$userGroups['group_cn'] = $groupCN;
				$insertData[] = $userGroups;
			}
		}
		
		insertIntoDatabase('ldap_group_members', $insertData, ['user_cn', 'group_cn']);
		
		unset($insertData);
		unset($ldapUsers);
	}
	else
	{
		throw new Exception('Could not get access to LDAP');
	}
}	
catch(Exception $e)
{
	\OCP\Util::writeLog('CRON user_ldap', $e->getMessage(), \OCP\Util::ERROR);
	$handle = fopen('cron_user_ldap_error.log', 'w');
	$trace = $e->getTrace();
	foreach($trace as $token)
	{
		$file = (isset($token['file'])?$token['file']:'');
		$function = (isset($token['function'])?$token['function']:'');
		$line = (isset($token['line'])?$token['line']:'');
		
		fwrite($handle, $file . ' ' .$function . ' ' .$line);
	}
	
	fflush($handle);
	fclose($handle);
}

?>