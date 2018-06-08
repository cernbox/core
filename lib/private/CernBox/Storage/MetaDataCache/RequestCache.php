<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:06 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;


use OCP\Files\Cache\ICacheEntry;

class RequestCache implements  IMetaDataCache {

	public function __construct() {
		$GLOBALS['cernbox'] = array(
			'getUidAndGid' => array(),
			'getCacheEntry' => array(),
			'getPathById' => array(),
		);
	}

	public function getCacheEntry($key) {
		if(isset($GLOBALS['cernbox']['getCacheEntry'][$key])) {
			return $GLOBALS['cernbox']['getCacheEntry'][$key];
		}
		return null;
	}

	public function setCacheEntry($key, ICacheEntry $data) {
		$GLOBALS['cernbox']['getCacheEntry'][$key] = $data;
	}

	public function getPathById($key) {
		if(isset($GLOBALS['cernbox']['getPathById'][$key])) {
			return $GLOBALS['cernbox']['getPathById'][$key];
		}
		return null;
	}

	public function setPathById($key, $data) {
		$GLOBALS['cernbox']['getPathById'][$key] = $data;
	}

	public function getUidAndGid($key) {
		if (isset($GLOBALS['cernbox']['getUidAndGid'][$key])) {
			return $GLOBALS['cernbox']['getUidAndGid'][$key];
		}
		return null;
	}

	public function setUidAndGid($key, array $data) {
		$GLOBALS['cernbox']['getUidAndGid'][$key] = $data;
	}

	public function clearCacheEntry($key) {
		unset($GLOBALS['cernbox']['getCacheEntry'][$key]);
	}


}