<?php

namespace OC\Cernbox\Storage;

class Redis
{
	private static $redisInstance;
	
	private static function init()
	{
		if(self::$redisInstance == null)
		{
			self::$redisInstance = new \Redis();
				
			if(!self::$redisInstance->connect('127.0.0.1', 6379))
			{
				\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to connect to redis server on 127.0.0.1:6379', \OCP\Util::ERROR);
				self::$redisInstance = null;
				return false;
			}
		}
		
		return true;
	}
	
	public static function writeToCache($key, $value)
	{
		if(self::init())
		{
			self::$redisInstance->set($key, $value);
		}
		else
		{
			\OCP\Util::writeLog('REDIS MEMCACHE', 'Unable to access redis server', \OCP\Util::ERROR);
		}
	}
	
	public static function readFromCache($key)
	{
		if(self::init())
		{
			self::$redisInstance->get($key);
		}
		else
		{
			\OCP\Util::writeLog('REDIS MEMCACHE', 'Unable to access redis server', \OCP\Util::ERROR);
		}
	}
	
	public static function deleteFromCache($key)
	{
		if(self::init())
		{
			self::$redisInstance->del($key);
		}
		else
		{
			\OCP\Util::writeLog('REDIS MEMCACHE', 'Unable to access redis server', \OCP\Util::ERROR);
		}
	}
	
	public static function writeToCacheMap($hash, $key, $value)
	{
		if(self::init())
		{
			self::$redisInstance->hSet($hash, $key, $value);
		}
		else
		{
			\OCP\Util::writeLog('REDIS MEMCACHE', 'Unable to access redis server', \OCP\Util::ERROR);
		}
	}
	
	public static function readHashFromCacheMap($hash)
	{
		if(self::init())
		{
			return self::$redisInstance->hGetAll($hash);
		}
		else
		{
			\OCP\Util::writeLog('REDIS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
	}
	
	public static function readFromCacheMap($hash, $key)
	{
		if(self::init())
		{
			return self::$redisInstance->hGet($hash, $key);
		}
		else
		{
			\OCP\Util::writeLog('REDIS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
	}
	
	public static function deleteFromCacheMap($hash, $key)
	{
		if(self::init())
		{
			self::$redisInstance->hDel($hash, $key);
		}
		else
		{
			\OCP\Util::writeLog('REDIS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
	}
}