<?php

namespace OC\CernBox\Storage\Eos;


use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;

class Instance implements IInstance {
	const READ_BUFFER_SIZE = 8192;
	const VERSIONS_PREFIX = ".sys.v#.";

	private $id;
	private $name;
	private $mgmUrl;
	private $prefix;
	private $metaDataPrefix;
	private $recycleDir;
	private $hideRegex;
	private $projectPrefix;
	private $stagingDir;
	private $homeDirScript;

	private $metaDataCache;
	private $logger;
	private $shareUtil;

	/**
	 * Instance constructor.
	 * When calling a method on an Instance object be sure to
	 * determine beforehand that the passed $username has a valid
	 * uid and gid.
	 * Checks for valid uid and gid are not done in this class for performance
	 * reasons.
	 *
	 * @param $id
	 * @param $instanceConfig
	 *
	 */
	public function __construct($id, $instanceConfig) {
		$this->logger = \OC::$server->getLogger();
		$this->shareUtil = \OC::$server->getCernBoxShareUtil();

		$this->id = $id;
		$this->name = $instanceConfig['name'];
		$this->mgmUrl = $instanceConfig['mgmurl'];
		$this->prefix = $instanceConfig['prefix'];
		$this->metaDataPrefix = $instanceConfig['metadatadir'];
		$this->recycleDir = $instanceConfig['recycledir'];
		$this->hideRegex = $instanceConfig['hideregex'];
		$this->projectPrefix = $instanceConfig['projectprefix'];
		$this->stagingDir = $instanceConfig['stagingdir'];
		$this->homeDirScript = $instanceConfig['homedirscript'];

		$this->metaDataCache = \OC::$server->getCernBoxMetaDataCache();
	}

	public function getId() {
		return $this->id;
	}

	public function getName() {
		return $this->name;
	}

	public function getPrefix() {
		return $this->prefix;
	}

	public function getProjectPrefix() {
		return $this->projectPrefix;
	}

	public function getMgmUrl() {
		return $this->mgmUrl;
	}

	public function getRecycleDir() {
		return $this->recycleDir;
	}

	public function getMetaDataPrefix() {
		return $this->metaDataPrefix;
	}

	public function getFilterRegex() {
		return $this->hideRegex;
	}

	public function getStagingDir() {
		return $this->stagingDir;
	}


	/*
	 * Storage functions
	 */
	public function createDir($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);
		$eosPath = escapeshellarg($eosPath);
		$command = "mkdir -p $eosPath";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	public function remove($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);
		$eosPath = escapeshellarg($eosPath);
		$command = "rm -r $eosPath";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	public function read($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);

		$entry = $this->get($username, $ocPath);
		if (!$entry) {
			return false;
		}

		// try to read the file from the local caching area first.
		$localCachedFile = $this->stagingDir . "/eosread:" . $entry->getId() . ":" . $entry->getMTime();
		if (file_exists($localCachedFile)) {
			$this->logger->info("downloading file from local caching area");
			return fopen($localCachedFile, 'r');
		}

