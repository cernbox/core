<?php 
namespace OC\Cernbox\LDAP;

class LDAPDatabase
{
	const GROUPS_TABLE = 'cernbox_ldap_groups';
	const USERS_TABLE = 'cernbox_ldap_users';
	const GROUP_MAPPINGS = 'cernbox_ldap_group_members';
	
	private static $TABLES_SCHEMA =
	[
		'cernbox_ldap_users' => 
		[
			'cn' => 's',
			'uidnumber' => 'd',
			'displayname' => 's',
			'employeetype' => 's',
		],
		
		'cernbox_ldap_groups' =>
		[
			'cn' => 's'		
		]				
	];
	
	/**
	 * 
	 * @param unknown $search
	 * @param array $searchTokens
	 * @param string $logicalOp
	 */
	private static function buildSelectQuery($tableName, $searchOp = ['='], array $searchTokens = [], $logicalOp = ['OR'])
	{
		$result = '';
		$count = count($searchTokens) - 1;
		if(isset(self::$TABLES_SCHEMA[$tableName]))
		{
			$tableSchema = self::$TABLES_SCHEMA[$tableName];
		}
		else
		{
			$tableSchema = false;
		}
		
		for($i = 0; $i < $count; $i++)
		{
			$extraParam = '';
			$token = $searchTokens[$i];
			
			if($tableSchema && isset($tableSchema[$token]) && $tableSchema[$token] === 's')
			{
				$extraParam = ' COLLATE UTF8_GENERAL_CI';
			}
			
			$result .= $searchTokens[$i] . $extraParam . ' ' . $searchOp[$i] . ' ? ' . $logicalOp[$i] . ' ';
		}
		
		$token = $searchTokens[$count];
		if($tableSchema && isset($tableSchema[$token]) && $tableSchema[$token] === 's')
		{
			$extraParam = ' COLLATE UTF8_GENERAL_CI';
		}
		
		$result .= $searchTokens[$count] . $extraParam . ' ' . $searchOp[$count] . ' ?';
		
		return $result;
	}
	
	/**
	 * 
	 * @param array $array
	 * @param unknown $query
	 * @param array $params
	 */
	private static function executeQueryOverArray(array &$array, $query, array $params = [])
	{
		foreach($array as $entry)
		{
			$queryParams = [];
			foreach($params as $p)
				$queryParams[] = $entry[$p];
			
			try
			{
				$query = \OC_DB::prepare($query);
				$query->execute($queryParams);
			}
			catch(Exception $e)
			{
			}
		}
	}
	
