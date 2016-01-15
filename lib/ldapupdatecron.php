<?php

/**
 * Call the given function from \OCA\user_ldap\lib\Access to retrive data from LDAP server
 * @param \OCA\user_ldap\lib\Access $access ldap access handle
 * @param string $function Function from $access instance to be called
 * @param array $parameters The parameters to pass to the function (filters, attributes, ...)
 * @param number $limit Maximun number of entries retrieved on each call (Server-side limit is 2000)
 * @throws Exception If the given $function does not exist in the $access instance
 */
function retrieveLDAPData(&$access, $function, array $parameters = ['cn'], $limit = 2000)
{
	if(!method_exists($access, $function))
		throw new Exception('Ldap database update: Cannot find function ' . $function . ' in the Access instance');
	
	$ldapData = [];
	$lastReturn = 0;
	// We keep requesting new chunks of data until the number of retrieved entries is lower than the requested
	// number of entries
	do
	{
		$ldapDataCache = call_user_func_array([$access, $function], ['(cn=*)', $parameters, $limit, count($ldapData)]);
		$lastReturn = count($ldapDataCache);
		$ldapData = array_merge($ldapData, $ldapDataCache);
	}
	while($lastReturn >= $limit);
	
	return $ldapData;
}

function arrangeLDAPArray(array $array)
{
	$result = [];
	foreach($array as $token)
	{
		$key = $token['cn'][0];
		$innerArray = [];
		foreach($token as $keyy => $value)
		{
			if(count($value) > 1)
				$innerArray[$keyy] = arrangeInnerLDAPArray($value);
			else
				$innerArray[$keyy] = $value[0];
		}
		
		$result[$key] = $innerArray;
	}
	
	return $result;
}

function arrangeInnerLDAPArray(array $array)
{
	$result = [];
	foreach($array as $v)
	{
		$result[] = $v;
	}
	
	return $result;
}

/**
 * Re-arranges the array structure from database to make it comparable with other arrays
 * @param array $array The array to arrange
 * @return array The re-arranged array
 */
function arrangeDatabaseArray(array $array)
{
	$result = [];
	foreach($array as $token)
	{
		$cn = '';
		if(is_array($token['cn']))
			$cn = $token['cn'][0];
		else
			$cn = $token['cn'];
			
		$result[$cn] = $token;
	}
	
	return $result;
}

/**
 * Returns the cn parameters from a DN string
 * @param string $dn The source DN string
 * @return string The cn from the given $dn
 */
function getCNFromDN($dn)
{
	$tokenized = explode(',', $dn);
	return explode('=', $tokenized[0])[1];
}

function cronlog($msg)
{
	\OCP\Util::writeLog('CRON LDAP', $msg, \OCP\Util::ERROR);
}

function buildQuery($table, $types, array $tags, array $data)
{
	cronlog('Starting query build...');

	$limit = count($data) - 1;
	$cache = 'INSERT INTO ' . $table . ' VALUES ';
	$outerKeys = array_keys($data);
	$innerCount = count($tags) - 1;

	for($i = 0; $i < $limit; $i++)
	{
		$innerArray = $data[$outerKeys[$i]];
		$cache .= '(';
		for($j = 0; $j < $innerCount; $j++)
		{
			$val = (isset($innerArray[$tags[$j]])?  $innerArray[$tags[$j]] : 'NULL');
			if($types[$j] == 's')
				$cache .= '"' . $val . '",';
			else
				$cache .= $val . ',';
		}
		if($types[$innerCount] == 's')
			$cache .= '"' . $innerArray[$tags[$innerCount]] . '"),';
		else
			$cache .= $innerArray[$tags[$innerCount]] . '),';
	}
	
	$innerArray = $data [$outerKeys [$limit]];
	$cache .= '(';
	for($j = 0; $j < $innerCount; $j ++) {
		$val = (isset ( $innerArray [$tags [$j]] ) ? $innerArray [$tags [$j]] : 'NULL');
		if ($types [$j] == 's')
			$cache .= '"' . $val . '",';
		else
			$cache .= $val . ',';
	}
	if ($types [$innerCount] == 's')
		$cache .= '"' . $innerArray [$tags [$innerCount]] . '");';
	else
		$cache .= $innerArray [$tags [$innerCount]] . ');';

	cronlog('Query build finished');

	return $cache;
}

