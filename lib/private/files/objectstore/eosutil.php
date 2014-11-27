<?php
namespace OC\Files\ObjectStore;

class EosUtil {

	public static function putEnv() { // VERIFIED
		$eos_mgm_url = \OCP\Config::getSystemValue("eos_mgm_url");
		if (!getenv("EOS_MGM_URL")) {
			putenv("EOS_MGM_URL=" . $eos_mgm_url);
		}
	}
	
	public static function getEosMgmUrl() { // VERIFIED
		$eos_mgm_url = \OCP\Config::getSystemValue("eos_mgm_url");
		return $eos_mgm_url;
	}

	public static function getEosPrefix() { // VERIFIED
		$eos_prefix = \OCP\Config::getSystemValue("eos_prefix");
		return $eos_prefix;
	}

	public static function getEosMetaDir() { // VERIFIED
		$eos_meta_dir = \OCP\Config::getSystemValue("eos_meta_dir");
		return $eos_meta_dir;
	}
	public static function getEosRecycleDir() { // VERIFIED
		$eos_recycle_dir = \OCP\Config::getSystemValue("eos_recycle_dir");
		return $eos_recycle_dir;
	}
	public static function getEosHideRegex() { // VERIFIED
		$eos_hide_regex = \OCP\Config::getSystemValue("eos_hide_regex");
		return $eos_hide_regex;
	}
	public static function getBoxStagingDir() { // VERIFIED
		$staging_dir = \OCP\Config::getSystemValue("box_staging_dir");
		return $staging_dir;
	}
	public static function getBoxStagingDirMaxSize() { // VERIFIED
		$staging_dir_max_size = \OCP\Config::getSystemValue("box_staging_dir_max_size");
		return $staging_dir_max_size;
	}
	public static function getBoxStagingDirCurrentSize() { // VERIFIED
		$staging_dir = self::getBoxStagingDir();
	    $io = popen ( '/usr/bin/du -sb ' . $staging_dir, 'r' );
	    $size = fgets ( $io, 4096);
	    $size = substr ( $size, 0, strpos ( $size, "\t" ) );
	    pclose ( $io );
	    $staging_dir_current_size = $size;
	    return $staging_dir_current_size;
	}

