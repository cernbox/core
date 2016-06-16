<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Cernbox\Storage;


use OC\Cernbox\Storage\EosUtil;
use OC\Cernbox\Storage\EosProxy;
use OC\Cernbox\Storage\EosCacheManager;
use OC\Files\Cache\Storage;
use OCP\Files\Cache\ICache;
use OC\Files\Cache\CacheEntry;

/**
 * Metadata cache for the filesystem
 * don't use this class directly if you need to get metadata, use \OC\Files\Filesystem::getFileInfo instead
 */
class EosCache implements ICache 
{
	const NOT_FOUND = 0;
	const PARTIAL   = 1;//only partial data available, file not cached in the database
	const SHALLOW   = 2;//folder in cache, but not all child files are completely scanned
	const COMPLETE  = 3;
	
	const REDIS_KEY_MIMETYPES = 'eos_cache_mimetypes';

	/**
	 * @var array partial data for the cache
	 */
	protected $partial = array();

	/**
	 * @var string
	 */
	protected $storageId;

	/**
	 * @var Storage $storageCache
	 */
	protected $storageCache;

	protected $mimetypeIds = array();
	protected $mimetypes   = array();

	/**
	 * @param \OC\Files\Storage\Storage|string $storage
	 */
	public function __construct($storage) 
	{
		if ($storage instanceof \OC\Files\Storage\Storage) 
		{
			$this->storageId = $storage->getId();
		} 
		else 
		{
			$this->storageId = $storage;
		}
		
		// with the objectstore the $this->storageId is 'object::user:gonzaleh'
		if (strlen($this->storageId) > 64) 
		{
			$this->storageId = md5($this->storageId);
		}
		$this->storageCache = new Storage($storage);
	}

	public function getNumericStorageId() 
	{
		$id = $this->storageCache->getNumericId();
		return $id;
	}
	
	/**
	 * normalize mimetypes
	 *
	 * @param string $mime
	 * @return int
	 */
	public function getMimetypeId($mime) 
	{
		if (empty($mime)) 
		{
			// Can not insert empty string into Oracle NOT NULL column.
			$mime = 'application/octet-stream';
		}
		
		if (empty($this->mimetypeIds)) 
		{
			$this->loadMimetypes();
		}

		if (!isset($this->mimetypeIds[$mime]))
		{
			try 
			{
				\OC_DB::executeAudited('INSERT INTO `*PREFIX*mimetypes`(`mimetype`) VALUES(?)', array($mime));
				$this->mimetypeIds[$mime] = \OC_DB::insertid('*PREFIX*mimetypes');
				$this->mimetypes[$this->mimetypeIds[$mime]] = $mime;
			}
			 catch (\Doctrine\DBAL\DBALException $e) 
			 {
			 	\OCP\Util::writeLog('CORE', 'Exception during mimetype insertion: ' . $e->getmessage(), \OCP\Util::DEBUG);
				return -1;
			}
		}

		$toret = $this->mimetypeIds[$mime];
		return $toret;
	}

	public function getMimetype($id) 
	{
		if (empty($this->mimetypes)) 
		{
			$this->loadMimetypes();
		}
		
		$mime = isset($this->mimetypes[$id]) ? $this->mimetypes[$id] : null;
		
		return $mime;
	}

	public function loadMimetypes() 
	{
		$getAll = json_decode(Redis::readHashFromCacheMap(self::REDIS_KEY_MIMETYPES));
		
		if(!$getAll)
		{
			$result = \OC_DB::executeAudited('SELECT `id`, `mimetype` FROM `*PREFIX*mimetypes`', []);
			if ($result) 
			{
				while ($row = $result->fetchRow()) 
				{
					Redis::writeToCacheMap(self::REDIS_KEY_MIMETYPES, $row['mimetype'], $row['id']);
					$this->mimetypeIds[$row['mimetype']] = $row['id'];
					$this->mimetypes[$row['id']]         = $row['mimetype'];
				}
			}
		}
		else
		{
			foreach($getAll as $mime => $id)
			{
				$this->mimetypeIds[$mime] = $id;
				$this->mimetypes[$id] = $mime;
			}
		}
	}

