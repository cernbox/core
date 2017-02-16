<?php

namespace OC;

use \OC\Files\ObjectStore\EosUtil;

class ShareUtil {
	public static function checkParentDirSharedById($fileId, $isShareByLink) {
		self::checkParentDirShared(EosUtil::getFileById($fileId), $isShareByLink);
	}

	public static function checkParentDirShared(array $eosMeta, $isShareByLink) {
		if ($isShareByLink) {
			return;
		}

		if (!$eosMeta) {
			throw \Exception('The file does not exist');
		}

		$owner = EosUtil::getOwner($eosMeta['eospath']);
		$currentPath = $eosMeta['path'];

		if (strpos($currentPath, 'files') === 0) {
			$currentPath = substr($currentPath, 5);
		}

		if ($currentPath === '' || $currentPath === '/') {
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

		$query = \OC_DB::prepare('SELECT * FROM oc_share WHERE uid_owner = ? AND share_type != 3');
		$shares = $query->execute([$owner])->fetchAll();
		$allPaths = array();
		foreach ($shares as $share) {
			$fileID = $share['item_source'];
			$meta = EosUtil::getFileById($fileID);
			if ($meta) {
				$allPaths[] = substr($meta['path'], 5); // remote files/ prefix
			}
		}

		$sharedFolderPath = self::parentFoldersHaveBeenShared($allPaths, $currentPath);
		if ($sharedFolderPath !== false) {
			throw new \Exception("Unable to share the file because the ancestor directory '$sharedFolderPath' has been already shared");
		}

		$sharedFolderPath = self::childrenFoldersHaveBeenShared($allPaths, $currentPath);
		if ($sharedFolderPath) {
			throw new \Exception("Unable to share the file because the subfolder '$sharedFolderPath' has been already shared");
		}
	}

	private static function getParentFolders($path) {
		$path = trim($path, "/");
		$parentPaths = array();
		$tokens = explode("/", $path);
		foreach ($tokens as $index => $token) {
			$p = implode("/", array_slice($tokens, 0, $index + 1));
			$parentPaths[] = "/" . $p;
		}
		return $parentPaths;
	}

	private static function parentFoldersHaveBeenShared($allPaths, $currentPath) {
		$parentPaths = self::getParentFolders(dirname($currentPath));
		foreach($parentPaths as $path) {
			if(in_array($path, $allPaths)) {
				return $path;
			}
		}
		return false;
	}

	private static function childrenFoldersHaveBeenShared($allPaths, $currentPath) {
		$currentPath = $currentPath . "/";
		foreach ($allPaths as $path) {
			if (strpos($path, $currentPath) === 0) {
				return $path;
			}
		}
		return false;
	}
}
