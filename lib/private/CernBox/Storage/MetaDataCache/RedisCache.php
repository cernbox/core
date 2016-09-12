<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:06 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;


use OC\CernBox\Storage\Drivers\Redis;

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

	/**
	 * @var Redis
	 */
	private $driver;

	public function __construct(Redis $redisDriver)
	{
		$this->driver = $redisDriver;
	}

	public function setFileById($id, $value)
	{
		$this->writeToCache(self::KEY_FILE_BY_ID, $id, json_encode($value));
	}

	public function getFileById($id)
	{
		return json_decode($this->readFromCache(self::KEY_FILE_BY_ID, $id), TRUE);
	}

	public function clearFileById($id)
	{
		$this->deleteFromCache(self::KEY_FILE_BY_ID, $id);
	}

	public function setFileByEosPath($path, $value)
	{
		$this->writeToCache(self::KEY_FILE_BY_PATH, $path, json_encode($value));
	}

	public function getFileByEosPath($path)
	{
		return json_decode($this->readFromCache(self::KEY_FILE_BY_PATH, $path), TRUE);
	}

	public function clearFileByEosPath($eosPath)
	{
		$this->deleteFromCache(self::KEY_FILE_BY_PATH, $eosPath);
	}

	public function setMeta($ocPath, $value)
	{
		$this->writeToCache(self::KEY_META, $ocPath, json_encode($value));
	}

	public function getMeta($ocPath)
	{
		return json_decode($this->readFromCache(self::KEY_META, $ocPath), TRUE);
	}

	public function setEGroups($user, $value)
	{
		$this->writeToCache(self::KEY_EGROUPS, $user, json_encode($value));
	}

	public function getEGroups($user)
	{
		return json_decode($this->readFromCache(self::KEY_EGROUPS, $user), TRUE);
	}

	public function setOwner($path, $value)
	{
		$this->writeToCache(self::KEY_OWNER, $path, $value);
	}

	public function getOwner($path)
	{
		return $this->readFromCache(self::KEY_OWNER, $path);
	}

	public function setUidAndGid($user, $data)
	{
		$this->writeToCache(self::KEY_UID_GID, $user, json_encode($data));
	}

	public function getUidAndGid($user)
	{
		return json_decode($this->readFromCache(self::KEY_UID_GID, $user, self::EXPIRE_UID_GID_TIME_SECONDS), TRUE);
	}

	public function setFileInfoByEosPath($depth, $eosPath, $data)
	{
		$key = $depth . '-' . $eosPath;
		$this->writeToCache(self::KEY_FILEINFO_BY_PATH, $key, json_encode($data));
	}

	public function getFileInfoByEosPath($depth, $eosPath)
	{
		$key = $depth . '-' . $eosPath;
		return json_decode($this->readFromCache(self::KEY_FILEINFO_BY_PATH, $key), TRUE);
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
		if($value !== FALSE)
		{
			if(!$validTime)
			{
				$validTime = self::EXPIRE_TIME_SECONDS;
			}

			// Check for expire date
			$value = json_decode($value, TRUE);
			$elapsed = time() - (int)$value[0];

			if($elapsed > $validTime)
			{
				$value = FALSE;
			}
			else
			{
				$value = $value[1];
			}
		}

		return $value;
	}
}