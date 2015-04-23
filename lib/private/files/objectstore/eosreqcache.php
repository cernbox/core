<?php

namespace OC\Files\ObjectStore;


class EosReqCache {
	
	public static function init() {
		if (!isset( $GLOBALS['cernbox'])) {
			$GLOBLAS['cernbox'] = array();
			$GLOBALS['cernbox']['idresolution'] = array();
			$GLOBALS['cernbox']['fileinfo'] = array();
			$GLOBALS['cernbox']['pathowner'] = array();
		}
	}
	
	public static function getUidAndGid($username) {
		self::init();
		\OCP\Util::writeLog('CACHE:getuid',"username: $username",3);
		if(isset($GLOBALS['cernbox']['idresolution'][$username])) {
			return $GLOBALS['cernbox']['idresolution'][$username];	
		}
		return null;
	}
	public static function setUidAndGid($username, $data) {
		self::init();
		\OCP\Util::writeLog('CACHE:setuid',"username: $username",3);
		if(!isset($GLOBALS['cernbox']['idresolution'][$username])) {
			$GLOBALS['cernbox']['idresolution'][$username] = $data;	
		}
	}
	
	public static function getFileById($id) {
		self::init();
                \OCP\Util::writeLog('CACHE:getfilebyid',"id: $id",3);
                if(isset($GLOBALS['cernbox']['getfilebyid'][$id])) {
                        return $GLOBALS['cernbox']['getfilebyid'][$getfilebyid];
                }
                return null;
	}
	public static function setFileById($id, $data) {
		 self::init();
                \OCP\Util::writeLog('CACHE:setfilebyid',"id: $id",3);
                if(!isset($GLOBALS['cernbox']['idresolution'][$id])) {
                        $GLOBALS['cernbox']['idresolution'][$id] = $data;
                }

	}
	public static function getMeta($ocPath) {
		self::init();
                \OCP\Util::writeLog('CACHE:getmeta',"ocpath: $ocPath",3);
                if(isset($GLOBALS['cernbox']['getmeta'][$ocPath])) {
                        return $GLOBALS['cernbox']['getmeta'][$ocPath];
                }
                return null;
	}
	public static function setMeta($ocPath, $data) {
		 self::init();
                \OCP\Util::writeLog('CACHE:setmeta',"ocpath: $ocPath",3);
                if(!isset($GLOBALS['cernbox']['setmeta'][$ocPath])) {
                        $GLOBALS['cernbox']['setmeta'][$ocPath] = $data;
                }

	}
	
	public static function getFileByEosPath($eosPath) {
		self::init();
                \OCP\Util::writeLog('CACHE:getfilebyeospath',"eospath: $eosPath",3);
                if(isset($GLOBALS['cernbox']['getfilebyeospath'][$eosPath])) {
                        return $GLOBALS['cernbox']['getfilebyeospath'][$eosPath];
                }
                return null;
	}
	public static function setFileByEosPath($eosPath, $data) {
		self::init();
                \OCP\Util::writeLog('CACHE:setfilebyeospath',"eospath: $eosPath",3);
                if(!isset($GLOBALS['cernbox']['setfilebyeospath'][$eosPath])) {
                        $GLOBALS['cernbox']['setfilebyeospath'][$eosPath] = $data;
                }

	}

	public static function getOwner($eosPath) {
                self::init();
                \OCP\Util::writeLog('CACHE:getowner',"eospath: $eosPath",3);
                if(isset($GLOBALS['cernbox']['getowner'][$eosPath])) {
                        return $GLOBALS['cernbox']['getowner'][$eosPath];
                }
                return null;
        }
        public static function setOwner($eosPath, $data) {
                self::init();
                \OCP\Util::writeLog('CACHE:setowner',"eospath: $eosPath",3);
                if(!isset($GLOBALS['cernbox']['setowner'][$eosPath])) {
                        $GLOBALS['cernbox']['setowner'][$eosPath] = $data;
                }

        }



}
