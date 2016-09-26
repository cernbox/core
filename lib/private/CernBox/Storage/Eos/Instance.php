<?php

namespace OC\CernBox\Storage\Eos;


use OCP\Files\Cache\ICacheEntry;

class Instance implements IInstance {

	const READ_BUFFER_SIZE = 8192;
	const VERSIONS_PREFIX = ".sys.v#.";

	private $id;
	private $name;
	private $eosMgmUrl;
	private $eosPrefix;
	private $eosMetaDataPrefix;
	private $eosRecycleDir;
	private $eosProjectPrefix;
	private $metaDataCache;
	private $stagingDir;

	private $logger;

	public function __construct($id, $instanceConfig) {
		$this->logger = \OC::$server->getLogger();

		$this->id = $id;
		$this->name= $instanceConfig['name'];
		$this->eosMgmUrl = $instanceConfig['mgmurl'];
		$this->eosPrefix = $instanceConfig['prefix'];
		$this->eosMetaDataPrefix = $instanceConfig['metadatadir'];
		$this->eosRecycleDir = $instanceConfig['recycledir'];
		$this->eosProjectPrefix = $instanceConfig['projectprefix'];
		$this->stagingDir = $instanceConfig['stagingdir'];

		$this->metaDataCache = \OC::$server->getCernBoxMetaDataCache();
	}

	public function getId() {
		return $this->id;
	}

	public function getName() {
		return $this->name;
	}

	public function getPrefix() {
		return $this->eosPrefix;
	}

