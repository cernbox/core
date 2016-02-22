<?php

namespace OC\Files\ObjectStore;

use OCP\Files\ObjectStore\IObjectStore;

class Eos implements IObjectStore {
	/**
	 * @var $params
	 * Save info about eos_mgm_url and eos_prefix
	 */
	private $params;

	public function __construct($params) {
		$this->params = $params;
	}

	/**
	 * @return string the EOS MGM URL where files are saved
	 */
	public function getStorageId() {
		$eos_prefix = EosUtil::getEosPrefix();
		return $eos_prefix;
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @param resource $stream stream with the data to write
	 * @throws Exception from openstack lib when something goes wrong
	 */
	public function writeObject($urn, $stream) {
		$staging_dir = EosUtil::getBoxStagingDir();
		$eos_mgm_url = EosUtil::getEosMgmUrl();
		$tempname = tempnam($staging_dir, "eoswrite");
		$temp = fopen($tempname, "w");
		$data = '';
		while (!feof($stream)) {
		  $data = fread($stream, 8192);
		  fwrite($temp, $data);
		}
		fclose($stream);
		fclose($temp);
		$path = $tempname;
		
		// Try to write as the logged user, normally this should be succesful most of the time but in case like public upload it will fail
		$tryWithOwnerPath = false; // when true indicates tht the write as logged user has failed and we need to try with the owner path
		$username = \OCP\User::getUser();
		if(!$username){
			$tryWithOwnerPath = true;
		} else {
			$uidAndGid = EosUtil::getUidAndGid($username);
			if(!$uidAndGid){
				$tryWithOwnerPath = true;
			} else {
				list($uid,$gid) = $uidAndGid;
				$dst = escapeshellarg($eos_mgm_url ."//" . $urn);
		        $cmd = "xrdcopy -f $path $dst -ODeos.ruid=$uid\&eos.rgid=$gid";
				list($result, $errcode) =  EosCmd::exec($cmd);
				if($errcode === 0){
					unlink($tempname); // remove the temp file
					return true;
				} else {
					$tryWithOwnerPath = true;
				}
			}
		}
		
		if($tryWithOwnerPath){
			// If we are here the previous write failed so we need to write as the "path owner"
			list($uid, $gid) = EosUtil::getEosRole($urn, false);
			$dst = escapeshellarg($eos_mgm_url . "//" . $urn);
	        $cmd = "xrdcopy -f $path $dst -ODeos.ruid=$uid\&eos.rgid=$gid";
			list($result, $errcode) = EosCmd::exec($cmd);
			@unlink($tempname); // remove the temp file
			if($errcode === 0){
				return true;
			}
			return false;
		} else {
			return false;
		}

	}

	/**
	 * Copy a file from EOS to the stage directory with the following filename
   	 * eosread:<inode>:<mtime>
	 * Before copying the file from EOS we check if the file is in the staging area and the mtimes are the same else we start the copy
	 * @param string $urn the unified resource name used to identify the object
	 * @return resource stream with the read data
	 * @throws Exception from openstack lib when something goes wrong
	 */
	public function readObject($urn) {
		$eos_mgm_url = EosUtil::getEosMgmUrl();
		$staging_dir = EosUtil::getBoxStagingDir();
		list($uid, $gid) = EosUtil::getEosRole($urn, true);
		
		$meta = EosUtil::getFileByEosPath($urn);
		if(!$meta) {
			return false;
		}
		$dst = $staging_dir . "/eosread:" . $meta['fileid'] . ":" .  $meta['mtime'];
		if(file_exists($dst)) {
			\OCP\Util::writeLog("EOSSTAGE", sprintf("serving file from stage area. inode:%s mtime:%s eospath:%s", $meta['fileid'], $meta['mtime'], $urn), \OCP\Util::ERROR);
			return fopen($dst, "r");
		}
		
		$src = escapeshellarg($eos_mgm_url . "//" . $urn );
		$cmd = "xrdcopy -f $src $dst -OSeos.ruid=$uid\&eos.rgid=$gid";
		list($result, $errcode) = EosCmd::exec($cmd);
		if($errcode !== 0){
			return false;
		}
        	return fopen($dst, "r");
	}


	/**
	 * @param string $urn Unified Resource Name
	 * @return void
	 * @throws Exception from openstack lib when something goes wrong
	 */
	public function deleteObject($urn) {
		$urnEscaped = escapeshellarg($urn);
		EosCacheManager::clearFileByEosPath($urnEscaped);
		list($uid, $gid) = EosUtil::getEosRole($urn, false);
		$cmd             = "eos -b -r $uid $gid rm -r $urnEscaped";
		list($result, $errcode) = EosCmd::exec($cmd);
		if($errcode !== 0) {
			return false;
		}
		return true;
	}
	public function mkdir($urn) {
		$urnEscaped = escapeshellarg($urn);
		$eos_metadir = EosUtil::getEosMetaDir();
		list($uid, $gid) = EosUtil::getEosRole($urn, false);
		// the logic to create the metafolder for the user should be in the login FIXME
		if(strpos($urn, $eos_metadir) === 0) { // to create files in meta folder we use the -p option to not throw exception each time we list files
			$cmd = "eos -b -r 0 0 mkdir -p $urnEscaped";
			EosCmd::exec($cmd);
			$owner = EosUtil::getOwner($urn);
			$path = $eos_metadir . substr($owner, 0, 1) . "/" . $owner;
			$pathEscaped = escapeshellarg($path);
			$cmd2 = "eos -b -r 0 0 chown -r $uid:$gid $pathEscaped";
			list($result, $errcode) = EosCmd::exec($cmd2);
			if($errcode !== 0){
				return false;
			}
			return true;

		} else {
			$cmd = "eos -b -r $uid $gid mkdir $urnEscaped";
			list($result, $errcode) = EosCmd::exec($cmd);
			if($errcode !== 0){
				return false;
			}
			return true;
		}
	}
	public function rename($from, $to) {
		$fromEscaped = escapeshellarg($from);
		$toEscaped = escapeshellarg($to);
		list($uid, $gid) = EosUtil::getEosRole($from, false);
		$cmd             = "eos -b -r $uid $gid file rename $fromEscaped $toEscaped";
		list($result, $errcode) = EosCmd::exec($cmd);
		if($errcode !== 0){
			return false;
		}
		return true;
	}
}
