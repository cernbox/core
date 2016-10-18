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
		if($isShareByLink) {
			return;
		}
		
		if(!$eosMeta) {
			throw \Exception('The file does not exist');
		}
		
		$owner = EosUtil::getOwner($eosMeta['eospath']);
		$currentPath = $eosMeta['path'];
		$parentPath = dirname($eosMeta['path']);
		
		if(strpos($currentPath, 'files') === 0) {
			$currentPath= substr($currentPath, 5);
		}
		
		if($currentPath=== '' || $currentPath=== '/') {
			return;
		}
		
		if(strpos($parentPath, 'files') === 0) {
			$parentPath = substr($parentPath, 5);
		}
		
		if($parentPath === '' || $parentPath === '/') {
			return;
		}
		
		/*
		The new algorithm will apply this logic:
		Ex: we want to share /test/L0/L1
		Two checks will be performed: 1) Check that parents of /test/L0/L1 have not been shared (/test and /test/L0)
		2) Check that children of /test/L0/L1 have not been shared (all shares with prefix /test/L0/L1/*)

		If both checks are OK, proceed to share the folder as it won't override any share.
		If some check fails, provide the user feedback:

		1) Parent folder X already shared
		2) Children folder X already shared
		*/

		// /test/L0, /test
		$parentPaths = self::getParentFolders($parentPath);	
	
		$query = \OC_DB::prepare('SELECT file_target FROM oc_share WHERE uid_owner = ? AND share_type != 3');
		$shares = $query->execute([$owner])->fetchAll();
		
		$sharedFolderPath = self::parentFoldersHaveBeenShared($parentPaths, $shares);
		if($sharedFolderPath !== false) {
			throw new \Exception("Unable to share the file because the ancestor directory '$sharedFolderPath' has been already shared");
		}
		
		$sharedFolderPath = self::childrenFoldersHaveBeenShared($currentPath, $shares);		
		if($sharedFolderPath) {
			throw new \Exception("Unable to share the file because the subfolder '$sharedFolderPath' has been already shared");
		}
	}

	private static function getParentFolders($path) {
		$path = trim($path, "/");
		$parentPaths = array();
		$tokens = explode("/", $path);
		foreach($tokens as $index => $token) {
			$p = implode("/", array_slice($tokens, 0, $index + 1));	
			$parentPaths[] = "/" . $p;
		}
		return $parentPaths;
	}

	private static function parentFoldersHaveBeenShared($parentFolders, $shares) {
		$targets = array_map(function ($share) {
			// file_targets of user shares end with a particular suffix.
			// Ex: /test/L1 (#18400030) so we need to remove that suffix
			// before comparing.
			$fileTarget = $share['file_target'];
			$fileTargetTokens = explode(" ", $fileTarget);
			array_pop($fileTargetTokens); // remove suffix
			$fileTarget = implode(" ", $fileTargetTokens);
			return $fileTarget;
		}, $shares);
		foreach($parentFolders as $path) {
			if (in_array($path, $targets)) {
				return $path;
			}
		}
		return false;
	}
	
	private static function childrenFoldersHaveBeenShared($pathToBeShared, $shares) {
		$prefix = rtrim($pathToBeShared, "/") . "/";
		foreach($shares as $share) {
			$fileTarget = $share['file_target'];
			if(strpos($fileTarget, $prefix) === 0) {
				$fileTargetTokens = explode(" ", $fileTarget);
				array_pop($fileTargetTokens); // remove suffix
				$fileTarget = implode(" ", $fileTargetTokens);
				return $fileTarget;
			}
		}
		return false;
	}
}
