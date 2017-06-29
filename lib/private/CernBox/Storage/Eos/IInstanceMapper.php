<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/9/16
 * Time: 10:11 AM
 */

namespace OC\CernBox\Storage\Eos;


interface IInstanceMapper {
	/**
	 * @param $instanceRoot
	 * @return InstanceInfo
	 */
 	public function getInstanceInfoByPath($instanceRoot); // /eos/public/experiment

	/**
	 * @param $instanceName
	 * @return InstanceInfo
	 */
	public function getInstanceInfoByName($instanceName);

	/**
	 * @return InstanceInfo[]
	 */
	public function getAllMappings();
}

