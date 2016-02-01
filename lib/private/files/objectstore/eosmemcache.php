<?php

namespace OC\Files\ObjectStore;

class EosMemCache implements IEosCache
{
	/** @var int Cached data validity time */
	const EXPIRE_TIME_SECONDS = 15;
	
	/** @var string Cache key for files stored by inode id */
	const KEY_FILE_BY_ID = 'getFileById';
	/** @var string Cache key for files stored by eos path */
	const KEY_FILE_BY_PATH = 'getFileByEosPath';
	/** @var string Cache key for files metadata stored by oc path */
	const KEY_META = 'getMeta';
	/** @var string Cache key for e-group lists stored by username */
	const KEY_EGROUPS = 'getEGroups';
	/** @var string Cache key for owner of files stored by those files path */
	const KEY_OWNER = 'getOwner';
	/** @var string Cache key for user id and group id stored by username */
	const KEY_UID_GID = 'getUidAndGid';
	/** @var string Cache key for files identified by eospath and a given depth */
	const KEY_FILEINFO_BY_PATH = 'getFileInfoByEosPath';
	
	/** @var Redis redis client object */
	private $redisClient;
	/** @var bool stores whether cache system is available or not */
	private $unableToConnect;
	
	public function __construct()
	{
		$this->init();
	}
	
	/**
	 * Stablish (if not done already) and test the connection to the cache
	 * server on every call
	 * 
	 * @return bool True if server is available, false otherwise
	 */
	private function init()
	{
		if($this->redisClient == NULL)
		{
			$this->unableToConnect = false;
			$this->redisClient = new \Redis();
			
			if(!$this->redisClient->connect('127.0.0.1', 6379))
			{
				$this->unableToConnect = true;
				\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to connect to redis server on 127.0.0.1:6379', \OCP\Util::ERROR);
			}
		}
		
		return !$this->unableToConnect;
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setFileById()
	 */
	public function setFileById($id, $value)
	{
		$this->writeToCache(self::KEY_FILE_BY_ID, $id, json_encode($value));
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getFileById()
	 */
	public function getFileById($id)
	{
		return json_decode($this->readFromCache(self::KEY_FILE_BY_ID, $id), TRUE);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::clearFileById()
	 */
	public function clearFileById($id)
	{
		$this->deleteFromCache(self::KEY_FILE_BY_ID, $id);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setFileByEosPath()
	 */
	public function setFileByEosPath($path, $value)
	{
		$this->writeToCache(self::KEY_FILE_BY_PATH, $path, json_encode($value));
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getFileByEosPath()
	 */
	public function getFileByEosPath($path)
	{
		return json_decode($this->readFromCache(self::KEY_FILE_BY_PATH, $path), TRUE);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\IEosCache::clearFileByEosPath()
	 */
	public function clearFileByEosPath($eosPath)
	{
		$this->deleteFromCache(self::KEY_FILE_BY_PATH, $eosPath);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setMeta()
	 */
	public function setMeta($ocPath, $value)
	{
		$this->writeToCache(self::KEY_META, $ocPath, json_encode($value));
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getMeta()
	 */
	public function getMeta($ocPath)
	{
		return json_decode($this->readFromCache(self::KEY_META, $ocPath), TRUE);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setEGroups()
	 */
	public function setEGroups($user, $value)
	{
		$this->writeToCache(self::KEY_EGROUPS, $user, json_encode($value));
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getEGroups()
	 */
	public function getEGroups($user)
	{
		return json_decode($this->readFromCache(self::KEY_EGROUPS, $user), TRUE);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setOwner()
	 */
	public function setOwner($path, $value)
	{
		$this->writeToCache(self::KEY_OWNER, $path, $value);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getOwner()
	 */
	public function getOwner($path)
	{
		return $this->readFromCache(self::KEY_OWNER, $path);
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::setUidAndGid()
	 */
	public function setUidAndGid($user, $data)
	{
		$this->writeToCache(self::KEY_UID_GID, $user, json_encode($data));
	}
	
	/**
	 * {@inheritDoc}
	 * @see IEosCache::getUidAndGid()
	 */
	public function getUidAndGid($user)
	{
		return json_decode($this->readFromCache(self::KEY_UID_GID, $user), TRUE);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\IEosCache::setFileInfoByEosPath()
	 */
	public function setFileInfoByEosPath($depth, $eosPath, $data)
	{
		$key = $data . '-' . $eosPath;
		$this->writeToCache(self::KEY_FILEINFO_BY_PATH, $key, json_encode($data));
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\IEosCache::getFileInfoByEosPath()
	 */
	public function getFileInfoByEosPath($depth, $eosPath)
	{
		$key - $depth . '-' . $eosPath;
		return json_decode($this->readFromCache(self::KEY_FILEINFO_BY_PATH, $key));
	}
	
	/**
	 * General method to write data to the cache server.
	 * 
	 * @param string $outerKey key to identify the hash to write to
	 * @param string $key key to place the given data
	 * @param string $value data to be placed on the cache
	 */
	private function writeToCache($outerKey, $key, $value)
	{
		if($this->init())
		{
			$this->redisClient->hSet($outerKey, $key, json_encode([time(), $value]));
		}
		else 
		{
			\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to access redis server', \OCP\Util::ERROR);
		}
	}
	
	/**
	 * Erases an entry from the cache, given the hash set and the data key
	 * 
	 * @param string $outerKey hash list identifier
	 * @param string $key data key identifier
	 */
	private function deleteFromCache($outerKey, $key)
	{
		if($this->init())
		{
			$this->redisClient->hDel($outerKey, $key);
		}
		else 
		{
			\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
	}
	
	/**
	 * Reads a value from cache, given the hash key and the data key
	 * 
	 * @param string $outerKey Key to identify the hash list
	 * @param string $key Key to identify the data within the hash
	 * @return string|bool a string containing the found data or FALSE otherwise.
	 */
	private function readFromCache($outerKey, $key)
	{
		$value = NULL;
		if($this->init())
		{
			$value = $this->redisClient->hGet($outerKey, $key);
			// Key found
			if($value !== FALSE)
			{
				// Check for expire date
				$value = json_decode($value, TRUE);
				$elapsed = time() - (int)$value[0];
				if($elapsed > self::EXPIRE_TIME_SECONDS)
				{
					$value = FALSE;
				}
				else
				{
					$value = $value[1];
				}
			}
		}
		else
		{
			\OCP\Util::writeLog('EOS MEMCACHE', 'Unable to access memcache', \OCP\Util::ERROR);
		}
		
		return $value;
	}
}