<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Cache;

/**
 * Metadata cache for the filesystem
 * don't use this class directly if you need to get metadata, use \OC\Files\Filesystem::getFileInfo instead
 */
use OC\Files\ObjectStore\EosUtil;
use OC\Files\ObjectStore\EosParser;
use OC\Files\ObjectStore\EosProxy;
use OC\Files\ObjectStore\EosCmd;

/**
 * Metadata cache for the filesystem
 * don't use this class directly if you need to get metadata, use \OC\Files\Filesystem::getFileInfo instead
 */

class EosCache {
	const NOT_FOUND = 0;
	const PARTIAL   = 1;//only partial data available, file not cached in the database
	const SHALLOW   = 2;//folder in cache, but not all child files are completely scanned
	const COMPLETE  = 3;

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

	protected static $mimetypeIds = array();
	protected static $mimetypes   = array();

	/**
	 * @param \OC\Files\Storage\Storage|string $storage
	 */
	public function __construct($storage) {

		if ($storage instanceof \OC\Files\Storage\Storage) {
			$this->storageId = $storage->getId();
		} else {
			$this->storageId = $storage;
		}
		// with the objectstore the $this->storageId is 'object::user:gonzaleh'
		if (strlen($this->storageId) > 64) {
			$this->storageId = md5($this->storageId);
		}
		$this->storageCache = new Storage($storage);
		EosUtil::putEnv();
	}

	public function getNumericStorageId() {
		$id = $this->storageCache->getNumericId();
		return $id;
	}
	/**
	 * normalize mimetypes
	 *
	 * @param string $mime
	 * @return int
	 */
	public function getMimetypeId($mime) {
		if (empty($mime)) {
			// Can not insert empty string into Oracle NOT NULL column.
			$mime = 'application/octet-stream';
		}
		if (empty(self::$mimetypeIds)) {
			$this->loadMimetypes();
		}

		if (!isset(self::$mimetypeIds[$mime])) {
			try {
				$result                                     = \OC_DB::executeAudited('INSERT INTO `*PREFIX*mimetypes`(`mimetype`) VALUES(?)', array($mime));
				self::$mimetypeIds[$mime]                   = \OC_DB::insertid('*PREFIX*mimetypes');
				self::$mimetypes[self::$mimetypeIds[$mime]] = $mime;
			}
			 catch (\Doctrine\DBAL\DBALException $e) {
				\OC_Log::write('core', 'Exception during mimetype insertion: ' . $e->getmessage(), \OC_Log::DEBUG);
				return -1;
			}
		}

		$toret = self::$mimetypeIds[$mime];
		return $toret;
	}

	public function getMimetype($id) {
		if (empty(self::$mimetypes)) {
			$this->loadMimetypes();
		}
		$mime = isset(self::$mimetypes[$id]) ? self::$mimetypes[$id] : null;
		return $mime;
	}

	public function loadMimetypes() {
		$result = \OC_DB::executeAudited('SELECT `id`, `mimetype` FROM `*PREFIX*mimetypes`', array());
		if ($result) {
			while ($row = $result->fetchRow()) {
				self::$mimetypeIds[$row['mimetype']] = $row['id'];
				self::$mimetypes[$row['id']]         = $row['mimetype'];
			}
		}
	}

	/**
	 * get the stored metadata of a file or folder
	 *
	 * @param $ocPath the owncloud path. like files/a.txt or cache/some.txt
	 * @return array|false
	 */
	public function get($ocPath) {
		$eos_prefix = EosUtil::getEosPrefix();
		$ocPath = $this->normalize($ocPath);
		$eosPath = EosProxy::toEos($ocPath, $this->storageId);
		// HUGO-DANGER If we dont have a valid eospath we return FALSE
		if(!$eosPath){ 
			return false;
		}
		list($uid, $gid) = EosUtil::getEosRole($eosPath, true);
		$eosPathEscaped = escapeshellarg($eosPath);
		$get     = "eos -b -r $uid $gid  file info $eosPathEscaped -m";
		$info    = array();
		list($result, $errcode) = EosCmd::exec($get);
		if ($errcode !== 0) { 
			return false;
		} else {
			$line_to_parse   = $result[0];
			$data            = EosParser::parseFileInfoMonitorMode($line_to_parse);
			if($data["path"]=== false){
				return false;
			}
			$data["storage"] = $this->storageId;
			$data["permissions"] = 31;
			return $data;
		}
	}
	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param string $folder
	 * @return array
	 */
	public function getFolderContents($ocPath, $deep = false) {
		$eos_hide_regex = EosUtil::getEosHideRegex();
		$ocPath = $this->normalize($ocPath);
		$lenPath = strlen($ocPath);
		$eosPath = EosProxy::toEos($ocPath, $this->storageId);
		if(!$eosPath){
			return false;
		}	
		list($uid, $gid) = EosUtil::getEosRole($eosPath, true);
		$eosPathEscaped = escapeshellarg($eosPath);
		$getFolderContents = "eos -b -r $uid $gid  find --fileinfo --maxdepth 1 $eosPathEscaped";
		if ($deep === true) {
			$getFolderContents = "eos -b -r $uid $gid  find --fileinfo --maxdepth 10 $eosPathEscaped";
		}
		$files             = array();
		list($result, $errcode) = EosCmd::exec($getFolderContents);
		if ($errcode !== 0) {
			return $files;
		}
		foreach ($result as $line_to_parse) {
			$data            = EosParser::parseFileInfoMonitorMode($line_to_parse);
			if( $data["path"] !== false && rtrim($data["eospath"],"/") !== rtrim($eosPath,"/") ){ 
				$data["storage"] = $this->storageId;
				$data["permissions"] = 31;
				//$files[] = $data;
				// HUGO versions. This function is called to get the list of files inside a folder.
				// but we need to be careful of not showing .sys.v#. folders when the folder asked to show the contents is a standard folder
				if ( !preg_match("|".$eos_hide_regex."|", $ocPath) ) {
					if ( !preg_match("|".$eos_hide_regex."|", $data["eospath"]) ) {// the folder is a standar folder and we hide the .sys.v#. directories
						$files[] = $data;
					}
				} else {
					$files[] = $data;
				}
			}
		}
		// we need to remove the current folder but now the file info about directories is listed at the end and the current dir can be the last or in the middle
		//array_shift($files);
		return $files;
	}

