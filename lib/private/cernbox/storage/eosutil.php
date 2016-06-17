<?php

namespace OC\Cernbox\Storage;

function startsWith($haystack, $needle) 
{
  // search backwards starting from haystack length characters from the end                                                                                                                                                                        
  return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

final class EosUtil 
{
	const REDIS_KEY_PROJECT_USER_MAP = 'project_spaces_mapping';
	const STAT_FILE_NOT_EXIST = 14;
	const STAT_FILE_EXIST = 0;

	private static $internalScript = false;
	
	/**
	 * Sets to true the internal script execution flag. This allows the system
	 * to use root when accessing EOS when needed
	 * @param bool $val True to enable root access to interal scripts, false to deny
	 */
	public static function setInternalScriptExecution($val)
	{
		self::$internalScript = $val;
	}
	
	/**
	 * Return the EOS MGM URL to be used in the next request to EOS.
	 * The default URL is configured in settings with the property eos_mgm_url.
	 * @return string the mgm URL to be used in next EOS call
	 */
	public static function getEosMgmUrl() 
	{
		
		$val = EosInstanceManager::getUserInstance();
		
		$eosInstance = '';
		
		if($val === NULL || !$val || intval($val) < 1)
		{
			$eosInstance = \OCP\Config::getSystemValue("eos_mgm_url");
		}
		else
		{
			$instanceData = EosInstanceManager::getMappingById($val);
			$eosInstance = $instanceData['mgm_url'];
		}
		
		return $eosInstance;
	}
	
	/**
	 * Returns the user's home directories root path ( /eos/user by default ).
	 * Might be configured with the setting eos_prefix
	 * @return User's home directories root path
	 */
	public static function getEosPrefix() 
	{ 
		return \OCP\Config::getSystemValue("eos_prefix");
	}

	/**
	 * Returns the project's home directories root path ( /eos/project by default ).
	 * Might be configured with the setting eos_projext_prefix
	 * @return string Project Spaces home directories root path
	 */
	public static function getEosProjectPrefix() 
	{ 
		return \OCP\Config::getSystemValue("eos_project_prefix");
	}
	
	/**
	 * Returns the user's metadata home directories root path.
	 * Might be configured with the setting eos_meta_dir
	 * @return string Metadata home directories root path
	 */
	public static function getEosMetaDir() 
	{ 
		return \OCP\Config::getSystemValue("eos_meta_dir");
	}
	
	/**
	 * Returns the user's recycle bins directories root path
	 * Might be configured with the setting eos_recycle_dir
	 * @return string Trashbins root directory path
	 */
	public static function getEosRecycleDir() 
	{
		return \OCP\Config::getSystemValue("eos_recycle_dir");
	}
	
	/**
	 * Return the regex expression to check for hide eos files.
	 * Might be configured with the setting eos_hide_regex
	 * @return string regex expression for hidden files on EOS
	 */
	public static function getEosHideRegex() 
	{ 
		return \OCP\Config::getSystemValue("eos_hide_regex");
	}
	
	/**
	 * Return the eos file's version folder common name part
	 * Might be configured with the setting eos_version_regex
	 * @return string eos version folder common name part
	 */
	public static function getEosVersionRegex() 
	{ 
		return \OCP\Config::getSystemValue("eos_version_regex");
	}
	
	/**
	 * Return the path to the folder within the webserver that should act as
	 * staging area for transfers between the webserver box and EOS
	 * Might be configued with the setting box_staging_dir
	 * @return string staging directory within webserver box for webserver <-> EOS transfers
	 */
	public static function getBoxStagingDir() 
	{ 
		return \OCP\Config::getSystemValue("box_staging_dir");
	}
	
	/**
	 * Return the users and groups shares root directory path
	 * Might be configured with the setting eos_share_prefix
	 * @return string eos path to share's root directory
	 */
	public static function getEosSharePrefix()
	{
		return \OCP\Config::getSystemValue('eos_share_prefix');
	}
	
	/** 
	 * Retrive the owner (username) of a given path
	 * /eos/devbox/user/	------------------------------------ FALSE
	 * /eos/devbox/user/l/ ------------------------------------ FALSE
	 * /eos/devbox/user/l/labrador/ --------------------------- labrador
	 * /eos/devbox/user/l/labrador/some.txt ------------------- labrador
	 * /eos/devbox/user/.metacernbox/ ------------------------- FALSE
	 * /eos/devbox/user/.metacernbox/l/ ----------------------- FALSE
	 * /eos/devbox/user/.metacernbox/l/labador/ --------------- labrador
	 * /eos/devbox/user/.metacernbox/l/labrador/avatar.png ---- labrador
	 * @param string $eosPath EOS Path from which we want to know the owner
	 * @return string the owner of the provided eos path, or FALSE if it could not
	 * 			be deducted.
	 */
	public static function getOwner($eosPath)
	{	
		if(EosInstanceManager::isInGlobalInstance())
		{
			return false;
		}
		
		$eos_project_prefix = self::getEosProjectPrefix();
		$cached = EosCacheManager::getOwner($eosPath);
		if($cached) 
		{
			return $cached;
		}
		
		$eos_prefix = EosUtil::getEosPrefix();
		$eos_meta_dir = EosUtil::getEosMetaDir();
		if (strpos($eosPath, $eos_meta_dir) === 0) 
		{
			$len_prefix = strlen($eos_meta_dir);
			$rel        = substr($eosPath, $len_prefix);
			$splitted   = explode("/", $rel);
			if (count($splitted >= 2)){
				$user       = $splitted[1];
				EosCacheManager::setOwner($eosPath, $user);
				return $user;
			} 
			else 
			{
				return false;
			}
		} 
		else if (strpos($eosPath, $eos_prefix) === 0)
		{
			$len_prefix = strlen($eos_prefix);
			$rel        = substr($eosPath, $len_prefix);
			$splitted = explode("/", $rel);
			if(count($splitted) >= 2){
				$user     = $splitted[1];
				EosCacheManager::setOwner($eosPath, $user);
				return $user; 
			} 
			else 
			{
				return false;
			}
		} 
		else if (strpos($eosPath, $eos_project_prefix) === 0)
		{
		    $rel = substr($eosPath,strlen($eos_project_prefix));
			$prjname = explode("/",$rel)[0];
			$user = self::getUserForProjectName($prjname);
			
			if (!$user)
			{ 
				return false; 
			} 
			
			EosCacheManager::setOwner($eosPath, $user);	
			
			return $user;
		} 
		else 
		{
			return false;
		}
	}

	/**
	 * Return the uid and gid of the user who should execute the eos command
	 * we have three cases
	 * 1) Try to obtain the username from the eospath
	 * 2 ) Use the logged username
	 * 3) Use the root user
	 * the parameter $rootAllowd tell us if we can use the root rol in this function 
	 * for example we could use it for read but not for write
	 * if return false the user has not been found
	 * @param string $eosPath The EOS path target of the command which will be executed
	 * @param bool $rootAllowed (disabled) Sets whether root user may be used to executed
	 * 				the command
	 * @return array|bool Upon success, it will return an array with the uid in the position 0
	 * 						and the gid in the position 1; will return false otherwise 
	 */
	public static function getEosRole($eosPath, $rootAllowed)
	{ 
		if(!$eosPath)
		{
			return false;
		}
		// 1) get owner
		$owner = self::getOwner($eosPath);
		if($owner)
		{
			$uidAndGid = self::getUidAndGid($owner);
			if (!$uidAndGid) 
			{
				return false;
			}
			return $uidAndGid;
		} 
		else 
		{
			$user = \OCP\User::getUser();
			if($user)
			{
				$uidAndGid = self::getUidAndGid($user);
				if (!$uidAndGid) 
				{
					return false;
				}
				return $uidAndGid;
			}
		} 
		
		return false;
	}
	
	/**
	 * Attempts to get the user owner of a share by link using 
	 * the token provided in the request. 
	 * @return boolean|string Upon success, it will return the username owner of
	 * 							this anonymous share; Will return false otherwise
	 */
	private static function isSharedLinkGuest()
	{
		$uri = $_SERVER['REQUEST_URI'];
		$uri = trim($uri, '/');
		
		$token = false;
		
		if(strpos($uri, '?') !== FALSE)
		{
			$paramsRaw = explode('&', explode('?', $uri)[1]);
			$paramMap = [];
			foreach($paramsRaw as $pRaw)
			{
				$parts = explode('=', $pRaw);
				if(count($parts) < 2)
				{
					continue;
				}
				
				$paramMap[$parts[0]] = $parts[1];
			}
			
			if(isset($paramMap['token']))
			{
				$token = $paramMap['token'];
			}
			else if(isset($paramMap['t']))
			{
				$token = $paramMap['t'];
			}
		}
		
		if(!$token && isset($_POST['dirToken']))
		{
			$token = $_POST['dirToken'];
		}
		
		if(!$token)
		{
			$split = explode('/', $uri);
		
			if(count($split) < 3)
			{
				return false;
			}
		
			$token = $split[2];
			if(($pos = strpos($token, '?')) !== FALSE)
			{
				$token = substr($token, 0, $pos);
			}
		}
		
		$result = \OC_DB::prepare('SELECT uid_owner FROM oc_share WHERE token = ? LIMIT 1')->execute([$token])->fetchAll();
		
		if($result && count($result) > 0)
		{
			return $result[0]['uid_owner'];
		}
		
		return false;
	}

	/**
	 * Performs an "id" command on the system to retrieve the given $username uid
	 * and gid. On failure, it returns false
	 * @param string $username The username of the user we want to know his uid and gid
	 * @return number[]|boolean Upon success, returns an array with the uid and gid,
	 * 							on failure will return false.
	 */
	public static function getUidAndGid($username) 
	{	
		if(!$username)
		{
			if(self::$internalScript)
			{
				return [0, 0];
			}
			else if(($username = self::isSharedLinkGuest()) === false)
			{
				return false;
			}
		}
		
		$cached = EosCacheManager::getUidAndGid($username);
		if($cached) 
		{
			return $cached;
		}
		
		$cmd     = "id " . $username;
		$result  = null;
		$errcode = null;
		exec($cmd, $result, $errcode);
		$list = array();
		if ($errcode === 0) 
		{
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
		} 
		else 
		{
			return false;
		}
		if (count($list) != 2) 
		{
			return false;
		}
		
		EosCacheManager::setUidAndGid($username, $list);
		return $list;
	}


	
	/**
	  * Return the storage id or false depending on the eospath received
	  * 
	  * /eos/devbox/user/	------------------------------------ FALSE
	  * /eos/devbox/user/l/ ------------------------------------ FALSE
	  * /eos/devbox/user/l/labador/ ---------------------------- object::user:labrador
	  * /eos/devbox/user/l/labrador/some.txt ------------------- object::user:labrador
	  * /eos/devbox/user/.metacernbox/ ------------------------- FALSE
	  * /eos/devbox/user/.metacernbox/l/ ----------------------- FALSE
	  * /eos/devbox/user/.metacernbox/l/labador/ --------------- object::user:labrador
	  * /eos/devbox/user/.metacernbox/l/labrador/avatar.png ---- object::user:labrador
	  * 
	  * @param string $eosPath the path about we want to know the storage id
	  * @return string|bool The storage ID if it could be found, false otherwise
	  */	
	public static function getStorageId($eosPath) 
	{
		$eos_prefix   = self::getEosPrefix();
		$eos_meta_dir = self::getEosMetaDir();
		if (strpos($eosPath, $eos_meta_dir) === 0) 
		{ 
			$len_prefix = strlen($eos_meta_dir);
			$rel        = substr($eosPath, $len_prefix);
			$splitted   = explode("/", $rel);
			if (count($splitted >= 2))
			{
				$user       = $splitted[1];
				$storage_id = "object::user::" . $user;
				return $storage_id;
			} 
			else 
			{
				return false;
			}
		} 
		else if (strpos($eosPath, $eos_prefix) === 0)
		{
			$len_prefix = strlen($eos_prefix);
			$rel        = substr($eosPath, $len_prefix);
			$splitted = explode("/", $rel);
			if (count($splitted >= 2))
			{
				$user       = $splitted[1];
				$storage_id = "object::user::" . $user;
				return $storage_id;
			} 
			else 
			{
				return false;
			}
		} 
		else 
		{
			return false;
		}
	}

	/**
	 * Modify the ACL of the given file (identified by its inode id), adding, updating or
	 * removing an user from the ACL
	 * @param string $from username owner of the file
	 * @param string $to username that must be added/updated/removed from the ACL
	 * @param string|int $fileId inode ID of the file we want to update
	 * @param int $ocPerm ownCloud permissions. Setting them to 0 will remove the given user ($to) from the ACL
	 * @param string $type user type: u for users, e-group for egroups
	 * @return bool True upon success, false otherwise
	 */
	public static function addUserToAcl($from, $to, $fileId, $ocPerm, $type) 
	{
		$data = self::getFileById($fileId);
		if(!$data) 
		{
			return false;
		}
		
	    $eosPath = $data["eospath"];
		$eosPathEscaped = escapeshellarg($eosPath);
		// do not allow shares above user home directory. Improve to not allow self home dir.
		$eosprefix = self::getEosPrefix();
		$eosjail = $eosprefix . $from[0] . "/" . $from . "/";
		if (strpos($eosPath, $eosjail) !== 0 ) 
		{
			\OCP\Util::writeLog('EOS', "SECURITY PROBLEM. user $from tried to share the folder $eosPath with $to", \OCP\Util::ERROR);
			return false;
		}

		$ocacl = self::toOcAcl($data["sys.acl"]);
		
		$ocacl[$to] = array("type"=>$type, "ocperm"=>$ocPerm);
		$sysAcl = EosUtil::toEosAcl($ocacl);
		$eosPath = $data["eospath"];
		
		// THIS COMMAND ONLY WORKS WITH ROOT USER
		$uid = 0; $gid = 0;
		
		EosCacheManager::clearFileById($fileId);
		
		$addUserToSysAcl = "eos -b -r $uid $gid attr -r set sys.acl=$sysAcl $eosPathEscaped";
		list($result, $errcode) = EosCmd::exec($addUserToSysAcl);
		
		return ($errcode === 0 && $result); 
	}

	/**
	 * Updates the ACL of the given file (identified by its inode id) with the given permissions
	 * @param string $from username of the owner of the file
	 * @param string $to username of the user to be modified on the ACL
	 * @param string|int $fileid inode id of the file we want to update
	 * @param int $permissions ownCloud permission mask
	 * @param string $type User type, u for users, e-group for egroups
	 * @return bool True upon success, false otherwise
	 */
	public static function changePermAcl($from, $to, $fileid, $permissions, $type)
	{
		$data = self::getFileById($fileid);
		if(!$data) 
		{
			return false;
		}
		
		$ocacl = self::toOcAcl($data["sys.acl"]);
		$ocacl[$to] = array("type"=>$type, "ocperm"=>$permissions);
		$sysAcl = EosUtil::toEosAcl($ocacl);
		$eosPath = $data["eospath"];
		$eosPathEscaped = escapeshellarg($eosPath);
		
		//THIS COMMAND ONLY WORKS WITH ROOT USER
		$uid = 0; $gid = 0; 
		
		EosCacheManager::clearFileById($fileid);
		$changeSysAcl = "eos -b -r $uid $gid attr -r set sys.acl=$sysAcl $eosPathEscaped";
		list(, $errcode) = EosCmd::exec($changeSysAcl);
		if ($errcode !== 0) 
		{
			return false;
		}
	}

	/**
	 * Extract the given user permissions ($to) from the EOS ACL in ownCloud ACL Format
	 * @param string $from username owner of the file to check
	 * @param string $to username of the user we want to get the permissions of
	 * @param string|int $fileid inode id of the file to check
	 * @return boolean|int Upon success, returns an ownCloud permission mask. Will return
	 * 						false otherwise
	 */
	public static function getAclPerm($from , $to, $fileid)
	{
		$data = self::getFileById($fileid);
		if(!$data) 
		{
			return false;
		}
		
		$ocacl = self::toOcAcl($data["sys.acl"]);
		if(isset($ocacl[$to]))
		{
			return $ocacl[$to]["ocperm"];
		}
		return false;
	}
	
	/**
	 * Translates a whole EOS ACL into a ownCloud ACL. The ownCloud ACL format is:
	 * [
	 * 		$user1 =>
	 * 		[
	 * 			"type" => u for users, e-group for egroups
	 * 			"ocperm" => integer mask with the permissions
	 * 			"eosperm" => string with the original EOS ACL
	 * 		],
	 * 		$user2 =>
	 * 		[
	 * 			...
	 * 		]
	 * 		...
	 * ]
	 * 
	 * @param string $sysAcl an EOS ACL as string
	 * @return array[] An array as described abover with the OC permissions
	 */
	public static function toOcAcl($sysAcl)
	{
		$ocAcl = [];
		$usersSysAcl = [];

		$parts = explode(",", $sysAcl);
		if($parts) 
		{
		    foreach($parts as $user) 
		    {
		        $data = explode(":",$user);
		        if(count($data) >= 3) 
		        {
		            $usersSysAcl[] = $data;
		        }
		    }
		}
		
		foreach($usersSysAcl as $user) 
		{
		    $ocAcl[$user[1]] = array("type"=>$user[0], "ocperm"=>self::toOcPerm($user[2]), "eosperm"=>$user[2]);
		}
		
		return $ocAcl;
	}
	
	/**
	 * Transforms the ownCloud ACL to EOS ACL (sys.acl)
	 * @param array $ocAcl array map with the oc permissions. Format:
	 * 			[ 
	 * 			   userID => 
	 * 				[
	 * 					"ocperm" => integer mask with permissions,
	 * 					"type" 	 => "u" for users or "e-group" for groups
	 * 				],
	 * 			  userID2 => ...
	 *          ]
	 * 
	 * @return string The EOS Acl after trasnlating it from OC ACL
	 */
	public static function toEosAcl($ocAcl)
	{ 
		$sysAcl = [];
		$loggedUser = \OCP\User::getUser();
		
		foreach($ocAcl as $username=>$data) 
		{
			if($username == $loggedUser) 
		  	{
		  		$entry = "u:$loggedUser:rwx!m";
		  		$sysAcl[] = $entry;
		  	} 
		  	else 
		  	{
		  		if($data["ocperm"] !== 0) 
		  		{
			    	$type = $data["type"];
			    	$eosperm = self::toEosPerm($data["ocperm"]);
                	$entry = "$type:$username:$eosperm"; // Be very careful with this
                	$sysAcl[]  = $entry;
				}		
		  	}
		}
		$sysAcl = implode(",", $sysAcl);
		return $sysAcl;
	}
	
	/**
	 * Translates OC Permissions into EOS ACL
	 * Conversion table:
	 *  On OC meas on EOS
	 *  -----------------
	 *  	1   - 	r	
	 *  	2   -  \
	 *  	4   -  -  w (all toguether)
	 *  	8   -  /
	 *  	16  -  Not reflected on EOS ACL. NOt used by us (we dont allow reshare)
	 *  	31  -  rwx
	 * @param int $ocPerm ownCloud permissions as an integer mask
	 * @return string EOS sys.acl from the given permissions
	 */
	public static function toEosPerm($ocPerm) 
	{
		// ocPerm is a comibation of
		// const PERMISSION_CREATE = 4;
		// const PERMISSION_READ = 1;
		// const PERMISSION_UPDATE = 2;
		// const PERMISSION_DELETE = 8;
		// const PERMISSION_SHARE = 16;
		// const PERMISSION_ALL = 31;
		// for us create update and delete is the same eos permission W
		$eosPerm = null;
		if($ocPerm > 1) 
		{
			$eosPerm = "rwx+d";
		} 
		else if ($ocPerm == 1)
		{
			$eosPerm = "rx";
		} 
		return $eosPerm;
	}
	
	/**
	 * Translates EOS ACL to OC permissions
	 * Conversion table:
	 * 	On EOS means on OC
	 *  ------------------
	 *  	r   -		1
	 *  	w   -		14
	 *  
	 * @param string $eosPerm EOS ACL from the file metadata (sys.acl xattrv)
	 * @return int a mask with ownCloud permissions
	 */
	public static function toOcPerm($eosPerm)
	{ 
		$total = 0;
		if(strpos($eosPerm, "r") !== false)
		{
			$total += 1;
		}
		if(strpos($eosPerm, "w") !== false)
		{
			$total += 14;
		}
		return $total;
	}

	/**
	 * Retrieves the metadata of the file pointed by the file inode $id
	 * @param string|int $id The file inode within the EOS namespace
	 * @param callable $callback A function callback to add extra parameters to the returned metadata.
	 * 			The function must have the signature function(&$data), where $data is the metadata array
	 * @return array|bool Upon success, it will return a map array with the file metadata, false otherwise
	 */
	public static function getFileById($id, $callback = false)
	{
		$cached = EosCacheManager::getFileById($id);
		if($cached) 
		{
			return $cached;
		}
		
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());
		$fileinfo = "eos -b -r $uid $gid  file info  inode:" . $id . " -m";
		list($result, $errcode) = EosCmd::exec($fileinfo);
		if ($errcode === 0 && $result) 
		{
			$line_to_parse = $result[0];
			$data          = EosParser::parseFileInfoMonitorMode($line_to_parse);
			
			if($callback)
			{
				$callback($data);
			}
			
			EosCacheManager::setFileById($id, $data);
			
			return $data;
		}
		
		return null;
	}

	/**
	 * Retrieves the metadata of the file pointed by the path $eospath
	 * @param string $eospath The EOS Path to the file
	 * @param callable $callback A function callback to add extra parameters to the returned metadata.
	 * 			The function must have the signature function(&$data), where $data is the metadata array
	 * @return array|bool Upon success, it will return a map array with the file metadata, false otherwise
	 */
	public static function getFileByEosPath($eospath, $callback = false)
	{
		
		$eospath = rtrim($eospath, "/");
		
		$cached = EosCacheManager::getFileByEosPath($eospath);
		if($cached) 
		{
			return $cached;
		}
		
		$eospathEscaped = escapeshellarg($eospath);
		
		list($uid, $gid) = self::getEosRole($eospath, false);
		$fileinfo = "eos -b -r $uid $gid file info $eospathEscaped -m";
		list($result, $errcode) = EosCmd::exec($fileinfo);
		if ($errcode === 0 && $result) 
		{
			$line_to_parse = $result[0];
			$data          = EosParser::parseFileInfoMonitorMode($line_to_parse);
			
			if($callback)
			{
				$callback($data);
			}
			
			EosCacheManager::setFileByEosPath($eospath, $data);
			
			return $data;
		}
		return null;
	}
	
	/**
	 * Test wether the given $username is member of $egroup or not
	 * @param string $username Username of the user we want to know if is member or not
	 * @param string $egroup group name of the group we want to test
	 * @return bool True if the user is member of the group, false otherwise
	 */
	public static function isMemberOfEGroup($username, $egroup) 
	{
		$uidAndGid = self::getUidAndGid($username);
		if (!$uidAndGid) 
		{
			return false;
		}
		
		list($uid, $gid) = $uidAndGid;
		$egroupEscaped = escapeshellarg($egroup);
		$member = "eos -b -r $uid $gid member $egroupEscaped";
		list($result, $errcode) = EosCmd::exec($member);
        if ($errcode === 0 && $result) 
        {
        	$line_to_parse = $result[0];
            $data = EosParser::parseMember($line_to_parse);
            return $data;
        }
        
        return false;
	}

	/**
	 * Return the mimetype of a file based on its extension and OC type (OC type is either 'folder' or 'file')
	 * @param string $path to the file we want to get the mimetype from
	 * @param string $type OC file type (either 'file' or 'folder')
	 */
	public static function getMimeType($path, $type)
	{
		if($type === "folder") 
		{
			return "httpd/unix-directory";
		} 
		else 
		{
		    $mime_types =
		    [
			    /* CERN mime types */
			    'root' => 'application/x-root',			
				'ipynb' => 'application/pynb',
		    		
			    'txt' => 'text/plain',
			    'htm' => 'text/html',
			    'html' => 'text/html',
			    'php' => 'text/html',
			    'css' => 'text/css',
			    'js' => 'application/javascript',
			    'json' => 'application/json',
			    'xml' => 'application/xml',
			    'swf' => 'application/x-shockwave-flash',
			    'flv' => 'video/x-flv',

			    // images
			    'png' => 'image/png',
			    'jpe' => 'image/jpeg',
			    'jpeg' => 'image/jpeg',
			    'jpg' => 'image/jpeg',
			    'gif' => 'image/gif',
			    'bmp' => 'image/bmp',
			    'ico' => 'image/vnd.microsoft.icon',
			    'tiff' => 'image/tiff',
			    'tif' => 'image/tiff',
			    'svg' => 'image/svg+xml',
			    'svgz' => 'image/svg+xml',

			    // archives
			    'zip' => 'application/zip',
			    'rar' => 'application/x-rar-compressed',
			    'exe' => 'application/x-msdownload',
			    'msi' => 'application/x-msdownload',
			    'cab' => 'application/vnd.ms-cab-compressed',

			    // audio/video
			    'mp3' => 'audio/mpeg',
			    'qt' => 'video/quicktime',
			    'mov' => 'video/quicktime',

			    // adobe
			    'pdf' => 'application/pdf',
			    'psd' => 'image/vnd.adobe.photoshop',
			    'ai' => 'application/postscript',
			    'eps' => 'application/postscript',
			    'ps' => 'application/postscript',

			    // ms office
			    'doc' => 'application/msword',
			    'rtf' => 'application/rtf',
			    'xls' => 'application/vnd.ms-excel',
			    'ppt' => 'application/vnd.ms-powerpoint',

			    // open office
			    'odt' => 'application/vnd.oasis.opendocument.text',
	            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	        ];
		    
			$val = explode('.', $path);
			$ext = strtolower(array_pop($val));
		    if (array_key_exists($ext, $mime_types)) 
		    {
		    	return $mime_types[$ext];
		    }
            
		    return 'application/octet-stream';
		}
	}

	/**
	 * Recursively stablish the extended attribute sys.acl X permission from the user's root directory.
	 * @param array $filedata metadata of anyfile inside the user home directory (the root folder metadata itself is valid)
	 * @param string $to username of the user to add to the ACL
	 * @param string $type entity type: u for users, e-group for groups
	 * @return bool True upon success, false otherwise
	 */
	public static function propagatePermissionXToParents($filedata, $to, $type)
	{
	  	// type == 'u' -- specifies that $to is a user name
        // type == 'egroup' -- specifies that $to is egroup name
        // other values of type are not allowed

  	    if (!in_array($type, array('u','egroup'))) 
  	    {
		  \OCP\Util::writeLog("PROGRAMMNG ERROR", "Wrong type passed to propagatePermissionXToParents (".$type.")", \OCP\Util::ERROR);
		  return false;
		}

		$eospath = $filedata["eospath"];
		$eosprefix = self::getEosPrefix(); // like /eos/devbox/user/
		$rest = substr($eospath, strlen($eosprefix));
		$parts = explode("/", $rest);
		$letter = $parts[0];
		$username = $parts[1];
		$result   = null;
		$errcode  = null;
		$rootFolder = $eosprefix .$letter ."/". $username;
		$data = self::getFileByEosPath($rootFolder);
		if(!$data) 
		{
			return false;
		}
		
		$sysAcl = $data["sys.acl"];
		// if the user is already in the acl we dont do nothing
		if(strpos($sysAcl, $to) !== false) 
		{
			return true;
		}
		
		$newSysAcl = implode(",", array($sysAcl, "$type:$to:x"));
		$uid = 0; $gid = 0; // root is the only one allowed to change permissions
		$cmd = "eos -b -r $uid $gid attr set sys.acl=$newSysAcl '$rootFolder'";
		list($result, $errcode) = EosCmd::exec($cmd, $result, $errcode);
		if ($errcode !== 0) 
		{
			return false;
		}
		return true;
	}

	// return the list of EGroups this member is part of, but NOT all, just the ones that appear in share database.
	/**
	 * Retrieves the e-groups of which the given $username is member of.
	 * NOTE: Currently limited to the e-groups present in the oc_share database table.
	 * @param string $username. The username of the user we want to get his/her e-groups
	 * @return array. An array containing the e-groups to which this user belongs
	 */
	public static function getEGroups($username) 
	{
		return \OC\LDAPCache\LDAPCacheManager::getUserEGroups($username);
	}
		
	/**
	 * Creates a version folder of for the file pointed by $eosPath.
	 * WARNING: This function does not make any check whether the path points to
	 * a file or a folder
	 * @param string $eosPath path to the file we want to create a version folder of
	 * @return bool True upon success, false otherwise 
	 */
	public static function createVersion($eosPath) 
	{
		EosCacheManager::clearFileByEosPath($eosPath);
		
		$eosPathEscaped = escapeshellarg($eosPath);
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());
		//$uid = 0; $gid = 0; // root is the only one allowed to change permissions
		$cmd = "eos -b -r $uid $gid file version $eosPathEscaped";
        list(, $errcode) = EosCmd::exec($cmd);
        if ($errcode !== 0) 
        {
        	return false;
        }
        return true;
	}
	
	/**
	 * Returns the version folder metadata of the file identified by the argument $id (file inode)
	 * @param string|int $id inode id from the file which we want to know it's version folder id
	 * @param bool $createVersion If true, and the version folder does not exist, it will create it
	 * @return null|array The version folder metadata, null if it could not be found and $createVersion is false
	 */
	public static function getVersionFolderFromFileID($id, $createVersion = true)
	{
		$meta = self::getFileById($id);
		// here we can receive the file to convert to version folder
		// or the version folder itself
		// so we need to check carefuly
		$eos_version_regex = \OCP\Config::getSystemValue("eos_version_regex");
		// if file is already version folder we return that inode
		if (preg_match("|".$eos_version_regex."|", basename($meta["eospath"])) ) 
		{
			return $meta;
		} 
		else 
		{
			$dirname = dirname($meta['eospath']);
			$basename = basename($meta['eospath']);
			// We need to handle the case where the file is already a version.
			// In that case the version folder is the parent.
			$versionFolder = $dirname . "/.sys.v#." . $basename;
			if (preg_match("|".$eos_version_regex."|", $dirname) ) {
				$versionFolder = $dirname;
			}
		
		
			$versionInfo = self::getFileByEosPath($versionFolder);
			if(!$versionInfo) 
			{
				if($createVersion)
				{
					self::createVersion($meta['eospath']);
				}
				else
				{
					return null;
				}
				$versionInfo = self::getFileByEosPath($versionFolder);
			}
				
			return $versionInfo;
		}
	}
	
	/**
	 * Returns the version folder id of the file identified by the argument $id (file inode)
	 * @param string|int $id inode id from the file which we want to know it's version folder id
	 * @param bool $createVersion If true, and the version folder does not exist, it will create it
	 * @return null|string|int The version folder id, null if it could not be found and $createVersion is false
	 */
	public static function getVersionsFolderIDFromFileID($id, $createVersion = true) 
	{
		$version = self::getVersionFolderFromFileID($id, $createVersion);
		if($version)
		{
			return $version['fileid'];
		}
		
		return null;
	}
	
	/**
	 * Return current file metadata from the file owner of the version folder with the id specified in the argument
	 * @param int|string $id of the version folder from the file we want to get the metadata
	 * @return false|array On success, it will return a map array containing the file metadata, otherwise it will return false
	 */
	public static function getFileMetaFromVersionsFolderID($id) 
	{
		$meta = self::getFileById($id);
		$dirname = dirname($meta['eospath']);
		$basename = basename($meta['eospath']);
		$realfile = $dirname . "/" . substr($basename, 8);
		$realfilemeta = self::getFileByEosPath($realfile);
		return $realfilemeta;
	}
	
	/**
	 * Loads the project spaces mapping from database and save them on redis for faster access. If
	 * a project is specified in the arguments, this function will also return the relative path for that
	 * project
	 * @param string|null $projectReturn <optional> Specify the project from which to return the relative path
	 * @return bool|string The project relative path if any project is specified in the arguments, false otherwise
	 * 			(which does not mean failure)
	 */
	private static function loadProjectSpaceMappings($projectReturn = null)
	{
		$data = \OC_DB::prepare('SELECT * FROM cernbox_project_mapping')->execute()->fetchAll();
		
		$result = false;
		
		foreach($data as $projectData)
		{
			$project = $projectData['project_name'];
			$relativePath = $projectData['eos_relative_path'];
			$owner = $projectData['project_owner'];
			
			if($projectReturn && $projectReturn === $project)
			{
				$result = $relativePath;
			}
			
			Redis::writeToCacheMap(self::REDIS_KEY_PROJECT_USER_MAP, $project, json_encode(['path' => $relativePath, 'owner' => $owner]));
		}
		
		return $result;
	}
	
	/**
	 * Return the project relative path (Relative from /eos/project or whichever is set
	 * in config.php file for eos_project_prefix) for the given project name
	 * @param string $project The name of the project to search for its relative path
	 * @return string|NULL The project relative path (thats it, project name for projects under /eos/project,
	 * 			x/xname for project already under the new dir structure) 
	 */
	public static function getProjectRelativePath($project) 
	{
		
		$projectPath = json_decode(Redis::readFromCacheMap(self::REDIS_KEY_PROJECT_USER_MAP, $project), TRUE);
		
		if(!$projectPath)
		{
			return self::loadProjectSpaceMappings($project);
		}
		
		if($projectPath)
		{
			return $projectPath['path'];
		}
		
		return null;
	}
	
	/**
	 * Return the service account owner for the given project
	 * @param string $project Name of the project
	 * @return string|null If found, it will return the username of the owner, null otherwise
	 */
	public static function getUserForProjectName($project) 
	{
		$projectMapping = json_decode(Redis::readFromCacheMap(self::REDIS_KEY_PROJECT_USER_MAP, $project), TRUE);
		
		if(!$projectMapping)
		{
			self::loadProjectSpaceMappings($project);
		}
		
		$projectMapping = json_decode(Redis::readFromCacheMap(self::REDIS_KEY_PROJECT_USER_MAP, $project), TRUE);
		
		if(!$projectMapping || !isset($projectMapping['owner']) || $projectMapping['owner'] === 'temp')
		{
			return null;
		}
		
		return $projectMapping['owner'];
	}
	
	/**
	 * Returns the name of the project to which the given path is pointing.
	 * @param null|string If the path belongs to a project spaces, it will return the name of this,
	 * 			otherwise it will return null
	 */
	public static function getProjectNameForPath($relativePath)
	{
		$all = Redis::readHashFromCacheMap(self::REDIS_KEY_PROJECT_USER_MAP);
		
		if(!$all)
		{
			self::loadProjectSpaceMappings();
		}
		
		$all = Redis::readHashFromCacheMap(self::REDIS_KEY_PROJECT_USER_MAP);
		
		if(!$all)
		{
			return null;
		}
		
		$relativePath = trim($relativePath, '/');
		foreach($all as $project => $data)
		{
			$data = json_decode($data, TRUE);
			$t = trim($data['path'], '/');
			if(strpos($relativePath, $t) === 0)
			{
				return ["/  project $project", $data['path']];
			}
		}
		
		return false;
	}
	
	/**
	 * Returns the project name associated to the given service account ($user).
	 * @param string $user name of the service account associated with a project space (owner svc accont)
	 * @return null|string If the given user is the owner of any project space, it will return the project name.
	 * 			Otherwise, it will return null
	 */
	public static function getProjectNameForUser($user)
	{
		$all = Redis::readHashFromCacheMap(self::REDIS_KEY_PROJECT_USER_MAP);
		
		if(!$all)
		{
			self::loadProjectSpaceMappings();
		}
		
		$all = Redis::readHashFromCacheMap(self::REDIS_KEY_PROJECT_USER_MAP);
		
		if(!$all)
		{
			return null;
		}
		
		foreach($all as $project => $data)
		{
			$data = json_decode($data, TRUE);
			if($data['owner'] === $user)
			{
				return $project;
			}
		}
		
		return false;
	}
	
	/**
	 * Tests wether the given URI (Used for the request to the webserver) is a project space
	 * related URI [CURRENTLY USED BY KUBA'S OPTIMIZATION IN SHARED MOUNTS apps/files_sharing/lib/mountprovider.php)
	 * @param string $uri_path The URI issued against the server
	 * @return bool True if the uri is a request to some project space file, false otherwise
	 */
	public static function isProjectURIPath($uri_path) 
	{
		// uri paths always start with leading slash (e.g. ?dir=/bla)
		$uri_path = trim($uri_path, '/');
		if (startsWith ( $uri_path, '/' )) 
		{
			$topdir = explode ( "/", $uri_path ) [1];
		} 
		else 
		{
			$topdir = explode ( "/", $uri_path ) [0];
		}
		
		return startsWith ( $topdir, '  project ' );
	}
	
	/**
	 * Tests wether the given URI (Used for the request to the webserver) is a shared file
	 * related URI [CURRENTLY USED BY KUBA'S OPTIMIZATION IN SHARED MOUNTS apps/files_sharing/lib/mountprovider.php)
	 * @param string $uri_path The URI issued against the server
	 * @return bool True if the uri is a request to some shared file, false otherwise
	 */
	public static function isSharedURIPath($uri_path) 
	{
		// uri paths always start with leading slash (e.g. ?dir=/bla) # assume that all shared items follow the same naming convention at the top directory (and they do not clash with normal files and directories)
		// the convention for top directory is: "name (#123)"
		// examples:
		// "/aaa (#1234)/jas" => true
		// "/ d s (#77663455)/jas" => true
		// "/aaa (#1)/jas" => false
		// "/aaa (#ssss)/jas" => false
		// "aaa (#1234)/jas" => false
		// "/(#7766)/jas" => false
		// "/ (#7766)/jas" => true (this is a flaw)
		if (startsWith ( $uri_path, '/' )) 
		{
			$topdir = explode ( "/", $uri_path ) [1];
		} 
		else 
		{
			$topdir = explode ( "/", $uri_path ) [0];
		}
		
		$parts = explode ( " ", $topdir );
		if (count ( $parts ) < 2) 
		{
			return false;
		}
		$marker = end ( $parts );
		return preg_match ( "/[(][#](\d{3,})[)]/", $marker ); // we match at least 3 digits enclosed within our marker: (#123)
	}

	/**
	 * Retrieve the file metadata of the files inside the folder pointed by $eosPath (It does NOT include the folder metadata itself).
	 * Executing this function for a file will return an empty array.
	 * @param string $eosPath Path to the file/folder we want to get the content's files metadata
	 * @param callable $additionalParameterCallback Function callback with the signature: function(&$data) { ... }. Used to perform any 
	 * 			change on a file metadata. It is called once per file metadata. The argument $data is the array with the file metadata. Modifications
	 * 			to this array will remain after callback exits
	 * @param bool $deep True to get the folder contents of up to 10 nested level folders. False to get only the metadata of the path's direct children
	 * @return array|bool. On success, it will return an array in which each entry will be the metadata of a file inside the folder.
	 * 			Will return false otherwise. 
	 */
	public static function getFolderContents($eosPath, $additionalParameterCallback = null, $deep = false)
	{
		$eos_hide_regex = self::getEosHideRegex();
		$eos_version_regex = self::getEosVersionRegex();
		
		$eosPathEscaped = escapeshellarg($eosPath);
		
		$cached = EosCacheManager::getFileInfoByEosPath(($deep? 10 : 1), $eosPathEscaped);
		if($cached)
		{
			return $cached;
		}
		
		list($uid, $gid) = self::getEosRole($eosPath, false);
		if ($deep === true) 
		{
			$getFolderContents = "eos -b -r $uid $gid  find --fileinfo --maxdepth 10 $eosPathEscaped";
		} 
		else 
		{
			$getFolderContents = "eos -b -r $uid $gid  find --fileinfo --maxdepth 1 $eosPathEscaped";
		}
		
		$files = [];
		list($result, $errcode) = EosCmd::exec($getFolderContents);
		if ($errcode !== 0) 
		{
			return $files;
		}
		
		/*
		 * This array is used to pass extra attributes to a file/folder
		 * The keys are eos paths and the values are arrays of key-value pairs (attr/value)
		 * Example: ["/eos/scratch/user/o/ourense/photos/1.png" => ["cboxid" => 456123]]
		 */
		$extraAttrs = [];
		
		$hiddenFolder = preg_match("|".$eos_hide_regex."|", $eosPath);
		
		foreach ($result as $line_to_parse) 
		{
			$data            = EosParser::parseFileInfoMonitorMode($line_to_parse);
			if( $data["path"] !== false && rtrim($data["eospath"],"/") !== rtrim($eosPath,"/") )
			{
				if($additionalParameterCallback !== null)
				{
					$additionalParameterCallback($data);	
				}
				
				//$data["storage"] = $this->storageId;
				//$data["permissions"] = 31;
		
				// HUGO  we need to be careful of not showing .sys.v#. folders when the folder asked to show the contents is a non sys folder.
				// the folder asked to list is not a sys folder, i.e does not have the hide_regex.
				if (!$hiddenFolder && preg_match("|".$eos_hide_regex."|", $data["eospath"]) ) 
				{
					/* If we found a versions folder we add its inode to the original file under cboxid attribute */
					if (preg_match("|".$eos_version_regex."|", $data["eospath"]) ) 
					{
						$dirname = dirname($data['eospath']);
						$basename = basename($data['eospath']);
						$filename = substr($basename, 8);
						$filepath = $dirname . "/" . $filename;
						$extraAttrs[$filepath]["cboxid"] = $data['fileid'];
					}
				}
				// the folder asked to list its contents is a sys folder, so we list the contents. 
				// This behaviour is not used directly by a user but it is used by versions and trashbin apps.
				else 
				{
					$files[$data['eospath']] = $data;
				}
				
				//$itemEosPath = escapeshellarg(rtrim($data['eospath'], '/'));
				
				EosCacheManager::setFileByEosPath($data['eospath'], $data);
				EosCacheManager::setFileById($data['fileid'], $data);
			}
		}
		
		/* Add extra attributes */
		foreach($extraAttrs as $eospath => $attrs) 
		{
			if(isset($files[$eospath]))
			{
				$file = $files[$eospath];
				foreach($attrs as $attr => $value) 
				{
					$file[$attr] = $value;
				}
				$files[$eospath] = $file;
			}
		}
		
		$result = array_values($files);
		EosCacheManager::setFileInfoByEosPath(($deep? 10 : 1), $eosPathEscaped, $result);
		
		return $result;
	}
	
	/**
	 * Executes a stat over a file. Used mostly as file existence and permission checks test.
	 * @param string $eosPath Path to the file we want to stat
	 * @return bool True if the file was successfully stated, false otherwise
	 */
	public static function statFile($eosPath)
	{
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());
		$eosPathEscaped = escapeshellarg($eosPath);
		$cmd = "eos -r $uid $gid stat $eosPathEscaped";
		list(, $errorCode) = EosCmd::exec($cmd);
		return $errorCode;
	}
	
	/**
	 * Retrieves the user quota for the eos_prefix variable node.
	 * @param string|null $userName The username to get the quota for. If it is null, it will use
	 * 			the current logged in user. If the user cannot be identified, it will return the default
	 * 			quota (infinite free space) and we will let EOS handle the writtings if there is no space left
	 * @return array|bool. On success, it will return a map array of:
	 * 			- 'free' free space in bytes
	 * 			- 'used' used space in bytes
	 * 			- 'total' total space in bytes,
	 * 			- 'relative' percentage of used space
	 * 			- 'owner' username of the quota's owner
	 * 			- 'ownerDisplayName' display name of the quota's owner.
	 * 
	 *   		On failure, it will return false
	 */
	public static function getUserQuota($userName = false)
	{
		if(!$userName)
		{
			$userName = \OC_User::getUser();
		}
		
		if(!$userName)
		{
			return [ 'free' => -1, 'used' => 0, 'total' => 0, 'relative' => 0, 'owner' => '', 'ownerDisplayName' => ''];
		}
		
		list($uid, $gid) = self::getUidAndGid($userName);
		$eosPrefix = self::getEosPrefix();
		$cmd = "eos -r $uid $gid quota $eosPrefix -m";
		list($result, $errorCode) = EosCmd::exec($cmd);
		if($errorCode === 0)
		{
			$parsed = EosParser::parseQuota($result);
			$parsed['owner'] = $userName;
			$parsed['ownerDisplayName'] = \OC_User::getDisplayName($userName);
			
			return $parsed;
		}
		
		return [ 'free' => -1, 'used' => 0, 'total' => 0, 'relative' => 0, 'owner' => $userName, 'ownerDisplayName' => \OC_User::getDisplayName($userName)];
	}
	
	/**
	 * Performs a plain ls (list files) on the path pointed by eosPath.
	 * @param string $eosPath Path to the file/folder that we want to 'ls'
	 * @return array|bool On success, it will return an array with the file names of the pointed
	 * 			eosPath, otherwise it will return false
	 */
	public static function ls($eosPath)
	{
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());
		$eosPath = escapeshellarg($eosPath);
		$cmd = "eos -r $uid $gid ls $eosPath";
		list($result, $errCode) = EosCmd::exec($cmd);
		if($errCode === 0)
		{
			return $result;
		}
	
		return FALSE;
	}
	
	/**
	 * Creates a version folder for the file pointed by $eosPath (using root user, since version folders
	 * may be only created with root). Then, it chown the create folder to the file owner
	 * @param string $eosPath Path to the file we want to create its version folder
	 * @return boolean|string On success, it will return the path of the newly created version folder (= True). 
	 * 			Otherwise, it will return false.
	 */
	public static function createVersionFolder($eosPath)
	{
		$user = self::getOwner($eosPath);
		list($uid, $gid) = self::getUidAndGid($user);
		
		$dir = dirname($eosPath);
		$file = basename($eosPath);
		$versionFolder = $dir . "/.sys.v#." . $file;
		$versionFolder = escapeshellarg($versionFolder);
		$cmd = "eos -b -r 0 0 mkdir -p $versionFolder";
		list(, $errcode) = EosCmd::exec($cmd);
	
		if($errcode !== 0)
		{
			return false;
		}
		
		$cmd2 = "eos -b -r 0 0 chown -r $uid:$gid $versionFolder";
		list(, $errcode) = EosCmd::exec($cmd2);
		
		if($errcode !== 0)
		{
			return false;
		}
	
		return $versionFolder;
	}
	
	/**
	 * Creates a symlink to the file pointed by the file $eosPath in the user's metadata dir. Then,
	 * it will move it to the file's version folder.
	 * @param string $eosPath Path to a file or to the version folder of a file. VERSION FOLDER MUST EXISTS
	 * @return Bool True on success, false otherwise
	 */
	public static function createSymLinkInVersionFolder($eosPath)
	{
		$meta = self::getFileByEosPath($eosPath);
		
		if(!$meta)
		{
			return false;
		}
		
		$fileId = $meta['fileid']; 
		
		$user = self::getOwner($eosPath);
		list($uid, $gid) = self::getUidAndGid($user);
		
		$tempMetaFolderPath = rtrim(self::getEosMetaDir(), '/') . '/' . substr($user, 0 , 1) . '/' . $user . '/link_cache';
		$escapedMetaFolder = escapeshellarg($tempMetaFolderPath);
		$tempMetaFolder = "eos -b -r $uid $gid mkdir -p $escapedMetaFolder";
		list(, $errorcode) = EosCmd::exec($tempMetaFolder);
		
		if($errorcode !== 0)
		{
			return false;
		}
		
		$file = basename($eosPath);
		$dir = dirname($eosPath);
	
		$linkDst = $tempMetaFolderPath . '/' . $fileId;
		$escapedlinkDst = escapeshellarg($linkDst);
		
		$target = escapeshellarg('../' . $file);
		
		$cmd = "eos -b -r $uid $gid ln $escapedlinkDst $target";
		list(, $errorcode) = EosCmd::exec($cmd);
		
		if($errorcode !== 0)
		{
			return false;
		}
		
		/*$cmd2 = "eos -b -r 0 0 chown -r $uid:$gid $linkDst";
		list($result, $errorcode) = EosCmd::exec($cmd2);*/
		
		$moveDst = escapeshellarg($dir . "/.sys.v#." . $file . '/' . $file);
		$moveLink = "eos -b -r $uid $gid mv $escapedlinkDst $moveDst";
		list(, $errorcode) = EosCmd::exec($moveLink);
		
		return $errorcode === 0;
	}
	
	/**
	 * Creates a symlink in $destinationEosPath, which points to the file $linkTargetEosPath
	 * @param string $destinationEosPath Path where the symlink must be created (must include symlink name)
	 * @param string $linkTargetEosPath Path to which the symlink will point (might be absolut or relative path)
	 */
	public static function createSymLink($destinationEosPath, $linkTargetEosPath)
	{
		$uidGid = self::getUidAndGid(\OC_User::getUser());
	
		$uid = $uidGid[0];
		$gid = $uidGid[1];
	
		$destinationEosPath = escapeshellarg($destinationEosPath);
		$linkTargetEosPath = escapeshellarg($linkTargetEosPath);
	
		$cmd = "eos -b -r $uid $gid ln $destinationEosPath $linkTargetEosPath";
		list(, $errcode) = EosCmd::exec($cmd);
	
		return $errcode === 0;
	}
	
	/**
	 * Remove a symlink from the storage. The deletion is performed under the logged in user
	 * uid and gid (He/she must have permissions in the symlink's folder)
	 * @param string $targetPath Path to the symlink to be removed
	 * @return bool True on success, false otherwise
	 */
	public static function removeSymLink($targetPath)
	{
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());

		$targetPath = escapeshellarg($targetPath);
	
		$cmd = "eos -b -r $uid, $gid rm $targetPath";
		list(, $errcode) = EosCmd::exec($cmd);
		return $errcode === 0;
	}
	
	/**
	 * Sets the given extended attribute with the given value to the folder identified by the argument eosPath
	 * @param string $eosPath Path to the folder to which we want to add the attribute
	 * @param string $attributeName Name of the attribute (E.G., 'sys.acl', 'custom.share_type', etc.)
	 * @param string $attributeValue Value of the attribute
	 * @return bool True on success, false otherwise
	 */
	public static function setExtendedAttribute($eosPath, $attributeName, $attributeValue)
	{
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());
	
		$eosPath = escapeshellarg($eosPath);
	
		$cmd = "eos -b -r $uid $gid set attr $attributeName=$attributeValue $eosPath";
		list(, $errcode) = EosCmd::exec($cmd);
	
		return $errcode === 0;
	}
}