<?php

namespace OC\CernBox\Storage\Drivers;

class Redis
{
	private $redisInstance;
	private $logger;

	public function __construct() {
		$this->redisInstance = new \Redis();
		$this->logger = \OC::$server->getLogger();

		if(!$this->redisInstance->connect('127.0.0.1', 6379))
		{
			$this->logger->error('Unable to connect to redis server on 127.0.0.1:6379');
		}
	}

	public function writeToCache($key, $value)
	{
		$this->redisInstance->set($key, $value);
	}

	public function readFromCache($key)
	{
		$this->redisInstance->get($key);
	}

	public function deleteFromCache($key)
	{
		$this->redisInstance->del($key);
	}

	public function writeToCacheMap($hash, $key, $value)
	{
		$this->redisInstance->hSet($hash, $key, $value);
	}

	public function readHashFromCacheMap($hash)
	{
		return $this->redisInstance->hGetAll($hash);
	}

	public function readFromCacheMap($hash, $key)
	{
		return $this->redisInstance->hGet($hash, $key);
	}

	public function deleteFromCacheMap($hash, $key)
	{
		$this->redisInstance->hDel($hash, $key);
	}
}