	/**
	 * get the metadata of all files stored in $folder
	 *
	 * @param int $fileId the file id of the folder
	 * @return array
	 */
	public function getFolderContentsById($fileId) {
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
	public function put($file, array $data) {
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
	public function update($id, array $data) {
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
	function buildParts(array $data) {
		return array();
	}

	/**
	 * get the file id for a file
	 *
	 * @param string $file
	 * @return int
	 */
	public function getId($file) {
		$info = $this->get($file);
		if ($info) {
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
	public function getParentId($file) {
		$info = $this->get($file);
		if ($info) {
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
	public function inCache($file) {
		return (bool) $this->get($file);
	}

	/**
	 * remove a file or folder from the cache
	 *
	 * @param string $file
	 */
	public function remove($file) {
		return true;
	}

	/**
	 * Move a file or folder in the cache
	 *
	 * @param string $source
	 * @param string $target
	 */
	public function move($source, $target) {
		return true;
	}

	/**
	 * remove all entries for files that are stored on the storage from the cache
	 */
	public function clear() {
		return true;
	}

	/**
	 * @param string $file
	 *
	 * @return int, Cache::NOT_FOUND, Cache::PARTIAL, Cache::SHALLOW or Cache::COMPLETE
	 */
	public function getStatus($file) {
		return self::COMPLETE;
	}

	/**
	 * search for files matching $pattern
	 *
	 * @param string $pattern
	 * @return array an array of file data
	 */
	public function search($pattern) {
		return array();
	}

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype
	 * @return array
	 */
	public function searchByMime($mimetype) {
		$files = self::getFolderContents("files", true);
		$images = [];
		foreach($files as $file) {
			$ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
			if($ext === "png" || $ext === "jpeg" || $ext === "jpg") {
				$images[] = $file;
			}
		}
		\OCP\Util::writeLog('eosgallery', "entered number images = " . count($images), \OCP\Util::ERROR);
		return $images;
	}

	/**
	 * update the folder size and the size of all parent folders
	 *
	 * @param string|boolean $path
	 * @param array $data (optional) meta data of the folder
	 */
	public function correctFolderSize($path, $data = null) {
		return true;
	}

	/**
	 * get the size of a folder and set it in the cache
	 *
	 * @param string $path
	 * @param array $entry (optional) meta data of the folder
	 * @return int
	 */
	public function calculateFolderSize($path, $entry = null) {
		return 0;
	}

	/**
	 * get all file ids on the files on the storage
	 *
	 * @return int[]
	 */
	public function getAll() {
		$files = $this->getFolderContents("");
		$ids   = array();
		foreach ($files as $file) {
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
	public function getIncomplete() {
		return false;
	}

	/**
	 * get the path of a file on this storage by it's id
	 *
	 * @param int $id
	 * @return string|null
	 */
	public function getPathById($id) {
		$data = self::getById($id);
		if ($data) {
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
	static public function getById($id) {
		$uid = 0; $gid = 0;
		EosUtil::putEnv();
		$fileinfo = "eos -b -r $uid $gid file info inode:" . $id . " -m";
		$files    = array();
		list($result, $errcode) = EosCmd::exec($fileinfo);
		if ($errcode === 0 && $result) {
			$line_to_parse = $result[0];
			$data          = EosParser::parseFileInfoMonitorMode($line_to_parse);
			if($data["path"] === false){
				return null;
			}
			$ocPath        = $data["path"];
			$storage_id    = EosUtil::getStorageId($data["eospath"]);
			return array($storage_id, $ocPath);
		} else {
			return null;
		}

	}

	/**
	 * normalize the given path
	 * @param string $path
	 * @return string
	 */
	public function normalize($path) {
		return \OC_Util::normalizeUnicode($path);
	}
}
