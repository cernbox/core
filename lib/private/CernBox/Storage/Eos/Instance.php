<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/9/16
 * Time: 9:00 AM
 */

namespace OC\CernBox\Storage\Eos;


use OCP\Files\Cache\ICacheEntry;

class Instance {

	const READ_BUFFER_SIZE = 8192;

	private $id;
	private $eosMgmUrl;
	private $eosPrefix;
	private $eosMetaDataPrefix;
	private $eosRecycleDir;
	private $eosProjectPrefix;
	private $metaDataCache;
	private $stagingDir;

	private $logger;

	public function __construct() {
		$this->logger = \OC::$server->getLogger();

		$this->id = "eosbackup";
		$this->eosMgmUrl = "root://eosbackup.cern.ch";
		$this->eosPrefix = "/eos/scratch/user/<letter>/<username>/";
		$this->eosMetaDataPrefix = "/eos/scratch/user/.sys.dav.hide#.user.metadata/<letter>/<username>/";
		$this->eosRecycleDir = "/eos/backup/proc/recycle/";
		$this->eosProjectPrefix = "/eos/scratch/project";
		$this->stagingDir = "/tmp/";

		$this->metaDataCache = \OC::$server->getCernBoxMetaDataCache();
	}

	public function getId() {
		return $this->id;
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
			$this->metaDataCache->clearFileByEosPath($eosPath);
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

		$xrdTarget = $this->eosMgmUrl . "//" . $eosPath;
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

	public function getFolderContents($username, $ocPath) {
		$translator = $this->getTranslator($username);
		$eosPath = $translator->toEos($ocPath);
		$eosPath = escapeshellarg($eosPath);
		$command = "find --fileinfo --maxdepth 1 $eosPath";
		$commander = $this->getCommander($username);
		list($result, $errorCode) = $commander->exec($command);
		if($errorCode !== 0) {
			return false;
		} else {
			$entries = array();
			foreach($result as $lineToParse) {
				$eosMap = CLIParser::parseEosFileInfoMResponse($lineToParse);
				if($eosMap['eos.file']) {
					$ownCloudMap = $this->getOwnCloudMapFromEosMap($username, $eosMap);
					$entries[] = new CacheEntry($ownCloudMap);
				}
			}
			return $entries;
		}
	}

	public function getFolderContentsById($username, $id) {
		$entry = $this->getById($username, $id);
		return $this->getFolderContents($username, $entry->getPath());
	}

	public function getPathById($username, $id) {
		$entry = $this->getById($username, $id);
		if (!$entry) {
			return false;
		}
		return $entry->getPath();
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

		$eosMap['fileid'] = $eosMap['eos.ino'];
		$eosMap['mtime'] = $eosMap['eos.mtime'];
		$eosMap['size'] = isset($eosMap['eos.size']) ? $eosMap['eos.size'] : 0;
		$eosMap['storage_mtime'] = $eosMap['mtime'];
		$eosMap['path'] = $translator->toOc($eosMap['eos.file']);
		$eosMap['path_hash'] = md5($eosMap['path']);
		$eosMap['parent'] = $eosMap['eos.pid'];
		$eosMap['encrypted'] = 0;
		$eosMap['unencrypted_size'] = $eosMap['size'];

		if(isset($eosMap['eos.container'])) {
			$eosMap['mimetype'] = "httpd/unix-directory";
		} else {
			$eosMap['mimetype'] = \OC::$server->getMimeTypeDetector()->detectString($eosMap['file']);
		}

		//$eosMap['permissions'] = \OC::$server->getCernBoxEosUtil()->convertEosACLToOwnCloudACL($eosMap['eos.sys.acl']);
		$eosMap['permissions'] = \OCP\Constants::PERMISSION_ALL;
		return $eosMap;
	}
}