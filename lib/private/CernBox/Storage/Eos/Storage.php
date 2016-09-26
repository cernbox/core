<?php

/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 29/07/16
 * Time: 15:56
 */

namespace OC\CernBox\Storage\Eos;

use Icewind\Streams\IteratorDirectory;
use OC\CernBox\Share\Util as ShareUtil;
use OC\CernBox\Storage\MetaDataCache\IMetaDataCache;
use OC\Files\Filesystem;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\Lock\ILockingProvider;

/**
 * Class EosStoreStorage
 * @package OC\Files\EosStore
 * This class implements the ownCloud Storage interfaces to use
 * EOS as primary storage for ownCloud.
 */
// TODO(labkode) it should be EosStoreStorage implements \OCP\Files\Storage\IStorage
// but ownCloud is not flexible enough to handle it.
/**
 * Class Storage
 *
 * @package OC\CernBox\Storage\Eos
 */
class Storage implements \OCP\Files\Storage
{
    /**
     * @var string
     */
    private $storageId;

    /**
     * @var Catalog
     */
    protected $catalog;

	/**
	 * @var Util
	 */
	private $util;

	/**
	 * @var ShareUtil
	 */
	private $shareUtil;

	/**
	 * @var null|\OC\User\User
	 */
	public $user;
	/**
	 * @var int
	 */
	private $userUID;
	/**
	 * @var int
	 */
	private $userGID;
	/**
	 * @var InstanceManager
	 */
	public $instanceManager;

	/**
	 * @var array string
	 */
	private static $tmpFiles;
	/**
	 * @var \OCP\ILogger
	 */
	private $logger;

	/**
	 * @var IMetaDataCache
	 */
	private $metaDataCache;


	/**
	 * Storage constructor.
	 *
	 * @param array $params
	 * @throws StorageNotAvailableException
	 */
	public function __construct($params)
    {

    	$this->logger = \OC::$server->getLogger();
    	$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
    	$this->util = \OC::$server->getCernBoxEosUtil();
		$this->shareUtil= \OC::$server->getCernBoxShareUtil();
		$this->metaDataCache = \OC::$server->getCernBoxMetaDataCache();

		$user = $params['user'];

		// if the username is not passed it means the
		// request is pointing to shared by link resource, thus
		// we don't have user context but we can extract it querying the
		// share database and in the future EOS.
        if (!$user) {
        	$user = $this->shareUtil->getUsernameFromSharedToken();
        }

        if(!$user) {
			// if at this point we do not have an username
			// we abort the operation.
			$ex = new \Exception("impossible to get username");
			$this->logger->error($ex->getMessage());
			throw $ex;
		}

        // sometimes the user is passed as a string instead of a full User object
        // so we check it and convert the string to User object if needed.
		if(is_string($user)) {
			$this->logger->debug("username is $user");
			// convert user string to user object
			$user = \OC::$server->getUserManager()->get($user);
		}

		if (!$user) {
			throw  new \Exception("eos storage instantiated with unknown user");
		}

		$userID = null;
		$userGroupID = null;
		// obtain uid and gid for user
		// try first in the cache
		list($userID, $userGroupID) = $this->metaDataCache->getUidAndGid($user->getUID());
		if($userID && $userGroupID) {
			$this->logger->info("cache hit for " . $user->getUID());
		} else {
			$this->logger->info("cache miss for " . $user->getUID());
			list ($userID, $userGroupID) = $this->util->getUidAndGidForUsername($user->getUID());
			$this->metaDataCache->setUidAndGid($user->getUID(), array($userID, $userGroupID));
		}
		$this->logger->debug("username " . $user->getUID() . " has uid:$userID and gid:$userGroupID");
		if (!$userID || !$userGroupID) {
			throw  new \Exception('user does not have an uid or gid');
		}

		$this->logger->debug("eos storage instantiated for user " . $user->getUID());
		$this->user = $user;
		$this->userUID = $userID;
		$this->userGID = $userGroupID;

		$this->storageId= 'eos::store:' . $this->instanceManager->getInstanceId() . "-" . $user->getUID();

        // instantiate the catalog
        $this->catalog= new Catalog($this);

    }

