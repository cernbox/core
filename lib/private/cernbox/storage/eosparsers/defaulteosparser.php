<?php
namespace OC\Cernbox\Storage\EosParsers;

use OC\Cernbox\Storage\AbstractEosParser;
use OC\Cernbox\Storage\EosUtil;
use OC\Cernbox\Storage\EosProxy;

class DefaultEosParser extends AbstractEosParser 
{
	public function parseFileInfo($line_to_parse) 
	{
		$rawMap = explode(' ', $line_to_parse);
		
		$name = $this->extractFileName($rawMap);
		$map = $this->buildAttributeMap($rawMap);
		
		$pathinfo                 = pathinfo($name);
		$data                     = [];
		$data['fileid']           = $this->parseField($map, 'ino', 0);
		$data['etag']             = $this->parseField($map, 'etag', 0);
				
		$data['mtime'] = $this->getCorrectMTime($map);
		$data['storage_mtime'] = $data['mtime'];
		
		$data['size']             = $this->parseField($map, 'size', 0);
		$data['name']             = $pathinfo['basename'];
		// if the path is in the trashbin we return false
		$eos_recycle_dir = EosUtil::getEosRecycleDir();
		$data['path']             = strpos($name, $eos_recycle_dir) === 0 ? false:  EosProxy::toOc($name);
		$data['path_hash']        = md5($data["path"]);
		$data['parent']           = $this->parseField($map, 'pid', 0);//KUBA: needed?
		$data['encrypted']        = 0;
		$data['unencrypted_size'] = $data['size'];//KUBA: needed?
		$data["eospath"]          = rtrim($map["file"], '/');
		$data["eosuid"]			  = $this->parseField($map, 'uid', 0);
		$data["eosmode"]		  = $this->parseField($map, 'mode', '');
		$data["eostype"]		  = isset($map["container"]) ? 'folder' : 'file';
		$data['mimetype']         = EosUtil::getMimeType($data["eospath"],$data["eostype"]);
		$data["sys.acl"]		  = $this->parseField($map, 'sys.acl', '');
		$data["sys.owner.auth"]   = $this->parseField($map, 'sys.owner.auth', '');
		
		$aclMap = EosUtil::toOcAcl($data['sys.acl']);
		$currentUser = \OC_User::getUser();
		$pathOwner = EosUtil::getOwner($data['eospath']);
		
		if(!$currentUser)
		{
			$currentUser = EosUtil::isSharedLinkGuest();
		}
		
		// Attempt to set permissions based on current user
		if($currentUser)
		{
			if($currentUser === $pathOwner)
			{
				$data['permissions'] = \OCP\Constants::PERMISSION_ALL;
			}
			else if(isset($aclMap[$currentUser]))
			{
				$data['permissions'] = $aclMap[$currentUser]['ocperm'];
			}
		}
		else
		{
			$data['permissions']      = 0; //default to 0 to avoid security leaks.
		}
		
		return $data;
	}
	
	protected function getCorrectMTime($map)
	{
		$mtimeTest = $this->parseField($map, 'mtime', 0);
		if($mtimeTest === '0.0')
		{
			$mtimeTest = $this->parseField($map, 'ctime', 0);
		}
		
		return $mtimeTest;
	}

	//eos recycle ls -m
	public function parseRecycleFileInfo($line_to_parse) 
	{
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
		
		if($total != $keylength)
		{
		  	$index = 10; $stop = false;
		  	while(!$stop)
		  	{
		    	$realFile .= " " . $fields[$index];
		    	$total += strlen($fields[$index]) + 1;
		    	unset($fields[$index]);
		    	$index++;
		    	if($total == $keylength)
		    	{
		    		$stop = true;
		    	}
		  	}
		  	$fields[9] = "restore-path=" . $realFile;	
		  	$fields = array_values($fields);
		}
		
		$info = [];
		foreach ($fields as $value) 
		{
			$splitted           = explode("=", $value);
			$info[$splitted[0]] = implode("=",array_slice($splitted,1));
		}
		return $info;
	}
	
	//Parse eos -b -r uid gid member egroup command
    public static function parseMember($line_to_parse) 
    {
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
    	$info = [];
        foreach ($fields as $value) 
        {
        	$splitted = explode("=", $value);
        	if(count($splitted) > 1)
        	{
        		$info[$splitted[0]] = $splitted[1];
        	}
        	else
        	{
        		$info[$splitted[0]] = NULL;
        	}
        }
        return $info['member'] == "true" ? true : false;
	}


	public function parseQuota($cmdResult)
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
