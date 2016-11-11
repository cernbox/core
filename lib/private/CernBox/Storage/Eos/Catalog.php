<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 31/07/16
 * Time: 23:57
 */

namespace OC\CernBox\Storage\Eos;


use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\NotFoundException;

class Catalog implements ICache
{

	/**
	 * @var \OC\CernBox\Storage\Eos\Storage
	 */
	private $instanceManager;
	private $storage;
	private $username;

    /**
     * @param \OC\CernBox\Storage\Eos\Storage $storage
     */
    public function __construct($storage)
    {
    	$this->storage = $storage;
		$this->username =  $storage->username;
		$this->instanceManager = $storage->instanceManager;
    }

    /**
     * Get the numeric storage id for this cache's storage
     *
     * @return int
     * @since 9.0.0
     */
    public function getNumericStorageId()
    {
        return $this->storage->userUID;
    }

    public function getNumericId() {
        return $this->getNumericStorageId();
    }
    /**
     * get the stored metadata of a file or folder
     *
     * @param string | int $file either the path of a file or folder or the file id for a file or folder
     * @return ICacheEntry|false the cache entry or false if the file is not found in the cache
     * @since 9.0.0
     */
    public function get($file)
    {
		if($this->username === Storage::USERNAME_FOR_REQUEST_WITHOUT_USER_CONTEXT) {
			return false;
		}
    	return $this->instanceManager->get($this->username, $file);
    }

    /**
     * get the metadata of all files stored in $folder
     *
     * Only returns files one level deep, no recursion
     *
     * @param string $folder
     * @return ICacheEntry[]
     * @since 9.0.0
     */
    public function getFolderContents($folder)
    {
		if($this->username === Storage::USERNAME_FOR_REQUEST_WITHOUT_USER_CONTEXT) {
			return array();
		}
		return $this->instanceManager->getFolderContents($this->username, $folder);
    }

    /**
     * get the metadata of all files stored in $folder
     *
     * Only returns files one level deep, no recursion
     *
     * @param int $fileId the file id of the folder
     * @return ICacheEntry[]
     * @since 9.0.0
     */
    public function getFolderContentsById($fileId)
    {
		if($this->username === Storage::USERNAME_FOR_REQUEST_WITHOUT_USER_CONTEXT) {
			return array(); // ideally return false
		}
		return $this->instanceManager->getFolderContentsById($this->username, $fileId);
    }

    /**
     * store meta data for a file or folder
     * This will automatically call either insert or update depending on if the file exists
     *
     * @param string $file
     * @param array $data
     *
     * @return int file id
     * @throws \RuntimeException
     * @since 9.0.0
     */
    public function put($file, array $data)
    {
        return;
    }

    /**
     * insert meta data for a new file or folder
     *
     * @param string $file
     * @param array $data
     *
     * @return int file id
     * @throws \RuntimeException
     * @since 9.0.0
     */
    public function insert($file, array $data)
    {
        return;
    }

    /**
     * update the metadata of an existing file or folder in the cache
     *
     * @param int $id the fileid of the existing file or folder
     * @param array $data [$key => $value] the metadata to update, only the fields provided in the array will be updated, non-provided values will remain unchanged
     * @since 9.0.0
     */
    public function update($id, array $data)
    {
        return;
    }

    /**
     * get the file id for a file
     *
     * A file id is a numeric id for a file or folder that's unique within an owncloud instance which stays the same for the lifetime of a file
     *
     * File ids are easiest way for apps to store references to a file since unlike paths they are not affected by renames or sharing
     *
     * @param string $file
     * @return int
     * @since 9.0.0
     */
    public function getId($file)
    {
		if($this->username === Storage::USERNAME_FOR_REQUEST_WITHOUT_USER_CONTEXT) {
			return false;
		}
    	$entry = $this->get($file);
		if (!$entry) {
			return null;
		}
    	return $entry->getId();
    }

    /**
     * get the id of the parent folder of a file
     *
     * @param string $file
     * @return int
     * @since 9.0.0
     */
    public function getParentId($file)
    {
		if($this->username === Storage::USERNAME_FOR_REQUEST_WITHOUT_USER_CONTEXT) {
			return false;
		}
        $parent = dirname($file);
        $entry = $this->get($parent);
		if(!$entry) {
			throw new NotFoundException($file);
		}
        return $entry->getId();
    }

