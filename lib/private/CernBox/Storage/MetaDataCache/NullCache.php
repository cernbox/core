<?php

namespace OC\CernBox\Storage\MetaDataCache;


class NullCache implements IMetaDataCache {
	public function setFileById($id, $data) {
		// TODO: Implement setFileById() method.
	}

	public function getFileById($id) {
		// TODO: Implement getFileById() method.
	}

	public function clearFileById($id) {
		// TODO: Implement clearFileById() method.
	}

	public function setFileByEosPath($eosPath, $data) {
		// TODO: Implement setFileByEosPath() method.
	}

	public function getFileByEosPath($eosPath) {
		// TODO: Implement getFileByEosPath() method.
	}

	public function clearFileByEosPath($eosPath) {
		// TODO: Implement clearFileByEosPath() method.
	}

	public function getFileInfoByEosPath($depth, $eosPath) {
		// TODO: Implement getFileInfoByEosPath() method.
	}

	public function setFileInfoByEosPath($depth, $eosPath, $data) {
		// TODO: Implement setFileInfoByEosPath() method.
	}

	public function setOwner($eosPath, $owner) {
		// TODO: Implement setOwner() method.
	}

	public function getOwner($eosPath) {
		// TODO: Implement getOwner() method.
	}

	public function setMeta($ocPath, $meta) {
		// TODO: Implement setMeta() method.
	}

	public function getMeta($ocPath) {
		// TODO: Implement getMeta() method.
	}

	public function setEGroups($user, $egroups) {
		// TODO: Implement setEGroups() method.
	}

	public function getEGroups($user) {
		// TODO: Implement getEGroups() method.
	}

	public function getUidAndGid($username) {
		// TODO: Implement getUidAndGid() method.
	}

	public function setUidAndGid($username, $data) {
		// TODO: Implement setUidAndGid() method.
	}

	public function getCacheEntry($key) {
		// TODO: Implement getCacheEntry() method.
	}


}