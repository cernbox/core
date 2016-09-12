<?php

namespace OC;

use \OC\Files\ObjectStore\EosUtil;

class ShareUtil
{
	public static function checkParentDirSharedById($fileId, $isShareByLink)
	{
		self::checkParentDirShared(EosUtil::getFileById($fileId), $isShareByLink);
	}	
	
	public static function checkParentDirShared(array $eosMeta, $isShareByLink)
	{
		if($isShareByLink)
		{
			return;
		}
		
		if(!$eosMeta)
		{
			throw \Exception('The file does not exist');
		}
		
		$owner = EosUtil::getOwner($eosMeta['eospath']);
		$parentPath = dirname($eosMeta['path']);
		
		if(strpos($parentPath, 'files') === 0)
		{
			$parentPath = substr($parentPath, 5);
		}
		
		if($parentPath === '' || $parentPath === '/')
		{
			return;
		}
		
		$query = \OC_DB::prepare('SELECT file_target FROM oc_share WHERE uid_owner = ? AND share_type != 3');
		$result = $query->execute([$owner])->fetchAll();
		
		$parentDirs = explode('/', $parentPath);
		$parentDirsCompiled = [];
		$len = count($parentDirs);
		$parentDirsCompiled[] = $parentDirs[0];
		$previous = '';
		for($i = 1; $i < $len; $i++)
		{
			$generated = $previous . '/' . $parentDirs[$i];
			$parentDirsCompiled[] = $generated;
			$previous = $generated;
		}
		
		foreach($result as $row)
		{
			if(empty($row['file_target']))
			{
				continue;
			}
			
			foreach($parentDirsCompiled as $parent)
			{
				if(empty($parent))
				{
					continue;
				}
				
				if(strpos($row['file_target'], $parent) !== FALSE)
				{
					throw new \Exception('Unable to share the file because the parent directory ' . $row['file_target'] . ' has been already shared');
				}	
			}
		}
	}
}