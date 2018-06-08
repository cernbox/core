<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:25 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;
use OCP\Files\Cache\ICacheEntry;


/**
 * Class MultiCache
 *
 * @package OC\CernBox\Storage\MetaDataCache
 * This cache takes an array of IMetaDataCache and
 * retrieves the cache information in the order the caches are defined.
 * If some cached data is defined on less priority caches, this
 * data will saved to higher caches.
 */
class MultiCache implements IMetaDataCache {

	/** @var IMetaDataCache[] list of enabled caches */
	private $caches = array();

	/**
	 * MultiCache constructor.
	 *
	 * @param array $caches
	 */
	public function __construct($caches) {
		if(count($caches) > 0) {
			// lowest number => higher priority
			$priority = 0;
			foreach($caches as $cache) {
				$this->caches[$priority] = $cache;
				$priority++;
			}
		}
	}



	public function getCacheEntry($key)
	{
		$args = func_get_args();
		foreach($this->caches as $priority=> $cache) {
			$data = call_user_func_array(array($cache, __FUNCTION__), $args);
			if ($data) {
				if($priority !== 0) {
					$this->propagateToHigherCaches($priority, 'setCacheEntry', $key, $data);
				}
				return $data;
			}
		}
		return null;
	}

	public function setCacheEntry($ocPath, ICacheEntry $data)
	{
		$args = func_get_args();
		foreach($this->caches as $priority => $cache) {
			call_user_func_array(array($cache, __FUNCTION__), $args);
		}
	}

	public function getUidAndGid($key)
	{
		$args = func_get_args();
		foreach($this->caches as $priority => $cache) {
			$data = call_user_func_array(array($cache, __FUNCTION__), $args);
			if ($data) {
				if($priority !== 0) {
					$this->propagateToHigherCaches($priority, 'setUidAndGid', $key, $data);
				}
				return $data;
			}
		}
		return null;
	}

	public function setUidAndGid($key, array $data)
	{
		$args = func_get_args();
		foreach($this->caches as $priority => $cache) {
			call_user_func_array(array($cache, __FUNCTION__), $args);
		}
	}

	public function getPathById($key) {
		$args = func_get_args();
		foreach($this->caches as $priority => $cache) {
			$data = call_user_func_array(array($cache, __FUNCTION__), $args);
			if ($data !== null) {
				if($priority !== 0) {
					$this->propagateToHigherCaches($priority, 'setPathById', $key, $data);
				}
				return $data;
			}
		}
		return null;
	}

	public function setPathById($key, $data) {
		$args = func_get_args();
		foreach($this->caches as $priority => $cache) {
			call_user_func_array(array($cache, __FUNCTION__), $args);
		}
	}

	public function clearCacheEntry($key) {
		$args = func_get_args();
		foreach($this->caches as $priority => $cache) {
			call_user_func_array(array($cache, __FUNCTION__), $args);
		}
	}


	private function propagateToHigherCaches($foundAtPriority, $functionName, $key, $data) {
		/*
		\OC::$server->getLogger()->debug("cache propagation => foundprio:$foundAtPriority func:$functionName key:$key");
		foreach($this->caches as $priority => $cache) {
			if($priority < $foundAtPriority) {
				call_user_func_array(array($cache, $functionName), array($key, $data));
			} else {
				return;
			}
		}
		*/
	}
}
