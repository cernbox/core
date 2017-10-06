<?php

namespace OC\CernBox\Share;

use OCP\Files\Storage\IStorageFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\Share\IManager;

class MountProvider extends \OCA\Files_Sharing\MountProvider {

	private $fastUrlsEnabled;
	private $fastUrls;
	protected $logger;
	
	/**
	 * @param \OCP\IConfig $config
	 * @param IManager $shareManager
	 * @param ILogger $logger
	 */
	public function __construct(IConfig $config, IManager $shareManager, ILogger $logger) {
		$this->logger = $logger;
		$this->fastUrlsEnabled = \OC::$server->getConfig()->getSystemValue("cbox.fasturlsenabled", false);
		$this->fastUrls = \OC::$server->getConfig()->getSystemValue("cbox.fasturls", array());
		parent::__construct($config, $shareManager, $logger);
	}

	public function getMountsForUser(IUser $user, IStorageFactory $storageFactory) {
		if ($this->fastUrlsEnabled) {
			$shouldMount = $this->shouldMountShares();
			$this->logger->info(sprintf("should mount shares=%s uri=%s", $shouldMount === true? 'yes' : 'no', $_SERVER['REQUEST_URI']));
			if($shouldMount === true) {
				return parent::getMountsForUser($user, $storageFactory);
			} else {
				return [];
			}
		} else {
			return parent::getMountsForUser($user, $storageFactory);
		}
	}

	// black sorcery
	private function shouldMountShares() {
		// if we match the fast urls we do not mount the shares
		$url = urldecode(trim(\OC::$server->getRequest()->getRequestUri(), '/'));
		$url = trim($url, '/');
		foreach($this->fastUrls as $fastUrl) {
			if(strpos($url, $fastUrl) !== false) {
				return false;
			}
		}
		
		// from version 9 basic file ops are done through remote.php/webdav endpoint
		// Example: /remote.php/webdav/cernbox-project-data-mining%20(%239993727)
		// so we get the filename and check if it points to a shared mount.
		$remotePos = strpos($url, 'remote.php/webdav');
		if($remotePos !== false) {
			$filename = explode('/', trim(substr($url, $remotePos + strlen('remote.php/webdav')), '/'))[0];	
			$isShared = $this->isSharedPath($filename);
			return $isShared;
		} 

		if(isset($_GET['shared_with_me']) && $_GET['shared_with_me'] === 'true') {
			if(isset($_GET['path'])) {
				$isShared = $this->isSharedPath($_GET['path']);
				return $isShared;
			}
			return true;
		}

		if(isset($_GET['reshares'])) {
			if(isset($_GET['path'])) {
				return $this->isSharedPath($_GET['path']);
			}
		}

		if(strpos($url, 'apps/eosinfo/getinfo') !== false) {
			if(isset($_POST['path'])) {
				$this->isSharedPath($_POST['path']);
			}
			return true;
		}
		
		if(strpos($url, 'apps/wopiviewer/open') !== false) {
			if(isset($_POST['filename'])) {
				$this->isSharedPath($_GET['filename']);
			}
			return true;
		}
		
		if(strpos($url, 'apps/swanviewer/load') !== false) {
			if(isset($_GET['path'])) {
				$this->isSharedPath($_GET['path']);
			}
			return true;
		}
		
		if(strpos($url, 'apps/rootviewer/load') !== false) {
			if(isset($_GET['path'])) {
				$this->isSharedPath($_GET['path']);
			}
			return true;
		}
		
		if(strpos($url, 'core/preview') !== false) {
			if(isset($_GET['file'])) {
				$this->isSharedPath($_GET['file']);
			}
			return true;
		}
		
		if(strpos($url, 'apps/gallery/preview') !== false) {
			if(isset($_GET['file'])) {
				$this->isSharedPath($_GET['file']);
			}
			return true;
		}
		
		if(strpos($url, 'apps/swanviewer/eosinfo') !== false) {
			if(isset($_GET['filename'])) {
				$this->isSharedPath($_GET['filename']);
			}
			return true;
		}

		return false;

		// we always mount shares as fallback
		return true;
	}
	
	private function isSharedPath($path) { // uri paths always start with leading slash (e.g. ?dir=/bla) 
		// assume that all shared items follow the same // naming convention at the top directory (and they do not clash with normal files and directories)
		// the convention for top directory is: "name (#123)"
		// examples:
		// "/aaa (#1234)/jas" => true
		// "/ d s (#77663455)/jas" => true
		// "/aaa (#1)/jas" => false
		// "/aaa (#ssss)/jas" => false
		// "aaa (#1234)/jas" => false
		// "/(#7766)/jas" => false
		// "/ (#7766)/jas" => true (this is a flaw)
		$path = trim($path, '/');
		if ($this->startsWith ( $path, '/' )) {
			$topdir = explode ( "/", $path ) [1];
		} else {
			$topdir = explode ( "/", $path ) [0];
		}

		$parts = explode ( " ", $topdir );
		if (count ( $parts ) < 2) {
			return false;
		}

		$marker = end($parts);
		// preg_match returns 1 or 0 or false
		$isShared = preg_match ( "/[(][#](\d{3,})[)]/", $marker ); // we match at least 3 digits enclosed within our marker: (#123)
		$isShared === 1? $isShared = true : $isShared = false;
		if($isShared) {
			$_SERVER['cernbox_file_target'] = $topdir;
		}
		$this->logger->info(sprintf("shared_path=%s shared=%s", $topdir, $isShared ? 'true' : 'false'));
		return $isShared;
	}
	
	private function startsWith($haystack, $needle) {
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
	}
}