	// Retrive the owner of the path
	/*
	/eos/devbox/user/	------------------------------------ FALSE
	/eos/devbox/user/l/ ------------------------------------ FALSE
	/eos/devbox/user/l/labador/ ---------------------------- labrador
	/eos/devbox/user/l/labrador/some.txt ------------------- labrador
	/eos/devbox/user/.metacernbox/ ------------------------- FALSE
	/eos/devbox/user/.metacernbox/l/ ----------------------- FALSE
	/eos/devbox/user/.metacernbox/l/labador/ --------------- labrador
	/eos/devbox/user/.metacernbox/l/labrador/avatar.png ---- labrador
	*/
	public static function getOwner($eosPath){ // VERIFIED BUT WE ARE ASUMING THAT THE OWNER OF A FILE IS THE ONE INSIDE THE USER ROOT INSTEAD SEEING THE UID AND GID
		$eos_prefix = EosUtil::getEosPrefix();
		$eos_meta_dir = EosUtil::getEosMetaDir();
		if (strpos($eosPath, $eos_meta_dir) === 0) { // match eos meta dir like /eos/devbox/user/.metacernbox/...
			$len_prefix = strlen($eos_meta_dir);
			$rel        = substr($eosPath, $len_prefix);
			$splitted   = explode("/", $rel);
			if (count($splitted >= 2)){
				$user       = $splitted[1];
				return $user;
			} else {
				return false;
			}
		} else if (strpos($eosPath, $eos_prefix) === 0){ // match eos prefix like /eos/devbox/user/...
			$len_prefix = strlen($eos_prefix);
			$rel        = substr($eosPath, $len_prefix);
			$splitted = explode("/", $rel);
			if(count($splitted) >= 2){
				$user     = $splitted[1];
				return $user; 
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	public static function getOwnerNEW($eosPath){ // VERIFIED BUT WE ARE ASUMING THAT THE OWNER OF A FILE IS THE ONE INSIDE THE USER ROOT INSTEAD SEEING THE UID AND GID
		$eos_prefix = EosUtil::getEosPrefix();
		$eos_meta_dir = EosUtil::getEosMetaDir();
		if (strpos($eosPath, $eos_meta_dir) === 0) { // match eos meta dir like /eos/devbox/user/.metacernbox/...
			$len_prefix = strlen($eos_meta_dir);
			$rel        = substr($eosPath, $len_prefix);
			$splitted   = explode("/", $rel);
			if (count($splitted >= 2)){
				// eos stat
				$get     = "eos -b -r 0 0  file info \"" . $eosPath . "\" -m";
				\OCP\Util::writeLog('getowner', "$get", \OCP\Util::ERROR);

				$result  = null;
				$errcode = null;
				$info    = array();
				exec($get, $result, $errcode);
				if ($errcode !== 0) {
					return false;
				}
				$line_to_parse   = $result[0];
				$data            = EosParser::parseFileInfoMonitorMode($line_to_parse);
				$uid = $data["uid"];
				$getusername = "getent passwd $uid";
				\OCP\Util::writeLog('getusername', "$getusername", \OCP\Util::ERROR);

				$result  = null;
				$errcode = null;
				exec($getusername, $result, $errcode);
				if ($errcode !== 0) {
					return false;
				}
				$username = $result[0];
				$username = explode(":", $username);
				$username = $username[0];
				\OCP\Util::writeLog('username', "$username", \OCP\Util::ERROR);

				return $username;
			} else {
				return false;
			}
		} else if (strpos($eosPath, $eos_prefix) === 0){ // match eos prefix like /eos/devbox/user/...
			$len_prefix = strlen($eos_prefix);
			$rel        = substr($eosPath, $len_prefix);
			$splitted = explode("/", $rel);
			if(count($splitted) >= 2){
				// eos stat
				$get     = "eos -b -r 0 0  file info \"" . $eosPath . "\" -m";
				\OCP\Util::writeLog('getowner', "$get", \OCP\Util::ERROR);

				$result  = null;
				$errcode = null;
				$info    = array();
				exec($get, $result, $errcode);
				if ($errcode !== 0) {
					return false;
				}
				$line_to_parse   = $result[0];
				$data            = EosParser::parseFileInfoMonitorMode($line_to_parse);
				$uid = $data["eosuid"];
				$getusername = "getent passwd $uid";
				\OCP\Util::writeLog('getusername', "$getusername", \OCP\Util::ERROR);
				$result  = null;
				$errcode = null;
				exec($getusername, $result, $errcode);
				if ($errcode !== 0) {
					return false;
				}
				$username = $result[0];
				$username = explode(":", $username);
				$username = $username[0];
				\OCP\Util::writeLog('username', "$username", \OCP\Util::ERROR);

				return $username;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	// return the uid and gid of the user who should execute the eos command
	// we have three cases
	// 1) Try to obtain the username from the eospath
	// 2 ) Use the logged username
	// 3) Use the root user
	// the parameter $rootAllowd tell us if we can use the root rol in this function 
	// for example we could use it for read but not for write
	// if return false the user has not been found
	public static function getEosRole($eosPath, $rootAllowed){ 
		if(!$eosPath){
			return false;
		}
		// 1) get owner
		$owner = self::getOwner($eosPath);
		if($owner){
			$uidAndGid = self::getUidAndGid($owner);
			if (!$uidAndGid) {
				return false;
			}
			return $uidAndGid;
		} else {
			if($rootAllowed){
				return array(0,0);
			} else {
				$user  = \OCP\User::getUser();
				if($user){
					$uidAndGid = self::getUidAndGid($owner);
					if (!$uidAndGid) {
						return false;
					}
					return $uidAndGid;
				} else {
					return false;
				}
			}
		} 
	}

	// it return the id and gid of a normal user or false in other case, including the id is 0 (root) to avoid security leaks
	public static function getUidAndGid($username) { // VERIFIED
		self::putEnv();
		$cmd     = "id " . $username;
		$result  = null;
		$errcode = null;
		exec($cmd, $result, $errcode);
		$list = array();
		if ($errcode === 0) { // the user exists else it not exists
			$output   = var_export($result, true);
			$lines    = explode(" ", $result[0]);
			$line_uid = $lines[0];
			$line_gid = $lines[1];

			$split_uid = explode("=", $line_uid);
			$split_gid = explode("=", $line_gid);

			$end_uid = explode("(", $split_uid[1]);
			$end_gid = explode("(", $split_gid[1]);

			$uid = $end_uid[0];
			$gid = $end_gid[0];

			$list[] = $uid;
			$list[] = $gid;
		} else {
			return false;
		}
		if (count($list) != 2) {
			return false;
		}
		return $list;
	}


	
	/*
	return the storage id or false depending on the eospath received

	/eos/devbox/user/	------------------------------------ FALSE
	/eos/devbox/user/l/ ------------------------------------ FALSE
	/eos/devbox/user/l/labador/ ---------------------------- object::user:labrador
	/eos/devbox/user/l/labrador/some.txt ------------------- object::user:labrador
	/eos/devbox/user/.metacernbox/ ------------------------- FALSE
	/eos/devbox/user/.metacernbox/l/ ----------------------- FALSE
	/eos/devbox/user/.metacernbox/l/labador/ --------------- object::user:labrador
	/eos/devbox/user/.metacernbox/l/labrador/avatar.png ---- object::user:labrador
	*/	
	public static function getStorageId($eosPath) { // VERIFIED
		$eos_prefix   = self::getEosPrefix();
		$eos_meta_dir = self::getEosMetaDir();
		if (strpos($eosPath, $eos_meta_dir) === 0) { // match eos meta dir like /eos/devbox/user/.metacernbox/...
			$len_prefix = strlen($eos_meta_dir);
			$rel        = substr($eosPath, $len_prefix);
			$splitted   = explode("/", $rel);
			if (count($splitted >= 2)){
				$user       = $splitted[1];
				$storage_id = "object::user::" . $user;
				return $storage_id;
			} else {
				return false;
			}
		} else if (strpos($eosPath, $eos_prefix) === 0){ // match eos prefix like /eos/devbox/user/...
			$len_prefix = strlen($eos_prefix);
			$rel        = substr($eosPath, $len_prefix);
			$splitted = explode("/", $rel);
			if (count($splitted >= 2)){
				$user       = $splitted[1];
				$storage_id = "object::user::" . $user;
				return $storage_id;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}


	// $from the owner of the file
	// $to the recipient
	// $fileid the inode of the file to be shared
	// $ocPerm the permissions the file is going to be shared
	public static function addUserToAcl($from, $to, $fileId, $ocPerm) {
		$data = self::getFileById($fileId);
		if(!$data) {
			return false;
		}
		$ocacl = self::toOcAcl($data["sys.acl"], $data["sys.owner.auth"]);
		$ocacl[$to] = array("type"=>"u", "ocperm"=>$ocPerm);
		list($sysAcl, $sysOwner) = EosUtil::toEosAcl($ocacl);
		$eosPath = $data["eospath"];
		$username  = $from;
		if($username) {
			$uidAndGid = self::getUidAndGid($username);
			if (!$uidAndGid) {
				exit();
			}
			list($uid, $gid) = $uidAndGid;
		} else {
			$uid =0;$gid=0; // harcoded because we cannot acces the storageID
		}
		$addUserToSysAcl = "eos -b -r $uid $gid attr -r set sys.acl=$sysAcl \"$eosPath\"";
		$result   = null;
		$errcode  = null;
		exec($addUserToSysAcl, $result, $errcode);
		if ($errcode === 0 && $result) {
			//return true;
		} else {
			\OCP\Util::writeLog('eos', "share folder $addUserToSysAcl $errcode", \OCP\Util::ERROR);
		}
		$addUserToSysOwner = "eos -b -r $uid $gid attr -r set sys.owner.auth=$sysOwner \"$eosPath\"";
		$result   = null;
		$errcode  = null;
		exec($addUserToSysOwner, $result, $errcode);
		if ($errcode === 0 && $result) {
			//return true;
		} else {
			\OCP\Util::writeLog('eos', "share folder $addUserToSysOwner $errcode", \OCP\Util::ERROR);
		}
		return false;
	}

	public static function changePermAcl($from, $to, $fileid, $permissions){
		$data = self::getFileById($fileid);
		if(!$data) {
			return false;
		}
		$ocacl = self::toOcAcl($data["sys.acl"], $data["sys.owner.auth"]);
		$ocacl[$to] = array("type"=>"u", "ocperm"=>$permissions);
		list($sysAcl, $sysOwner) = EosUtil::toEosAcl($ocacl);
		$eosPath = $data["eospath"];
		
		$username  = $from;
		if($username) {
			$uidAndGid = self::getUidAndGid($username);
			if (!$uidAndGid) {
				exit();
			}
			list($uid, $gid) = $uidAndGid;
		} else {
			$uid =0;$gid=0; // harcoded because we cannot acces the storageID
		}
		$changeSysAcl = "eos -b -r $uid $gid attr -r set sys.acl=$sysAcl \"$eosPath\"";
		if(empty($sysAcl)) {
			$changeSysAcl = "eos -b -r $uid $gid attr -r rm sys.acl \"$eosPath\"";
		}
		$result   = null;
		$errcode  = null;
		exec($changeSysAcl, $result, $errcode);
		if ($errcode === 0 && $result) {
			//return true;
		}
		$changeSysOwner = "eos -b -r $uid $gid attr -r set sys.owner.auth=$sysOwner \"$eosPath\"";
		if(empty($sysOwner)) {
			$changeSysOwner = "eos -b -r $uid $gid attr -r rm sys.owner.auth \"$eosPath\"";
		}
		$result   = null;
		$errcode  = null;
		exec($changeSysOwner, $result, $errcode);
		if ($errcode === 0 && $result) {
			//return true;
		}
		return false;

	}

	// return the acl in Oc format => 15 or 1 or 31
	public static function getAclPerm($from , $to, $fileid){
		$data = self::getFileById($fileid);
		if(!$data) {
			return false;
		}
		$ocacl = self::toOcAcl($data["sys.acl"], $data["sys.owner.auth"]);
		if(isset($ocacl[$to])){
			return $ocacl[$to]["ocperm"];
		}
		return false;
	}
	
	// We receive the sys.acl (u:kuba:rwx) and the sys.owner.auth (krb5:kuba,unix:kuba)
	// if the user is in sys.owner.auth we return full oc permissions
	// if the user is ONLY in sys.acl we return only oc read permission
	// transform these 2 acls in a map
	// "kuba" => array(type=>u, ocperm=>15, eosperm=>rwx)
	public static function toOcAcl($sysAcl, $sysOwner){ // VERIFIED
		$ocAcl = array();
		
		$usersSysAcl = array();
		$usersSysOwner = array();
		
		$parts = explode(",", $sysAcl);
		if($parts) {
		    foreach($parts as $user) {
		        $data = explode(":",$user);
		        if(count($data) >= 3) {
		            $usersSysAcl[] = $data[1];
		        }
		    }
		}
		
		$parts = explode(",", $sysOwner);
		if($parts) {
		    foreach($parts as $user) {
		        $data = explode(":",$user);
		        if(count($data) >= 2) {
		            $usersSysOwner[] = $data[1];
		        }
		    }
		}
		
		$usersSysAcl = array_unique($usersSysAcl);
		$usersSysOwner = array_unique($usersSysOwner);
		
		
		foreach($usersSysAcl as $user) {
		    $ocAcl[$user] = array("type"=>"u", "ocperm"=>self::toOcPerm("rx"), "eosperm"=>"rx");
		} 
		foreach($usersSysOwner as $user) {
		    $ocAcl[$user] = array("type"=>"u", "ocperm"=>self::toOcPerm("rwx+d"), "eosperm"=>"rwx+d");
		} 
		return $ocAcl;
	}

	// transform the ocacl to eosacl 
	public static function toEosAcl($ocAcl){ // VERIFIED
		$sysAcl = array();
		$sysOwner = array();
		foreach($ocAcl as $username=>$data) {
		  if($data["ocperm"] > 1 ) { // indicates user has write privileges
		      $entry = "krb5:$username,https:$username,gsi:$username,unix:$username";
		      $sysOwner[] = $entry;
		      $type = $data["type"];
		      $eosperm = self::toEosPerm($data["ocperm"]);
		      $entry = "$type:$username:$eosperm"; // Be very careful with this
		      $sysAcl[]  = $entry;
		  } else if ($data["ocperm"] == 1) {
		      $type = $data["type"];
		      $eosperm = self::toEosPerm($data["ocperm"]);
		      $entry = "$type:$username:$eosperm"; // Be very careful with this
		      $sysAcl[] = $entry;
		  } else { // permissions are set to 0 => remove user from sys.acl and sys.owner.auth
		  	 //dont add
		  }
		}
		$sysAcl = implode(",", $sysAcl);
		$sysOwner = implode(",", $sysOwner);
		return array($sysAcl, $sysOwner);
	}
	// transform unix perm to number
	public static function toEosPerm($ocPerm) { // VERIFIED ASK KUBA/ANDREAS HOW TO MAP TO EOS PERM
		// ocPerm is a comibation of
		// const PERMISSION_CREATE = 4;
		// const PERMISSION_READ = 1;
		// const PERMISSION_UPDATE = 2;
		// const PERMISSION_DELETE = 8;
		// const PERMISSION_SHARE = 16;
		// const PERMISSION_ALL = 31;
		// for us create update and delete is the same eos permission W
		$eosPerm = null;
		if($ocPerm > 1) {
			$eosPerm = "rwx+d";
		} else if ($ocPerm == 1){
			$eosPerm = "rx";
		} 
		return $eosPerm;
	}
	// transform number to unix perm
	public static function toOcPerm($eosPerm){ // VERIFIED ASK KUBA/ANDREAS HOW TO MAP TO EOS PERMs
		$total = 0;
		if(strpos($eosPerm, "r") !== false) {
			$total += 1;
		}
		if(strpos($eosPerm, "w") !== false){
			$total += 14;
		}
		return $total;
	}

	// get the file/dir metadata by his eos fileid/inode
	// due to we dont have the path we use the root user to obtain the info
	public static function getFileById($id){ 
		$uid = 0; $gid = 0;
		self::putEnv();
		$fileinfo = "eos -b -r $uid $gid  file info  inode:" . $id . " -m";
		$result   = null;
		$errcode  = null;
		$files    = array();
		exec($fileinfo, $result, $errcode);
		if ($errcode === 0 && $result) {
			$line_to_parse = $result[0];
			$data          = EosParser::parseFileInfoMonitorMode($line_to_parse);
			return $data;
		}
		return null;
	}

	public static function getMimeType($path, $type){
		$pathinfo = pathinfo($path);
		if($type === "folder") {
			return "httpd/unix-directory";
		} else {
			$extension = isset($pathinfo['extension'])? $pathinfo['extension'] : "";
			if ($extension) {
				switch ($extension) {
					case "txt": 	return "text/plain";break;
					case "pdf":		return "application/pdf";break;
					case "jpg":		return "image/jpg";break;
					case "jpeg":	return "image/jpeg";break;
					case "png":		return "image/png";break;
					default: 		return "text/plain";break;
				}
			} else {
				return "application";
			}
		}
	}

	// This propagates permission X to parent when you share a folder. This is because of desktop sync clients need to browse the tree to find your shared directory.
	public static function propagatePermissionXToParents($eospath){
		
		$eosprefix = self::getEosPrefix(); // like /eos/devbox/user/
		$rest = substr($eospath, strlen($eosprefix));
		$parts = explode("/", $rest);
		$letter = $parts[0];
		$username = $parts[1];
		$rootFolderAfterUsername = isset($parts[2]) ? $parts[2] : ""; 
		$folderToApplyPermissions = $eosprefix .$letter ."/". $username. "/" . $rootFolderAfterUsername;
		list($uid, $gid) = self::getEosRole($eospath, false);
		$result   = null;
		$errcode  = null;
		$cmd = "eos -b -r $uid $gid chmod -r 2744 $folderToApplyPermissions";
		exec($cmd, $result, $errcode);
		if ($errcode !== 0) {
			\OCP\Util::writeLog('eos', "eoschmod $cmd $errcode", \OCP\Util::ERROR);
			return false;
		}
		return true;
	}
	public static function isEnoughSpaceInStagingForFileWithSize($fileSize){
		$max_size = self::getBoxStagingDirMaxSize();
		$current_size = self::getBoxStagingDirCurrentSize();
		$window = 0.8; // 80%
		if($fileSize + $current_size > $window * $max_size) {
			return false;
		}
		return true;
	}

}