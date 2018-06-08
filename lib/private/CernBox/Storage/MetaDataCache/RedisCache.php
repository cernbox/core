<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:06 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;


use OC\CernBox\Drivers\Redis;
use OC\CernBox\Storage\Eos\CacheEntry;
use OCP\Files\Cache\ICacheEntry;

/**
 * Class RedisCache
 *
 * @package OC\CernBox\Storage\MetaDataCache
 */
class RedisCache  implements  IMetaDataCache {

	/** @var int Cached data validity time */
	const EXPIRE_TIME_SECONDS = 15;

	/** @var int Cached uid and gid validity time */
	const EXPIRE_UID_GID_TIME_SECONDS = 3600;

	const KEY_GET_UID_AND_GID = 'getUidAndGid';
	const KEY_GET_CACHE_ENTRY = 'getCacheEntry';
	const KEY_GET_PATH_BY_ID = 'getPathById';
	const KEY_GET_FOLDER_CONTENTS = 'getFolderContents';
	const KEY_GET_FOLDER_CONTENTS_BY_ID = 'getFolderContentsById';

	/**
	 * @var Redis
	 */
	private $driver;

	public function __construct(Redis $redisDriver)
	{
		$this->driver = $redisDriver;
	}

	public function getUidAndGid($key) {
		return json_decode($this->readFromCache(self::KEY_GET_UID_AND_GID, $key, self::EXPIRE_UID_GID_TIME_SECONDS), true);
	}

	public function setUidAndGid($key, array $data) {
		$this->writeToCache(self::KEY_GET_UID_AND_GID, $key, json_encode($data));
	}

	public function getCacheEntry($key) {
		$val = $this->readFromCache(self::KEY_GET_CACHE_ENTRY, $key);
		if ($val) {
			return new CacheEntry(json_decode($val, true));
		}
	}

	public function setCacheEntry($key, ICacheEntry $data) {
		$array = json_encode($data);
		$this->writeToCache(self::KEY_GET_CACHE_ENTRY, $key, $array);
	}

	public function getPathById($key) {
		return json_decode($this->readFromCache(self::KEY_GET_PATH_BY_ID, $key), true);
	}

	public function setPathById($key, $data) {
		$this->writeToCache(self::KEY_GET_PATH_BY_ID, $key, json_encode($data));
	}

	public function clearCacheEntry($key) {
		$this->deleteFromCache(self::KEY_GET_CACHE_ENTRY, $key);
	}


	private function writeToCache($outerKey, $key, $value)
	{
		$this->driver->writeToCacheMap($outerKey, $key, json_encode([time(), $value]));
	}

	private function deleteFromCache($outerKey, $key)
	{
		$this->driver->deleteFromCacheMap($outerKey, $key);
	}

	private function readFromCache($outerKey, $key, $validTime = false)
	{
		$value = $this->driver->readFromCacheMap($outerKey, $key);
		if($value !== false)
		{
			if(!$validTime)
			{
				$validTime = self::EXPIRE_TIME_SECONDS;
			}

			// Check for expire date
			$value = json_decode($value, true);
			$elapsed = time() - (int)$value[0];

			if($elapsed > $validTime)
			{
				$value = false;
			}
			else
			{
				$value = $value[1];
			}
		}

		return $value;
	}
}