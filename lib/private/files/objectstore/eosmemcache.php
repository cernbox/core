<?php

namespace OC\Files\ObjectStore;

class EosMemCache
{
	private static $memCache;
	private static $unableToConnect;
	
	public static function init()
	{
		if(self::$memCache == NULL)
		{
			self::$unableToConnect = false;
			self::$memCache = new \Memcache();
			
			if(!self::$memCache->connect('localhost', 11211))
			{
				self::$unableToConnect = true;
				\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to connect to memcached server on localhost:11211', \OCP\Util::ERROR);
			}
		}
		
		return !self::$unableToConnect;
	}
	
	public static function writeToCache($key, $value)
	{
		if(self::init())
		{
			if(!self::$memCache->set($key, $value, /*MEMCACHE_COMPRESSED*/0, 30))
			{
				\OCP\Util::writeLog('EOS MEMCACHE', 'Failed to store value for key ' .$key, \OCP\Util::ERROR);
			}
		}
		else 
		{
			\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
	}
	
	public static function deleteFromCache($key)
	{
		if(self::init())
		{
			self::$memCache->delete($key);
		}
		else 
		{
			\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
	}
	
	public static function readFromCache($key)
	{
		$value = NULL;
		if(self::init())
		{
			$value = self::$memCache->get($key);
			if($value == FALSE)
			{
				\OCP\Util::writeLog('EOS MEMCACHE', 'Miss or failure retrieving ' .$key, \OCP\Util::ERROR);
			}
		}
		else
		{
			\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
		
		return $value;
	}
	
	public static function invalidateCache()
	{
		if(self::init())
		{
			self::$memCache->flush();
		}
		else
		{
			\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
	}
}