	/**
	 * Retrieves an array where each entry is a row from the table ldap_users
	 * @param string $search The searh pattern
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function fetchUsersData($search = '', array $searchTokens = ['cn'], array $params = ['cn'], $limit = null, $logicalOp = 'OR')
	{
		return self::fetchData(self::USERS_TABLE, $search, $searchTokens, $limit, $params, 'LIKE', $logicalOp);
	}
	
	/**
	 * Retrieves an array where each entry is a row from the table ldap_users where employeetype is 'Primary'
	 * @param string $search The searh pattern
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function fetchPrimaryUsersData($search = '', array $searchTokens = ['cn'], array $params = ['cn'], $limit = null, $logicalOp = 'OR')
	{
		$jointParams = implode(',', $params);
		$searchOp = 'LIKE';
		try
		{
			$count = count($searchTokens);
			$search = self::processQueryArg($count, $search);
			$searchOp = self::processQueryArg($count, $searchOp);
			$logicalOp = self::processQueryArg($count - 1, $logicalOp);
			
			$queryStr = 'SELECT '.$jointParams.' FROM '.self::USERS_TABLE.' WHERE ( '.self::buildSelectQuery(self::USERS_TABLE, $searchOp, $searchTokens, $logicalOp).' ) AND employeetype="Primary"';
			
			$query = \OC_DB::prepare($queryStr, $limit);
			$result = $query->execute($search);
			
			$data = $result->fetchAll();
			return $data;
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('user_ldap', 'Could not fetch data from ' . self::USER_TABLE . ' from database: ' . $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}
		
		return false;
	}
	
	/**
	 * Retrieves an array with the requested data from the specified user
	 * @param string $search The parameter to look for the user (cn, displayname, etc.)
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function getUserData($search, array $searchTokens = ['cn'], array $params = ['cn'], $limit = 1, $logicalOp = 'OR')
	{
		return self::fetchData(self::USERS_TABLE, $search, $searchTokens, $limit, $params, '=', $logicalOp);
	}
	
	/**
	 * Retrieves an array where each entry is a row from the table ldap_groups
	 * @param string $search The searh pattern
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function fetchGroupsData($search = '', array $searchTokens = ['cn'], array $params = ['cn'], $limit = null, $logicalOp = 'OR')
	{
		return self::fetchData(self::GROUPS_TABLE, $search, $searchTokens, $limit, $params, 'LIKE', $logicalOp);
	}
	
	/**
	 * Retrieves an array with the data of the specified group
	 * @param string $search The searh pattern (cn)
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function getGroupData($search, array $searchTokens = ['cn'], array $params = ['cn'], $limit = 1, $logicalOp = 'OR')
	{
		return self::fetchData(self::GROUPS_TABLE, $search, $searchTokens, $limit, $params, '=', $logicalOp);
	}
	
	/**
	 * Returns the number of users that match the specified search parameters
	 * @param string $search The searh pattern
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function countNumberOfUsers($search = '', array $searchTokens = ['cn'], $searchOp = '=', $logicalOp = 'OR')
	{
		return self::countEntries(self::USERS_TABLE, $search, $searchTokens, $searchOp, $logicalOp);
	}
	
	/**
	 * Retrieves the number of groups that match the specified search parameters
	 * @param string $search The searh pattern
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function countNumberOfGroups($search = '', array $searchTokens = ['cn'], $searchOp = '=', $logicalOp = 'OR')
	{
		return self::countEntries(self::GROUPS_TABLE, $search, $searchTokens, $searchOp, $logicalOp);
	}
	
	/**
	 * Retrieves an array with the cn of the users who belong to the specified group in the parameter $search
	 * @param string $search The searh pattern
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function fetchGroupMembers($group, $search = '', $limit = null)
	{
		$tempGroupCN = str_replace('%', '', $group);
		if(empty($tempGroupCN))
			$searchOp = 'LIKE';
		else
			$searchOp = '=';
		
		try 
		{
			if(!$search || empty($search))
			{
				$queryStr = 'SELECT user_cn FROM ' . self::GROUP_MAPPINGS . ' WHERE group_cn ' .$searchOp. ' ?';
				$query = \OC_DB::prepare($queryStr, $limit);
				$result = $query->execute([$group]);
			}
			else
			{
				$queryStr = 'SELECT user_cn FROM ' . self::GROUP_MAPPINGS . ' WHERE group_cn ' .$searchOp. ' ? AND user_cn LIKE ?';
				$query = \OC_DB::prepare($queryStr, $limit);
				$result = $query->execute([$group, $search]);
			}
			
			$data = $result->fetchAll();
			
			return $data;
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('user_ldap', 'Could not fetch users from group ' . $group . ' from database: ' . $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}
	}
	/**
	 * Retrieves an array with all e-groups that the given user belongs to
	 * @param string $search The searh pattern
	 * @param array $searchTokens The columns to compare the search pattern against
	 * @param unknown $limit The maximun number of rows to fetch
	 * @param array $params The parametes to retrieve from the table
	 * @param string $logicalOp The logical operator to concatenate the searchTokens (AND, OR, ...)
	 */
	public static function fetchUserGroups($user, $limit = null)
	{
		try
		{
			$query = \OC_DB::prepare('SELECT group_cn FROM ' . self::GROUP_MAPPINGS . ' WHERE user_cn = ?', $limit);
			$result = $query->execute([$user]);
			
			$data = $result->fetchAll();
			return $data;
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('user_ldap', 'Could not fetch groups for user ' . $user . ' from database: ' . $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}
	}
	
	/**
	 * Checks if the given user is member of the given group
	 * @param unknown $user (cn)
	 * @param unknown $group (cn)
	 * @returns bool
	 */
	public static function isInGroup($user, $group)
	{
		$data = self::fetchData(self::GROUP_MAPPINGS, [$user, $group], ['user_cn', 'group_cn'], 1, ['user_cn'], '=', 'AND');
		return ($data && count($data) > 0);
	}
	
	private static function processQueryArg($requestedCount, $arg)
	{
		if($requestedCount < 1)
			return (is_array($arg)? $arg : [$arg]);
		
		if(is_array($arg))
		{
			if(count($arg) != $requestedCount)
				throw new Exception('Insufficent values for the given search parameters');
			
			return $arg;
		}
		else
		{
			return array_fill(0, $requestedCount, $arg);
		}
	}

	/**
	 * 
	 * @param unknown $table
	 * @param unknown $search
	 * @param array $searchTokens
	 * @param unknown $limit
	 * @param array $params
	 * @param string $searchOp
	 * @param string $logicalOp
	 */
	private static function fetchData($table, $search, array $searchTokens = ['cn'], $limit = null, array $params = ['cn'], $searchOp = '=', $logicalOp = 'OR')
	{
		$jointParams = implode(',', $params);
				
		try 
		{
			$count = count($searchTokens);
			$search = self::processQueryArg($count, $search);
			$searchOp = self::processQueryArg($count, $searchOp);
			$logicalOp = self::processQueryArg($count - 1, $logicalOp); // There is always 1 logical operator less than parameters
			
			if(!$search || empty($search))
			{
				$queryStr = 'SELECT ' . $jointParams . ' FROM ' .$table;
			}
			else
			{
				$queryStr = 'SELECT ' . $jointParams . ' FROM ' . $table . ' WHERE ' . self::buildSelectQuery($table, $searchOp, $searchTokens, $logicalOp);
			}
			
			$query = \OC_DB::prepare($queryStr, $limit);
			$result = $query->execute($search);
			return $result->fetchAll();
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('user_ldap', __METHOD__ . ': Could not fetch data from ' . $table . ' from database: ' . $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}
		
		return false;
	}
	
	/**
	 * 
	 * @param unknown $table
	 * @param unknown $search
	 * @param array $searchTokens
	 * @param string $logicalOp
	 */
	private static function countEntries($table, $search, array $searchTokens = ['cn'], $searchOp = '=', $logicalOp = 'OR')
	{
		try 
		{
			$count = count($searchTokens);
			$search = self::processQueryArg($count, $search);
			$searchOp = self::processQueryArg($count, $searchOp);
			$logicalOp = self::processQueryArg($count - 1, $logicalOp); // There is always 1 logical operator less than parameters
			
			// Special query if we are not looking for any specific parameter
			$queryStr = '';
			if(!$search || empty($search))
			{
				$queryStr = 'SELECT COUNT(*) FROM ' . $table;
			}
			else
			{
				$queryStr = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . self::buildSelectQuery($table, $searchOp, $searchTokens, $logicalOp);
			}
			
			$query = \OC_DB::prepare($queryStr);
			$result = $query->execute($search);
			$data = $result->fetchRow();
			return $data['COUNT(*)'];
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('user_ldap', __METHOD__ . ': Could not count ' . $table . ' from database: ' . $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}
		
		return false;
	}
}