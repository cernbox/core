<?php
namespace OC\Files\ObjectStore;

class EosParser {

	public static function parseFileInfoMonitorMode($line_to_parse) {
		$fields = explode(" ", $line_to_parse);
		$keylength = explode("=",$fields[0]);
		$keylength = $keylength[1];
		$realFile = explode("=",$fields[1]);
		$realFile = implode("=",array_slice($realFile,1));
		$total = strlen($realFile);
		if($total != $keylength){
		  $index = 2; $stop = false;
		  while(!$stop){
		    $realFile .= " " . $fields[$index];
		    $total += strlen($fields[$index]) + 1;
		    unset($fields[$index]);
		    $index++;
		    if($total == $keylength){
		      $stop = true;
		    }
		  }
		  $fields[1] = "file=".$realFile;	
		  $fields = array_values($fields);
		}
		// wee need to find the sys.acl manually
		// to do that we need to search the field=attrn=sys.acl
		$indexSysAcl = -1;
		foreach ($fields as $i=>$v) {
			if($v === "xattrn=sys.acl"){
				$indexSysAcl = $i;
			}
		}
		$indexSysOwnerAuth = -1;
		foreach ($fields as $i=>$v) {
			if($v === "xattrn=sys.owner.auth"){
				$indexSysOwnerAuth = $i;
			}
		}
		
		$info = [];

		foreach ($fields as $value) {
			$splitted           = explode("=", $value);
			$info[$splitted[0]] = implode("=",array_slice($splitted,1));
		}
		$pathinfo                 = pathinfo($info["file"]);
		$data                     = array();
		$data['fileid']           = $info['ino'];
		$data['etag']             = $info['etag'];
		
		$mtimeTest = $info['mtime'];
		if($mtimeTest === '0.0')
		{
			$data['mtime']            = $info['ctime'];
			$data['storage_mtime']    = $info['ctime'];//KUBA: what is a difference: mtime vs storage_mtime
		}
		else
		{
			$data['mtime']            = $mtimeTest;
			$data['storage_mtime']    = $mtimeTest;//KUBA: what is a difference: mtime vs storage_mtime
		}
		$data['size']             = isset($info['size']) ? $info['size'] : 0;
		$data['name']             = $pathinfo['basename'];
		// if the path is in the trashbin we return false
		$eos_recycle_dir = EosUtil::getEosRecycleDir();
		$data['path']             = strpos($info["file"], $eos_recycle_dir) === 0 ? false:  EosProxy::toOc($info["file"]);
		$data['path_hash']        = md5($data["path"]);
		$data['permissions']      = 0; //default to 0 to avoid security leaks. KUBA: requires mapping to and from EOS extended attributes (ACL)
		$data['parent']           = $info["pid"];//KUBA: needed?
		$data['encrypted']        = 0;
		$data['unencrypted_size'] = isset($info['size']) ? $info['size'] : 0;//KUBA: needed?
		$data["eospath"]          = rtrim($info["file"], '/');
		$data["eosuid"]			  = $info["uid"];
		$data["eosmode"]		  = $info["mode"];
		$data["eostype"]		  = isset($info["container"]) ? 'folder' : 'file';
		$data["eosmgmurl"] = EosUtil::getEosMgmUrl();
		/*
		if(isset($info['xattrn']) && isset($info['xattrv']) && $info['xattrn'] === 'user.acl'){
			$data["eosacl"] = $info['xattrv'];
		}
		*/
		if($indexSysAcl !== -1) {
			$xattrv = explode("=",$fields[$indexSysAcl+1]);
			$data["sys.acl"] = $xattrv[1];
		} else {
			$data["sys.acl"] = "";
		}
		
		$currentUser = \OC_User::getUser();
		$linkShare = false;
		if(!$currentUser)
		{
			$currentUser = EosUtil::isSharedLinkGuest();
			$linkShare = true;
			$data['permissions'] = 31;
		}
		
		if(!$linkShare)
		{
			$isOwner = EosUtil::getOwner($data['eospath']) === $currentUser;
			if($data['eostype'] === 'file' || strpos($data['eospath'], '.sys.v#') !== FALSE) //Files and sys folder
			{
				if($isOwner)
				{
					$data['permissions'] = 31; // Read + write + share
				}
				else
				{
					$data['permissions'] = 15; // Read + write
				}
			}
			else // Folders
			{
				// check first that the folder is under a project space and we are the owner
				// of such project space
				$eos_project_prefix = EosUtil::getEosProjectPrefix();
				if (strpos($data['eospath'], $eos_project_prefix) === 0) {
					$rest = trim(substr($data['eospath'], strlen($eos_project_prefix)), '/');	
					$rest = substr($rest, 2); // remove "letter/"
					$parts = explode("/", $rest);
					$projectName = $parts[0];
					$projectInfo = EosUtil::getProjectInfoForUser($currentUser);
					if($projectInfo) {
						$data['permissions'] = 31;
					}
				} else {
					$groups = \OC\LDAPCache\LDAPCacheManager::getUserEGroups($currentUser);
					$ocPerm = EosUtil::toOcAcl($data['sys.acl']);
					
					$isInACL = false;
					$aclMember = '';
					if(isset($ocPerm[$currentUser]))
					{
						$isInACL = true;
						$aclMember = $currentUser;
					}
					else
					{
						$highestOcPerm = 0;
						foreach($groups as $group)
						{
							if(isset($ocPerm[$group]) && $ocPerm[$group]['ocperm'] > $highestOcPerm)
							{
								$isInACL = true;
								$aclMember = $group;
								$highestOcPerm = $ocPerm[$group]['ocperm'];
							}
						}
					}
						
					if($isInACL)
					{
						if($isOwner) //is the owner, so give share permissions
						{
							$permissions = 31;
						}
						else
						{
							$permissions = $ocPerm[$aclMember]['ocperm'];
						}
						$data['permissions'] = $permissions;
					}

					}
			}
		}
		
		if($indexSysOwnerAuth !== -1) {
			$xattrv = explode("=",$fields[$indexSysOwnerAuth+1]);
			$data["sys.owner.auth"] = $xattrv[1];
		} else {
			$data["sys.owner.auth"] = "";
		}
		$data['mimetype']         = EosUtil::getMimeType($data["eospath"],$data["eostype"]);
		return $data;
	}