    /**
     * Get the identifier for the storage,
     * the returned id should be the same for every storage object that is created with the same parameters
     * and two storage objects with the same id should refer to two storages that display the same files.
     *
     * @return string
     * @since 9.0.0
     */
    public function getId()
    {
        return $this->storageId;
    }

    /**
     * see http://php.net/manual/en/function.mkdir.php
     * implementations need to implement a recursive mkdir
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function mkdir($path)
    {
    	return $this->instanceManager->createDir($this->user->getUID(), $path);
    }

    /**
     * see http://php.net/manual/en/function.rmdir.php
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function rmdir($path)
    {
    	return $this->instanceManager->remove($this->user->getUID(), $path);
    }

    /**
     * see http://php.net/manual/en/function.opendir.php
     *
     * @param string $path
     * @return resource|false
     * @since 9.0.0
     */
    public function opendir($path)
    {
		try {
			$files = array();
			$folderContents = $this->getCache()->getFolderContents($path);
			foreach ($folderContents as $file) {
				$files[] = $file['name'];
			}
			return IteratorDirectory::wrap($files);
		} catch (\Exception $e) {
			$this->logger->error($e);
			return false;
		}
    }

    /**
     * see http://php.net/manual/en/function.is-dir.php
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function is_dir($path)
    {
    	$entry = $this->getCache()->get($path);
		if (!$entry) {
			return false;
		}

		return $entry->getMimeType() === 'httpd/unix-directory';
    }

    /**
     * see http://php.net/manual/en/function.is-file.php
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function is_file($path)
    {
		$entry = $this->getCache()->get($path);
		if (!$entry) {
			return false;
		}
		return $entry->getMimeType() !== 'httpd/unix-directory';
    }

    /**
     * see http://php.net/manual/en/function.stat.php
     * only the following keys are required in the result: size and mtime
     *
     * @param string $path
     * @return array|false
     * @since 9.0.0
     */
    public function stat($path)
    {
		$entry = $this->getCache()->get($path);
		if (!$entry) {
			return false;
		}
		return ["size" => $entry->getSize(), "mtime" => $entry->getMTime()];
    }

    /**
     * see http://php.net/manual/en/function.filetype.php
     *
     * @param string $path
     * @return string|false
     * @since 9.0.0
     */
    public function filetype($path)
    {
    	$isDir = $this->is_dir($path);
		if ($isDir) {
			return 'dir';
		} else {
			return 'file';
		}
    }

    /**
     * see http://php.net/manual/en/function.filesize.php
     * The result for filesize when called on a folder is required to be 0
     *
     * @param string $path
     * @return int|false
     * @since 9.0.0
     */
    public function filesize($path)
    {
    	$entry = $this->getCache()->get($path);
		if (!$entry) {
			throw  new NotFoundException($path);
		}
		return $entry->getMimeType() === 'httpd/unix-directory' ? 0 : $entry->getSize();
    }

    /**
     * check if a file can be created in $path
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function isCreatable($path)
    {
    	if ($this->is_dir($path) && $this->isUpdatable($path)) {
    		return true;
		} else {
			return false;
		}
    }

    /**
     * check if a file can be read
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function isReadable($path)
    {
    	return $this->file_exists($path);
    }

    /**
     * check if a file can be written to
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function isUpdatable($path)
    {
    	return $this->file_exists($path);
    }

    /**
     * check if a file can be deleted
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function isDeletable($path)
    {
    	// home directories cannot be deleted
    	if ($path === '' || $path === '/' || $path === '.') {
    		return false;
		}

		$parent = dirname($path);
		return $this->isUpdatable($parent) && $this->isUpdatable($path);
    }

    /**
     * check if a file can be shared
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function isSharable($path)
    {
    	// FIXME: plug here custom logic to say if a file can be shared or not.
		return $this->isReadable($path);
    }

    /**
     * get the full permissions of a path.
     * Should return a combination of the PERcache missION_ constants defined in lib/public/constants.php
     *
     * @param string $path
     * @return int
     * @since 9.0.0
     */
    public function getPermissions($path)
    {
    	$entry = $this->getCache()->get($path);
		if(!$entry) {
			return false;
		}
		return $entry['permissions'];
    }

