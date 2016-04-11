<?php

namespace OC\Files\ObjectStore;


class EosReqCache implements IEosCache {
	
	public function __construct() {
		$this->init();	
	}
	
	private function init() {
		if (!isset( $GLOBALS['cernbox'])) {
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
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::clearFileById()
	 */
	public function clearFileById($id)
	{
		if($id && isset($GLOBALS['cernbox']['getfilebyid'][$id]))
		{
			unset($GLOBALS['cernbox']['getfilebyid'][$id]);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getUidAndGid()
	 */
	public function getUidAndGid($username) {
		$this->init();
		if(isset($GLOBALS['cernbox']['idresolution'][$username])) {
			//\OCP\Util::writeLog('EOSCACHE',"HIT op:" .  __FUNCTION__ . "(username:$username)", \OCP\Util::ERROR);
			return $GLOBALS['cernbox']['idresolution'][$username];	
		}
		//\OCP\Util::writeLog('EOSCACHE',"MIS op:" .  __FUNCTION__ . "(username:$username)", \OCP\Util::ERROR);
		return FALSE;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setUidAndGid()
	 */
	public function setUidAndGid($username, $data) {
		$this->init();
		$GLOBALS['cernbox']['idresolution'][$username] = $data;	
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getFileById()
	 */
	public function getFileById($id) {
		$this->init();
        if(isset($GLOBALS['cernbox']['getfilebyid'][$id])) {
			//\OCP\Util::writeLog('EOSCACHE',"HIT op:" .  __FUNCTION__ . "(id:$id)", \OCP\Util::ERROR);
        	return $GLOBALS['cernbox']['getfilebyid'][$id];
        }
		//\OCP\Util::writeLog('EOSCACHE',"MIS op:" .  __FUNCTION__ . "(id:$id)", \OCP\Util::ERROR);
        return FALSE;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setFileById()
	 */
	public function setFileById($id, $data) {
		 $this->init();
         $GLOBALS['cernbox']['getfilebyid'][$id] = $data;
	}
		
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getMeta()
	 */
	public function getMeta($ocPath) {
		$this->init ();
		if (isset ( $GLOBALS ['cernbox'] ['getmeta'] [$ocPath] )) {
			// \OCP\Util::writeLog('EOSCACHE',"HIT op:" . __FUNCTION__ . "(ocpath:$ocPath)", \OCP\Util::ERROR);
			return $GLOBALS ['cernbox'] ['getmeta'] [$ocPath];
		}
		//\OCP\Util::writeLog ( 'EOSCACHE', "MIS op:" . __FUNCTION__ . "(ocpath:$ocPath)", \OCP\Util::ERROR );
		return FALSE;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setMeta()
	 */
	public function setMeta($ocPath, $data) {
		$this->init ();
		$GLOBALS ['cernbox'] ['getmeta'] [$ocPath] = $data;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getFileByEosPath()
	 */
	public function getFileByEosPath($eosPath) {
		$this->init ();
		if (isset ( $GLOBALS ['cernbox'] ['getfilebyeospath'] [$eosPath] )) {
			//\OCP\Util::writeLog ( 'EOSCACHE', "HIT op:" . __FUNCTION__ . "(eospath:$eosPath)", \OCP\Util::ERROR );
			return $GLOBALS ['cernbox'] ['getfilebyeospath'] [$eosPath];
		}
		//\OCP\Util::writeLog ( 'EOSCACHE', "MIS op:" . __FUNCTION__ . "(eospath:$eosPath)", \OCP\Util::ERROR );
		return FALSE;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setFileByEosPath()
	 */
	public function setFileByEosPath($eosPath, $data) {
		$this->init ();
		$GLOBALS ['cernbox'] ['getfilebyeospath'] [$eosPath] = $data;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\IEosCache::clearFileByEosPath()
	 */
	public function clearFileByEosPath($eosPath) {
		$this->init();
		unset($GLOBALS['cernbox']['getfilebyeospath'][$eosPath]);
	}
	
	/** 
	 * {@inheritDoc}
	 * @see IEosCache::getOwner()
	 */
	public function getOwner($eosPath) {
		$this->init ();
		if (isset ( $GLOBALS ['cernbox'] ['getowner'] [$eosPath] )) {
			//\OCP\Util::writeLog ( 'EOSCACHE', "HIT op:" . __FUNCTION__ . "(eospath:$eosPath)", \OCP\Util::ERROR );
			return $GLOBALS ['cernbox'] ['getowner'] [$eosPath];
		}
		//\OCP\Util::writeLog ( 'EOSCACHE', "MIS op:" . __FUNCTION__ . "(eospath:$eosPath)", \OCP\Util::ERROR );
		return FALSE;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setOwner()
	 */
	public function setOwner($eosPath, $data) {
		$this->init ();
		$GLOBALS ['cernbox'] ['getowner'] [$eosPath] = $data;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getEGroups()
	 */
	public function getEGroups($username) {
		$this->init ();
		if (isset ( $GLOBALS ['cernbox'] ['getegroups'] [$username] )) {
			//\OCP\Util::writeLog ( 'EOSCACHE', "HIT op:" . __FUNCTION__ . "(username:$username)", \OCP\Util::ERROR );
			return $GLOBALS ['cernbox'] ['getegroups'] [$username];
		}
		//\OCP\Util::writeLog ( 'EOSCACHE', "MIS op:" . __FUNCTION__ . "(username:$username)", \OCP\Util::ERROR );
		return FALSE;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setEGroups()
	 */
	public function setEGroups($username, $data) {
		$this->init ();
		$GLOBALS ['cernbox'] ['getegroups'] [$username] = $data;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\IEosCache::setFileInfoByEosPath()
	 */
	public function setFileInfoByEosPath($depth, $eosPath, $data)
	{
		$this->init();
		$key = $depth . '-' . $eosPath;
		$GLOBALS['cernbox']['getFileInfoByEosPath'][$key] = $data;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\IEosCache::getFileInfoByEosPath()
	 */
	public function getFileInfoByEosPath($depth, $eosPath)
	{
		$this->init();
		$key = $depth . '-' . $eosPath;
		if(isset($GLOBALS['cernbox']['getFileInfoByEosPath'][$key]))
		{
			return $GLOBALS['cernbox']['getFileInfoByEosPath'][$key];
		}
		return FALSE;
	}
}