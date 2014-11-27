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
		// TO OBTAIN EOS ROLE SEE THE DOC oc-user-map-to-eos-user
		/*
		$staging_dir = EosUtil::getBoxStagingDir();
		list($uid, $gid) = EosUtil::getEosRole($urn, false);
		$data            = stream_get_contents($stream);
		$tempname        = tempnam($staging_dir, "eoswrite");
		$temp = fopen($tempname, "w");
		fwrite($temp, $data);
		$path = $tempname;
		$cmd       = "eos -b -r $uid $gid cp \"$path\" \"$urn\"";
		$result    = null;
		$errcode   = null;
		exec($cmd, $result, $errcode);
		if($errcode !== 0){
			return false;
		}
		fclose($temp);
		unlink($tempname);
		if($errcode !== 0){
			\OCP\Util::writeLog('eos', "eoswrite $cmd $errcode", \OCP\Util::ERROR);
			return false;
		}
		return true;
		*/
		
		$staging_dir = EosUtil::getBoxStagingDir();
		$tempname        = tempnam($staging_dir, "eoswrite");
		$temp = fopen($tempname, "w");
		$data = '';
		while (!feof($stream)) {
		  $data = fread($stream, 8192);
		  fwrite($temp, $data);
		}
		fclose($stream);
		fclose($temp);
		$path = $tempname;

		// Try to write as the logged user
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
				$cmd       = "eos -b -r $uid $gid cp \"$path\" \"$urn\"";
				$result    = null;
				$errcode   = null;
				exec($cmd, $result, $errcode);
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
			$cmd       = "eos -b -r $uid $gid cp \"$path\" \"$urn\"";
			$result    = null;
			$errcode   = null;
			exec($cmd, $result, $errcode);
			fclose($temp);// this removes the tmp file but nomore
			unlink($tempname); // remove the temp file
			if($errcode === 0){
				return true;
			}
			\OCP\Util::writeLog('eos', "eoswrite writing as path owner $cmd $errcode", \OCP\Util::ERROR);
			return false;
		} else {
			return false;
		}

	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return resource stream with the read data
	 * @throws Exception from openstack lib when something goes wrong
	 */
	public function readObject($urn) {
		$eos_mgm_url = EosUtil::getEosMgmUrl();
		$staging_dir = EosUtil::getBoxStagingDir();
		list($uid, $gid) = EosUtil::getEosRole($urn, true);
		$dst             = $staging_dir . "/" . uniqid("eosread");
		//$cmd             = "eos -b -r  $uid $gid cp \"$urn\" \"$dst\"";
		//HUGO XRDCOPY If tou are reading you put -S if not you put -D
		$cmd             = 'xrdcopy -f "'.$eos_mgm_url .'//'.$urn.'" -OSeos.ruid='.$uid.'\&eos.rgid='.$gid.' '.$dst;
		$result          = null;
		$errcode         = null;
		exec($cmd, $result, $errcode);
		if($errcode !== 0){
			\OCP\Util::writeLog('eos', "eosread $cmd $errcode", \OCP\Util::ERROR);
			return false;
		}
		//$data   = file_get_contents($dst);
		return fopen($dst, "r");
		
		/*$stream = fopen('php://memory', 'r+');
		fwrite($stream, $data);
		rewind($stream);
		unlink($dst);
		return $stream;*/
	}

	/**
	 * @param string $urn Unified Resource Name
	 * @return void
	 * @throws Exception from openstack lib when something goes wrong
	 */
	public function deleteObject($urn) {
		list($uid, $gid) = EosUtil::getEosRole($urn, false);
		$cmd             = "eos -b -r $uid $gid rm -r \"$urn\"";
		$result          = null;
		$errcode         = null;
		exec($cmd, $result, $errcode);
		if($errcode !== 0){
			//throw new \Exception("Error Deleting from EOS $cmd", 1);		
			\OCP\Util::writeLog('eos', "eosdelete $cmd $errcode", \OCP\Util::ERROR);
			return false;
		}
		return true;
	}
	public function mkdir($urn) {
		$eos_metadir = EosUtil::getEosMetaDir();
		list($uid, $gid) = EosUtil::getEosRole($urn, false);
		if(strpos($urn, $eos_metadir) === 0) { // to create files in meta folder we use the -p option to not throw exception each time we list files
			$cmd = "eos -b -r 0 0 mkdir -p \"$urn\"";
			$result          = null;
			$errcode         = null;
			exec($cmd, $result, $errcode);
			if($errcode !== 0){
				//throw new \Exception("Error Mkdir from EOS $cmd", 1);		
				\OCP\Util::writeLog('eos', "eosmkdir $cmd $errcode", \OCP\Util::ERROR);
			}
			$owner = EosUtil::getOwner($urn);
			$path = $eos_metadir . substr($owner, 0, 1) . "/" . $owner;
			$cmd2 = "eos -b -r 0 0 chown -r $uid:$gid $path";
			exec($cmd2, $result, $errcode);
			if($errcode !== 0){
				//throw new \Exception("Error Mkdir from EOS $cmd", 1);		
				\OCP\Util::writeLog('eos', "eoschown $cmd2 $errcode", \OCP\Util::ERROR);
				return false;
			}
			return true;

		} else {
			$cmd = "eos -b -r $uid $gid mkdir \"$urn\"";
			$result          = null;
			$errcode         = null;
			exec($cmd, $result, $errcode);
			if($errcode !== 0){
				//throw new \Exception("Error Mkdir from EOS $cmd", 1);		
				\OCP\Util::writeLog('eos', "eosmkdir $cmd $errcode", \OCP\Util::ERROR);
				return false;
			}
			return true;
		}
	}
	public function rename($from, $to) {
		list($uid, $gid) = EosUtil::getEosRole($from, false);
		$cmd             = "eos -b -r $uid $gid file rename \"$from\" \"$to\"";
		$result          = null;
		$errcode         = null;
		exec($cmd, $result, $errcode);
		if($errcode !== 0){
			//throw new \Exception("Error Renaming from EOS $cmd", 1);		
			\OCP\Util::writeLog('eos', "eosrename $cmd $errcode", \OCP\Util::ERROR);
			return false;
		}
		return true;
	}
}
