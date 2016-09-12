<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:29 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;


/**
 * Class PathControlledCache
 *
 * @package OC\CernBox\Storage\MetaDataCache
 *
 * This cache contains a list of paths to disable metadata caching.
 * It wraps a IMetaDataCache that is called or not depending on the path.
 */
class PathControlledCache {

	private $paths;
	private $wrapped;

	public function __construct($wrapped) {
		$paths = \OC::$server->getConfig()->getSystemValue("avoid_req_cache_paths", array());
		$this->paths = $paths;
		$this->wrapped = $wrapped;
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


	public  function getFileById($id)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function setFileById($id, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function clearFileById($id)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function getFileByEosPath($eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function getSecureFileByEosPath($eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function setFileByEosPath($eosPath, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function clearFileByEosPath($eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function getFileInfoByEosPath($depth, $eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function setFileInfoByEosPath($depth, $eosPath, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function getMeta($ocPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function setMeta($ocPath, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function getEGroups($user)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function setEGroups($user, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function getOwner($eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function setOwner($eosPath, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function getUidAndGid($user)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}

	public  function setUidAndGid($user, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return call_user_func_array(array($this->wrapped, __FUNCTION__), func_get_args());
		}
	}
}