	/**
	 * get the stored metadata of a file or folder
	 *
	 * @param $ocPath the owncloud path. like files/a.txt or cache/some.txt
	 * @return ICacheEntry|false
	 */
	public function get($ocPath) 
	{ 
		$ocPath = $this->normalize($ocPath);
		$eosPath = EosProxy::toEos($ocPath, $this->storageId);
	
		if(EosCacheManager::shouldAvoidCache())
		{
			EosCacheManager::clearFileByEosPath($eosPath);
		}
		
		$self = $this;
		$meta = EosUtil::getFileByEosPath($eosPath, function(&$data) use($self)
		{
			$data['storage'] = $self->storageId;
		});
		
		if($meta)
		{
			return new CacheEntry($meta);
		}
		
		return false;
	}
	
	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param string $folder
	 * @return ICacheEntry[]
	 */
	public function getFolderContents($ocPath, $deep = false) 
	{
		$ocPath = $this->normalize($ocPath);
		$eosPath = EosProxy::toEos($ocPath, $this->storageId);
		
		$self = $this;
		$meta = EosUtil::getFolderContents($eosPath, function(array &$data) use($self)
		{
			$data['storage'] = $self->storageId;
		}, 
		$deep);
		
		/** @var CacheEntry $files */
		$files = [];
		foreach($meta as $m)
		{
			if($m)
			{
				$files[] = new CacheEntry($m);
			}
		}
		
		return $files[];
	}

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param int $fileId the file id of the folder
	 * @return array
	 */
	public function getFolderContentsById($fileId) 
	{
		$ocPath = $this->getPathById($fileId);
		return $this->getFolderContents($ocPath);
	}

	/**
	 * store meta data for a file or folder
	 *
	 * @param string $file
	 * @param array $data
	 *
	 * @return int file id
	 */
	public function put($file, array $data) 
	{
		//If we change the permissions of a file for example in sharing may be we need to call
		// eos put xatrr sys.user.acl or something like that ? I think no becasuse the share permissions go to share table and this cache in a replacement for table oc_filecache
		//KUBA: PERMISSIONS?
		return $this->getId($file);
	}

	/**
	 * update the metadata in the cache
	 *
	 * @param int $id
	 * @param array $data
	 */
	public function update($id, array $data) 
	{
		// Same as put
		//KUBA: PERMISSIONS?
		return true;
	}

	/**
	 * extract query parts and params array from data array
	 *
	 * @param array $data
	 * @return array
	 */
	function buildParts(array $data) 
	{
		return [];
	}

	/**
	 * get the file id for a file
	 *
	 * @param string $file
	 * @return int
	 */
	public function getId($file) 
	{
		$info = $this->get($file);
		if ($info) 
		{
			return $info["fileid"];
		}
		return false;
	}

	/**
	 * get the id of the parent folder of a file
	 *
	 * @param string $file
	 * @return int
	 */
	public function getParentId($file) 
	{
		$info = $this->get($file);
		if ($info) 
		{
			return $info["parent"];
		}
		return false;
	}

	/**
	 * check if a file is available in the cache
	 *
	 * @param string $file
	 * @return bool
	 */
	public function inCache($file) 
	{
		return (bool) $this->get($file);
	}

	/**
	 * remove a file or folder from the cache
	 *
	 * @param string $file
	 */
	public function remove($file) 
	{
		return true;
	}

	/**
	 * Move a file or folder in the cache
	 *
	 * @param string $source
	 * @param string $target
	 */
	public function move($source, $target) 
	{
		return true;
	}

	/**
	 * remove all entries for files that are stored on the storage from the cache
	 */
	public function clear() 
	{
		return true;
	}

	/**
	 * @param string $file
	 *
	 * @return int, Cache::NOT_FOUND, Cache::PARTIAL, Cache::SHALLOW or Cache::COMPLETE
	 */
	public function getStatus($file) 
	{
		return self::COMPLETE;
	}

	/**
	 * search for files matching $pattern
	 *
	 * @param string $pattern
	 * @return array an array of file data
	 */
	public function search($pattern) 
	{
		return [];
	}

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype
	 * @return array
	 */
	public function searchByMime($mimetype) 
	{
		$files = self::getFolderContents("files", true);
		$images = [];
		foreach($files as $file) 
		{
			$ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
			
			if($ext === "png" || $ext === "jpeg" || $ext === "jpg") 
			{
				$images[] = new CacheEntry($file);
			}
		}
		return $images;
	}
	