    /**
     * check if a file is available in the cache
     *
     * @param string $file
     * @return bool
     * @since 9.0.0
     */
    public function inCache($file)
    {
        return true;
    }

    /**
     * remove a file or folder from the cache
     *
     * when removing a folder from the cache all files and folders inside the folder will be removed as well
     *
     * @param string $file
     * @since 9.0.0
     */
    public function remove($file)
    {
        return;
    }

    /**
     * Move a file or folder in the cache
     *
     * @param string $source
     * @param string $target
     * @since 9.0.0
     */
    public function move($source, $target)
    {
        return;
    }

    /**
     * Move a file or folder in the cache
     *
     * Note that this should make sure the entries are removed from the source cache
     *
     * @param \OCP\Files\Cache\ICache $sourceCache
     * @param string $sourcePath
     * @param string $targetPath
     * @throws \OC\DatabaseException
     * @since 9.0.0
     */
    public function moveFromCache(ICache $sourceCache, $sourcePath, $targetPath)
    {
        return;
    }

    /**
     * Get the scan status of a file
     *
     * - ICache::NOT_FOUND: File is not in the cache
     * - ICache::PARTIAL: File is not stored in the cache but some incomplete data is known
     * - ICache::SHALLOW: The folder and it's direct children are in the cache but not all sub folders are fully scanned
     * - ICache::COMPLETE: The file or folder, with all it's children) are fully scanned
     *
     * @param string $file
     *
     * @return int ICache::NOT_FOUND, ICache::PARTIAL, ICache::SHALLOW or ICache::COMPLETE
     * @since 9.0.0
     */
    public function getStatus($file)
    {
        return ICache::COMPLETE;
    }

    /**
     * search for files matching $pattern, files are matched if their filename matches the search pattern
     *
     * @param string $pattern the search pattern using SQL search syntax (e.g. '%searchstring%')
     * @return ICacheEntry[] an array of cache entries where the name matches the search pattern
     * @since 9.0.0
     * @deprecated 9.0.0 due to lack of pagination, not all backends might implement this
     */
    public function search($pattern)
    {
        return array();
    }

    /**
     * search for files by mimetype
     *
     * @param string $mimetype either a full mimetype to search ('text/plain') or only the first part of a mimetype ('image')
     *        where it will search for all mimetypes in the group ('image/*')
     * @return ICacheEntry[] an array of cache entries where the mimetype matches the search
     * @since 9.0.0
     * @deprecated 9.0.0 due to lack of pagination, not all backends might implement this
     */
    public function searchByMime($mimetype)
    {
        return array();
    }

    /**
     * Search for files by tag of a given users.
     *
     * Note that every user can tag files differently.
     *
     * @param string|int $tag name or tag id
     * @param string $userId owner of the tags
     * @return ICacheEntry[] file data
     * @since 9.0.0
     * @deprecated 9.0.0 due to lack of pagination, not all backends might implement this
     */
    public function searchByTag($tag, $userId)
    {
        return array();
    }

    /**
     * find a folder in the cache which has not been fully scanned
     *
     * If multiple incomplete folders are in the cache, the one with the highest id will be returned,
     * use the one with the highest id gives the best result with the background scanner, since that is most
     * likely the folder where we stopped scanning previously
     *
     * @return string|bool the path of the folder or false when no folder matched
     * @since 9.0.0
     */
    public function getIncomplete()
    {
        return array();
    }

    /**
     * get the path of a file on this storage by it's file id
     *
     * @param int $id the file id of the file or folder to search
     * @return string|null the path of the file (relative to the storage) or null if a file with the given id does not exists within this cache
     * @since 9.0.0
     */
    public function getPathById($id)
    {
        return $this->instanceManager->getPathById($this->username, $id);
    }

    /**
     * normalize the given path for usage in the cache
     *
     * @param string $path
     * @return string
     * @since 9.0.0
     */
    public function normalize($path)
    {
        // TODO: Implement normalize() method.
    }

}
