<?php
namespace OC\Files\ObjectStore;

class EosProxy {

	// if the storageId is a user storage we return the username else false (object::store:$eos_prefix)
	// when we are creating files (the storage id is still not defined) we receive the username of logged user
	public static function getUsernameFromStorageId($storageId) {
		$eos_prefix     = EosUtil::getEosPrefix();
		$splitted       = explode(":", $storageId);
		$differenciator = isset($splitted[2]) ? $splitted[2] : "";
		if ($differenciator === "store") {
			return false;
		} else if ($differenciator === "user") {
			$username = $splitted[3];
			return $username;
		} else {// when we are creating files (the storage id is still not defined) we receive the username of logged user
			$username = $storageId;
			return $username;
		}

	}
	// ocPath is local oc like "" or "files" or "files/A/B/test_file./txt"
	// storageId is object::store:/eos/dev/user/ or object::user:labrador
	public static function toEos($ocPath, $storageId) {//ocPath like files/abc.txt or cache/557 or files or ""
		$eos_prefix   = EosUtil::getEosPrefix();
		$eos_meta_dir = EosUtil::getEosMetaDir();
		$username     = self::getUsernameFromStorageId($storageId);
		if ($username === false) {
			return false;
			///$eosPath = $eos_prefix;
			//return $eosPath;
		}
		if ($ocPath === "") {
			$eosPath = $eos_prefix . substr($username, 0, 1) . "/" . $username . "/";
			return $eosPath;
		}
		//we must be cautious becasue there is files_encryption, that is the reason we do this check
		$condition = false;
		$posFiles  = strpos($ocPath, "files");
		$lenOcPath = strlen($ocPath);
		if (strpos($ocPath, "files") === 0) {
			if ($lenOcPath === 5) {
				$condition = true;
			} else if ($lenOcPath > 5 && $ocPath[5] === "/") {
				$condition = true;
			}
		}
		if ($condition) {
			$splitted = explode("/", $ocPath);// [files, hola.txt] or [files]
			$last     = "";
			if (count($splitted) >= 2) {
				$last = implode("/", array_slice($splitted, 1));
			}
			$eosPath = $eos_prefix . substr($username, 0, 1) . "/" . $username . "/" . $last;
			return $eosPath;
		} else {
			$eosPath = $eos_meta_dir . substr($username, 0, 1) . "/" . $username . "/" . $ocPath;
			return $eosPath;
		}

	}

	// EOS give us the path ended with a / so perfect
	public static function toOc($eosPath) {//eosPath like /eos/dev/user/ or /eos/dev/user/l/labrador/abc.txt or /eos/dev/user/.metacernbox/l/labrador/cache/abc.txt or /eos/dev/user/.metacernbox/thumbnails/abc.txt
		$eos_prefix   = EosUtil::getEosPrefix();
		$eos_meta_dir = EosUtil::getEosMetaDir();
		$eos_recycle_dir = EosUtil::getEosRecycleDir();
		if ($eosPath == $eos_prefix) {
			return "";
		}
		if (strpos($eosPath, $eos_meta_dir) === 0) {
			$len_prefix = strlen($eos_prefix);
			$rel        = substr($eosPath, $len_prefix);
			// splitted should be something like [.metacernbox, l, labrador, cache, 123]
			$splitted = explode("/", $rel);
			$ocPath   = implode("/", array_slice($splitted, 3));
			return $ocPath;
		} else if (strpos($eosPath, $eos_prefix) === 0) {
			$len_prefix = strlen($eos_prefix);
			$rel        = substr($eosPath, $len_prefix);
			$splitted   = explode("/", $rel);
			$letter     = $splitted[0];
			$username   = $splitted[1];
			$lastPart   = "";
			if (count($splitted) > 2 && $splitted[2] !== "") {
				$lastPart = implode("/", array_slice($splitted, 2));
				$ocPath   = "files/" . $lastPart;
			} else {
				$ocPath = "files";
			}
			// we strip posible end slashes
			$ocPath = rtrim($ocPath, "/");
			return $ocPath;
		} else if(strpos($eosPath, $eos_recycle_dir) === 0){
			return false;
		} else {
			\OCP\Util::writeLog("eos", "The eos_prefix,eos_meta_dir,eos_recycle_dir does not match this path: $eosPath", \OCP\Util::ERROR);
			return false;
		}
	}
}
