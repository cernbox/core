<?php
namespace OC\Files\ObjectStore;

class EosProxy {

	// if the storageId is a user storage we return the username else false (object::store:$eos_prefix)
	// when we are creating files (the storage id is still not defined) we receive the username of logged user
	public static function getUsernameFromStorageId($storageId) {
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
	
	private static function toGlobalEos($ocPath)
	{
		$instanceId = EosInstanceManager::getUserInstance();
		$instanceData = EosInstanceManager::getMappingById($instanceId);
		
		if(strpos($ocPath, 'files') === 0)
		{
			$ocPath = substr($ocPath, 5);
		}
		
		$ocPath = trim($ocPath, '/');
		
		return rtrim($instanceData['user_root_dir'], '/') . '/' . $ocPath;
	}
	public static function toEos($ocPath, $storageId) {
		$eosPath = self::_toEos($ocPath, $storageId);	
		\OCP\Util::writeLog('EOS PROXY', 
		"OC($ocPath) => EOS($eosPath)",  \OCP\Util::ERROR);
		return $eosPath;
	}
	
	// ocPath is local oc like "" or "files" or "files/A/B/test_file./txt"
	// storageId is object::store:/eos/dev/user/ or object::user:labrador
	public static function _toEos($ocPath, $storageId) {//ocPath like files/abc.txt or cache/557 or files or ""
		
		if(EosInstanceManager::isInGlobalInstance())
		{
			return self::toGlobalEos($ocPath);
		}
		
		$eos_project_prefix = EosUtil::getEosProjectPrefix();
		$eos_prefix   = EosUtil::getEosPrefix();
		$eos_meta_dir = EosUtil::getEosMetaDir();
		$username     = self::getUsernameFromStorageId($storageId);
		if ($username === false) {
			return false;
			///$eosPath = $eos_prefix;
			//return $eosPath;
		}
		// if the user is a project owner,instead send him to his homedir we send him to the project dir.
		
		$tempOcPath = $ocPath;
		if(strpos($ocPath, 'files') === 0)
		{
			$tempOcPath = substr($tempOcPath, 5);
		}
		
		$tempOcPath = trim($tempOcPath, '/');
		
		if(strpos($tempOcPath, '  project') === 0)
		{
			$len = strlen('  project ');
			$nextSlash = strpos($tempOcPath, '/');
			if($nextSlash === FALSE)
			{
				$nextSlash = strlen($tempOcPath);
			}
			
			$project = substr($tempOcPath, $len, $nextSlash - $len);
			$pathLeft = substr($tempOcPath, $nextSlash);
			$relativePath = EosUtil::getProjectRelativePath($project);
			
			if($relativePath) {
				return (rtrim($eos_project_prefix, '/') . '/' . trim($relativePath, '/') . '/' . trim($pathLeft, '/'));
			} else {
				return false;
			}
		}
		
		$projectInfo = EosUtil::getProjectInfoForUser($username);
		if($projectInfo !== false) {
			$projectPath = $projectInfo['path'];
			\OCP\Util::writeLog('EOS PROXY', "ENTRO $projectPath", \OCP\Util::ERROR);
			$project_path=  rtrim($eos_project_prefix, '/') . '/' . rtrim($projectPath, '/') . '/' . ltrim(substr($ocPath,6), '/'); #KUBA: added /
			return $project_path;
		}
		

		if ($ocPath === "") {
			$eosPath = $eos_prefix . substr($username, 0, 1) . "/" . $username . "/";
			return $eosPath;
		}
		//we must be cautious becasue there is files_encryption, that is the reason we do this check
		$condition = false;
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
	
	private static function toGlobalOc($eosPath)
	{
		$instanceId = EosInstanceManager::getUserInstance();
		$instanceData = EosInstanceManager::getMappingById($instanceId);
		$userRootDir = $instanceData['user_root_dir'];
		
		if(strpos($eosPath, $userRootDir) !== 0)
		{
			\OCP\Util::writeLog('EOS PROXY', 
					"The requested EOS Path ($eosPath) does not match the user's current instance: $userRootDir",
					\OCP\Util::ERROR);
			
			return "files/"; //Redirect to instance's home directory
		}
		
		$prefixLen = strlen($userRootDir);
		$ocPath = trim(substr($eosPath, $prefixLen), '/');
		
		return 'files/' . $ocPath;
	}

	public static function toOC($eosPath) {
		$ocPath = self::_toOC($eosPath);
		\OCP\Util::writeLog('EOS PROXY', 
		"EOS($eosPath) => OC($ocPath)",  \OCP\Util::ERROR);
		return $ocPath;
	}
	// EOS give us the path ended with a / so perfect
	public static function _toOc($eosPath) {//eosPath like /eos/dev/user/ or /eos/dev/user/l/labrador/abc.txt or /eos/dev/user/.metacernbox/l/labrador/cache/abc.txt or /eos/dev/user/.metacernbox/thumbnails/abc.txt
		
		if(EosInstanceManager::isInGlobalInstance())
		{
			return self::toGlobalOc($eosPath);
		}
		
		$eos_project_prefix = EosUtil::getEosProjectPrefix();
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
		} else if (strpos($eosPath, $eos_project_prefix) === 0) {
			$len_prefix = strlen($eos_project_prefix);
			$rel        = substr($eosPath, $len_prefix);
			
			$projectRelName = EosUtil::getProjectNameForPath($rel);
			if($projectRelName)
			{
				$rel = trim($rel);
				$pathLeft = substr($rel, strlen($projectRelName[1]));
				// if the loggedin user is the owner of the project we
				// have to expose the project spaces as home directory,
				// thus we cannot send the "project  <projid>" prefix.
				//$projectOwner = $projectRelName[1];
				//$ocPath = 'files/' . $projectRelName[0] . '/' . $pathLeft;
				$ocPath = 'files/'  . $pathLeft;
				return $ocPath;
			}
			\OCP\Util::writeLog('EOSPROXY','Cannot find project mapping for path ' . $eosPath, \OCP\Util::ERROR);
			return false;
			
			/*$splitted   = explode("/", $rel);
			$projectname     = $splitted[0];
			$ocPath = "files/" . substr($eosPath, strlen($eos_project_prefix . $projectname));
			$ocPath = rtrim($ocPath, "/");
			return $ocPath;*/
		} else {
			\OCP\Util::writeLog("eos", "The eos_prefix,eos_meta_dir,eos_recycle_dir does not match this path: $eosPath", \OCP\Util::ERROR);
			return false;
		}
	}
}
