<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/21/16
 * Time: 11:55 AM
 */

namespace OC\CernBox\Storage\Eos;


class DeletedEntry implements IDeletedEntry, \ArrayAccess, \JsonSerializable   {
	private $data;

	public function __construct($ownCloudRecycleMap) {
		$this->data = $ownCloudRecycleMap;
	}

	public function getRestoreKey() {
		return $this->data['eos.restore-key'];
	}

	public function getOriginalPath() {
		return $this->data['path'];
	}

	public function getSize() {
		return $this->data['size'];
	}

	public function getDeletionMTime() {
		return $this->data['mtime'];
	}

	public function getType() {
		if(isset($this->data['eos.type']) && $this->data['eos.type'] === 'file') {
			return 'file';
		} else {
			return 'dir';
		}
	}

	public function offsetSet($offset, $value) {
		$this->data[$offset] = $value;
	}

	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetUnset($offset) {
		unset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		if (isset($this->data[$offset])) {
			return $this->data[$offset];
		} else {
			return null;
		}
	}

	public function jsonSerialize() {
		return $this->data;
	}

}