	/**
	 * Search for files by tag of a given users.
	 *
	 * Note that every user can tag files differently.
	 *
	 * @param string|int $tag name or tag id
	 * @param string $userId owner of the tags
	 * @return array file data
	 */
	public function searchByTag($tag, $userId) 
	{
		$sql = 'SELECT objid FROM ' .
			'`*PREFIX*vcategory_to_object` `tagmap`, ' .
			'`*PREFIX*vcategory` `tag` ' .
			// JOIN vcategory_to_object to vcategory
			'WHERE `tagmap`.`type` = `tag`.`type` ' .
			'AND `tagmap`.`categoryid` = `tag`.`id` ' .
			// conditions
			'AND `tag`.`type` = \'files\' ' .
			'AND `tag`.`uid` = ? ';
		
		if (is_int($tag)) 
		{
			$sql .= 'AND `tag`.`id` = ? ';
		} 
		else 
		{
			$sql .= 'AND `tag`.`category` = ? ';
		}
		
		$result = \OC_DB::executeAudited($sql, [/*$this->getNumericStorageId(),*/ $userId, $tag]);
		$files = [];
		while ($row = $result->fetchRow()) 
		{
			$path = $this->getPathById($row['objid']);
			$meta = $this->get($path);
			$files[] = new CacheEntry($meta);
		}
		return $files;
	}

	/**
	 * update the folder size and the size of all parent folders
	 *
	 * @param string|boolean $path
	 * @param array $data (optional) meta data of the folder
	 */
	public function correctFolderSize($path, $data = null) 
	{
		return true;
	}

	/**
	 * get the size of a folder and set it in the cache
	 *
	 * @param string $path
	 * @param array $entry (optional) meta data of the folder
	 * @return int
	 */
	public function calculateFolderSize($path, $entry = null) 
	{
		return 0;
	}

	/**
	 * get all file ids on the files on the storage
	 *
	 * @return int[]
	 */
	public function getAll() 
	{
		$files = $this->getFolderContents("");
		$ids = [];
		foreach ($files as $file) 
		{
			$ids[] = $file["fileid"];
		}
		return $ids;
	}

	/**
	 * find a folder in the cache which has not been fully scanned
	 *
	 * If multiply incomplete folders are in the cache, the one with the highest id will be returned,
	 * use the one with the highest id gives the best result with the background scanner, since that is most
	 * likely the folder where we stopped scanning previously
	 *
	 * @return string|bool the path of the folder or false when no folder matched
	 */
	public function getIncomplete() 
	{
		return false;
	}

	/**
	 * get the path of a file on this storage by it's id
	 *
	 * @param int $id
	 * @return string|null
	 */
	public function getPathById($id) 
	{
		$data = self::getById($id);
		if ($data) 
		{
			return $data[1];// the ocPath
		}
		return null;
	}

	/**
	 * get the storage id of the storage for a file and the internal path of the file
	 * unlike getPathById this does not limit the search to files on this storage and
	 * instead does a global search in the cache table
	 *
	 * @param int $id
	 * @return array, first element holding the storage id, second the path
	 */
	public function getById($id) 
	{
		$cached = EosCacheManager::getFileById($id);
		if($cached)
		{
			if($cached['path']) 
			{
				$storage_id = EosUtil::getStorageId($cached['eospath']);
				return [$storage_id, $cached['path']];
			}
		} 
		
		$eosMeta = EosUtil::getFileById($id);
		if(!$eosMeta || !isset($eosMeta['path']) || $eosMeta['path'] === false)
		{
			return null;
		}
		
		$ocPath = $eosMeta["path"];
		$storage_id = EosUtil::getStorageId($eosMeta["eospath"]);
		return [$storage_id, $ocPath];
	}

	/**
	 * normalize the given path
	 * @param string $path
	 * @return string
	 */
	public function normalize($path) 
	{
		return \OC_Util::normalizeUnicode($path);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OCP\Files\Cache\ICache::insert()
	 */
	public function insert($file, array $data) 
	{
		/**
		 * CERNBOX - NOT IMPLEMENTED 
		 * Used by ownCloud to insert metadata into the filecache databae table
		 */
		
		if(isset($data['fileid']))
		{
			return $data['fileid'];
		}
		else
		{
			$eosPath = EosProxy::toEos($file, 'object::user:'.\OC_User::getUser());
			$eosMeta = EosUtil::getFileByEosPath($eosPath);
			
			if(!$eosMeta)
			{
				throw new \RuntimeException('EOSCache: Could not find the file ' . $eosPath);
			}
			
			return $eosMeta['fileid'];
		}
	}

	/**
	 * {@inheritDoc}
	 * @see \OCP\Files\Cache\ICache::moveFromCache()
	 */
	public function moveFromCache(ICache $sourceCache, $sourcePath, $targetPath) 
	{
		/**
		 * CERNBOX - NOT IMPLEMENTED
		 * Used by owncloud to change some metadata info from one cache to another in the database
		 */
	}
}