    /**
     * see http://php.net/manual/en/function.file_exists.php
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function file_exists($path)
    {
    	return $this->getCache()->get($path);
    }

    /**
     * see http://php.net/manual/en/function.filemtime.php
     *
     * @param string $path
     * @return int|false
     * @since 9.0.0
     */
    public function filemtime($path)
    {
    	$entry = $this->getCache()->get($path);
		if (!$entry) {
			return false;
		}
		return $entry->getMTime();
    }

    /**
     * see http://php.net/manual/en/function.file_get_contents.php
     *
     * @param string $path
     * @return string|false
     * @since 9.0.0
     */
    public function file_get_contents($path)
    {
    	$handle = $this->fopen($path, 'r');
		if($handle) {
			return false;
		}
		$data = stream_get_contents($handle);
		fclose($handle);
		return $data;
    }

    /**
     * see http://php.net/manual/en/function.file_put_contents.php
     *
     * @param string $path
     * @param string $data
     * @return bool
     * @since 9.0.0
     */
    public function file_put_contents($path, $data)
    {
    	$handle = $this->fopen($path, 'w');
		$count = fwrite($handle, $data);
		fclose($handle);
		return $count;
    }

    /**
     * see http://php.net/manual/en/function.unlink.php
     *
     * @param string $path
     * @return bool
     * @since 9.0.0
     */
    public function unlink($path)
    {
    	return $this->instanceManager->remove($this->user->getUID(), $path);
    }

    /**
     * see http://php.net/manual/en/function.rename.php
     *
     * @param string $path1
     * @param string $path2
     * @return bool
     * @since 9.0.0
     */
    public function rename($path1, $path2)
    {
		return $this->instanceManager->rename($this->user->getUID(), $path1, $path2);
    }

    /**
     * see http://php.net/manual/en/function.copy.php
     *
     * @param string $path1
     * @param string $path2
     * @return bool
     * @since 9.0.0
     */
    public function copy($path1, $path2)
    {
		if ($this->is_dir($path1)) {
			$this->instanceManager->remove($this->user->getUID(), $path2);
			$dir = $this->opendir($path1);
			$this->mkdir($path2);
			while ($file = readdir($dir)) {
				if (!Filesystem::isIgnoredDir($file)) {
					if (!$this->copy($path1 . '/' . $file, $path2 . '/' . $file)) {
						return false;
					}
				}
			}
			closedir($dir);
			return true;
		} else {
			$source = $this->fopen($path1, 'r');
			$target = $this->fopen($path2, 'w');
			list(, $result) = \OC_Helper::streamCopy($source, $target);
			return $result;
		}
    }

    /**
     * see http://php.net/manual/en/function.fopen.php
     *
     * @param string $path
     * @param string $mode
     * @return resource|false
     * @since 9.0.0
     */
    public function fopen($path, $mode)
    {
		switch ($mode)
		{
			case 'r':
			case 'rb':
				return $this->instanceManager->read($this->user->getUID(), $path);
			case 'w':
			case 'wb':
			case 'a':
			case 'ab':
			case 'r+':
			case 'w+':
			case 'wb+':
			case 'a+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				if (strrpos($path, '.') !== false)
				{
					$ext = substr($path, strrpos($path, '.'));
				} else
				{
					$ext = '';
				}
				$tmpFile = \OC::$server->getTempManager()->getTemporaryFile($ext);
				\OC\Files\Stream\Close::registerCallback($tmpFile, array($this, 'writeBack'));
				if ($this->file_exists($path))
				{
					$source = $this->fopen($path, 'r');
					file_put_contents($tmpFile, $source);
				}
				self::$tmpFiles[$tmpFile] = $path;

				return fopen('close://' . $tmpFile, $mode);
		}
		return false;
    }

