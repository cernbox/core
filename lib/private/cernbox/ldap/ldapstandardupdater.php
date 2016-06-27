<?php

namespace OC\Cernbox\LDAP;

abstract class LDAPStandardUpdater extends LDAPUpdater
{
	protected $tableColumns;
	protected $sqlInsertTypes;
	protected $table;
	
	public function __construct(&$ldapAccess)
	{
		parent::__construct($ldapAccess);
		
		$this->ldapFilter = '(cn=*)';
	}
	
	public function fetchData()
	{
		$this->callLDAPMethod();
		$this->ldapData = $this->sortLDAPArray($this->ldapData);
	}
	
	protected function createSQL()
	{
		$limit = count($this->ldapData) - 1;
		$cache = 'INSERT INTO ' . $this->table . ' VALUES ';
		$outerKeys = array_keys($this->ldapData);
		$innerCount = count($this->tableColumns) - 1;
	
		for($i = 0; $i < $limit; $i++)
		{
			$innerArray = $this->ldapData[$outerKeys[$i]];
			$cache .= '(';
			for($j = 0; $j < $innerCount; $j++)
			{
				$val = (isset($innerArray[$this->tableColumns[$j]])?  $innerArray[$this->tableColumns[$j]] : 'NULL');
				if($this->sqlInsertTypes[$j] == 's')
					$cache .= '"' . $val . '",';
				else
					$cache .= $val . ',';
			}
			if($this->sqlInsertTypes[$innerCount] == 's')
				$cache .= '"' . $innerArray[$this->tableColumns[$innerCount]] . '"),';
			else
				$cache .= $innerArray[$this->tableColumns[$innerCount]] . '),';
		}
		
		$innerArray = $this->ldapData [$outerKeys [$limit]];
		$cache .= '(';
		for($j = 0; $j < $innerCount; $j ++) {
			$val = (isset ( $innerArray [$this->tableColumns [$j]] ) ? $innerArray [$this->tableColumns [$j]] : 'NULL');
			if ($this->sqlInsertTypes [$j] == 's')
				$cache .= '"' . $val . '",';
			else
				$cache .= $val . ',';
		}
		if ($this->sqlInsertTypes [$innerCount] == 's')
			$cache .= '"' . $innerArray [$this->tableColumns [$innerCount]] . '");';
		else
			$cache .= $innerArray [$this->tableColumns [$innerCount]] . ');';
	
		return $cache;
	}
	
	protected function fillDatabase()
	{
		$sql = $this->createSQL();
		try
		{
			\OC_DB::prepare('LOCK TABLE '.$this->table.' WRITE')->execute();
			\OC_DB::prepare('DELETE FROM '.$this->table)->execute();
			\OC_DB::prepare($sql)->execute();
			\OC_DB::prepare('UNLOCK TABLES')->execute();
		}
		catch(Exception $e)
		{
			\OCP\Util::writeLog('LDAP CACHE', 'Failed to update database for table ' .$this->table .'. Reason: ' . $e->getMessage(), \OCP\Util::ERROR);
		}
	}
}