<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/28/16
 * Time: 4:19 PM
 */

namespace OC\CernBox\Share;


class GroupShareProviderWithRestriction extends GroupShareProvider {

	public function __construct($rootFolder) {
		parent::__construct($rootFolder);
	}

	public function create(\OCP\Share\IShare $share) {
		if(!$this->canShare($share->getNode())) {
			$msg = "Currently not allowed. See KB at http://cern.ch/go/R7np";
			throw new \Exception($msg);
		}
		return parent::create($share);
	}

	public function update(\OCP\Share\IShare $share) {
		if(!$this->canShare($share->getNode())) {
			$msg = "Currently not allowed. See KB at http://cern.ch/go/R7np";
			throw new \Exception($msg);
		}
		return parent::update($share);
	}

	public function delete(\OCP\Share\IShare $share) {
		if(!$this->canShare($share->getNode())) {
			$msg = "Currently not allowed. See KB at http://cern.ch/go/R7np";
			throw new \Exception($msg);
		}
		parent::delete($share);
	}

	private function canShare(\OCP\Files\Node $node) {
		$owner = $node->getOwner()->getUID();
		$currentPath = $node->getInternalPath();

		if (strpos($currentPath, 'files') === 0) {
			$currentPath = substr($currentPath, 5);
		}

		if ($currentPath === '' || $currentPath === '/') {
			return true;
		}

		$currentPath = "/" . trim($currentPath, '/');
		$shares = $this->getSharesBy($owner, \OCP\Share::SHARE_TYPE_USER, null, false, -1, 0);
		$allPaths = array();
		foreach ($shares as $share) {
			$fileID = $share->getNodeId();
			$path = $this->instanceManager->getPathById($owner, $fileID);
			if ($path) {
				$p = "/" . trim(substr($path, 5), "/"); // remove files/ prefix
				if ($p !== "/" && $p !== $currentPath) {
					$allPaths[] = $p;
				}
			}
		}

		$sharedFolderPath = self::childrenFoldersHaveBeenShared($allPaths, $currentPath);
		if ($sharedFolderPath) {
			return false;
		}
		return true;
	}

	private static function childrenFoldersHaveBeenShared($allPaths, $currentPath) {
		foreach ($allPaths as $path) {
			// $path can be /FCC Two/Internal and $currentPath /FCC
			// so we cannot just check that $path starts with prefix $currentPath
			// we check that the paths to be matched ends with a /
			if (strpos($path, $currentPath . '/') === 0) {
				return $path;
			}
		}
		return false;
	}
}

