<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/7/16
 * Time: 10:53 AM
 */

namespace OC\CernBox\Storage\Eos;


/**
 * Class InstanceManager
 *
 * @package OC\CernBox\Storage\Eos
 */
class InstanceManager {


	/**
	 * @var IInstance
	 */
	private $currentInstance;

	/**
	 * InstanceManager constructor.
	 */
	public function __construct() {
		$this->currentInstance = new Instance();
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
		return $this->currentInstance->get($username, $ocPath);
	}

	public function getFolderContents($username, $ocPath) {
		return $this->currentInstance->getFolderContents($username, $ocPath);
	}

	public function getFolderContentsById($username, $id) {
		return $this->currentInstance->getFolderContentsById($username, $id);
	}

	public function getPathById($username, $id) {
		return $this->currentInstance->getPathById($username, $id);
	}
}