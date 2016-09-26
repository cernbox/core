<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/7/16
 * Time: 10:53 AM
 */

namespace OC\CernBox\Storage\Eos;
use OC\CernBox\Storage\MetaDataCache\IMetaDataCache;
use OCP\Files\Cache\ICacheEntry;


/**
 * Class InstanceManager
 *
 * @package OC\CernBox\Storage\Eos
 */
class InstanceManager implements  IInstance {

	private $instances;

	private $logger;

	/**
	 * @var IInstance
	 */
	private $currentInstance;

	private $homeDirectoryInstance;
	/**
	 * @var IMetaDataCache
	 */
	private $metaDataCache;

	/**
	 * InstanceManager constructor.
	 */
	public function __construct() {
		$this->logger = \OC::$server->getLogger();
		$this->metaDataCache = \OC::$server->getCernBoxMetaDataCache();

		// instantiate all the eos instances defined on the configuration file.
		$eosInstances = \OC::$server->getConfig()->getSystemValue("eosinstances");
		foreach($eosInstances as $instanceId => $instanceConfig) {
			$instance = new Instance($instanceId, $instanceConfig);
			$this->instances[$instance->getId()] = $instance;
		}

		// register instance for home directories
		$homeDirectoryInstance = \OC::$server->getConfig()->getSystemValue("eoshomedirectoryinstance");
		$this->homeDirectoryInstance = $this->instances[$homeDirectoryInstance];

		// TODO: load current instance based on URI or Redis Cache.
		$this->currentInstance = $this->homeDirectoryInstance;
	}

	public function loadInstancesFromDataBase() {
		$instanceConfigs = \OC_DB::prepare('SELECT * FROM cernbox_eos_instances_mapping')->execute()->fetchAll();
		foreach($instanceConfigs as $instanceConfig) {
			$instance = new Instance($instanceConfig['id'], $instanceConfig);
			$this->addInstance($instance);
		}
	}

	public function addInstance(IInstance $instance) {
		$this->instances[] = $instance;
	}

	public function getCurrentInstance() {
		return $this->currentInstance;
	}

	/**
	 * @return IInstance[]
	 */
	public function getAllInstances() {
		return $this->instances;
	}

	/**
	 * @param $id string
	 * @return IInstance
	 */
	public function getInstanceById($id) {
		if(isset($this->instances[$id])) {
			return $this->instances[$id];
		} else {
			return null;
		}
	}

	/**
	 * @param $id string
	 */
	public function setCurrentInstance($id) {
		if(!$id) { // default to home directory instance
			$this->currentInstance = $this->homeDirectoryInstance;
		} else {
			$this->currentInstance = $this->instances[$id];
			$this->logger->info("current instance is $id");
		}
	}

	/**
	 * @return string
	 */
	public function getInstanceId() {
		return $this->currentInstance->getId();
	}

	/*
	 * Storage functions
	 */
	public function createDir($username, $ocPath) {
		return $this->currentInstance->createDir($username, $ocPath);
	}

	public function remove($username, $ocPath) {
		return $this->currentInstance->remove($username, $ocPath);
	}

	public function read($username, $ocPath) {
		return $this->currentInstance->read($username, $ocPath);
	}

	public function write($username, $ocPath, $stream) {
		return $this->currentInstance->write($username, $ocPath, $stream);
	}

	public function rename($username, $fromOcPath, $toOcPath) {
		return $this->currentInstance->rename($username, $fromOcPath, $toOcPath);
	}

	/*
	 * Namespace functions
	 */
	/**
	 * @param $username
	 * @param $ocPath
	 * @return CacheEntry
	 */
	public function get($username, $ocPath) {
		$key = $this->currentInstance->getId() . ":" . $username . ":" . $ocPath;
		$cachedData = $this->metaDataCache->getCacheEntry($key);
		if($cachedData !== null) {
			$this->logger->info("cache hit for $key");
			return $cachedData;
		}
		$this->logger->info("cache miss for $key");
		$entry = $this->currentInstance->get($username, $ocPath);
		if(!$entry) {
			return null;
		} else {
			$this->metaDataCache->setCacheEntry($key, $entry);
			return $entry;
		}
	}

	/**
	 * @param $username
	 * @param $ocPath
	 * @return CacheEntry[]
	 */
	public function getFolderContents($username, $ocPath) {
		$data = $this->currentInstance->getFolderContents($username, $ocPath);
		if(!$data) {
			return null;
		} else {
			return $data;
		}
	}

	/**
	 * @param $username
	 * @param $id
	 * @return CacheEntry[]
	 */
	public function getFolderContentsById($username, $id) {
		$key = $this->currentInstance->getId() . ":" . $username . ":" . $id;
		$ocPath = $this->metaDataCache->getPathById($key);
		if($ocPath !== null) {
			$this->logger->info("cache hit for $key");
			return $this->currentInstance->getFolderContents($username, $ocPath);
		} else {
			$this->logger->info("cache miss for $key");
			$ocPath = $this->currentInstance->getPathById($username, $id);
			if($ocPath !== null) {
				$this->logger->debug("going to insert key $key with value $ocPath");
				$this->metaDataCache->setPathById($key, $ocPath);
				return $this->currentInstance->getFolderContents($username, $ocPath);
			} else {
				return null;
			}
		}
	}

	/**
	 * @param $username
	 * @param $id
	 * @return string
	 */
	public function getPathById($username, $id) {
		$key = $this->currentInstance->getId() . ":" . $username . ":" . $id;
		$cachedData = $this->metaDataCache->getPathById($key);
		if($cachedData !== null) {
			$this->logger->info("cache hit for $key");
			return $cachedData;
		}
		$this->logger->info("cache miss for $key");
		$data = $this->currentInstance->getPathById($username, $id);
		if($data !== null) {
			$this->metaDataCache->setPathById($key, $data);
			return $data;
		} else {
			return null;
		}
	}

	public function getId() {
		return $this->currentInstance->getId();
	}

	public function getName() {
		return $this->currentInstance->getName();
	}

	public function getPrefix() {
		return $this->currentInstance->getPrefix();
	}

	public function getProjectPrefix() {
		return $this->currentInstance->getProjectPrefix();
	}

	public function getDeletedFiles($username) {
		return $this->currentInstance->getDeletedFiles($username);
	}

	public function restoreDeletedFile($username, $key) {
		return $this->currentInstance->restoreDeletedFile($username, $key);
	}

	public function purgeAllDeletedFiles($username) {
		return $this->currentInstance->purgeAllDeletedFiles($username);
	}

	public function getVersionsForFile($username, $ocPath) {
		return $this->currentInstance->getVersionsForFile($username, $ocPath);
	}

	public function rollbackFileToVersion($username, $ocPath, $version) {
		return $this->currentInstance->rollbackFileToVersion($username, $ocPath, $version);
	}

	public function downloadVersion($username, $ocPath, $version) {
		return $this->currentInstance->downloadVersion($username, $ocPath, $version);
	}


}