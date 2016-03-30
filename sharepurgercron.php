<?php

require_once "lib/base.php";

try
{
	OC::$server->getSession()->close();
	
	$query = OC_DB::prepare('SELECT file_source FROM oc_share');
	$result = $query->execute();
	$row = null;
	$invalidList = [];
	while(($row = $result->fetchRow()))
	{
		$meta = OC\Files\ObjectStore\EosUtil::getFileById($row['file_source']);
		if(!$meta)
		{
			$invalidList[] = $row['file_source'];
		}
	}
	
	if(count($invalidList) > 0)
	{
		$sqlPlaceholder = implode(',', $invalidList);
		$clearQuery = OC_DB::prepare('DELETE FROM oc_share WHERE file_source IN (' .$sqlPlaceholder . ')');
		$clearQuery->execute();
	}
}
catch(Exception $e)
{
	\OCP\Util::writeLog('SHARE PURGER CRON', 'Unable to clean invalid shares: ' . $e->getMessage(), \OCP\Util::ERROR);
}