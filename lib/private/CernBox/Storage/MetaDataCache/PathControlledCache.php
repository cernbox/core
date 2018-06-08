<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:29 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;
use OCP\Files\Cache\ICacheEntry;


class PathControlledCache implements IMetaDataCache {

	private $paths;
	private $wrapped;

	public function __construct($wrapped) {
		$this->wrapped = $wrapped;
		$paths = \OC::$server->getConfig()->getSystemValue("cbox.caches.pathcontrolled.noncachedpaths", []);
		$this->paths = $paths;
	}

	private function shouldAvoidCache()
	{
		foreach($this->paths as $path)
		{
			if(strpos($_SERVER['REQUEST_URI'], $path) !== false)
			{
				return true;
			}
		}
		return false;
	}

	public function getUidAndGid($key) {
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public function setUidAndGid($key, array $data) {
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public function getCacheEntry($key) {
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public function setCacheEntry($key, ICacheEntry $data) {
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public function getPathById($key) {
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public function setPathById($key, $data) {
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public function clearCacheEntry($key) {
		return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
	}


}