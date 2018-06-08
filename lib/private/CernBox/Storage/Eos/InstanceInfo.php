<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 10/20/16
 * Time: 9:12 AM
 */

namespace OC\CernBox\Storage\Eos;


class InstanceInfo {
	private $name;
	private $mgm;
	private $root;

	public function __construct($name, $mgm, $path) {
		$this->name = $name;
		$this->mgm = $mgm;
		$this->root = $path;
	}

	public function getInstanceName() {
		return $this->name;
	}

	public function getInstanceRootPath() {
		return $this->root;
	}

	public function getInstanceMGMUrl() {
		return $this->mgm;
	}
}
