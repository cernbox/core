<?php
/**
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Files\Service;

use OC\Files\FileInfo;
use OC\Files\ObjectStore\EosUtil;


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
	 * @throws \OCP\NotFoundException if the file does not exist
	 */
	public function updateFileTags($path, $tags) {
		$fileInfo =  $this->homeFolder->get($path)->getFileInfo();
		$versionFolderInfo = null;
		
		if($fileInfo['type'] === 'file') {	
			$versionFolder = dirname($path) . "/" . ".sys.v#." . basename($path);
			try {
				$versionFolderInfo = $this->homeFolder->get($versionFolder)->getFileInfo();
			} catch (\OCP\Files\NotFoundException $e) {
				EosUtil::createVersion($fileInfo['eospath']);			
			 	$versionFolderInfo = $this->homeFolder->get($versionFolder)->getFileInfo();	
			}
		}
		
		$fileId = $fileInfo['type'] === 'file' ?  $versionFolderInfo['fileid'] : $fileInfo['fileid'];

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
	 * Updates the tags of the specified file path.
	 * The passed tags are absolute, which means they will
	 * replace the actual tag selection.
	 *
	 * @param array $tagName tag name to filter by
	 * @return FileInfo[] list of matching files
	 * @throws \Exception if the tag does not exist
	 */
	public function getFilesByTag($tagName) {
		$nodes = $this->homeFolder->searchByTag(
			$tagName, $this->userSession->getUser()->getUId()
		);
		$fileInfos = [];
		foreach ($nodes as $node) {
			try {
				/** @var \OC\Files\Node\Node $node */
				$fileInfos[] = $node->getFileInfo();
			} catch (\Exception $e) {
				// FIXME Should notify the user, when this happens
				// Can not get FileInfo, maybe the connection to the external
				// storage is interrupted.
			}
		}
		// HUGO do here the trick of pointing to versions folder for files tagge
		$fileInfosConverted = array();
		foreach($fileInfos as $info) {
			if($info['type'] === 'file') {
				\OCP\Util::writeLog('TAG', $info['eospath'] . " must point to its sys folder. This should have been done is creating the FAV", \OCP\Util::ERROR);
			} else {
				$basename = basename($info['path']);
				$dirname = dirname($info['path']);
				if(strpos($basename, '.sys.v#.') !== false) {
					\OCP\Util::writeLog('TAG', $info['eospath'] . " is a versions folder, we need to gave the user the real file", \OCP\Util::ERROR);
					$filename = $basename;
					$filepath =  substr($dirname, 6) . "/" . substr($filename, 8);
                                        \OCP\Util::writeLog('TAG', $filepath, \OCP\Util::ERROR);
					
					$newInfo = $node = $this->homeFolder->get($filepath)->getFileInfo();
                                        \OCP\Util::writeLog('TAG', $newInfo['eospath'], \OCP\Util::ERROR);
					$fileInfosConverted[] = $newInfo;
					
				} else {
					$fileInfosConverted[] = $info;
				}
				
			}
		}
		return $fileInfosConverted;
	}
}

