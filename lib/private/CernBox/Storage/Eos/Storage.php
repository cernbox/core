<?php

/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 29/07/16
 * Time: 15:56
 */

namespace OC\CernBox\Storage\Eos;

use Icewind\Streams\IteratorDirectory;
use League\Flysystem\FileNotFoundException;
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
class Storage implements \OCP\Files\Storage
{
    /**
     * @var string
     */
    private $storageId;

    /**
     * @var Cache
     */
    protected $namespace;

	private $util;

	private $user;
	private $userUID;
	private $userGID;
	private $instanceManager;
	private $eosHomeDirectory;


    public function __construct($params)
    {
    	$this->logger = \OC::$server->getLogger();
    	$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
    	$this->util = \OC::$server->getCernBoxEosUtil();

        // instantiate Cache
        if (!isset($params['user'])) {
            throw  new StorageNotAvailableException('eos storage instantiated without user');
        }

        // sometime the user is passed as a string instead of a full User object
        // so we check it and convert the string to User object if needed.
        $user = $params['user'];
		if(is_string($user)) {
			// convert user string to user object
			$user = \OC::$server->getUserManager()->get($user);
		}

		if (!$user) {
			throw  new StorageNotAvailableException("eos storage instantiated with unknown user: $user");
		}

		// obtain uid and guid for user
		list ($userID, $userGroupID) = $this->util->getUidAndGid($user->getUID());
		if (!$userID || !$userGroupID) {
			throw  new StorageNotAvailableException('user does not have an uid or gid');
		}

		$this->user = $user;
		$this->userUID = $userID;
		$this->userGID = $userGroupID;

		// $eosHomeDirectory is a full EOS path
		// ex: '/eos/user/l/labrador'
		$eosHomeDirectory = $this->instanceManager->getHomeDirectoryForUser($user->getUID());
		if (!$eosHomeDirectory) {
			throw  new StorageNotAvailableException('user does not have a valid home directory on EOS');
		}

        \OC::$server->getLogger()->info("eos storage instantiated for user:$user->getUID() with home:$eosHomeDirectory");

		// $eosInstance is the EOS instance that has the user home directory.
		// ex: eosuser or eosbackup
		$eosInstanceForThisUser = $this->instanceManager->getEosInstanceForUser($user->getUID());
		if (!$eosInstanceForThisUser) {
			throw  new StorageNotAvailableException('user is not assigned to any EOS instance');
		}
		$this->storageId= 'eos::store:' . $eosInstanceForThisUser . "-" . $user->getUID();

        // create user home directory if it does not exist
		/*
        if (!$this->is_dir('/')) {
            $this->mkdir('/');
        }

        // create files directory if it does not exists
        if (!$this->is_dir('/files')) {
            $this->mkdir('/files');
        }
		*/

        // instantiate the namespace
        $this->namespace= new Cache($this);

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
    	return $this->instanceManager->mkdir($this->user->getUID(), $path);
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
    	return $this->instanceManager->rmdir($this->user->getUID(), $path);
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

		return $entry->getMimeType() === 'http/unix-directory';
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
			throw  new FileNotFoundException($path);
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
     * Should return a combination of the PERMISSION_ constants defined in lib/public/constants.php
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
        $fullpath = join('/', array($this->userHome, trim($path, '/')));
        return @file_get_contents($fullpath);
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
		\OC::$server->getLogger()->info("file_put_contents $path");
        $fullpath = join('/', array($this->userHome, trim($path, '/')));
        @file_put_contents($fullpath, $data);
        return true;
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
		return $this->instanceManager->copy($this->user->getUID(), $path1, $path2);
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
		\OC::$server->getLogger()->info("fopen $path $mode");
        $fullpath = join('/', array($this->userHome, trim($path, '/')));
        return fopen($fullpath, $mode);
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
        return false;
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
        return $this->namespace;
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

}
