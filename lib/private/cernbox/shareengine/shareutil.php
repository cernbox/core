<?php

namespace OC\Cernbox\ShareEngine;

use OCP\Share\Exceptions\GenericShareException;
use OC\Cernbox\Storage\EosUtil;

final class ShareUtil 
{	
	public static function calcTokenHash($token)
	{
		$hash = 0;
		$tokenLen = strlen($token);
		for($i = 0; $i < $tokenLen; $i++)
		{
			$hash += ord($token[$i]);
		}
		
		return ($hash % 100);
	}
	
	public static function parseAcl($aclString)
	{
		$acls = explode(',', $aclString);
		$map = [];
		foreach($acls as $acl)
		{
			$parts = explode(':', $acl);
			$map[$parts[1]] = [$parts[2], $parts[0]];
		}
		
		return $map;
	}
	
	public static function buildAcl($aclMap)
	{
		$acl = '';
		foreach($aclMap as $user => $part)
		{
			$acl .= ($part[1] . ':' . $user . ':' . $part[0]);
		}
		
		return $acl;
	}
	
	public static function isDefaultLinkExpireDate()
	{
		return \OC::$server->getConfig()->getAppValue('core', 'shareapi_default_expire_date', 'no') === 'yes';
	}
	
	public static function enforceDefaultLinkExpireDate()
	{
		return self::isDefaultLinkExpireDate() &&
		\OC::$server->getConfig()->getAppValue('core', 'shareapi_enforce_expire_date', 'no') === 'yes';
	}
	
	public static function getDefaultLinkExpireDate()
	{
		return (int)\OC::$server->getConfig()->getAppValue('core', 'shareapi_expire_after_n_days', '7');
	}
	
	public static function getGlobalLinksFolder()
	{
		return \OC::$server->getConfig()->getSystemValue('share_global_link_folder', 'global_links');
	}
	
	public static function validateExpirationDate(\OCP\Share\IShare $share) 
	{
		$l = \OC::$server->getL10N('core');
		
		$expirationDate = $share->getExpirationDate();
	
		if ($expirationDate !== null) 
		{
			//Make sure the expiration date is a date
			$expirationDate->setTime(0, 0, 0);
	
			$date = new \DateTime();
			$date->setTime(0, 0, 0);
			if ($date >= $expirationDate) 
			{
				$message = $l->t('Expiration date is in the past');
				throw new GenericShareException($message, $message, 404);
			}
		}
	
		// If expiredate is empty set a default one if there is a default
		$fullId = null;
		try 
		{
			$fullId = $share->getFullId();
		} catch (\UnexpectedValueException $e) 
		{
			// This is a new share
		}
	
		if ($fullId === null && $expirationDate === null && self::isDefaultLinkExpireDate()) 
		{
			$expirationDate = new \DateTime();
			$expirationDate->setTime(0,0,0);
			$expirationDate->add(new \DateInterval('P'.self::getDefaultLinkExpireDate().'D'));
		}
	
		// If we enforce the expiration date check that is does not exceed
		if (self::enforceDefaultLinkExpireDate()) 
		{
			if ($expirationDate === null) 
			{
				throw new \InvalidArgumentException('Expiration date is enforced');
			}
	
			$date = new \DateTime();
			$date->setTime(0, 0, 0);
			$date->add(new \DateInterval('P' . self::getDefaultLinkExpireDate() . 'D'));
			if ($date < $expirationDate) 
			{
				$message = $l->t('Cannot set expiration date more than %s days in the future', [self::getDefaultLinkExpireDate()]);
				throw new GenericShareException($message, $message, 404);
			}
		}
	
		$share->setExpirationDate($expirationDate);
	
		return $share;
	}
	
	public static function getShareByLinkOwner($token)
	{
		$sharePrefix = rtrim(EosUtil::getEosSharePrefix(), '/');
		$tokenHash = self::calcTokenHash($token);
		$globalLinkFolder = trim(self::getGlobalLinksFolder(), '/');
		
		$path = $sharePrefix . '/' . $globalLinkFolder . '/' . $tokenHash . '/' . $token;
		
		$fileInfo = EosUtil::getFileByEosPathAsRoot($path);
		
		return $fileInfo['uid_owner'];
	}
}