function buildCVSFile($types, array $tags, array $data)
{
	cronlog('Starting query build...');
	
	$cache = '';
	$limit = count($data) - 1;

    $outerKeys = array_keys ( $data );
	$innerCount = count ( $tags ) - 1;
	
	for($i = 0; $i < $limit; $i ++) {
		$innerArray = $data [$outerKeys [$i]];
		for($j = 0; $j < $innerCount; $j ++) {
			$val = (isset ( $innerArray [$tags [$j]] ) ? $innerArray [$tags [$j]] : 'NULL');
			/*if ($types [$j] == 's')
				$cache .= '"' . $val . '",';
			else*/
				$cache .= $val . ',';
		}
		/*if ($types [$innerCount] == 's')
			$cache .= '"' . $innerArray [$tags [$innerCount]] . '"' . PHP_EOL;
		else*/
			$cache .= $innerArray [$tags [$innerCount]] . PHP_EOL;
	}
	
	$innerArray = $data [$outerKeys [$limit]];
	for($j = 0; $j < $innerCount; $j ++) {
		$val = (isset ( $innerArray [$tags [$j]] ) ? $innerArray [$tags [$j]] : 'NULL');
		/*if ($types [$j] == 's')
			$cache .= '"' . $val . '",';
		else*/
			$cache .= $val . ',';
	}
	/*if ($types [$innerCount] == 's')
		$cache .= '"' . $innerArray [$tags [$innerCount]] . '"' . PHP_EOL;
	else*/
		$cache .= $innerArray [$tags [$innerCount]] . PHP_EOL;

	cronlog('Done');

	return $cache;
}

function getDatabaseConnection()
{
	$host = \OCP\Config::getSystemValue('dbhost', 'localhost');
	$dbuser = \OCP\Config::getSystemValue('dbuser', 'root');
	$dbpwd = \OCP\Config::getSystemValue('dbpassword', '');
	$dbname = \OCP\Config::getSystemValue('dbname');
	
	$pdo = new PDO('mysql:host='.$host.';dbname='.$dbname.';charset=utf8', $dbuser, $dbpwd,
			[
					PDO::MYSQL_ATTR_LOCAL_INFILE => true,
					PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
			]);
	
	return $pdo;
}

function insertIntoDatabase($table, $sql, $useLoadData = false)
{
	$dbHandle = getDatabaseConnection();
	
	$dbHandle->exec('LOCK TABLE ' . $table . ' WRITE');
	$dbHandle->exec('DELETE FROM ' . $table);
	
	if($useLoadData)
	{
		$handle = fopen($table . '.txt', 'w');
		fwrite($handle, $sql);
		fflush($handle);
		fclose($handle);

		$dbHandle->exec("LOAD DATA LOCAL INFILE '/cernbox/lib/" .$table. ".txt' IGNORE INTO TABLE " .$table. " FIELDS TERMINATED BY ','");
	}
	else
	{		
		$dbHandle->exec($sql);
	}

	$dbHandle->exec('UNLOCK TABLES');
	
	$dbHandle = null;
}

try 
{
	require_once 'base.php';
	
	cronlog('Starting LDAP Database update...');
	
	// LDAP Access set up
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
		
		cronlog('Retrieving groups...');
		$ldapGroups = retrieveLDAPData($ldapAccess, 'searchGroups');
		$ldapGroups = arrangeLDAPArray($ldapGroups);
		cronlog('Groups retrieved');

		cronlog('Inserting groups into database...');

		insertIntoDatabase('ldap_groups', buildQuery('ldap_groups', 's', ['cn'], $ldapGroups));
		unset($ldapGroups);

		cronlog('Done');
		
		cronlog('Retrieving users...');
		$ldapUsers = retrieveLDAPData($ldapAccess, 'searchUsers', ['cn', 'uidNumber', 'displayName', 'employeeType', 'memberOf']);
		$ldapUsers = arrangeLDAPArray($ldapUsers);
		cronlog('Users retrieved');
		
		// Split groups from user data
		$userGroups = [];
		foreach($ldapUsers as $key => $value)
		{
			$memberOfArray = $value['memberof'];
			if(is_array($memberOfArray) && count($memberOfArray) > 0)
			{
				foreach($memberOfArray as $memberof)
				{
					if(strpos($memberof, 'e-groups') != FALSE)
						$userGroups[] = ['user_cn' => $key, 'group_cn' => getCNFromDN($memberof)];
				}
			}
				
			unset($ldapUsers[$key]['memberof']);
		}

		cronlog('Inserting users into database...');

		insertIntoDatabase('ldap_users', buildQuery('ldap_users', 'siss', ['cn','uidnumber','displayname','employeetype'], $ldapUsers));
		unset($ldapUsers);

		cronlog('Done');
		cronlog('Inserting users <-> groups into database...');
	
		insertIntoDatabase('ldap_group_members', buildCVSFile('ss', ['user_cn','group_cn'], $userGroups), true);
		unset($userGroups);
				
		cronlog('Done');
	}
	else
	{
		\OCP\Util::writeLog('CRON LDAP', 'Could not perform database Update');
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
		
		fwrite($handle, $file . ' ' .$function . ' ' .$line . PHP_EOL);
	}
	
	fflush($handle);
	fclose($handle);
}

?>