    /**
     * get the mimetype for a file or folder
     * The mimetype for a folder is required to be "httpd/unix-directory"
     *
     * @param string $path
     * @return string|false
     * @since 9.0.0
     */
    public function getMimeType($path)
    {
    	$entry = $this->getCache()->get($path);
		if ($entry) {
			return false;
		}
    	return \OC::$server->getMimeTypeDetector()->detectString($path);
    }

    /**
     * see http://php.net/manual/en/function.hash-file.php
     *
     * @param string $type
     * @param string $path
     * @param bool $raw
     * @return string|false
     * @since 9.0.0
	 * FIXME: this function is completely no sense, fclose really?
     */
    public function hash($type, $path, $raw = false)
    {
		$fh = $this->fopen($path, 'rb');
		$ctx = hash_init($type);
		hash_update_stream($ctx, $fh);
		fclose($fh);
		return hash_final($ctx, $raw);
    }

    /**
     * see http://php.net/manual/en/function.free_space.php
     *
     * @param string $path
     * @return int|false
     * @since 9.0.0
     */
    public function free_space($path)
    {
    	// FIXME: check really free space on EOS
    	return \OCP\Files\FileInfo::SPACE_UNLIMITED;
    }

    /**
     * see http://php.net/manual/en/function.touch.php
     * If the backend does not support the operation, false should be returned
     *
     * @param string $path
     * @param int $mtime
     * @return bool
     * @since 9.0.0
     */
    public function touch($path, $mtime = null)
    {
    	$stream = fopen('php://memory', 'r');
		return $this->instanceManager->write($this->user->getUID(), $path, $stream);
    }

    /**
     * get the path to a local version of the file.
     * The local version of the file can be temporary and doesn't have to be persistent across requests
     *
     * @param string $path
     * @return string|false
     * @since 9.0.0
     */
    public function getLocalFile($path)
    {
        return false;
    }

    /**
     * check if a file or folder has been updated since $time
     *
     * @param string $path
     * @param int $time
     * @return bool
     * @since 9.0.0
     *
     * hasUpdated for folders should return at least true if a file inside the folder is add, removed or renamed.
     * returning true for other changes in the folder is optional
     */
    public function hasUpdated($path, $time)
    {
    	return $this->filemtime($path) > $time;
    }

    /**
     * get the ETag for a file or folder
     *
     * @param string $path
     * @return string|false
     * @since 9.0.0
     */
    public function getETag($path)
    {
    	$entry = $this->getCache()->get($path);
		if(!$entry) {
			return false;
		}
		return $entry->getEtag();
    }

    /**
     * Returns whether the storage is local, which means that files
     * are stored on the local filesystem instead of remotely.
     * Calling getLocalFile() for local storages should always
     * return the local files, whereas for non-local storages
     * it might return a temporary file.
     *
     * @return bool true if the files are stored locally, false otherwise
     * @since 9.0.0
     */
    public function isLocal()
    {
        return false;
    }

    /**
     * Check if the storage is an instance of $class or is a wrapper for a storage that is an instance of $class
     *
     * @param string $class
     * @return bool
     * @since 9.0.0
     */
    public function instanceOfStorage($class)
    {
    	return is_a($this, $class);
    }

    /**
     * A custom storage implementation can return an url for direct download of a give file.
     *
     * For now the returned array can hold the parameter url - in future more attributes might follow.
     *
     * @param string $path
     * @return array|false
     * @since 9.0.0
     */
    public function getDirectDownload($path)
    {
        return false;
    }

    /**
     * @param string $path the path of the target folder
     * @param string $fileName the name of the file itself
     * @return void
     * @throws \OCP\Files\InvalidPathException
     * @since 9.0.0
	 * FIXME: here we can put forbidden names that go to EOS
	 * FIXME: or call put this behaviour on Eos instance;
     */
    public function verifyPath($path, $fileName)
    {
		return;
    }

