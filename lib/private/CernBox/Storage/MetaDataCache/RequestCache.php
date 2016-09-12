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
		if (!isset($GLOBALS['cernbox'])) {
			$GLOBALS['cernbox'] = array();
			$GLOBALS['cernbox']['idresolution'] = array();
			$GLOBALS['cernbox']['getfilebyid'] = array();
			$GLOBALS['cernbox']['getmeta'] = array();
			$GLOBALS['cernbox']['fileinfo'] = array();
			$GLOBALS['cernbox']['getfilebyeospath'] = array();
			$GLOBALS['cernbox']['getowner'] = array();
			$GLOBALS['cernbox']['getegroups'] = array();
		}
	}

	public function clearFileById($id)
	{
		if($id && isset($GLOBALS['cernbox']['getfilebyid'][$id]))
		{
			unset($GLOBALS['cernbox']['getfilebyid'][$id]);
		}
	}

	public function getUidAndGid($username) {
		if(isset($GLOBALS['cernbox']['idresolution'][$username])) {
			return $GLOBALS['cernbox']['idresolution'][$username];
		}
		return FALSE;
	}

	public function setUidAndGid($username, $data) {
		$GLOBALS['cernbox']['idresolution'][$username] = $data;
	}

	public function getFileById($id) {
        if(isset($GLOBALS['cernbox']['getfilebyid'][$id])) {
        	return $GLOBALS['cernbox']['getfilebyid'][$id];
        }
        return FALSE;
	}

	public function setFileById($id, $data) {
         $GLOBALS['cernbox']['getfilebyid'][$id] = $data;
	}

	public function getMeta($ocPath) {
		if (isset ( $GLOBALS ['cernbox'] ['getmeta'] [$ocPath] )) {
			return $GLOBALS ['cernbox'] ['getmeta'] [$ocPath];
		}
		return FALSE;
	}

	public function setMeta($ocPath, $data) {
		$GLOBALS ['cernbox'] ['getmeta'] [$ocPath] = $data;
	}

	public function getFileByEosPath($eosPath) {
		if (isset ( $GLOBALS ['cernbox'] ['getfilebyeospath'] [$eosPath] )) {
			return $GLOBALS ['cernbox'] ['getfilebyeospath'] [$eosPath];
		}
		return FALSE;
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
		return FALSE;
	}

	public function setOwner($eosPath, $data) {
		$GLOBALS ['cernbox'] ['getowner'] [$eosPath] = $data;
	}

	public function getEGroups($username) {
		if (isset ( $GLOBALS ['cernbox'] ['getegroups'] [$username] )) {
			return $GLOBALS ['cernbox'] ['getegroups'] [$username];
		}
		return FALSE;
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
		return FALSE;
	}
}