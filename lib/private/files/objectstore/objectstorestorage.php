<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Files\ObjectStore;

use Icewind\Streams\IteratorDirectory;
use OCP\Files\ObjectStore\IObjectStore;

class ObjectStoreStorage extends \OC\Files\Storage\Common {

	/**
	 * @var array
	 */
	private static $tmpFiles = array();
	/**
	 * @var \OCP\Files\ObjectStore\IObjectStore $objectStore
	 */
	protected $objectStore;
	/**
	 * @var \OC\User\User $user
	 */
	protected $user;
	
	public function getCache($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->cache)) {
			$this->cache = new \OC\Files\Cache\EosCache($storage);  
		}
		return $this->cache;
	}

	public function __construct($params) {
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

	public function mkdir($path) {
		$path = $this->normalizePath($path);

		$dirName = $this->normalizePath(dirname($path));
		return 	$this->objectStore->mkdir(EosProxy::toEos($path, $this->getOwner($path)));
	}

	// override from Common
	public function isReadable($path){
		$data = $this->getCache()->get($path);
		if($data){
			$permissions = $data["permissions"];
			if($permissions){
				if($permissions & \OCP\PERMISSION_READ){
					return true;
				} 
			} 
		}
		return false; 
	}
	

	// override from Common to use permissions instead of file existence
	public function isUpdatable($path){
		$data = $this->getCache()->get($path);
		if($data){
			$permissions = $data["permissions"];
			if($permissions){
				if($permissions & \OCP\PERMISSION_UPDATE){
					return true;
				} 
			} 
		}
		return false; 
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
	 * Object Stores use a NoopScanner because metadata is directly stored in
	 * the file cache and cannot really scan the filesystem. The storage passed in is not used anywhere.
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the scanner
	 * @return \OC\Files\ObjectStore\NoopScanner
	 */
	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->scanner)) {
			$this->scanner = new NoopScanner($storage);
		}
		return $this->scanner;
	}

	public function getId() {
		return $this->id;
	}

	public function rmdir($path) {
		$path = $this->normalizePath($path);
		return 	$this->objectStore->deleteObject(EosProxy::toEos($path, $this->id));
	}

	private function rmObjects($path) {
		$path = $this->normalizePath($path);
		$this->objectStore->deleteObject(EosProxy::toEos($path, $this->getOwner($path)));
	}

	public function unlink($path) {
		$path = $this->normalizePath($path);
		return	$this->objectStore->deleteObject(EosProxy::toEos($path, $this->getOwner($path)));
	}

	public function stat($path) {
		$path = $this->normalizePath($path);
		return $this->getCache()->get($path);
	}

	/**
	 * Override this method if you need a different unique resource identifier for your object storage implementation.
	 * The default implementations just appends the fileId to 'urn:oid:'. Make sure the URN is unique over all users.
	 * You may need a mapping table to store your URN if it cannot be generated from the fileid.
	 *
	 * @param int $fileId the fileid
	 * @return null|string the unified resource name used to identify the object
	 */
	protected function getURN($fileId) {
		if (is_numeric($fileId)) {
			return 'urn:oid:' . $fileId;
		}
		return null;
	}

	public function opendir($path) {
		$path = $this->normalizePath($path);

		try {
			$files = array();
			$folderContents = $this->getCache()->getFolderContents($path);
			foreach ($folderContents as $file) {
				$files[] = $file['name'];
			}

			return IteratorDirectory::wrap($files);
		} catch (\Exception $e) {
			\OCP\Util::writeLog('objectstore', $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}
	}

	public function filetype($path) {
		$path = $this->normalizePath($path);
		$stat = $this->stat($path);
		if ($stat) {
			if ($stat['mimetype'] === 'httpd/unix-directory') {
				return 'dir';
			}
			return 'file';
		} else {
			return false;
		}
	}

	public function fopen($path, $mode) {
		$path = $this->normalizePath($path);

		switch ($mode) {
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
				if (strrpos($path, '.') !== false) {
					$ext = substr($path, strrpos($path, '.'));
				} else {
					$ext = '';
				}
				$tmpFile = \OC_Helper::tmpFile($ext);
				\OC\Files\Stream\Close::registerCallback($tmpFile, array($this, 'writeBack'));
				if ($this->file_exists($path)) {
					$source = $this->fopen($path, 'r');
					file_put_contents($tmpFile, $source);
				}
				self::$tmpFiles[$tmpFile] = $path;

				return fopen('close://' . $tmpFile, $mode);
		}
		return false;
	}

	public function file_exists($path) {
		$path = $this->normalizePath($path);
		return (bool)$this->stat($path);
	}

	public function rename($source, $target) {
		$source = $this->normalizePath($source);
		$target = $this->normalizePath($target);
		if(!$this->objectStore->rename(EosProxy::toEos($source, $this->getOwner($source)), EosProxy::toEos($target, $this->getOwner($target)))){
			return false;
		}
		return true;
	}

	public function getMimeType($path) {
		$path = $this->normalizePath($path);
		$stat = $this->stat($path);
		if (is_array($stat)) {
			return $stat['mimetype'];
		} else {
			return false;
		}
	}

	public function touch($path, $mtime = null) {
		$path         = $this->normalizePath($path);
		return 	$this->objectStore->writeObject(EosProxy::toEos($path, $this->getOwner($path)), fopen('php://memory', 'r'));
	}

	public function writeBack($tmpFile) {
		if (!isset(self::$tmpFiles[$tmpFile])) {
			return;
		}

		$path = self::$tmpFiles[$tmpFile];
		$path = $this->normalizePath($path);
		$stat = $this->stat($path);
		try {
			//upload to object storage
			$this->objectStore->writeObject(EosProxy::toEos($path, $this->getOwner($path)), fopen($tmpFile, 'r'));
		} catch (\Exception $ex) {
			\OCP\Util::writeLog('objectstore', 'Could not create object: ' . $ex->getMessage(), \OCP\Util::ERROR);
			return false;
		}
	}

	/**
	 * external changes are not supported, exclusive access to the object storage is assumed
	 *
	 * @param string $path
	 * @param int $time
	 * @return false
	 */
	public function hasUpdated($path, $time) {
		return false;
	}
}