	//eos recycle ls -m
	public static function parseRecycleLsMonitorMode($line_to_parse) {

		// we need to be careful with extra whitespace after recyle=ls
		/*
		Array
		(
		    [recycle] => ls
		    [] => 
		    [recycle-bin] => /eos/devbox/proc/recycle/
		    [uid] => labrador
		    [gid] => it
		    [size] => 0
		    [deletion-time] => 1414600390
		    [type] => file
		    [keylength.restore-path] => 48
		    [restore-path] => /eos/devbox/user/l/labrador/YYY/ POCO MOCHP PIKO
		    [restore-key] => 0000000000017bd3
		)
		*/
		$fields = explode(" ", $line_to_parse);
		$keylength = explode("=",$fields[8]);
		$keylength = $keylength[1];
		$realFile = explode("=",$fields[9]);
		$realFile = implode("=",array_slice($realFile,1));
		$total = strlen($realFile);
		if($total != $keylength){
		  $index = 10; $stop = false;
		  while(!$stop){
		    $realFile .= " " . $fields[$index];
		    $total += strlen($fields[$index]) + 1;
		    unset($fields[$index]);
		    $index++;
		    if($total == $keylength){
		      $stop = true;
		    }
		  }
		  $fields[9] = "restore-path=" . $realFile;	
		  $fields = array_values($fields);
		}
		foreach ($fields as $value) {
			$splitted           = explode("=", $value);
			$info[$splitted[0]] = implode("=",array_slice($splitted,1));
		}
		return $info;
	}
	
	//Parse eos -b -r uid gid member egroup command
        public static function parseMember($line_to_parse) {

                // we need to be careful with extra whitespace after recyle=ls
                /*
                Array
                (
                    [recycle] => ls
                    [] => 
                    [recycle-bin] => /eos/devbox/proc/recycle/
                    [uid] => labrador
                    [gid] => it
                    [size] => 0
                    [deletion-time] => 1414600390
                    [type] => file
                    [keylength.restore-path] => 48
                    [restore-path] => /eos/devbox/user/l/labrador/YYY/ POCO MOCHP PIKO
                    [restore-key] => 0000000000017bd3
                )
                */

                $fields = explode(" ", $line_to_parse);
                foreach ($fields as $value) {
                        $splitted = explode("=", $value);
                        if(count($splitted) > 1)
                        	$info[$splitted[0]] = $splitted[1];
                        else
                        	$info[$splitted[0]] = NULL;
                }
                return $info['member'] == "true" ? true : false;
	}


	public static function parseQuota($cmdResult)
	{
		$line = $cmdResult;
		if(is_array($cmdResult) && count($cmdResult) > 0)
		{
			$line = $cmdResult[0];
		}
		else
		{
			return
			[
					'free' => -1,
					'used' => 0,
					'total' => 0,
					'relative' => 0,
					'usedfiles' => 0,
					'maxfiles' => 0,
			];
		}
		
		$line = explode(' ', $line);
		$data = [];
		foreach($line as $token)
		{
			$parts = explode('=', $token);
			$data[$parts[0]] = $parts[1];
		}
		
		if(!isset($data['usedlogicalbytes']) || !isset($data['maxlogicalbytes']))
		{
			return 
			[
				'free' => -1,
				'used' => 0,
				'total' => 0,
				'relative' => 0,
				'usedfiles' => 0,
				'maxfiles' => 0,
			];
		}
		
		$used = intval($data['usedlogicalbytes']);
		$total = intval($data['maxlogicalbytes']);
		
		return 
		[
				'free' => $total - $used,
				'used' => $data['usedlogicalbytes'],
				'total' => $data['maxlogicalbytes'],
				'relative' => $data['percentageusedbytes'],
				'usedfiles' => $data['usedfiles'],
				'maxfiles' => $data['maxfiles'],
		];
	}
}
