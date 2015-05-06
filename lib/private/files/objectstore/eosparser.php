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

		foreach ($fields as $value) {
			$splitted           = explode("=", $value);
			$info[$splitted[0]] = implode("=",array_slice($splitted,1));
		}
		$pathinfo                 = pathinfo($info["file"]);
		$data                     = array();
		$data['fileid']           = $info['ino'];
		$data['etag']             = $info['etag'];
		$data['mtime']            = $info['mtime'];
		$data['storage_mtime']    = $info['mtime'];//KUBA: what is a difference: mtime vs storage_mtime
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
		$data["eospath"]          = $info["file"];
		$data["eosuid"]			  = $info["uid"];
		$data["eosmode"]		  = $info["mode"];
		$data["eostype"]		  = isset($info["container"]) ? 'folder' : 'file';
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


}
