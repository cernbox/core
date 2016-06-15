<?php

namespace OC\Cernbox\ShareEngine;

use OCP\Share\Exceptions\GenericShareException;

final class ShareUtil 
{
	private static $l = \OC::$server->getL10N('core');
	
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
		return self::shareApiLinkDefaultExpireDate() &&
		\OC::$server->getConfig()->getAppValue('core', 'shareapi_enforce_expire_date', 'no') === 'yes';
	}
	
	public static function getDefaultLinkExpireDate()
	{
		return (int)\OC::$server->getConfig()->getAppValue('core', 'shareapi_expire_after_n_days', '7');
	}
	
	public static function validateExpirationDate(\OCP\Share\IShare $share) 
	{
		$expirationDate = $share->getExpirationDate();
	
		if ($expirationDate !== null) 
		{
			//Make sure the expiration date is a date
			$expirationDate->setTime(0, 0, 0);
	
			$date = new \DateTime();
			$date->setTime(0, 0, 0);
			if ($date >= $expirationDate) 
			{
				$message = self::$l->t('Expiration date is in the past');
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
				$message = self::$l->t('Cannot set expiration date more than %s days in the future', [self::getDefaultLinkExpireDate()]);
				throw new GenericShareException($message, $message, 404);
			}
		}
	
		$share->setExpirationDate($expirationDate);
	
		return $share;
	}
}