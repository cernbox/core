<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 6/27/17
 * Time: 11:16 AM
 */

namespace OC\CernBox\Storage\Eos;


class InstanceMapper implements IInstanceMapper {
	/**
	 * @var []InstanceInfo
	 */
	private $mappings;

	public function __construct() {
		$info = new InstanceInfo('Experiment', 'root://eospublic.cern.ch', '/eos/experiment/');
		$this->mappings = [$info];
	}

	public function getInstanceInfoByPath($rootPath) {
		foreach($this->mappings as $info) {
			if(strpos(trim($rootPath, '/'), trim($info->getInstanceRootPath(), '/')) === 0) {
				return $info;
			}
		}
		return false;
	}

	public function getInstanceInfoByName($instanceName) {
		foreach($this->mappings as $info) {
			if($info->getInstanceName() === $instanceName) {
				return $info;
			}
		}
		return false;
	}

	public function getAllMappings() {
		return $this->mappings;
	}
}