	public function getProjectPrefix() {
		return $this->eosProjectPrefix;
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
		list(,$errorCode) = $commander->exec($command);
		if($errorCode !== 0) {
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
		list(,$errorCode) = $commander->exec($command);
		if($errorCode !== 0) {
			return false;
		} else {
			return true;
		}
	}

	public function read($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);

		$entry = $this->get($username, $ocPath);
		if(!$entry) {
			return false;
		}

		// try to read the file from the local caching area first.
		$localCachedFile = $this->stagingDir . "/eosread:" . $entry->getId() . ":" . $entry->getMTime();
		if(file_exists($localCachedFile)) {
			$this->logger->info("downloading file from local caching area");
			return fopen($localCachedFile, 'r');
		}

		$xrdSource= escapeshellarg($this->eosMgmUrl . "//". $eosPath);
		list($uid, $gid) = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		$rawCommand = "xrdcopy -f $xrdSource $localCachedFile -OSeos.ruid=$uid\&eos.rgid=$gid";
		$commander = $this->getCommander($username);
		list(,$errorCode) = $commander->execRaw($rawCommand);
		if($errorCode !== 0) {
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

		while(!feof($stream)){
			$data = fread($stream, self::READ_BUFFER_SIZE);
			fwrite($handle, $data);
		}
		fclose($stream);
		fclose($handle);

		$xrdTarget = escapeshellarg($this->eosMgmUrl . "//" . $eosPath);
		list($uid, $gid) = \OC::$server->getCernBoxEosUtil()->getUidAndGidForUsername($username);
		$rawCommand = "xrdcopy -f $tempFileForLocalWriting $xrdTarget -ODeos.ruid=$uid\&eos.rgid=$gid";
		$commander = $this->getCommander($username);
		list(,$errorCode) = $commander->execRaw($rawCommand);
		if($errorCode !== 0) {
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
		list(,$errorCode) = $commander->exec($command);
		if($errorCode !== 0) {
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
		if($errorCode !== 0) {
			return false;
		} else {
			$lineToParse = $result[0];
			$eosMap = CLIParser::parseEosFileInfoMResponse($lineToParse);
			if(!$eosMap['eos.file']) {
				return false;
			}

			$ownCloudMap= $this->getOwnCloudMapFromEosMap($username, $eosMap);
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
		if($errorCode !== 0) {
			return false;
		} else {
			$entries = array();
			$this->logger->debug("eospath=$eosPath");
			foreach($result as $lineToParse) {
				$eosMap = CLIParser::parseEosFileInfoMResponse($lineToParse);
				if($eosMap['eos.file']) {
					// find also returns the directory
					// asked to be listed, so we filter it.
					if($eosMap['eos.file'] !== $eosPath) {
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
			return false;
		}
		return $entry->getPath();
	}

	/**
	 * @param $username
	 * @return ICacheEntry[] returns ICacheEntries with version information
	 */
	public function getDeletedFiles($username) {
		$command = "recycle ls -m";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if($errorCode === 0) {
			$deletedEntries= array();
			foreach($result as $lineToParse) {
				$eosRecycleMap = CLIParser::parseRecycleLSMResponse($lineToParse);
				if(count($eosRecycleMap) > 0) {
					$ownCloudRecycleMap = $this->getOwnCloudRecycleMapFromEosRecycleMap($username, $eosRecycleMap);
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
		}
	}

	/**
	 * @param $username
	 * @param $key the restore-key
	 * @return ICacheEntry the cache entry for the restored file.
	 */
	public function restoreDeletedFile($username, $key) {
		$command = "recycle restore $key";
		$commander = $this->getCommander($username);
		list(,$errorCode) = $commander->exec($command);
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
		list(,$errorCode) = $commander->exec($command);
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
		if($entries) {
			foreach($entries as $entry) {
				// filter the version folder to be output to the user.
				if($entry->getMimeType() !== 'httpd/unix-directory') {
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
		list($result, $errorCode) = $commander->exec($command);
		if($errorCode !== 0) {
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

	private function getById($username, $id) {
		$command = "file info inode:$id -m";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if($errorCode !== 0) {
			return false;
		} else {
			$lineToParse = $result[0];
			$eosMap = CLIParser::parseEosFileInfoMResponse($lineToParse);
			if(!$eosMap['eos.file']) {
				return false;
			}

			$ownCloudMap= $this->getOwnCloudMapFromEosMap($username, $eosMap);
			$entry = new CacheEntry($ownCloudMap);
			return $entry;
		}
	}


	private function getTranslator($username) {
		$translator = new Translator(
			$username,
			$this->eosPrefix,
			$this->eosMetaDataPrefix,
			$this->eosProjectPrefix,
			$this->eosRecycleDir);
		return $translator;
	}

	private function getCommander($username) {
		$commander = new Commander($this->eosMgmUrl, $username);
		return $commander;
	}

	private function getOwnCloudMapFromEosMap($username, $eosMap) {
		$translator = $this->getTranslator($username);

		$eosMap['etag'] = $eosMap['eos.etag'];
		$eosMap['fileid'] = $eosMap['eos.ino'];
		$eosMap['fileid'] = $eosMap['eos.ino'];
		$eosMap['mtime'] = $eosMap['eos.mtime'];
		$eosMap['size'] = isset($eosMap['eos.size']) ? $eosMap['eos.size'] : 0;
		$eosMap['storage_mtime'] = $eosMap['mtime'];
		$eosMap['path'] = $translator->toOc($eosMap['eos.file']);
		$eosMap['path_hash'] = md5($eosMap['path']);
		$eosMap['parent'] = $eosMap['eos.pid'];
		$eosMap['encrypted'] = 0;
		$eosMap['unencrypted_size'] = $eosMap['size'];
		$eosMap['name'] = basename($eosMap['path']);

		if(isset($eosMap['eos.container'])) {
			$eosMap['mimetype'] = "httpd/unix-directory";
		} else {
			$eosMap['mimetype'] = \OC::$server->getMimeTypeDetector()->detectPath($eosMap['eos.file']);
		}

		//$eosMap['permissions'] = \OC::$server->getCernBoxEosUtil()->convertEosACLToOwnCloudACL($eosMap['eos.sys.acl']);
		$eosMap['permissions'] = \OCP\Constants::PERMISSION_ALL;
		return $eosMap;
	}

	private function getOwnCloudRecycleMapFromEosRecycleMap($username, $eosRecycleMap) {
		$translator = $this->getTranslator($username);

		$eosRecycleMap['path'] = $translator->toOc($eosRecycleMap['eos.restore-path']);
		$eosRecycleMap['name'] = basename($eosRecycleMap['eos.restore-path']);
		$eosRecycleMap['mtime'] = basename($eosRecycleMap['eos.deletion-time']);
		return $eosRecycleMap;
	}


}