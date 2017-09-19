<?php
namespace OC\Files\ObjectStore;

function startsWith($haystack, $needle) {
  // search backwards starting from haystack length characters from the end                                                                                                                                                                        
  return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}


final class EosUtil {
	
	const REDIS_KEY_PROJECT_USER_MAP = 'project_spaces_mapping';

	private static $internalScript = false;
	public static $useSlave = false;
	
	public static function setInternalScriptExecution($val)
	{
		self::$internalScript = $val;
	}
	
	public static function getEosMgmUrl() 
	{
		// if the we are asked to use the slave, we use the home directory instance
		// gallery will not work for eos browser as it lives in another instance.
		if(self::$useSlave) {
			return \OCP\Config::getSystemValue("eos_slave_mgm_url");
		}

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
	

	public static function getEosPrefix() { 
		$eos_prefix = \OCP\Config::getSystemValue("eos_prefix");
		return $eos_prefix;
	}

	public static function getEosProjectPrefix() { 
		$eos_project_prefix = \OCP\Config::getSystemValue("eos_project_prefix");
		return $eos_project_prefix;
	}
	public static function getEosProjectMapping() { 
		$eos_project_mapping = \OCP\Config::getSystemValue("eos_project_mapping");
		return $eos_project_mapping;
	}
	public static function getEosMetaDir() { 
		$eos_meta_dir = \OCP\Config::getSystemValue("eos_meta_dir");
		return $eos_meta_dir;
	}
	public static function getEosRecycleDir() {
		$eos_recycle_dir = \OCP\Config::getSystemValue("eos_recycle_dir");
		return $eos_recycle_dir;
	}
	public static function getEosHideRegex() { 
		$eos_hide_regex = \OCP\Config::getSystemValue("eos_hide_regex");
		return $eos_hide_regex;
	}
	public static function getEosVersionRegex() { 
		$eos_version_regex = \OCP\Config::getSystemValue("eos_version_regex");
		return $eos_version_regex;
	}
	public static function getBoxStagingDir() { 
		$staging_dir = \OCP\Config::getSystemValue("box_staging_dir");
		return $staging_dir;
	}
	
	// Retrive the owner of the path
	/*
	/eos/devbox/user/	------------------------------------ FALSE
	/eos/devbox/user/l/ ------------------------------------ FALSE
	/eos/devbox/user/l/labrador/ --------------------------- labrador
	/eos/devbox/user/l/labrador/some.txt ------------------- labrador
	/eos/devbox/user/.metacernbox/ ------------------------- FALSE
	/eos/devbox/user/.metacernbox/l/ ----------------------- FALSE
	/eos/devbox/user/.metacernbox/l/labador/ --------------- labrador
	/eos/devbox/user/.metacernbox/l/labrador/avatar.png ---- labrador
	*/
	public static function getOwner($eosPath){ // VERIFIED BUT WE ARE ASUMING THAT THE OWNER OF A FILE IS THE ONE INSIDE THE USER ROOT INSTEAD SEEING THE UID AND GID
		
		if(EosInstanceManager::isInGlobalInstance())
		{
			return false;
		}
		
		$eos_project_prefix = self::getEosProjectPrefix();
		$cached = EosCacheManager::getOwner($eosPath);
		if($cached) {
			return $cached;
		}
		
		$eos_prefix = EosUtil::getEosPrefix();
		$eos_meta_dir = EosUtil::getEosMetaDir();
		if (strpos($eosPath, $eos_meta_dir) === 0) { // match eos meta dir like /eos/devbox/user/.metacernbox/...
			$len_prefix = strlen($eos_meta_dir);
			$rel        = substr($eosPath, $len_prefix);
			$splitted   = explode("/", $rel);
			if (count($splitted >= 2)){
				$user       = $splitted[1];
				EosCacheManager::setOwner($eosPath, $user);
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
				EosCacheManager::setOwner($eosPath, $user);
				return $user; 
			} else {
				return false;
			}
		} else if (strpos($eosPath, $eos_project_prefix) === 0){ // TODO: get the owner of the top level project dir
		    $rel = substr($eosPath,strlen($eos_project_prefix));
			$tokens = explode("/",$rel);
			$prjname = $tokens[0];
			if(strlen($prjname) === 1) { // means we hit a new project space with letter prefix
				$prjname = $tokens[1];
			}
			$user=self::getUserForProjectName($prjname);
			
			if (!$user) { 
				return false; 
			} 
			
			EosCacheManager::setOwner($eosPath, $user);	
			
			return $user;
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
			$user = \OCP\User::getUser();
			if($user){
				$uidAndGid = self::getUidAndGid($user);
				if (!$uidAndGid) {
					return false;
				}
				return $uidAndGid;
			}
		} 
		
		return false;
	}
	
	public static function isSharedLinkGuest()
	{
		$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
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
		
		// for public folder creation the token is passed in 'token' param
		if(!$token && isset($_POST['token']))
		{
			$token = $_POST['token'];
		}

		if(!$token && strpos($uri, 'gallery') !== FALSE)
		{
			$parts = explode('/', $uri);
			if(count($parts) < 5)
			{
				return false;
			}
			
			$token = $parts[4]; // Although in the web browser it appears as token#, the request sends just the token
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

	// it return the id and gid of a normal user or false in other case, including the id is 0 (root) to avoid security leaks
	public static function getUidAndGid($username) { // VERIFIED
		
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
		if($cached) {
			return $cached;
		}
		
		$cmd     = "id " . $username;
		$result  = null;
		$errcode = null;
		exec($cmd, $result, $errcode);
		$list = array();
		if ($errcode === 0) { // the user exists else it not exists
			//\OCP\Util::writeLog("DEBUGID", $username . "----" . $output, \OCP\Util::ERROR);
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
		EosCacheManager::setUidAndGid($username, $list);
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
	public static function addUserToAcl($from, $to, $fileId, $ocPerm, $type) {
		

		$data = self::getFileById($fileId);
		if(!$data) {
			return false;
		}
	    	$eosPath = $data["eospath"];
		$eosPathEscaped = escapeshellarg($eosPath);

		$projectjail = null;
		$projectInfo = self::getProjectInfoForUser($from);
		if($projectInfo) {
			$projectjail = self::getEosProjectPrefix() . "/" . $projectInfo['path'];
		}

		// do not allow shares above user home directory. Improve to not allow self home dir.
		$eosprefix = self::getEosPrefix();
		$eosjail = $eosprefix . $from[0] . "/" . $from . "/";
		if($projectjail) {
			if (strpos($eosPath, $eosjail) !== 0 && strpos($eosPath, $projectjail !==0)) {
				\OCP\Util::writeLog('EOS', "SECURITY PROBLEM (outside project and user paths). user $from tried to share the folder $eosPath with $to", \OCP\Util::ERROR);
				return false;
			}
		} else {
			if (strpos($eosPath, $eosjail) !== 0 ) {
				\OCP\Util::writeLog('EOS', "SECURITY PROBLEM (outside user path). user $from tried to share the folder $eosPath with $to", \OCP\Util::ERROR);
				return false;
			}
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
		if ($errcode === 0 && $result) {
			return true;
		} else {
			return false;
		}
	}

	public static function changePermAcl($from, $to, $fileid, $permissions, $type){
		
		$data = self::getFileById($fileid);
		if(!$data) {
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
		list($result, $errcode) = EosCmd::exec($changeSysAcl);
		if ($errcode !== 0) {
			return false;
		}
	}

	// return the acl in Oc format => 15 or 1 or 31
	public static function getAclPerm($from , $to, $fileid){
		$data = self::getFileById($fileid);
		if(!$data) {
			return false;
		}
		
		$ocacl = self::toOcAcl($data["sys.acl"]);
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
	public static function toOcAcl($sysAcl){ // VERIFIED
		$ocAcl = array();
		$usersSysAcl = array();

		$parts = explode(",", $sysAcl);
		if($parts) {
		    foreach($parts as $user) {
		        $data = explode(":",$user);
		        if(count($data) >= 3) {
		            $usersSysAcl[] = $data;
		        }
		    }
		}
		
		foreach($usersSysAcl as $user) {
		    $ocAcl[$user[1]] = array("type"=>$user[0], "ocperm"=>self::toOcPerm($user[2]), "eosperm"=>$user[2]);
		}
		
		return $ocAcl;
	}
	
	// transform the ocacl to eosacl 
	public static function toEosAcl($ocAcl){ // VERIFIED
		$sysAcl = array();
		$loggedUser = \OCP\User::getUser();
		foreach($ocAcl as $username=>$data) {
		  if($username == $loggedUser) {
		  	$entry = "u:$loggedUser:rwx!m";
		  	$sysAcl[] = $entry;
		  } else {
		  	if($data["ocperm"] !== 0) {
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
	// due to we dont have the path we use the root user(0,0) to obtain the info
	public static function getFileById($id){


	       #ob_start();
	       #var_dump(debug_backtrace());
	       #$KUBA_backtrace=ob_get_contents();
	       #ob_end_clean();
	       #\OCP\Util::writeLog('KUBA',"BACKTRACE" .  __FUNCTION__ . "($KUBA_backtrace)", \OCP\Util::ERROR);

#	       foreach (debug_backtrace() as $KUBA_b)
#		 {
#                  ob_start();
#	          var_dump($KUBA_b);
#	          $KUBA_bb=ob_get_contents();
#	          ob_end_clean();
#		   \OCP\Util::writeLog('KUBA',"BACKTRACE" .  __FUNCTION__ . "($KUBA_bb)", \OCP\Util::ERROR);
#		 }

		$cached = EosCacheManager::getFileById($id);
		if($cached) {
			return $cached;
		}
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());
		$fileinfo = "eos -b -r $uid $gid  file info  inode:" . $id . " -m";
		list($result, $errcode) = EosCmd::exec($fileinfo);
		if ($errcode === 0 && $result) {
			$line_to_parse = $result[0];
			$data          = EosParser::parseFileInfoMonitorMode($line_to_parse);
			EosCacheManager::setFileById($id, $data);
			
			return $data;
		}
		return null;
	}

	// get the file/dir metadata by his eospath
	// due to we dont have the path we use the root user to obtain the info
	public static function getFileByEosPath($eospath){
		
		$eospath = rtrim($eospath, "/");
		
		$cached = EosCacheManager::getFileByEosPath($eospath);
		if($cached) {
			return $cached;
		}
		
		$eospathEscaped = escapeshellarg($eospath);
		
		list($uid, $gid) = self::getEosRole($eospath, false);
		$fileinfo = "eos -b -r $uid $gid file info $eospathEscaped -m";
		list($result, $errcode) = EosCmd::exec($fileinfo);
		if ($errcode === 0 && $result) {
			$line_to_parse = $result[0];
			$data          = EosParser::parseFileInfoMonitorMode($line_to_parse);
			//$data['permissions'] = 31;
			EosCacheManager::setFileByEosPath($eospath, $data);
			
			return $data;
		}
		return null;
	}
	
	// checks if a user is member or not of an egroup
	// this command is executed agains the slave as it is very aggresive
	public static function isMemberOfEGroup($username, $egroup) {
		$eosSlaveInstance = \OCP\Config::getSystemValue("eos_slave_mgm_url");
		$uidAndGid = self::getUidAndGid($username);
		if (!$uidAndGid) {
			return false;
		}
		list($uid, $gid) = $uidAndGid;
		$egroupEscaped = escapeshellarg($egroup);
		$member = "eos -b -r $uid $gid member $egroupEscaped";
		list($result, $errcode) = EosCmd::exec($member, $eosSlaveInstance);
                if ($errcode === 0 && $result) {
                        $line_to_parse = $result[0];
                        $data = EosParser::parseMember($line_to_parse);
                        return $data;
                }
                return false;
	}

	public static function getMimeType($path, $type){
		$pathinfo = pathinfo($path);
		if($type === "folder") {
			return "httpd/unix-directory";
		} else {
		    $mime_types = array(
			
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
			    'docx' => 'application/msword',
			    'rtf' => 'application/rtf',
			    'xls' => 'application/vnd.ms-excel',
			    'xlsx' => 'application/vnd.ms-excel',
			    'ppt' => 'application/vnd.ms-powerpoint',
			    'pptx' => 'application/vnd.ms-powerpoint',

			    // open office, open with office 365
			    //'odt' => 'application/msword',
			    //'ods' => 'application/vnd.ms-excel',
	             );
		    
			$val = explode('.', $path);
		        $ext = strtolower(array_pop($val));
		        if (array_key_exists($ext, $mime_types)) {
		        	return $mime_types[$ext];
	        	}
            		return 'application/octet-stream';
		}
	}

	// This propagates permission X to parent when you share a folder. This is because of desktop sync clients need to browse the tree to find your shared directory.
	/*public static function propagatePermissionXToParents($eospath){
		
		$eosprefix = self::getEosPrefix(); // like /eos/devbox/user/
		$rest = substr($eospath, strlen($eosprefix));
		$parts = explode("/", $rest);
		$letter = $parts[0];
		$username = $parts[1];
		$rootFolderAfterUsername = isset($parts[2]) ? $parts[2] : ""; 
		$folderToApplyPermissions = $eosprefix .$letter ."/". $username. "/" . $rootFolderAfterUsername;
		//list($uid, $gid) = self::getEosRole($eospath, false);
		$uid = 0; $gid = 0; // users has !m bit sin sys.acl so they cannot chmod only root can
		$result   = null;
		$errcode  = null;
		$cmd = "eos -b -r $uid $gid chmod -r 2744 $folderToApplyPermissions";
		exec($cmd, $result, $errcode);
		if ($errcode !== 0) {
			\OCP\Util::writeLog('eos', "eoschmod $cmd $errcode", \OCP\Util::ERROR);
			return false;
		}
		$result   = null;
		$errcode  = null;
		$rootFolder = $eosprefix .$letter ."/". $username;
		$cmd = "eos -b -r $uid $gid chmod -r 2744 $rootFolder";
		exec($cmd, $result, $errcode);
		if ($errcode !== 0) {
			\OCP\Util::writeLog('eos', "eoschmod rootfolder $cmd $errcode", \OCP\Util::ERROR);
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
	*/

	// the previous version was trying to modifiy permissions this one changes the sys acl
	
	public static function propagatePermissionXToParents($filedata, $to, $type){
	  // type == 'u' -- specifies that $to is a user name
          // type == 'egroup' -- specifies that $to is egroup name
          // other values of type are not allowed

  	        if (!in_array($type, array('u','egroup'))) {
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
		if(!$data) {
			return false;
		}
		$sysAcl = $data["sys.acl"];
		if(strpos($sysAcl, $to) !== false) { // if the user is already in the acl we dont do nothing
			return true;
		}
		$newSysAcl = implode(",", array($sysAcl, "$type:$to:x"));
		$uid = 0; $gid = 0; // root is the only one allowed to change permissions
		$cmd = "eos -b -r $uid $gid attr set sys.acl=$newSysAcl '$rootFolder'";
		list($result, $errcode) = EosCmd::exec($cmd, $result, $errcode);
		if ($errcode !== 0) {
			return false;
		}
		return true;
	}

	// return the list of EGroups this member is part of, but NOT all, just the ones that appear in share database.
	public static function getEGroups($username) {
		return \OC\LDAPCache\LDAPCacheManager::getUserEGroups($username);
	}
		
	public static function createVersion($eosPath) {
		
		EosCacheManager::clearFileByEosPath($eosPath);
		
		$eosPathEscaped = escapeshellarg($eosPath);
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());
		//$uid = 0; $gid = 0; // root is the only one allowed to change permissions
		$cmd = "eos -b -r $uid $gid file version $eosPathEscaped";
        list($result, $errcode) = EosCmd::exec($cmd);
        if ($errcode !== 0) {
        	return false;
        }
        return true;
	}
	
	public static function getVersionFolderFromFileID($id, $createVersion = true)
	{
		$meta = self::getFileById($id);
		// here we can receive the file to convert to version folder
		// or the version folder itself
		// so we need to check carefuly
		$eos_version_regex = \OCP\Config::getSystemValue("eos_version_regex");
		// if file is already version folder we return that inode
		if (preg_match("|".$eos_version_regex."|", basename($meta["eospath"])) ) {
			return $meta;
		} else {
			$dirname = dirname($meta['eospath']);
			$basename = basename($meta['eospath']);
			// We need to handle the case where the file is already a version.
			// In that case the version folder is the parent.
			$versionFolder = $dirname . "/.sys.v#." . $basename;
			if (preg_match("|".$eos_version_regex."|", $dirname) ) {
				$versionFolder = $dirname;
			}
		
		
			$versionInfo = self::getFileByEosPath($versionFolder);
			if(!$versionInfo) {
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
	
	// this function returns the fileid of the versions folder of a file
	// if versions folder does not exist, it will create it
	public static function getVersionsFolderIDFromFileID($id, $createVersion = true) {
		$version = self::getVersionFolderFromFileID($id, $createVersion);
		if($version)
		{
			return $version['fileid'];
		}
		
		return null;
	}
	// given the fileid of a versions folder, returns the metadata of the real file
	public static function getFileMetaFromVersionsFolderID($id) {
		$meta = self::getFileById($id);
		$dirname = dirname($meta['eospath']);
		$basename = basename($meta['eospath']);
		$realfile = $dirname . "/" . substr($basename, 8);
		$realfilemeta = self::getFileByEosPath($realfile);
		return $realfilemeta;
	}
	
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
	
	// Given a username, it returns the name of the project the user is the owner.
	// If the user is not the owner of a project, then it returns null.
	// Example. given boxscv returns cernbox.
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
	
	public static function getProjectInfoForUser($user)
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
				$data['name'] = $project;
				return $data;
			}
		}
		
		return false;
	}
	
	public static function isProjectURIPath($uri_path) {
		// uri paths always start with leading slash (e.g. ?dir=/bla)
		$uri_path = trim($uri_path, '/');
		if (startsWith ( $uri_path, '/' )) {
			$topdir = explode ( "/", $uri_path ) [1];
		} else {
			$topdir = explode ( "/", $uri_path ) [0];
		}
		
		return startsWith ( $topdir, '  project ' );
	}
	
	public static function isSharedURIPath($uri_path) {
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
		if (startsWith ( $uri_path, '/' )) {
			$topdir = explode ( "/", $uri_path ) [1];
		} else {
			$topdir = explode ( "/", $uri_path ) [0];
		}
		
		$parts = explode ( " ", $topdir );
		if (count ( $parts ) < 2) {
			return false;
		}
		$marker = end ( $parts );
		return preg_match ( "/[(][#](\d{3,})[)]/", $marker ); // we match at least 3 digits enclosed within our marker: (#123)
	}

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
		if ($deep === true) {
			$getFolderContents = "eos -b -r $uid $gid  find --fileinfo --maxdepth 10 $eosPathEscaped";
		} else {
			$getFolderContents = "eos -b -r $uid $gid  find --fileinfo --maxdepth 1 $eosPathEscaped";
		}
		
		$files = array();
		list($result, $errcode) = EosCmd::exec($getFolderContents);
		if ($errcode !== 0) {
			// Safe bet: always throw permission denied if the call to eos fails.
			throw new \OCP\Files\NotPermittedException("cannot list contents of eos folder");
		}

		// we need to obtain the tree size performing an 'ls' command and merging attributes
		$ls = "eos -b -r $uid $gid  ls -la $eosPathEscaped";
		list($lsresult, $errcode) = EosCmd::exec($ls);
		if ($errcode !== 0) {
			return $files;
		}
		// $map_filename_size is a map of filenames (just the base name, not full eos paths) and its tree size.
		$map_filename_size = array();
		foreach($lsresult as $direntry) {
			$direntry = preg_replace('/\s+/', ' ',$direntry); // replace multiple spaces by one
			$elems = explode(" ", $direntry);
			$size = $elems[4];
			$filename_elems = array_slice($elems, 8);
			$filename = implode(' ', $filename_elems);
			$map_filename_size[$filename] = $size;
		}
		
		/*
		 * This array is used to pass extra attributes to a file/folder
		 * The keys are eos paths and the values are arrays of key-value pairs (attr/value)
		 * Example: ["/eos/scratch/user/o/ourense/photos/1.png" => ["cboxid" => 456123]]
		 */
		$extraAttrs = array();
		
		$hiddenFolder = preg_match("|".$eos_hide_regex."|", $eosPath);
		
		foreach ($result as $line_to_parse) {
			$data = EosParser::parseFileInfoMonitorMode($line_to_parse);
			if( $data["path"] !== false && rtrim($data["eospath"],"/") !== rtrim($eosPath,"/") ){
				
				if($additionalParameterCallback !== null)
				{
					$additionalParameterCallback($data);	
				}
				
				//$data["storage"] = $this->storageId;
				//$data["permissions"] = 31;
		
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
					// HUGO we add the eostreesize attribute to the information
					$data['eostreesize'] = isset($map_filename_size[$data['name']]) ? (int)$map_filename_size[$data['name']] : 0;	
					if($data['eostype'] === 'folder') {
						$data['size'] = $data['eostreesize'];
					}
					$files[$data['eospath']] = $data;
				}
				
				//$itemEosPath = escapeshellarg(rtrim($data['eospath'], '/'));

				
				// FIX: if the current instance is not EOSUSER means that
				// the request comes from EOS Browser so we only give RO permissions
				// This is a horrible hack, it hurts the eyes ... this cannot be in 9.
				$loadedInstance =  EosInstanceManager::getUserInstance();
				\OCP\Util::writeLog('EOSINSTANCE', "getFolderContents: loaded instance is: " . $loadedInstance, \OCP\Util::ERROR);
				if ($loadedInstance !== "-2" ) {
					$data['permissions'] = 1;
				}
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
		EosCacheManager::setFileInfoByEosPath(($deep? 10 : 1), $eosPathEscaped, $result);
		
		return $result;
	}
	
	const STAT_FILE_NOT_EXIST = 14;
	const STAT_FILE_EXIST = 0;
	
	public static function statFile($eosPath)
	{
		list($uid, $gid) = self::getUidAndGid(\OC_User::getUser());
		$eosPathEscaped = escapeshellarg($eosPath);
		$cmd = "eos -r $uid $gid stat $eosPathEscaped";
		list($result, $errorCode) = EosCmd::exec($cmd);
		return $errorCode;
	}
	
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
	
	// used with the clean_expired_users cronjob.
	public static function lsNoUserContext($eosPath)
	{
		$uid = 0;
		$gid = 0;
		$eosPath = escapeshellarg($eosPath);
		$cmd = "eos -r $uid $gid ls $eosPath";
		list($result, $errCode) = EosCmd::exec($cmd);
		if($errCode === 0)
		{
			return $result;
		}
	
		return false;
	}
	
	public static function createVersionFolder($eosPath)
	{
		$user = self::getOwner($eosPath);
		list($uid, $gid) = self::getUidAndGid($user);
		
		if(!$uid || !$gid)
		{
			return false;
		}
		
		$dir = dirname($eosPath);
		$file = basename($eosPath);
		$versionFolder = $dir . "/.sys.v#." . $file;
		$versionFolder = escapeshellarg($versionFolder);
		$cmd = "eos -b -r 0 0 mkdir -p $versionFolder";
		list($result, $errcode) = EosCmd::exec($cmd);
	
		if($errcode !== 0)
		{
			return false;
		}
		
		$cmd2 = "eos -b -r 0 0 chown -r $uid:$gid $versionFolder";
		list($result, $errcode) = EosCmd::exec($cmd2);
		
		if($errcode !== 0)
		{
			return false;
		}
	
		return $versionFolder;
	}
	
	public static function createSymLink($linkDestination, $linkSource)
	{
		$linkDestination = escapeshellarg($linkDestination);
		$linkSource = escapeshellarg($linkSource);
		$cmd = "eos -b -r 0 0 ln $linkDestination $linkSource";
		list(, $errcode) = EosCmd::exec($cmd);
		
		return $errcode === 0;
	}
	
	public static function removeSymLink($link)
	{
		$link = escapeshellarg($link);
		$cmd = "eos -b -r 0 0 rm $link";
		list(, $errcode) = EosCmd::exec($cmd);
		return $errcode === 0;
	}
}
