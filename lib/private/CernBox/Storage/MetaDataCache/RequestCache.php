<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:06 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;


class RequestCache implements  IMetaDataCache {

	public function __construct() {
		$GLOBALS['cernbox'] = array(
			'username_to_uidgid' => array(),
			'cacheentry' => array(),
			'idresolution' => array(),
			'foldercontents' => array(),
			'foldercontentsbyid' => array(),
		);

		/*
		$GLOBALS['cernbox'] = array();
		$GLOBALS['cernbox']['idresolution'] = array();
		$GLOBALS['cernbox']['getfilebyid'] = array();
		$GLOBALS['cernbox']['getmeta'] = array();
		$GLOBALS['cernbox']['fileinfo'] = array();
		$GLOBALS['cernbox']['getfilebyeospath'] = array();
		$GLOBALS['cernbox']['getowner'] = array();
		$GLOBALS['cernbox']['getegroups'] = array();
		*/
	}

	public function getCacheEntry($key) {
		if(isset($GLOBALS['cernbox']['cacheentry'][$key])) {
			return $GLOBALS['cernbox']['cacheentry'][$key];
		}
		return null;
	}

	public function setCacheEntry($key, $data) {
		$GLOBALS['cernbox']['cacheentry'][$key] = $data;
	}

	public function getPathById($key) {
		if(isset($GLOBALS['cernbox']['pathbyid'][$key])) {
			return $GLOBALS['cernbox']['pathbyid'][$key];
		}
		return $data? $data: null;
	}

	public function setPathById($key, $data) {
		$GLOBALS['cernbox']['pathbyid'][$key] = $data;
	}

	public function getUidAndGid($key) {
		if (isset($GLOBALS['cernbox']['username_to_uidgid'][$key])) {
			return $GLOBALS['cernbox']['username_to_uidgid'][$key];
		}
		return null;
	}

	public function setUidAndGid($key, $data) {
		$GLOBALS['cernbox']['username_to_uidgid'][$key] = $data;
	}

	public function getFolderContents($key) {
		$data= isset($GLOBALS['cernbox']['foldercontents'][$key]);
		return $data? $data: null;
	}

	public function setFolderContents($key, $data) {
		$GLOBALS['cernbox']['foldercontents'][$key] = $data;
	}

	public function getFolderContentsById($key) {
		$data= isset($GLOBALS['cernbox']['foldercontentsbyid'][$key]);
		return $data? $data: null;
	}

	public function setFolderContentsById($key, $data) {
		$GLOBALS['cernbox']['foldercontentsbyid'][$key] = $data;
	}


	public function clearFileById($id)
	{
		if($id && isset($GLOBALS['cernbox']['getfilebyid'][$id]))
		{
			unset($GLOBALS['cernbox']['getfilebyid'][$id]);
		}
	}

	/*
	public function getFileById($id) {
        if(isset($GLOBALS['cernbox']['getfilebyid'][$id])) {
        	return $GLOBALS['cernbox']['getfilebyid'][$id];
        }
        return false;
	}

	public function setFileById($id, $data) {
         $GLOBALS['cernbox']['getfilebyid'][$id] = $data;
	}

	public function getMeta($ocPath) {
		if (isset ( $GLOBALS ['cernbox'] ['getmeta'] [$ocPath] )) {
			return $GLOBALS ['cernbox'] ['getmeta'] [$ocPath];
		}
		return false;
	}

	public function setMeta($ocPath, $data) {
		$GLOBALS ['cernbox'] ['getmeta'] [$ocPath] = $data;
	}

	public function getFileByEosPath($eosPath) {
		if (isset ( $GLOBALS ['cernbox'] ['getfilebyeospath'] [$eosPath] )) {
			return $GLOBALS ['cernbox'] ['getfilebyeospath'] [$eosPath];
		}
		return false;
	}

	public function setFileByEosPath($eosPath, $data) {
		$GLOBALS ['cernbox'] ['getfilebyeospath'] [$eosPath] = $data;
	}

	public function clearFileByEosPath($eosPath) {
		unset($GLOBALS['cernbox']['getfilebyeospath'][$eosPath]);
	}

	public function getOwner($eosPath) {
		if (isset ( $GLOBALS ['cernbox'] ['getowner'] [$eosPath] )) {
			return $GLOBALS ['cernbox'] ['getowner'] [$eosPath];
		}
		return false;
	}

	public function setOwner($eosPath, $data) {
		$GLOBALS ['cernbox'] ['getowner'] [$eosPath] = $data;
	}

	public function getEGroups($username) {
		if (isset ( $GLOBALS ['cernbox'] ['getegroups'] [$username] )) {
			return $GLOBALS ['cernbox'] ['getegroups'] [$username];
		}
		return false;
	}

	public function setEGroups($username, $data) {
		$GLOBALS ['cernbox'] ['getegroups'] [$username] = $data;
	}

	public function setFileInfoByEosPath($depth, $eosPath, $data)
	{
		$key = $depth . '-' . $eosPath;
		$GLOBALS['cernbox']['getFileInfoByEosPath'][$key] = $data;
	}

	public function getFileInfoByEosPath($depth, $eosPath)
	{
		$key = $depth . '-' . $eosPath;
		if(isset($GLOBALS['cernbox']['getFileInfoByEosPath'][$key]))
		{
			return $GLOBALS['cernbox']['getFileInfoByEosPath'][$key];
		}
		return false;
	}
	*/
}