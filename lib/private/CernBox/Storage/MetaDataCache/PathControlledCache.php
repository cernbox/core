<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 4:29 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;


/**
 * Class PathControlledCache
 *
 * @package OC\CernBox\Storage\MetaDataCache
 *
 * This cache contains a list of paths to disable metadata caching.
 * It wraps a IMetaDataCache that is called or not depending on the path.
 */
class PathControlledCache {

	private $paths;
	private $wrapped;

	public function __construct($wrapped) {
		$paths = \OCP\Config::getSystemValue("avoid_req_cache_paths", array());
		$this->paths = $paths;
		$this->wrapped = $wrapped;
	}

	private function shouldAvoidCache()
	{
		foreach($this->paths as $path)
		{
			if(strpos($_SERVER['REQUEST_URI'], $path) !== false)
			{
				return true;
			}
		}
		return false;
	}


	/**
	 * Attempts to retrieve a file data stored by its inode id.
	 * @param string|int $id file inode id
	 * @return The file data as an associative array or null if the file
	 * 			was not found
	 */
	public  function getFileById($id)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped[___FUNCTION___]($id);
		}
	}

	/**
	 * Stores a file's data in all cache levels, using it's inode id as key
	 * @param string|int $id file inode id
	 * @param array $data file data as an associative array
	 */
	public  function setFileById($id, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Invalidates the data of a file stored in the cache by it's id (if any)
	 * @param string|int $id file inode id
	 */
	public  function clearFileById($id)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Attempts to retrieve a file data stored by it's path within EOS namespace
	 * @param string $eosPath file path within EOS
	 * @return array|null An associatve array containing the file data or null if
	 * 			the given eosPath key as not found in the cache
	 */
	public  function getFileByEosPath($eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Attempts to retrieve a file data stored by it's path within EOS namespace.
	 * If the request path is contained inside the config setting 'avoid_req_cache_paths',
	 * the cache will produce a fail in order to refresh the file info
	 * @param string $eosPath file path within EOS
	 * @return array|null An associatve array containing the file data or null if
	 * 			the given eosPath key as not found in the cache
	 */
	public  function getSecureFileByEosPath($eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Stores a file's data using it's EOS path as key to access it
	 * @param string $eosPath file path within EOS
	 * @param array $data of the file as an associative array
	 */
	public  function setFileByEosPath($eosPath, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Invalidates the data stored in the cache identified by it's EOS Path
	 * @param string $eosPath path within EOS
	 */
	public  function clearFileByEosPath($eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Attempts to retrieve a a file/directory full information from the cache, given
	 * it's EOS Path and a depth of exploration
	 * @param int $depth Max nested folders levels to explore
	 * @param string $eosPath file path within EOS
	 * @return array|null The list of information of the given file, or null if it wasn't present
	 * 						or valid in the cache
	 */
	public  function getFileInfoByEosPath($depth, $eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Stores the information of a given file (identified by it's EOS path and a given depth of nested exploration)
	 * @param int $depth the maximun level of nested directories exploration
	 * @param string $eosPath file path within EOS
	 * @param array $data containing all the information to store
	 */
	public  function setFileInfoByEosPath($depth, $eosPath, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Attempts to retrieve a file's metadata stored by it's OC path
	 * @param string $ocPath within ownCloud namespace
	 * @return array|null An associative array containting the file metadata or
	 * 			null if the key was not found in the cache
	 */
	public  function getMeta($ocPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Stores a file's metadata in the cache using it's ownCloud path as key
	 * @param string $ocPath file's path within ownCloud
	 * @param array $data An associative array containing the file metadata
	 */
	public  function setMeta($ocPath, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Attempts to retrieve a list of EGroups associated with the given $user
	 * @param string $user user's username within CERN/LDAP
	 * @return array a list of all EGreoups to which the user belongs and are relevant
	 * 			to sharing module
	 */
	public  function getEGroups($user)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Stores a list of EGroups to which the given $user belongs to.
	 * @param string $user user's username within CERN/LDAP
	 * @param array $data a list of EGroups
	 */
	public  function setEGroups($user, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Attempts to retrieve the owner of a file, given it's path within EOS
	 * @param string $eosPath file's path within EOS namespace
	 * @return string owner's username within EOS/LDAP
	 */
	public  function getOwner($eosPath)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Stores the owner's username of the given file by it's EOS path
	 * @param string $eosPath file's EOS path
	 * @param string $data owner's username
	 */
	public  function setOwner($eosPath, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Attempts to retrieve the user id and group id of the given $user
	 * @param string $user user's username within CERN/LDAP
	 * @return array an associative array containing the uid and gid
	 */
	public  function getUidAndGid($user)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}

	/**
	 * Stores the user id and group id of the given $user
	 * @param string $user user's username within CERN/LDAP
	 * @param array $data containing the uid and gid
	 */
	public  function setUidAndGid($user, $data)
	{
		if ($this->shouldAvoidCache()) {
			return null;
		} else {
			return $this->wrapped->__FUNCTION__;
		}
	}
}