		$xrdSource = escapeshellarg($this->mgmUrl . "//" . $eosPath);
		list($uid, $gid) = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		$rawCommand = "xrdcopy -f $xrdSource $localCachedFile -OSeos.ruid=$uid\&eos.rgid=$gid";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->execRaw($rawCommand);
		if ($errorCode !== 0) {
			return false;
		} else {
			return fopen($localCachedFile, 'r');
		}
	}

	public function write($username, $ocPath, $stream) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);

		$tempFileForLocalWriting = tempnam($this->stagingDir, "eostempwrite");
		$handle = fopen($tempFileForLocalWriting, 'w');

		while (!feof($stream)) {
			$data = fread($stream, self::READ_BUFFER_SIZE);
			fwrite($handle, $data);
		}
		fclose($stream);
		fclose($handle);

		$xrdTarget = escapeshellarg($this->mgmUrl . "//" . $eosPath);
		list($uid, $gid) = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		$rawCommand = "xrdcopy -f $tempFileForLocalWriting $xrdTarget -ODeos.ruid=$uid\&eos.rgid=$gid";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->execRaw($rawCommand);
		if ($errorCode !== 0) {
			unlink($tempFileForLocalWriting);
			return false;
		} else {
			return true;
		}
	}

	public function rename($username, $fromOcPath, $toOcPath) {
		$translator = $this->getTranslator($username);
		$fromEosPath = $translator->toEos($fromOcPath);
		$fromEosPath = escapeshellarg($fromEosPath);
		$toEosPath = $translator->toEos($toOcPath);
		$toEosPath = escapeshellarg($toEosPath);
		$command = "file rename $fromEosPath $toEosPath";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	/*
	 * Namespace functions
	 */

	/**
	 * @param $username
	 * @param $ocPath
	 * @return ICacheEntry | false
	 * FIXME: remember to handle permissions
	 */
	public function get($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);
		$eosPath = escapeshellarg($eosPath);
		$command = "file info $eosPath -m";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			$lineToParse = $result[0];
			$eosMap = CLIParser::parseEosFileInfoMResponse($lineToParse);
			if (!$eosMap['eos.file']) {
				return false;
			}

			$ownCloudMap = $this->getOwnCloudMapFromEosMap($username, $eosMap);
			$entry = new CacheEntry($ownCloudMap);
			return $entry;
		}
	}

	/**
	 * @param $username
	 * @param $ocPath
	 * @return ICacheEntry[]|bool
	 */
	public function getFolderContents($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);
		$eosPathEscaped = escapeshellarg($eosPath);
		$command = "find --fileinfo --maxdepth 1 $eosPathEscaped";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			$entries = array();
			$this->logger->debug("eospath=$eosPath");
			foreach ($result as $lineToParse) {
				$eosMap = CLIParser::parseEosFileInfoMResponse($lineToParse);
				if ($eosMap['eos.file']) {
					// find also returns the directory
					// asked to be listed, so we filter it.
					if (trim($eosMap['eos.file'], '/') !== trim($eosPath, '/')) {
						// hide filenames that match the hideregex
						if (preg_match("|".$this->hideRegex."|", basename($eosMap["eos.file"])) ) {
							continue;
						}
						$ownCloudMap = $this->getOwnCloudMapFromEosMap($username, $eosMap);
						$entries[] = new CacheEntry($ownCloudMap);
					}
				}
			}
			return $entries;
		}
	}

	public function getPathById($username, $id) {
		$entry = $this->getById($username, $id);
		if (!$entry) {
			return null;
		}
		return $entry->getPath();
	}

	/**
	 * @param $username
	 * @return ICacheEntry[] returns ICacheEntries with version information
	 */
	public function getDeletedFiles($username) {
		$deletedEntries = array();
		$command = "recycle ls -m";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if ($errorCode === 0) {
			foreach ($result as $lineToParse) {
				$recycleMap = CLIParser::parseRecycleLSMResponse($lineToParse);
				if (count($recycleMap) > 0) {
					$ownCloudRecycleMap = $this->getOwnCloudRecycleMapFromEosRecycleMap($username, $recycleMap);
					$deletedEntry = new DeletedEntry($ownCloudRecycleMap);
					$deletedEntries[] = $deletedEntry;
				}
				/* TODO(labkode): check this
				$isProjectSpaceAdmin = (EosUtil::getProjectNameForUser($username) != null);
				// only list files in the trashbin that were in the files dir
				if(!$isProjectSpaceAdmin)
				{
					$filter        = $eos_prefix . substr($username, 0, 1) . "/" . $username . "/";
					if (strpos($file["restore-path"], $filter) === 0) {
						$files[] = $file;
					}
				}
				else
				{
					$files[] = $file;
				}
				}
				*/
			}
			return $deletedEntries;
		} else {
			// check error 17 for custom errors
			return $deletedEntries;
		}
	}

	/**
	 * @param $username
	 * @param string $key the restore-key
	 * @return ICacheEntry the cache entry for the restored file.
	 */
	public function restoreDeletedFile($username, $key) {
		$command = "recycle restore $key";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->exec($command);
		return $errorCode;
	}

	/**
	 * @param $username
	 * @return bool
	 * Purge all files for the user
	 */
	public function purgeAllDeletedFiles($username) {
		$command = "recycle purge";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->exec($command);
		return $errorCode !== 0 ? false : true;
	}

	/**
	 * @param $username
	 * @param $ocPath
	 * @return ICacheEntry[]
	 */
	public function getVersionsForFile($username, $ocPath) {
		// code necessary to add the versions prefix to the ocPath
		$parts = explode("/", $ocPath);
		$fileName = array_pop($parts);
		$fileName = self::VERSIONS_PREFIX . $fileName;
		$parts[] = $fileName;
		$ocPathWithVersionPrefix = implode("/", $parts);

		$versions = array();
		$entries = $this->getFolderContents($username, $ocPathWithVersionPrefix);
		if ($entries) {
			foreach ($entries as $entry) {
				// filter the version folder to be output to the user.
				if ($entry->getMimeType() !== 'httpd/unix-directory') {
					// we also pass the path to the current file
					$entry['current_revision_path'] = $ocPath;
					$entry['revision'] = $entry->getName();
					$versions[] = $entry;
				}
			}
		}
		return $versions;
	}

	public function rollbackFileToVersion($username, $ocPath, $version) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);
		$eosPathEscaped = escapeshellarg($eosPath);
		$command = "file versions $eosPathEscaped $version";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	public function downloadVersion($username, $ocPath, $version) {
		// code necessary to add the versions prefix to the ocPath
		$parts = explode("/", $ocPath);
		$fileName = array_pop($parts);
		$fileName = self::VERSIONS_PREFIX . $fileName;
		$parts[] = $fileName;
		$parts[] = $version;
		$ocPathWithVersionFile = implode("/", $parts);
		return $this->read($username, $ocPathWithVersionFile);
	}

	public function addUserToFolderACL($username, $allowedUser, $ocPath, $ocPermissions) {
		$entry = $this->get($username, $ocPath);
		if (!$entry) {
			return false;
		}

		$eosSysAcl = isset($entry['eos.sys.acl']) ? $entry['eos.sys.acl'] : "";

		// aclManager contains the current sys.acl
		$aclManager = $this->getACLManager($eosSysAcl);
		$eosPermissions = $this->shareUtil->getEosPermissionsFromOwnCloudPermissions($ocPermissions);
		$aclManager->addUser($allowedUser, $eosPermissions);
		$newEosSysACL = $aclManager->serializeToEos();

		$eosPath = escapeshellarg($entry['eos.file']);
		$command = "attr -r set sys.acl=$newEosSysACL $eosPath";
		$commander = $this->getCommander('root');
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	public function removeUserFromFolderACL($username, $allowedUser, $ocPath) {
		$entry = $this->get($username, $ocPath);
		if (!$entry) {
			return false;
		}

		$eosSysAcl = isset($entry['eos.sys.acl']) ? $entry['eos.sys.acl'] : "";

		// aclManager contains the current sys.acl
		$aclManager = $this->getACLManager($eosSysAcl);
		$aclManager->deleteUser($allowedUser);
		$newEosSysACL = $aclManager->serializeToEos();

		$eosPath = escapeshellarg($entry['eos.file']);
		$command = "attr -r set sys.acl=$newEosSysACL $eosPath";
		$commander = $this->getCommander('root');
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	// add an egroup/unix group to the sys.acl attr of a folder
	public function addGroupToFolderACL($username, $allowedGroup, $ocPath, $ocPermissions) {
		$entry = $this->get($username, $ocPath);
		if (!$entry) {
			return false;
		}

		$eosSysAcl = isset($entry['eos.sys.acl']) ? $entry['eos.sys.acl'] : "";

		// aclManager contains the current sys.acl
		$aclManager = $this->getACLManager($eosSysAcl);
		$eosPermissions = $this->shareUtil->getEosPermissionsFromOwnCloudPermissions($ocPermissions);
		
		// $allowedGroup can be a unix group and has the format unix:zp and we need
		// to strip the unix: part
		if(strpos($allowedGroup, "unix:") === 0) {
			$allowedGroup = str_replace("unix:", "", $allowedGroup);
			$aclManager->addUnixGroup($allowedGroup, $eosPermissions);
		} else {
			$aclManager->addGroup($allowedGroup, $eosPermissions);
		}
		$newEosSysACL = $aclManager->serializeToEos();

		$eosPath = escapeshellarg($entry['eos.file']);
		$command = "attr -r set sys.acl=$newEosSysACL $eosPath";
		$commander = $this->getCommander('root');
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	public function removeGroupFromFolderACL($username, $allowedGroup, $ocPath) {
		$entry = $this->get($username, $ocPath);
		if (!$entry) {
			return false;
		}

		$eosSysAcl = isset($entry['eos.sys.acl']) ? $entry['eos.sys.acl'] : "";

		// aclManager contains the current sys.acl
		$aclManager = $this->getACLManager($eosSysAcl);
		
		// $allowedGroup can be a unix group and has the format unix:zp and we need
		// to strip the unix: part
		if(strpos($allowedGroup, "unix:") === 0) {
			$allowedGroup = str_replace("unix:", "", $allowedGroup);
			$aclManager->deleteUnixGroup($allowedGroup);
		} else {
			$aclManager->deleteGroup($allowedGroup);
		}

		$newEosSysACL = $aclManager->serializeToEos();

		$eosPath = escapeshellarg($entry['eos.file']);
		$command = "attr -r set sys.acl=$newEosSysACL $eosPath";
		$commander = $this->getCommander('root');
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	public function isUserMemberOfGroup($username, $group) {
		$command = "member $group";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		} else {
			$lineToParse = $result[0];
			$memberMap = CLIParser::parseMemberResponse($lineToParse);
			foreach ($memberMap as $entry) {
				if ($entry['user'] === $username &&
					$entry['egroup'] === $group &&
					$entry['member'] === 'true'
				) {
					return true;
				}
			}
			return false;
		}
	}

	public function createHome($username) {
		list($uid,) = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);

		// check that the needed parameters are supplied
		if (empty($this->homeDirScript) || empty($this->mgmUrl) || empty($this->prefix) || empty($this->recycleDir)) {
			$this->logger->critical("error creating homedir, missing instance parameters: homedirscript=%s mgmurl=%s prefix=%s recycledir=%s",
				$this->homeDirScript, $this->mgmUrl, $this->prefix, $this->recycleDir);
			return false;
		}

		// check if the user already has a home
		$code = -1;
		do {
			$code = $this->stat($username, 'files');
			usleep(5000000); // 0.5 seconds
		} while ($code !== 0 && $code !== 14);

		if ($code === 0) {
			$this->logger->info(sprintf("user=%s has a valid homedir", $username));
			return true;
		}

		// the user does not a valid home directory so we try to create it
		// calling the configured script so sites can have their own
		// requirements (quotas, permissions) for their deployments.
		$this->logger->error(sprintf("user=%s does not have a valid homedir", $username));

		$result = null;
		$errorCode = null;
		$command = sprintf("/bin/bash %s %s %s %s %s",
		$this->homeDirScript, $this->mgmUrl, $this->prefix, $this->recycleDir, $username);
		exec($command, $result, $errorCode);
		$this->logger->info(sprintf("homedirscript called: command:%s  returncode=%d result=%s", $command, $errorCode, $result));
		if ($errorCode === 0) {
			return true;
		}
		$this->logger->critical(sprintf("error creating homedir for user=%s command=%s", $username, $command));
		return false;
	}


	/**
	 * @param $eosSysACL
	 * @return ACLManager
	 */
	private function getACLManager($eosSysACL) {
		return new ACLManager($eosSysACL);
	}

	// get a Cache entry by id relative to this storage
	private function getById($username, $id) {
		list($uid,) = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		$command = "file info inode:$id -m";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return null;
		} else {
			$lineToParse = $result[0];
			$eosMap = CLIParser::parseEosFileInfoMResponse($lineToParse);
			if (!$eosMap['eos.file']) {
				return null;
			}
			$ownCloudMap = $this->getOwnCloudMapFromEosMap($username, $eosMap);

			// check if the file is owned by user triggering the call ?
			if ($uid === (int)$ownCloudMap['eos.uid']) {
				$this->logger->critical("xstorage " . $uid . "==" . (int)$ownCloudMap['eos.uid']);
				return new CacheEntry($ownCloudMap);
			}
			$this->logger->warning("xstorage access to file id in other storage. username($username) uid($uid), eospath:(" . $eosMap['eos.file']) . ") owneruid(" . $eosMap['eos.uid'] . ")";
			return null;
		}
	}

	public function getVersionsFolderForFile($username, $ocPath, $forceCreation = false) {
		$dirname = dirname($ocPath);
		$basename = basename($ocPath);
		$versionsFolder = sprintf("%s/.sys.v#.%s", $dirname, $basename);
		$metaData = $this->get($username, $versionsFolder);

		if(!$metaData) {
			if($forceCreation) {
				// TODO(labkode) create versions folder
			} else {
				return null;
			}
		}

		// if there is no metadata after we tried to
		// create the versions folder we abort
		if(!$metaData) {
			return null;
		}
		return $metaData;
	}

	public function getFileFromVersionsFolder($username, $ocPath) {
		// the share information points to a versions folder
		// we need to point the share back to its original file
		$dirname = dirname($ocPath);
		$basename= basename($ocPath);
		if(strpos($basename, ".sys.v#.") === false) {
			return null;
		}
		$basename = substr($basename, strlen(".sys.v#."));
		$versionsPath = sprintf("%s/%s", $dirname, $basename);
		return $this->get($username, $versionsPath);
	}


	/**
	 * @param $username
	 * @param $ocPath
	 * @return int eos error code (14 => file does not exists, 0 => file exists)
	 */
	private function stat($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);
		$eosPath = escapeshellarg($eosPath);
		$command = "stat $eosPath";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->exec($command);
		return $errorCode;
	}

	private function getTranslator($username) {
		$translator = new Translator($username, $this);
		return $translator;
	}

	private function getCommander($username) {
		$commander = new Commander($this->mgmUrl, $username);
		return $commander;
	}

	private function getOwnCloudMapFromEosMap($username, $eosMap) {
		$translator = $this->getTranslator($username);

		$eosMap['etag'] = $eosMap['eos.etag'];
		$eosMap['fileid'] = (int)$eosMap['eos.ino'];
		$eosMap['mtime'] = $eosMap['eos.mtime'];
		$eosMap['size'] = (int)isset($eosMap['eos.size']) ? $eosMap['eos.size'] : 0;
		$eosMap['storage_mtime'] = $eosMap['mtime'];
		$eosMap['path'] = $translator->toOc($eosMap['eos.file']);
		$eosMap['path_hash'] = md5($eosMap['path']);
		$eosMap['parent'] = (int)$eosMap['eos.pid'];
		$eosMap['encrypted'] = 0;
		$eosMap['unencrypted_size'] = $eosMap['size'];
		$eosMap['name'] = basename($eosMap['path']);

		if (isset($eosMap['eos.container'])) {
			$eosMap['mimetype'] = "httpd/unix-directory";
			$eosMap['size'] = $eosMap['eos.treesize'];
		} else {
			$eosMap['mimetype'] = \OC::$server->getMimeTypeDetector()->detectPath($eosMap['eos.file']);
		}

		//$eosMap['permissions'] = \OC::$server->getCernBoxEosUtil()->convertEosACLToOwnCloudACL($eosMap['eos.sys.acl']);
		$eosMap['permissions'] = Constants::PERMISSION_ALL;
		return $eosMap;
	}

	private function getOwnCloudRecycleMapFromEosRecycleMap($username, $recycleMap) {
		$translator = $this->getTranslator($username);

		$recycleMap['path'] = $translator->toOc($recycleMap['eos.restore-path']);
		$recycleMap['name'] = basename($recycleMap['eos.restore-path']);
		$recycleMap['mtime'] = basename($recycleMap['eos.deletion-time']);
		return $recycleMap;
	}
}
