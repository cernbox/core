<?php

/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 29/07/16
 * Time: 15:56
 */

namespace OC\Files\EosStore;

use OCP\Files\StorageInvalidException;
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
class EosStoreStorage implements \OCP\Files\Storage
{
    /**
     * @var string
     */
    private $storageId;
    /**
     * @var EosCache
     */
    protected $namespace;
    private $user;
    private $homeprefix;
    public  $userhome;

    /**
     * $parameters is a free form array with the configuration options needed to construct the storage
     *
     * @param array $parameters
     * @since 9.0.0
     */
    public function __construct($params)
    {

        // instantiate EosCache
        if (!isset($params['user'])) {
            throw  new StorageNotAvailableException('eos storage instantiated without user');
        }
        $this->user = $params['user'];

        if (!isset($params['homeprefix'])) {
            throw  new StorageNotAvailableException('home prefix has been not defined for eos storage');
        }
        $this->homeprefix= $params['homeprefix'];

        $this->userhome = join("/", array($this->homeprefix, $this->user->getUID()));

        \OC::$server->getLogger()->info('eos storage instantiated', array('user'=> $this->user->getUID()));
        \OC::$server->getLogger()->info('eos home is '. $this->userhome, array('home'=> $this->userhome));

        if (!isset($params['storageid'])) {
            $this->storageId= 'eos::store:' . $params['storageid'];
        } else {
            $this->storageId= 'eos::store:' . 'eosdefault';
        }

        // create user home directory if it does not exist
        if (!$this->is_dir('/')) {
            $this->mkdir('/');
        }

        // create files directory if it does not exists
        if (!$this->is_dir('/files')) {
            $this->mkdir('/files');
        }
        $this->namespace= new EosCache($this);

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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        @mkdir($fullpath);
        return true;
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        @rmdir($fullpath);
        return true;
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
        \OC::$server->getLogger()->info("opendir $path");
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return @opendir($fullpath);
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return @is_dir($fullpath);
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return @is_file($fullpath);
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return @stat($fullpath);
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return @filetype($fullpath);
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
        \OC::$server->getLogger()->info("filesize $path");
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        \OC::$server->getLogger()->info("filesize fullpath $fullpath");
        return @filesize($fullpath);
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
        return true;
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
        return true;
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
        return true;
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
        return true;
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
        return true;
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
        // TODO: Implement getPermissions() method.
        return 31;
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));

        return @file_exists($fullpath);
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
        \OC::$server->getLogger()->info("filemtime $path");
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return @filemtime($fullpath);
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        @unlink($fullpath);
        return true;
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
        $fullpath1 = join('/', array($this->userhome, trim($path1, '/')));
        $fullpath2 = join('/', array($this->userhome, trim($path2, '/')));
        @rename($fullpath1, $fullpath2);
        return true;
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
        $fullpath1 = join('/', array($this->userhome, trim($path1, '/')));
        $fullpath2 = join('/', array($this->userhome, trim($path2, '/')));
        @copy($fullpath1, $fullpath2);
        return true;
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return @fopen($fullpath, 'r');
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        if($this->is_dir($path)) {
            return 'httpd/unix-directory';
        } else {
            \OC::$server->getMimeTypeDetector()->detectPath($path);
        }
    }

    /**
     * see http://php.net/manual/en/function.hash-file.php
     *
     * @param string $type
     * @param string $path
     * @param bool $raw
     * @return string|false
     * @since 9.0.0
     */
    public function hash($type, $path, $raw = false)
    {
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return @hash($type, $fullpath, $raw);
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
        return '102423949239';
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        $mtime = $this->filemtime($path);
        return $mtime > $time;
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
        $fullpath = join('/', array($this->userhome, trim($path, '/')));
        return (string)($this->filemtime($path));
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
        return false;
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
     */
    public function verifyPath($path, $fileName)
    {
        // TODO: Implement verifyPath() method.
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
        return true;
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
        return true;
    }

    /**
     * Test a storage for availability
     *
     * @since 9.0.0
     * @return bool
     */
    public function test()
    {
        return true;
    }

    /**
     * @since 9.0.0
     * @return array [ available, last_checked ]
     */
    public function getAvailability()
    {
        return array("available" => true, "last_checked" => time());
    }

    /**
     * @since 9.0.0
     * @param bool $isAvailable
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
        return new EosNullPropagator();
    }

    /**
     * @return \OCP\Files\Cache\IScanner
     * @since 9.0.0
     */
    public function getScanner()
    {
        return new EosNullScanner();
    }

    /**
     * @return \OCP\Files\Cache\IUpdater
     * @since 9.0.0
     */
    public function getUpdater()
    {
        return new EosNullUpdater();
    }

    /**
     * @return \OCP\Files\Cache\IWatcher
     * @since 9.0.0
     */
    public function getWatcher()
    {
        return new EosNullWatcher();
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