<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files\Service;

use OC\Files\FileInfo;

/**
 * Service class to manage tags on files.
 */
class TagService {

	/**
	 * @var \OCP\IUserSession
	 */
	private $userSession;

	/**
	 * @var \OCP\ITags
	 */
	private $tagger;

	/**
	 * @var \OCP\Files\Folder
	 */
	private $homeFolder;

	public function __construct(
		\OCP\IUserSession $userSession,
		\OCP\ITags $tagger,
		\OCP\Files\Folder $homeFolder
	) {
		$this->userSession = $userSession;
		$this->tagger = $tagger;
		$this->homeFolder = $homeFolder;
	}

	/**
	 * Updates the tags of the specified file path.
	 * The passed tags are absolute, which means they will
	 * replace the actual tag selection.
	 *
	 * @param string $path path
	 * @param array  $tags array of tags
	 * @return array list of tags
	 * @throws \OCP\Files\NotFoundException if the file does not exist
	 */
	public function updateFileTags($path, $tags) {
		/** CERNBOX FAVORITES PATCH */
		//$fileId = $this->homeFolder->get($path)->getId();
		$fileInfo =  $this->homeFolder->get($path)->getFileInfo();
		$versionFolderInfo = null;
		
		if($fileInfo['type'] === 'file') {
			$versionFolder = dirname($path) . "/" . ".sys.v#." . basename($path);
			try {
				$versionFolderInfo = $this->homeFolder->get($versionFolder)->getFileInfo();
			} catch (\OCP\Files\NotFoundException $e) {
				\OC\Files\ObjectStore\EosUtil::createVersion($fileInfo['eospath']);
				$versionFolderInfo = $this->homeFolder->get($versionFolder)->getFileInfo();
			}
		}
		
		$fileId = $fileInfo['type'] === 'file' ?  $versionFolderInfo['fileid'] : $fileInfo['fileid'];
		/** END OF CERNBOX FAVORITES PATCH */
		
		$currentTags = $this->tagger->getTagsForObjects(array($fileId));

		if (!empty($currentTags)) {
			$currentTags = current($currentTags);
		}

		$newTags = array_diff($tags, $currentTags);
		foreach ($newTags as $tag) {
			$this->tagger->tagAs($fileId, $tag);
		}
		$deletedTags = array_diff($currentTags, $tags);
		foreach ($deletedTags as $tag) {
			$this->tagger->unTag($fileId, $tag);
		}

		// TODO: re-read from tagger to make sure the
		// list is up to date, in case of concurrent changes ?
		return $tags;
	}

	/**
	 * Get all files for the given tag
	 *
	 * @param array $tagName tag name to filter by
	 * @return FileInfo[] list of matching files
	 * @throws \Exception if the tag does not exist
	 */
	public function getFilesByTag($tagName) {
		try {
			$fileIds = $this->tagger->getIdsForTag($tagName);
		} catch (\Exception $e) {
			return [];
		}

		$fileInfos = [];
		foreach ($fileIds as $fileId) {
			/** CERNBOX FAVORITES PATCH */
			$eosMeta = \OC\Files\ObjectStore\EosUtil::getFileById((int)$fileId);
			if(!$eosMeta) continue;
			
			$eosPath = $eosMeta['eospath'];
			$owner = \OC\Files\ObjectStore\EosUtil::getOwner($eosPath);
			
			// ITS A PROJECT FAVORITE
			if(($prj = \OC\Files\ObjectStore\EosUtil::getProjectNameForUser($owner)))
			{
				$ocPath = '/  project ' . $prj;	
			}
			// ITS A USER SHARE
			else if($owner !== \OC_User::getUser())
			{
				//$sharedFolderId = $this->getShareInfo($fileTarget, $owner, $sharee)
				//$versionMeta = 
				$prefix = \OC\Files\ObjectStore\EosUtil::getEosPrefix();
				$firstLetter = substr($owner, 0, 1);
				$ownerHome = rtrim($prefix, '/') . '/' . $firstLetter . '/' . $owner;
				$homeLen = strlen($ownerHome);
				$relativeEosPath = ltrim(substr($eosPath, $homeLen), '/');
				$split = explode('/', $relativeEosPath);
				$sharedFolderName = $split[0];
				$versionMeta = \OC\Files\ObjectStore\EosUtil::getFileByEosPath($ownerHome . '/' . $sharedFolderName);
				
				$len = count($split);
				if(strpos($split[$len - 1], '.sys.v#.') !== false)
				{
					$split[$len - 1] = substr($split[$len - 1], 8);
				}
				
				$ocPath = $sharedFolderName . ' (#' . $versionMeta['fileid'] . ')/' . implode('/', array_slice($split, 1));
			}
			// ITS A USER FILE
			else
			{
				$ocPath = $eosMeta['path'];
				$ocPath = trim($ocPath, '/');
				$split = explode('/', $ocPath);
				$ocPath = implode('/', array_slice($split, 1));
			}
			$fileInfos[] = \OC\Files\Filesystem::getFileInfo($ocPath);
		}
		
		return $fileInfos;
	}
	
	private function getShareInfo($fileTarget, $owner, $sharee)
	{
		$allGroups = \OC\LDAPCache\LDAPCacheManager::getUserEGroups($sharee);
		$placeHolder = str_repeat('?,', count($allGroups));
		$placeHolder .= '?';
		$allGroups[] = $sharee;
		$share = \OC_DB::prepare("SELECT file_source FROM oc_share WHERE uid_owner = ? AND file_target like '/ ?%' AND share_with IN ($placeHolder) LIMIT 1")
			->execute($allGroups)
			->fetchAll();
		
		if($share && count($share) > 0)
		{
			return $share[0]['file_source'];
		}
		
		return false;
	}
}

