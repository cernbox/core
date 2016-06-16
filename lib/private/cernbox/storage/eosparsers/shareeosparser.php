<?php

namespace OC\Cernbox\Storage\EosParsers;

use OC\Cernbox\Storage\EosUtil;
use OC\Cernbox\Storage\EosProxy;

final class ShareEosParser extends DefaultEosParser
{
	public function parseFileInfo($line)
	{
		$rawMap = explode(' ', $line);
		
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
		$data['share_type']		  = $this->parseShareType($map);
		$data['share_stime']	  = $this->parseField($map, 'cernbox.share_stime', '0');
		$data['share_expiration'] = $this->parseField($map, 'cernbox.share_expiration', '0');
		$data['share_password']	  = $this->parseField($map, 'cernbox.share_password', '');
		$data['uid_owner']		  = EosUtil::getOwner($data['eospath']);
		$data['uid_initiator']	  = $data['uid_owner'];
		
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
	
	private function parseShareType($map)
	{
		$shareTypeRaw = $this->parseField($map, 'cernbox.share_type', '');
		$shares = explode(',', $shareTypeRaw);
		$sharesInt = [];
		foreach($shares as $s)
		{
			$sharesInt[] = intval($s);
		}
		
		return $sharesInt;
	}
}