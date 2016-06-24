<?php

namespace OC\Cernbox\Storage;

final class EosParser
{
	/**
	 * Parses the raw data from EOS into a map useable by owncloud core
	 * @param string $data The output line given by EOS
	 * @return array map of attributes => values
	 */
	public static function parseFileInfoMonitorMode($line)
	{
		$rawMap = explode(' ', $line);
		
		$name = self::extractFileName($rawMap);
		$map = self::buildAttributeMap($rawMap);
		
		$pathinfo                 = pathinfo($name);
		$data                     = [];
		$data['fileid']           = self::parseField($map, 'ino', 0);
		$data['etag']             = self::parseField($map, 'etag', 0);
		$data['mtime'] = self::getCorrectMTime($map);
		$data['storage_mtime'] = $data['mtime'];
		$data['size']             = self::parseField($map, 'size', 0);
		$data['name']             = $pathinfo['basename'];
		// if the path is in the trashbin we return false
		$data['path']             = strpos($name, EosUtil::getEosRecycleDir()) === 0 ? false:  EosProxy::toOc($name);
		$data['path_hash']        = md5($data["path"]);
		$data['parent']           = self::parseField($map, 'pid', 0);//KUBA: needed?
		$data['encrypted']        = 0;
		$data['unencrypted_size'] = $data['size'];//KUBA: needed?
		$data["eospath"]          = rtrim($name, '/');
		$data["eosuid"]			  = self::parseField($map, 'uid', 0);
		$data["eosmode"]		  = self::parseField($map, 'mode', '');
		$data["eostype"]		  = isset($map["container"]) ? 'folder' : 'file';
		$data['mimetype']         = EosUtil::getMimeType($data["eospath"],$data["eostype"]);
		$data["sys.acl"]		  = self::parseField($map, 'sys.acl', '');
		$data["sys.owner.auth"]   = self::parseField($map, 'sys.owner.auth', '');
		
		// Attempt to set permissions based on current user
		//default to 0 to avoid security leaks.
		$data['permissions'] = 0; 
		
		$aclMap = EosUtil::toOcAcl($data['sys.acl']);
		$currentUser = \OC_User::getUser();
		$pathOwner = EosUtil::getOwner($data['eospath']);
		
		if(!$currentUser)
		{
			$currentUser = EosUtil::isSharedLinkGuest();
		}
		
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
		
		return $data;
	}
	
	/**
	 * Parses the raw data from EOS recycle commands into a map useable by owncloud core
	 * @param string $data The output line given by EOS
	 * @return array map of attributes => values
	 */
	public static function parseMember($line)
	{
		$fields = explode(" ", $line);
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
	
	/**
	 * Parses the EOS answer when asking about the membership of an user to an e-group
	 * @param stirng $data The output line answered by EOS
	 * @return true|false whether the user is member or not of the e-group
	 */
	public static function parseQuota($line)
	{
		if(is_array($line) && count($line) > 0)
		{
			if(count($line) > 0)
			{
				$line = $line[0];
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
	
	/**
	 * Parses the raw data from EOS Quota into a map useable by owncloud core
	 * @param string $data The output line given by EOS
	 * @return array map of attributes => values
	 */
	public static function parseRecycleLsMonitorMode($line)
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
		$fields = explode(" ", $line);
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
	
	/**
	 * Builds and extract the name from the EOS answer. This function will remove
	 * the keylenght and file properties from the array
	 * @param array $rawMap array of EOS data splitted by white space
	 * @return string the name (full eos path) of the file
	 */
	private static function extractFileName(&$rawMap)
	{
		$keyLen = $rawMap[0];
		array_shift($rawMap);
		$keyLen = explode('=', $keyLen)[1];
	
		$name = $rawMap[0];
		array_shift($rawMap);
		$name = explode('=', $name)[1];
	
		// If the name contains spaces, they were splitted when creating the attribute map
		// we have to rebuild it
		$len = strlen($name);
		while($len < $keyLen)
		{
			$name .= ' ' . $rawMap[0];
			$len += strlen($rawMap[0]) + 1 ; //string len + 1 for white space
			array_shift($rawMap);
		}
	
		return $name;
	}
	
	/**
	 * Builds a map of attribute => value for the given file metadata.
	 * @param array $rawMap Raw EOS data splitted by white space
	 * @return array $map A map
	 */
	private static function buildAttributeMap($rawMap)
	{
		$map = [];
		$arrayLen = count($rawMap);
		for($i = 0; $i < $arrayLen; $i++)
		{
			$parts = explode('=', $rawMap[$i]);
			if($parts[0] === 'xattrn')
			{
				$key = $parts[1];
				$i++;
				$value = explode('=', $rawMap[$i])[1];
			}
			else
			{
				$key = $parts[0];
				$value = $parts[1];
			}
	
			$map[$key] = $value;
		}
	
		return $map;
	}
	
	/**
	 * Attempts to retrieve an attribute value from the attribute map. If this is not set,
	 * it will return a default value given as 3rd argument
	 * @param array $haystack The attribute map [property name => value]
	 * @param string $propertyName The name of the property we want to retrieve
	 * @param mixed $defaultValue The default value it should return in case the property is not found in the map
	 * @return mixed The property value or $defaultValue if the property could not be found in the map
	 */
	private static function parseField(array $haystack, $propertyName, $defaultValue = NULL)
	{
		if(isset($haystack[$propertyName]))
		{
			return $haystack[$propertyName];
		}
	
		return $defaultValue;
	}
	
	private static function getCorrectMTime($map)
	{
		$mtimeTest = self::parseField($map, 'mtime', 0);
		if($mtimeTest === '0.0')
		{
			$mtimeTest = self::parseField($map, 'ctime', 0);
		}
	
		return $mtimeTest;
	}
}