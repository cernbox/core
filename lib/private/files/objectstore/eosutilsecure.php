<?php

namespace OC\Files\ObjectStore;

class EosUtilSecure
{
	public static function getFileByEosPath($eospath)
	{
		$user = \OC::$server->getUserSession()->getUser()->getUID();
		$result = EosUtil::getUidAndGid($user);
		
		if($result == false)
		{
			\OCP\Util::writeLog('Files_ProjectSpaces', 'Helper.getFileByEosPath(): Cannot determine uid and gid for ' .$user, \OCP\Util::ERROR); 
			return false;
		}
		
		list($uid, $gid) = $result;
		
		$eospath = rtrim($eospath, "/");
	
		$cached = EosCacheManager::getFileByEosPath($eospath);
		if($cached) {
			return $cached;
		}
	
		$eospathEscaped = escapeshellarg($eospath);
	
		//EosUtil::putEnv();
		$fileinfo = "eos -b -r $uid $gid file info $eospathEscaped -m";
		$files    = array();
		list($result, $errcode) = EosCmd::exec($fileinfo);
		if ($errcode === 0 && $result) {
			$line_to_parse = $result[0];
			$data          = EosParser::parseFileInfoMonitorMode($line_to_parse);
			$data['permissions'] = 31;
			EosCacheManager::setFileByEosPath($eospath, $data);
	
			return $data;
		}
		return null;
	}
	
	public static function getFolderContents($eosPath)
	{
		$eos_hide_regex = EosUtil::getEosHideRegex();
		$eos_version_regex = EosUtil::getEosVersionRegex();
		
		$eosPathEscaped = escapeshellarg($eosPath);
		
		$cached = EosCacheManager::getFileInfoByEosPath(1, $eosPathEscaped);
		if($cached)
		{
			return $cached;
		}
		
		// TODO RE-ENABLED USER BASED COMMAND WHEN FIND FILEINFO COMMAND IS FIXED ON EOS
		
		/*$result = EosUtil::getUidAndGid($user = \OC::$server->getUserSession()->getUser()->getUID());
		if($result == false)
		{
			\OCP\Util::writeLog('Files_ProjectSpaces', 'Helper.getFolderContents(): Cannot determine uid and gid for ' .$user, \OCP\Util::ERROR);
			return false;	
		}*/
		
		list($uid, $gid) = [0,0];//$result;
		
		$getFolderContents = "eos -b -r $uid $gid find --fileinfo --maxdepth 1 $eosPathEscaped";
		
		//EosUtil::putEnv();
		$files             = array();
		list($result, $errcode) = EosCmd::exec($getFolderContents);
		if ($errcode !== 0) {
			return $files;
		}
		
		/*
		 * This array is used to pass extra attributes to a file/folder
		 * The keys are eos paths and the values are arrays of key-value pairs (attr/value)
		 * Example: ["/eos/scratch/user/o/ourense/photos/1.png" => ["cboxid" => 456123]]
		 */
		$extraAttrs = array();
		
		$hiddenFolder = preg_match("|".$eos_hide_regex."|", $eosPath);
		
		foreach ($result as $line_to_parse) {
			$data            = EosParser::parseFileInfoMonitorMode($line_to_parse);
			if( $data["path"] !== false && rtrim($data["eospath"],"/") !== rtrim($eosPath,"/") ){
		
				$data["storage"] = 'object::user:' . \OC::$server->getUserSession()->getUser()->getUID();
				$data["permissions"] = 31;
		
				// HUGO  we need to be careful of not showing .sys.v#. folders when the folder asked to show the contents is a non sys folder.
				if (!$hiddenFolder && preg_match("|".$eos_hide_regex."|", $data["eospath"]) ) { // the folder asked to list is not a sys folder, i.e does not have the hide_regex.
					/* If we found a versions folder we add its inode to the original file under cboxid attribute */
					if (preg_match("|".$eos_version_regex."|", $data["eospath"]) ) {
						$dirname = dirname($data['eospath']);
						$basename = basename($data['eospath']);
						$filename = substr($basename, 8);
						$filepath = $dirname . "/" . $filename;
						$extraAttrs[$filepath]["cboxid"] = $data['fileid'];
					}
				} else { // the folder asked to list its contents is a sys folder, so we list the contents. This behaviour is not used directly by a user but it is used by versions and trashbin apps.
					$files[$data['eospath']] = $data;
				}
		
				//$itemEosPath = escapeshellarg(rtrim($data['eospath'], '/'));
		
				EosCacheManager::setFileByEosPath($data['eospath'], $data);
				EosCacheManager::setFileById($data['fileid'], $data);
			}
		}
		
		/* Add extra attributes */
		foreach($extraAttrs as $eospath => $attrs) {
			if(isset($files[$eospath]))
			{
				$file = $files[$eospath];
				foreach($attrs as $attr => $value) {
					$file[$attr] = $value;
				}
				$files[$eospath] = $file;
			}
		}
		
		$result = array_values($files);
		EosCacheManager::setFileInfoByEosPath(1, $eosPathEscaped, $result);
		
		return $result;
	}
	
	public static function unserializeACL($acl)
	{
		if(empty($acl))
		{
			return true;
		}
	
		$temp = explode(',', $acl);
		$egroups = [];
		$users = [];
		foreach($temp as $aclToken)
		{
			$tempAcl = explode(':', $aclToken);
			if(count($tempAcl) < 3)
			{
				continue;
			}
				
			if($tempAcl[0] === 'egroup')
			{
				$egroups[$tempAcl[1]] = EosUtil::toOcPerm($tempAcl[2]);
			}
			else
			{
				$users[$tempAcl[1]] = EosUtil::toOcPerm($tempAcl[2]);
			}
		}
	
		return [$users, $egroups];
	}
	
	public static function hasPermissions($acl, $permission)
	{
		$user = \OC::$server->getUserSession()->getUser()->getUID();
	
		$result = self::unserializeACL($acl);
	
		if($result === true)
		{
			return true;
		}
	
		list($users, $egroups) = $result;
		if(isset($users[$user]))
		{
			return ($users[$user] & $permission == $permission);
		}
		else
		{
			$userGroups = \OC\LDAPCache\LDAPCacheManager::getUserEGroups($user);
			foreach($egroups as $group => $perm)
			{
				if(array_search($group, $userGroups) !== FALSE && ($perm & $permission == $permission))
				{
					return true;
				}
			}
		}
	
		return false;
	}
	
	public static function hasReadPermissions($acl)
	{
		return self::hasPermissions($acl, \OCP\Constants::PERMISSION_READ);
	}
	
	public static function hasWritePermissions($acl)
	{
		return self::hasPermissions($acl, \OCP\Constants::PERMISSION_CREATE
				& \OCP\Constants::PERMISSION_UPDATE
				& \OCP\Constants::PERMISSION_DELETE);
	}
}