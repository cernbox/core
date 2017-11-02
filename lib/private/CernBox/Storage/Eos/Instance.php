<?php

namespace OC\CernBox\Storage\Eos;


use OCA\Files_EosTrashbin\RecycleSizeLimitException;
use OCP\Constants;
use OCP\Files\Cache\ICacheEntry;

class Instance implements IInstance {
	const READ_BUFFER_SIZE = 8192;
	const VERSIONS_PREFIX = ".sys.v#.";

	private $id;
	private $name;
	private $mgmUrl;
	private $slaveMgmUrl;
	private $prefix;
	private $metaDataPrefix;
	private $recycleDir;
	private $recycleLimit;
	private $hideRegex;
	private $projectPrefix;
	private $stagingDir;
	private $homeDirScript;
	private $isReadOnly;

	private $metaDataCache;
	private $logger;
	private $shareUtil;
	private $projectMapper;

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
		$this->name = isset($instanceConfig['name']) ? $instanceConfig['name'] : null ;
		$this->mgmUrl = isset($instanceConfig['mgmurl']) ? $instanceConfig['mgmurl'] : null;
		$this->slaveMgmUrl = isset($instanceConfig["slavemgmurl"]) ? $instanceConfig["slavemgmurl"] : $this->mgmUrl; // if slave is not defined fallback to mgm
		$this->prefix = isset($instanceConfig['prefix']) ? $instanceConfig['prefix']: null;
		$this->metaDataPrefix = isset($instanceConfig['metadatadir']) ? $instanceConfig['metadatadir'] : null;
		$this->recycleDir = isset($instanceConfig['recycledir']) ? $instanceConfig['recycledir'] : null;
		$this->recycleLimit = isset($instanceConfig['recyclelimit']) ? $instanceConfig['recyclelimit'] : 10000;
		$this->hideRegex = isset($instanceConfig['hideregex']) ? $instanceConfig['hideregex'] : null;
		$this->projectPrefix = isset($instanceConfig['projectprefix']) ? $instanceConfig['projectprefix'] : null;
		$this->stagingDir = isset($instanceConfig['stagingdir']) ? $instanceConfig['stagingdir'] : null;
		$this->homeDirScript = isset($instanceConfig['homedirscript']) ? $instanceConfig['homedirscript'] : null;
		$this->isReadOnly = isset($instanceConfig['readonly']) ? $instanceConfig['readonly'] : false;

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
		return $this->slaveMgmUrl;
	}

	public function getSlaveMgmUrl() {
		return $this->slaveMgmUrl;
	}

	public function getRecycleDir() {
		return $this->recycleDir;
	}

	public function getRecycleLimit() {
		return $this->recycleLimit;
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

	public function isReadOnly() {
		return $this->isReadOnly;
	}


	/*
	 * Storage functions
	 */
	public function createDir($username, $ocPath) {
		if($this->isReadOnly) {
			return false;
		}
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
		if($this->isReadOnly) {
			return false;
		}
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
		$rawCommand = "XRD_NETWORKSTACK=IPv4 xrdcopy -f $xrdSource $localCachedFile -OSeos.ruid=$uid\&eos.rgid=$gid";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->execRaw($rawCommand);
		if ($errorCode !== 0) {
			return false;
		} else {
			return fopen($localCachedFile, 'r');
		}
	}

	public function write($username, $ocPath, $stream) {
		if($this->isReadOnly) {
			return false;
		}
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
		$rawCommand = "XRD_NETWORKSTACK=IPv4 xrdcopy -f $tempFileForLocalWriting $xrdTarget -ODeos.ruid=$uid\&eos.rgid=$gid";
		$commander = $this->getCommander($username);
		list(, $errorCode) = $commander->execRaw($rawCommand);
		if ($errorCode !== 0) {
			@unlink($tempFileForLocalWriting);
			return false;
		} else {
			@unlink($tempFileForLocalWriting);
			return true;
		}
	}

	public function rename($username, $fromOcPath, $toOcPath) {
		if($this->isReadOnly) {
			return false;
		}
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

			// permissions to access a project space are handled using three e-groups:
			// readers, writers and admins
			if(\OC::$server->getAppManager()->isInstalled("files_projectspaces")) {
				if(strpos($ocPath, "files/  project ") === 0) {
					$path = trim($ocPath, '/');
					$path = trim(substr($path, strlen("files/  project ")), "/");
					$projectName = explode('/', $path)[0];
					$projectInfo = \OC::$server->getCernBoxProjectMapper()->getProjectInfoByProject($projectName);

					if($projectInfo->getProjectOwner() === $username || \OC::$server->getCernBoxProjectMapper()->isAdmin($username, $projectInfo->getProjectName())) {
						$ownCloudMap['permissions'] = Constants::PERMISSION_ALL;
					} else if (\OC::$server->getCernBoxProjectMapper()->isWriter($username, $projectInfo->getProjectName())) {
						$ownCloudMap['permissions'] = Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE;
					} else if (\OC::$server->getCernBoxProjectMapper()->isReader($username, $projectInfo->getProjectName())) {
						$ownCloudMap['permissions'] = Constants::PERMISSION_READ;
					} else {
						$ownCloudMap['permissions'] = 0;
					}
				}
			}

			if(\OC::$server->getAppManager()->isInstalled("files_eosbrowser")) {
				if(strpos($ocPath, "files/  eos ") === 0) {
					$ownCloudMap['permissions'] = Constants::PERMISSION_READ;
				}
			}

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
			// we need to obtain the tree size performing an 'ls' command and merging attributes
			$command = "ls -la $eosPathEscaped";
			$commander = $this->getCommander($username);
			list($lsresult, $errorCode) = $commander->exec($command);
			if ($errorCode !== 0) {
				return [];
			}
			// $map_filename_size is a map of filenames (just the base name, not full eos paths) and its tree size.
			$map_filename_size = array();
			foreach($lsresult as $direntry) {
				$direntry = preg_replace('/\s+/', ' ',$direntry); // replace multiple spaces by one
				$elems = explode(" ", $direntry);
				$size = $elems[4];
				$filename_elems = array_slice($elems, 8);
				$filename = implode(' ', $filename_elems);
				$map_filename_size[$filename] = $size;
			}

			$entries = array();
			$this->logger->debug("eospath=$eosPath");
			foreach ($result as $lineToParse) {
				$eosMap = CLIParser::parseEosFileInfoMResponse($lineToParse);
				if ($eosMap['eos.file']) {
					// find also returns the directory
					// asked to be listed, so we filter it.
					if (trim($eosMap['eos.file'], '/') !== trim($eosPath, '/')) {
						// hide filenames that match the hideregex
						if ($this->hideRegex && preg_match("|".$this->hideRegex."|", basename($eosMap["eos.file"])) ) {
							continue;
						}

						if(isset($data['name'])) {
							$data['eos.treesize'] = isset($map_filename_size[$data['name']]) ? (int)$map_filename_size[$data['name']] : 0;
						}
						if(isset($data['eos.container'])) { // is a folder ?
							$data['size'] = $data['eos.treesize'];
						}

						$ownCloudMap = $this->getOwnCloudMapFromEosMap($username, $eosMap);

						// permissions to access a project space are handled using three e-groups:
						// readers, writers and admins
						if(\OC::$server->getAppManager()->isInstalled("files_projectspaces")) {
							$ocPath = $ownCloudMap['path'];
							if(strpos($ocPath, "files/  project ") === 0) {
								$path = trim($ocPath, '/');
								$path = trim(substr($path, strlen("files/  project ")), "/");
								$projectName = explode('/', $path)[0];
								$projectInfo = \OC::$server->getCernBoxProjectMapper()->getProjectInfoByProject($projectName);
								if($projectInfo->getProjectOwner() === $username || \OC::$server->getCernBoxProjectMapper()->isAdmin($username, $projectInfo->getProjectName())) {
									$ownCloudMap['permissions'] = Constants::PERMISSION_ALL;
								} else if (\OC::$server->getCernBoxProjectMapper()->isWriter($username, $projectInfo->getProjectName())) {
									$ownCloudMap['permissions'] = Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE;
								} else if (\OC::$server->getCernBoxProjectMapper()->isReader($username, $projectInfo->getProjectName())) {
									$ownCloudMap['permissions'] = Constants::PERMISSION_READ;
								} else {
									$ownCloudMap['permissions'] = 0;
								}
							}
						}

						$entries[] = new CacheEntry($ownCloudMap);
					}
				}
			}


			if(\OC::$server->getAppManager()->isInstalled("files_eosbrowser")) {
				if(strpos($ocPath, "files/  eos ") === 0) {
					for($i = 0; $i < count($entries); $i++) {
						$entries[$i]['permissions'] = Constants::PERMISSION_READ;
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
		// We check that the user does not have more than 10K files
		// into the trashbin, for that we contact the slave and if the
		// number of files is bigger we abort and alert the user to contact
		// cernbox-admins for more information.

		list($uid, $gid) = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		if(!$uid || !$gid) {
			return [];
		}
		$command = sprintf("find --count %s/%d/%d", $this->recycleDir, $gid, $uid);
		$commander = $this->getCommander("root"); // it must be executed as root
		$commander->setMgmUrl($this->slaveMgmUrl);
		list($result, $errorCode) = $commander->exec($command);
		if($errorCode === 0) {
			$lineToParse = $result[0];
			$m = CLIParser::parseFindCountReponse($lineToParse);
			if($m['nfiles'] > $this->recycleLimit || $m['ndirectories'] > $this->recycleLimit) {
				if(\OC::$server->getAppManager()->isInstalled("files_eostrashbin")) {
					throw new RecycleSizeLimitException(sprintf("Your recycle bin is too big, contact the service support (nfiles=%d ndirectories=%d uid=%d)", $m['nfiles'], $m['ndirectories'], $uid));
				} else {
					throw new \Exception("You recycle is too big, contact the service support");
				}
			}
		} else if ($errorCode !== 2) { // 2 => no such files, it's okay
			throw new \Exception("Cannot list files under the proc/recycle folder");
		}

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
		if($this->isReadOnly) {
			return false;
		}
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
		if($this->isReadOnly) {
			return false;
		}
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
		if($this->isReadOnly) {
			return false;
		}
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
		if($this->isReadOnly) {
			return false;
		}
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
		if($this->isReadOnly) {
			return false;
		}
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
		if($this->isReadOnly) {
			return false;
		}
		$entry = $this->get($username, $ocPath);
		if (!$entry) {
			return false;
		}

		$eosSysAcl = isset($entry['eos.sys.acl']) ? $entry['eos.sys.acl'] : "";

		// aclManager contains the current sys.acl
		$aclManager = $this->getACLManager($eosSysAcl);
		$eosPermissions = $this->shareUtil->getEosPermissionsFromOwnCloudPermissions($ocPermissions);
		
		// $allowedGroup can be a unix group and has the format g:zp and we need
		// to strip the g: part
		if(strpos($allowedGroup, "g:") === 0) {
			$allowedGroup = str_replace("g:", "", $allowedGroup);
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
		if($this->isReadOnly) {
			return false;
		}
		$entry = $this->get($username, $ocPath);
		if (!$entry) {
			return false;
		}

		$eosSysAcl = isset($entry['eos.sys.acl']) ? $entry['eos.sys.acl'] : "";

		// aclManager contains the current sys.acl
		$aclManager = $this->getACLManager($eosSysAcl);
		
		// $allowedGroup can be a unix group and has the format g:zp and we need
		// to strip the g: part
		if(strpos($allowedGroup, "g:") === 0) {
			$allowedGroup = str_replace("g:", "", $allowedGroup);
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
		if($this->isReadOnly) {
			return false;
		}
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
		$this->logger->error(sprintf("homedirscript called: command:%s  returncode=%d result=%s", $command, $errorCode, $result));
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
			return new CacheEntry($ownCloudMap);
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
				$created = $this->createVersionsFolder($username, $versionsFolder);
				if(!$created) {
					return null;	
				}
				$metaData = $this->get($username, $versionsFolder);
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

	private function createVersionsFolder($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);
		$eosPath = escapeshellarg($eosPath);
		$command = "mkdir -p $eosPath";
		// this command needs to be executed as root
		$commander = $this->getCommander("root");
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		}
		list($uid, $gid) = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		if(!$uid || !$gid) {
			//TODO(labkode): log something
			return false;
		}
		$command = "chown -r $uid:$gid $eosPath";
		list(, $errorCode) = $commander->exec($command);
		if ($errorCode !== 0) {
			return false;
		}
		return true;
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

	
	public function getQuotaForUser($username) {
		$prefix = $this->prefix;
		$command = "quota $prefix -m";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if($errorCode != 0) {
			throw new \Exception("cannot get quota for user");
		}
		$quotaInfo = CLIParser::parseQuotaResponse($result);
		$used = $quotaInfo[0];
		$total = $quotaInfo[1];
		return [
			'free' => $total - $used,
			'used' => $used,
			'quota' => $total,
			'total' => $total,
			'relative' => round( ($used/$total) * 100),
			'owner' => $username,
			'ownerDisplayName' => $username,
		];
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
		$eosMap['mtime'] = (int)$eosMap['eos.mtime'];
		$eosMap['size'] = (int)isset($eosMap['eos.size']) ? $eosMap['eos.size'] : 0;
		$eosMap['storage_mtime'] = (int)$eosMap['mtime'];
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
		$recycleMap['mtime'] = (int)basename($recycleMap['eos.deletion-time']);
		return $recycleMap;
	}
}
