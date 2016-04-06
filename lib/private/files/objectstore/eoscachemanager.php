<?php

namespace OC\Files\ObjectStore;

use OC\Files\ObjectStore\EosReqCache;
use OC\Files\ObjectStore\EosMemCache;

class EosCacheManager
{
	/** @var IEosCache[] list of enabled caches */
	private static $caches;
	/** @var bool flag to indicate the cache is initialized */
	private static $initialized;
	
	private static function shouldAvoidCache()
	{
		$avoid_paths = \OCP\Config::getSystemValue("avoid_req_cache_paths", array());
		foreach($avoid_paths as $path) 
		{
			if(strpos($_SERVER['REQUEST_URI'], $path) !== false) 
			{
				return TRUE;
			}
		}
		return FALSE;
	}
	
	/**
	 * Initializes the different cache levels and returns the current
	 * cache status
	 */
	public static function init()
	{
		if(!self::$initialized)
		{
			self::$caches = [];
			self::$caches[] = new EosReqCache();
			self::$caches[] = new EosMemCache();
			self::$initialized = true;
		}
		
		return self::$initialized;
	}
	
	/**
	 * Attempts to retrieve a file data stored by its inode id.
	 * @param string|int $id file inode id
	 * @return The file data as an associative array or null if the file
	 * 			was not found
	 */
	public static function getFileById($id)
	{
		if(self::init())
		{
			$data = NULL;
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				if(($data = $cache->getFileById($id)) !== FALSE)
					return $data;
			}
		}
		
		return null;
	}
	
	/**
	 * Stores a file's data in all cache levels, using it's inode id as key
	 * @param string|int $id file inode id
	 * @param array $data file data as an associative array
	 */
	public static function setFileById($id, $data)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				$cache->setFileById($id, $data);
			}
		}
	}
	
	/**
	 * Invalidates the data of a file stored in the cache by it's id (if any)
	 * @param string|int $id file inode id
	 */
	public static function clearFileById($id)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				$cache->clearFileById($id);
			}
		}
	}
	
	/**
	 * Attempts to retrieve a file data stored by it's path within EOS namespace
	 * @param string $eosPath file path within EOS
	 * @return array|null An associatve array containing the file data or null if
	 * 			the given eosPath key as not found in the cache
	 */
	public static function getFileByEosPath($eosPath)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				if(($data = $cache->getFileByEosPath($eosPath)) !== FALSE)
					return $data;
			}
		}
		
		return null;
	}
	
	/**
	 * Attempts to retrieve a file data stored by it's path within EOS namespace.
	 * If the request path is contained inside the config setting 'avoid_req_cache_paths',
	 * the cache will produce a fail in order to refresh the file info
	 * @param string $eosPath file path within EOS
	 * @return array|null An associatve array containing the file data or null if
	 * 			the given eosPath key as not found in the cache
	 */
	public static function getSecureFileByEosPath($eosPath)
	{
		if(self::shouldAvoidCache())
		{
			return null;	
		}
		
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				if(($data = $cache->getFileByEosPath($eosPath)) !== FALSE)
					return $data;
			}
		}
		
		return null;
	}
	
	/**
	 * Stores a file's data using it's EOS path as key to access it
	 * @param string $eosPath file path within EOS
	 * @param array $data of the file as an associative array
	 */
	public static function setFileByEosPath($eosPath, $data)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				$cache->setFileByEosPath($eosPath, $data);
			}
		}
	}
	
	/**
	 * Invalidates the data stored in the cache identified by it's EOS Path
	 * @param string $eosPath path within EOS
	 */
	public static function clearFileByEosPath($eosPath)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				$cache->clearFileByEosPath($eosPath);
			}
		}
	}
	
	/**
	 * Attempts to retrieve a a file/directory full information from the cache, given
	 * it's EOS Path and a depth of exploration
	 * @param int $depth Max nested folders levels to explore
	 * @param string $eosPath file path within EOS
	 * @return array|null The list of information of the given file, or null if it wasn't present 
	 * 						or valid in the cache
	 */
	public static function getFileInfoByEosPath($depth, $eosPath)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				if(($data = $cache->getFileInfoByEosPath($depth, $eosPath)) !== FALSE)
					return $data;
			}
		}
		
		return null;
	}
	
	/**
	 * Stores the information of a given file (identified by it's EOS path and a given depth of nested exploration)
	 * @param int $depth the maximun level of nested directories exploration
	 * @param string $eosPath file path within EOS
	 * @param array $data containing all the information to store
	 */
	public static function setFileInfoByEosPath($depth, $eosPath, $data)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				$cache->setFileInfoByEosPath($depth, $eosPath, $data);
			}
		}
	}
	
	/**
	 * Attempts to retrieve a file's metadata stored by it's OC path
	 * @param string $ocPath within ownCloud namespace
	 * @return array|null An associative array containting the file metadata or
	 * 			null if the key was not found in the cache
	 */
	public static function getMeta($ocPath)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				if(($data = $cache->getMeta($ocPath)) !== FALSE)
					return $data;
			}
		}
		
		return null;
	}
	
	/**
	 * Stores a file's metadata in the cache using it's ownCloud path as key
	 * @param string $ocPath file's path within ownCloud
	 * @param array $data An associative array containing the file metadata
	 */
	public static function setMeta($ocPath, $data)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{	
				$cache->setMeta($ocPath, $data);
			}
		}
	}
	
	/**
	 * Attempts to retrieve a list of EGroups associated with the given $user
	 * @param string $user user's username within CERN/LDAP
	 * @return array a list of all EGreoups to which the user belongs and are relevant
	 * 			to sharing module
	 */
	public static function getEGroups($user)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				if(($data = $cache->getEGroups($user)) !== FALSE)
					return $data;
			}
		}
		
		return null;
	}
	
	/**
	 * Stores a list of EGroups to which the given $user belongs to.
	 * @param string $user user's username within CERN/LDAP
	 * @param array $data a list of EGroups
	 */
	public static function setEGroups($user, $data)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				$cache->setEGroups($user, $data);
			}
		}
	}
	
	/**
	 * Attempts to retrieve the owner of a file, given it's path within EOS
	 * @param string $eosPath file's path within EOS namespace
	 * @return string owner's username within EOS/LDAP
	 */
	public static function getOwner($eosPath)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				if(($data = $cache->getOwner($eosPath)) !== FALSE)
					return $data;
			}
		}
		
		return null;
	}
	
	/**
	 * Stores the owner's username of the given file by it's EOS path
	 * @param string $eosPath file's EOS path
	 * @param string $data owner's username
	 */
	public static function setOwner($eosPath, $data)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				$cache->setOwner($eosPath, $data);
			}
		}
	}
	
	/**
	 * Attempts to retrieve the user id and group id of the given $user
	 * @param string $user user's username within CERN/LDAP
	 * @return array an associative array containing the uid and gid
	 */
	public static function getUidAndGid($user)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				if(($data = $cache->getUidAndGid($user)) !== FALSE)
					return $data;
			}
		}
		
		return null;
	}
	
	/**
	 * Stores the user id and group id of the given $user
	 * @param string $user user's username within CERN/LDAP
	 * @param array $data containing the uid and gid
	 */
	public static function setUidAndGid($user, $data)
	{
		if(self::init())
		{
			/** @var IEosCache $cache */
			foreach(self::$caches as &$cache)
			{
				$cache->setUidAndGid($user, $data);
			}
		}
	}
}