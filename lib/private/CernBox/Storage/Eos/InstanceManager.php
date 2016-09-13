<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/7/16
 * Time: 10:53 AM
 */

namespace OC\CernBox\Storage\Eos;
use OC\CernBox\Storage\MetaDataCache\IMetaDataCache;


/**
 * Class InstanceManager
 *
 * @package OC\CernBox\Storage\Eos
 */
class InstanceManager {

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
	public function get($username, $ocPath) {
		$key = $this->currentInstance->getId() . ":" . $username . ":" . $ocPath;
		$cachedData = $this->metaDataCache->getCacheEntry($key);
		if($cachedData) {
			$this->logger->info("HIT for $key");
			return $cachedData;
		}
		$this->logger->info("MISS for $key");
		$data = $this->currentInstance->get($username, $ocPath);
		$this->metaDataCache->setCacheEntry($key, $data);
		return $data;
	}

	public function getFolderContents($username, $ocPath) {
		return $this->currentInstance->getFolderContents($username, $ocPath);
	}

	public function getFolderContentsById($username, $id) {
		return $this->currentInstance->getFolderContentsById($username, $id);
	}

	public function getPathById($username, $id) {
		$key = $this->currentInstance->getId() . ":" . $username . ":" . $id;
		$cachedData = $this->metaDataCache->getPathById($key);
		if($cachedData) {
			$this->logger->info("HIT for $key");
			return $cachedData;
		}
		$this->logger->info("MISS for $key");
		$data = $this->currentInstance->getPathById($username, $id);
		$this->metaDataCache->setPathById($key, $data);
		return $data;
	}
}