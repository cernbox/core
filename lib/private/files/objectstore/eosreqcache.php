<?php

namespace OC\Files\ObjectStore;


class EosReqCache {
	
	public static function init() {
		if (!isset( $GLOBALS['cernbox'])) {
			$GLOBLAS['cernbox'] = array();
			$GLOBALS['cernbox']['idresolution'] = array();
			$GLOBALS['cernbox']['getfilebyid'] = array();
			$GLOBALS['cernbox']['getmeta'] = array();
			$GLOBALS['cernbox']['fileinfo'] = array();
			$GLOBALS['cernbox']['getfilebyeospath'] = array();
			$GLOBALS['cernbox']['getowner'] = array();
		}
	}
	
	public static function getUidAndGid($username) {
		self::init();
		if(isset($GLOBALS['cernbox']['idresolution'][$username])) {
			\OCP\Util::writeLog('EOSCACHE',"HIT op:" .  __FUNCTION__ . "(username:$username)", \OCP\Util::ERROR);
			return $GLOBALS['cernbox']['idresolution'][$username];	
		}
		\OCP\Util::writeLog('EOSCACHE',"MIS op:" .  __FUNCTION__ . "(username:$username)", \OCP\Util::ERROR);
		return null;
	}
	public static function setUidAndGid($username, $data) {
		self::init();
		$GLOBALS['cernbox']['idresolution'][$username] = $data;	
	}
	
	public static function getFileById($id) {
		self::init();
                if(isset($GLOBALS['cernbox']['getfilebyid'][$id])) {
			\OCP\Util::writeLog('EOSCACHE',"HIT op:" .  __FUNCTION__ . "(id:$id)", \OCP\Util::ERROR);
                        return $GLOBALS['cernbox']['getfilebyid'][$getfilebyid];
                }
		\OCP\Util::writeLog('EOSCACHE',"MIS op:" .  __FUNCTION__ . "(id:$id)", \OCP\Util::ERROR);
                return null;
	}
	public static function setFileById($id, $data) {
		 self::init();
                 $GLOBALS['cernbox']['idresolution'][$id] = $data;

	}
	public static function getMeta($ocPath) {
		self::init();
                if(isset($GLOBALS['cernbox']['getmeta'][$ocPath])) {
			\OCP\Util::writeLog('EOSCACHE',"HIT op:" .  __FUNCTION__ . "(ocpath:$ocPath)", \OCP\Util::ERROR);
                        return $GLOBALS['cernbox']['getmeta'][$ocPath];
                }
		\OCP\Util::writeLog('EOSCACHE',"MIS op:" .  __FUNCTION__ . "(ocpath:$ocPath)", \OCP\Util::ERROR);
                return null;
	}
	public static function setMeta($ocPath, $data) {
		 self::init();
                 $GLOBALS['cernbox']['getmeta'][$ocPath] = $data;
	}
	
	public static function getFileByEosPath($eosPath) {
		self::init();
                if(isset($GLOBALS['cernbox']['getfilebyeospath'][$eosPath])) {
			\OCP\Util::writeLog('EOSCACHE',"HIT op:" .  __FUNCTION__ . "(eospath:$eosPath)", \OCP\Util::ERROR);
                        return $GLOBALS['cernbox']['getfilebyeospath'][$eosPath];
                }
		\OCP\Util::writeLog('EOSCACHE',"MIS op:" .  __FUNCTION__ . "(eospath:$eosPath)", \OCP\Util::ERROR);
                return null;
	}
	public static function setFileByEosPath($eosPath, $data) {
		self::init();
                $GLOBALS['cernbox']['getfilebyeospath'][$eosPath] = $data;
	}

	public static function getOwner($eosPath) {
                self::init();
                if(isset($GLOBALS['cernbox']['getowner'][$eosPath])) {
			\OCP\Util::writeLog('EOSCACHE',"HIT op:" .  __FUNCTION__ . "(eospath:$eosPath)", \OCP\Util::ERROR);
                        return $GLOBALS['cernbox']['getowner'][$eosPath];
                }
                return null;
        }
        public static function setOwner($eosPath, $data) {
                self::init();
                $GLOBALS['cernbox']['getowner'][$eosPath] = $data;
        }



}