    /**
     * @param \OCP\Files\Storage $sourceStorage
     * @param string $sourceInternalPath
     * @param string $targetInternalPath
     * @return bool
     * @since 9.0.0
     */
    public function copyFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath)
    {
        return false;
    }

    /**
     * @param \OCP\Files\Storage $sourceStorage
     * @param string $sourceInternalPath
     * @param string $targetInternalPath
     * @return bool
     * @since 9.0.0
     */
    public function moveFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath)
    {
        return false;
    }

    /**
     * Test a storage for availability
     *
     * @since 9.0.0
     * @return bool
	 * FIXME: here we can put our SLA conditions for EOS to be in
	 * FIXME: a working condition
     */
    public function test()
    {
        return true;
    }

    /**
     * @since 9.0.0
     * @return array [ available, last_checked ]
	 * FIXME: here we can put our SLA conditions for EOS to be in
	 * FIXME: a working condition
     */
    public function getAvailability()
    {
        return array("available" => true, "last_checked" => time());
    }

    /**
     * @since 9.0.0
     * @param bool $isAvailable
	 * @return bool
     */
    public function setAvailability($isAvailable)
    {
        return true;
    }

    /**
     * @param string $path path for which to retrieve the owner
     * @since 9.0.0
     */
    public function getOwner($path)
    {
    	// all files that come are relative to user storage
		// so the user will always be the owner of its own files.
        return $this->user->getUID();
    }

    /**
     * @return \OCP\Files\Cache\ICache
     * @since 9.0.0
     */
    public function getCache()
    {
        return $this->catalog;
    }

    /**
     * @return \OCP\Files\Cache\IPropagator
     * @since 9.0.0
     */
    public function getPropagator()
    {
        return new NullPropagator();
    }

    /**
     * @return \OCP\Files\Cache\IScanner
     * @since 9.0.0
     */
    public function getScanner()
    {
        return new NullScanner();
    }

    /**
     * @return \OCP\Files\Cache\IUpdater
     * @since 9.0.0
     */
    public function getUpdater()
    {
        return new NullUpdater();
    }

    /**
     * @return \OCP\Files\Cache\IWatcher
     * @since 9.0.0
     */
    public function getWatcher()
    {
        return new NullWatcher();
    }

    /**
     * search for occurrences of $query in file names
     *
     * @param string $query
     * @return array|false
     * @since 6.0.0
     */
    public function search($query)
    {
        return array();
    }

    /**
     * @param string $path The path of the file to acquire the lock for
     * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
     * @param \OCP\Lock\ILockingProvider $provider
     * @throws \OCP\Lock\LockedException
     * @since 8.1.0
     */
    public function acquireLock($path, $type, ILockingProvider $provider)
    {
        // TODO: Implement acquireLock() method.
    }

    /**
     * @param string $path The path of the file to acquire the lock for
     * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
     * @param \OCP\Lock\ILockingProvider $provider
     * @since 8.1.0
     */
    public function releaseLock($path, $type, ILockingProvider $provider)
    {
        // TODO: Implement releaseLock() method.
    }

    /**
     * @param string $path The path of the file to change the lock for
     * @param int $type \OCP\Lock\ILockingProvider::LOCK_SHARED or \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE
     * @param \OCP\Lock\ILockingProvider $provider
     * @throws \OCP\Lock\LockedException
     * @since 8.1.0
     */
    public function changeLock($path, $type, ILockingProvider $provider)
    {
        // TODO: Implement changeLock() method.
    }

    	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\ObjectStoreStorage::writeBack()
		 * FIXME: can we avoid this?
	 */
	public function writeBack($tmpFile)
	{
		if (!isset(self::$tmpFiles[$tmpFile]))
		{
			return;
		}

		$path = self::$tmpFiles[$tmpFile];
		//$path = $this->normalizePath($path);
		$stat = $this->stat($path);
		try
		{
			$this->instanceManager->write($this->user->getUID(), $path, fopen($tmpFile, 'r'));
		}
		catch (\Exception $ex)
		{
			\OC::$server->getLogger()->error('could not write back: ' . $ex->getMessage());
			return false;
		}
	}
}
