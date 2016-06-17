<?php

namespace OC\Cernbox\Storage;

use OCP\Files\ObjectStore\IObjectStore;

class EosObjectStoreStorage extends \OC\Files\ObjectStore\ObjectStoreStorage
{
	/**
	 * @var array
	 */
	private static $tmpFiles = [];
	
	/**
	 * Overriding default ObjectStoreStorage constructor to avoid creating
	 * the root directory on cache on every request.
	 * @param unknown $params
	 * @throws \Exception
	 */
	public function __construct($params)
	{
		if (isset($params['objectstore']) && $params['objectstore'] instanceof IObjectStore) {
			$this->objectStore = $params['objectstore'];
		} else {
			throw new \Exception('missing IObjectStore instance');
		}
		if (isset($params['storageid'])) {
			$this->id = 'object::store:' . $params['storageid'];
		} else {
			$this->id = 'object::store:' . $this->objectStore->getStorageId();
		}	
	}
	
	/**
	 * @param string $path
	 * @return string
	 */
	private function normalizePath($path) {
		$path = trim($path, '/');
		//FIXME why do we sometimes get a path like 'files//username'?
		$path = str_replace('//', '/', $path);
	
		// dirname('/folder') returns '.' but internally (in the cache) we store the root as ''
		if (!$path || $path === '.') {
			$path = '';
		}
	
		return $path;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\Storage\Common::getCache()
	 * 
	 * This function overrides default getCache function in order
	 * to "inject" our custom eos cache into the server core
	 */
	public function getCache($path = '', $storage = null) 
	{
		if (!$storage) 
		{
			$storage = $this;
		}
		
		if (!isset($this->cache)) 
		{
			$this->cache = new EosCache($storage);
		}
		return $this->cache;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\ObjectStoreStorage::mkdir()
	 */
	public function mkdir($path)
	{
		$path = $this->normalizePath($path);
		
		$dirName = $this->normalizePath(dirname($path));
		return 	$this->objectStore->mkdir(EosProxy::toEos($path, $this->getOwner($path)));
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\Storage\Common::isReadable()
	 */
	public function isReadable($path)
	{
		$data = $this->getCache()->get($path);
		if($data)
		{
			$permissions = $data["permissions"];
			if($permissions)
			{
				if($permissions & \OCP\PERMISSION_READ)
				{
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\Storage\Common::isUpdatable()
	 */
	public function isUpdatable($path)
	{
		$data = $this->getCache()->get($path);
		if($data)
		{
			$permissions = $data["permissions"];
			if($permissions)
			{
				if($permissions & \OCP\PERMISSION_UPDATE)
				{
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\ObjectStoreStorage::rmdir()
	 */
	public function rmdir($path) 
	{
		$path = $this->normalizePath($path);
		return 	$this->objectStore->deleteObject(EosProxy::toEos($path, $this->id));
	}
	
	/**
	 * Deletes an object from the Object Storage
	 * @param string $path
	 */
	private function rmObjects($path) 
	{
		$path = $this->normalizePath($path);
		$this->objectStore->deleteObject(EosProxy::toEos($path, $this->getOwner($path)));
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\ObjectStoreStorage::unlink()
	 */
	public function unlink($path) 
	{
		$path = $this->normalizePath($path);
		return	$this->objectStore->deleteObject(EosProxy::toEos($path, $this->getOwner($path)));
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\ObjectStoreStorage::fopen()
	 */
	public function fopen($path, $mode) 
	{
		$path = $this->normalizePath($path);
	
		switch ($mode) 
		{
			case 'r':
			case 'rb':
				return $this->objectStore->readObject(EosProxy::toEos($path, $this->getOwner($path)));
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
				$tmpFile = \OC_Helper::tmpFile($ext);
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
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\ObjectStoreStorage::rename()
	 */
	public function rename($source, $target) 
	{
		$source = $this->normalizePath($source);
		$target = $this->normalizePath($target);
		if(!$this->objectStore->rename(EosProxy::toEos($source, $this->getOwner($source)), EosProxy::toEos($target, $this->getOwner($target))))
		{
			return false;
		}
		return true;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\ObjectStoreStorage::touch()
	 */
	public function touch($path, $mtime = null) 
	{
		$path = $this->normalizePath($path);
		return 	$this->objectStore->writeObject(EosProxy::toEos($path, $this->getOwner($path)), fopen('php://memory', 'r'));
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\ObjectStore\ObjectStoreStorage::writeBack()
	 */
	public function writeBack($tmpFile) 
	{
		if (!isset(self::$tmpFiles[$tmpFile])) 
		{
			return;
		}
	
		$path = self::$tmpFiles[$tmpFile];
		$path = $this->normalizePath($path);
		$stat = $this->stat($path);
		try 
		{
			//upload to object storage
			$this->objectStore->writeObject(EosProxy::toEos($path, $this->getOwner($path)), fopen($tmpFile, 'r'));
		} 
		catch (\Exception $ex) 
		{
			\OCP\Util::writeLog('objectstore', 'Could not create object: ' . $ex->getMessage(), \OCP\Util::ERROR);
			return false;
		}
	}
}