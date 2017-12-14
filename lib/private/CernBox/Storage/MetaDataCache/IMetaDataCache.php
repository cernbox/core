<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/6/16
 * Time: 3:40 PM
 */

namespace OC\CernBox\Storage\MetaDataCache;

use OCP\Files\Cache\ICacheEntry;

interface IMetaDataCache
{
//
//	/**
//	 * Stores a file by it's inode id in the cache server. Existing files will
//	 * be overwritten
//	 *
//	 * @param string|int $id file inode id
//	 * @param array $value file data to be stored
//	 */
//	public function setFileById($id, $data);
//
//	/**
//	 * Retrieves a file stored by it's id from the server cache
//	 *
//	 * @param string|int $id file inode id
//	 * @return array|bool Will return the file data as an associative array, or FALSE whether
//	 * 						the file has not been found or a problem with the server cache has happen
//	 */
//	public function getFileById($id);
//
//	/**
//	 * Invalidates the stored file data by the given file inode ide
//	 * @param string|int $id file inode id
//	 */
//	public function clearFileById($id);
//
//	/**
//	 * Stores a file by it's eos path in the cache server. Existing files will
//	 * be overwritten
//	 *
//	 * @param string $path file path within eos
//	 * @param array $value file data to be stored
//	 */
//	public function setFileByEosPath($eosPath, $data);
//
//	/**
//	 * Retrieves a file stored by it's eos path from the server cache
//	 *
//	 * @param string $path file path within eos
//	 * @return array|bool Will return the file data as an associative array, or FALSE whether
//	 * 						the file has not been found or a problem with the server cache has happen
//	 */
//	public function getFileByEosPath($eosPath);
//
//	/**
//	 * Invalidates the stored file data by the given EOS Path
//	 * @param string $eosPath file path within EOS
//	 */
//	public function clearFileByEosPath($eosPath);
//
//	/**
//	 * Attempts to retrieve a a file/directory full information from the cache, given
//	 * it's EOS Path and a depth of exploration
//	 * @param int $depth Max nested folders levels to explore
//	 * @param string $eosPath file path within EOS
//	 * @return array|null The list of information of the given file, or null if it wasn't present
//	 * 						or valid in the cache
//	 */
//	public function getFileInfoByEosPath($depth, $eosPath);
//
//	/**
//	 * Stores the information of a given file (identified by it's EOS path and a given depth of nested exploration)
//	 * @param int $depth the maximun level of nested directories exploration
//	 * @param string $eosPath file path within EOS
//	 * @param array $data containing all the information to store
//	 */
//	public function setFileInfoByEosPath($depth, $eosPath, $data);
//
//	/**
//	 * Stores the username owner of the file given by it's eos path
//	 *
//	 * @param string $path path of the file within eos
//	 * @param string $value owner's username
//	 */
//	public function setOwner($eosPath, $owner);
//
//	/**
//	 * Retrieves the username of the owner of the given file by it's eos path
//	 *
//	 * @param string $path path of the file within eos
//	 * @return string the username of the file owner
//	 */
//	public function getOwner($eosPath);
//
//	/**
//	 * Stores a file metadata by it's oc path in the cache server. Existing files will
//	 * be overwritten
//	 *
//	 * @param string $path file path within oc namespace
//	 * @param array $value file data to be stored
//	 */
//	public function setMeta($ocPath, $meta);
//
//	/**
//	 * Retrieves a file stored by it's oc path from the server cache
//	 *
//	 * @param string $path file path within oc namespace
//	 * @return array|bool Will return the file data as an associative array, or FALSE whether
//	 * 						the file has not been found or a problem with the server cache has happen
//	 */
//	public function getMeta($ocPath);
//
//	/**
//	 * Stores a list of e-groups to which the given user belongs to. Existing entries will
//	 * be overwritten.
//	 *
//	 * @param string $user user id (user ldap's CN parameter or CERN username)
//	 * @param array $value list of e-groups
//	 */
//	public function setEGroups($user, $egroups);
//
//	/**
//	 * Retrieves a list of e-groups to which the given user belongs to.
//	 *
//	 * @param string $user user id (user ldap's CN parameter or CERN username)
//	 * @return array|bool An array containing the groups of the given user or FALSE
//	 * if the entry was not found or a problem with the server has happened.
//	 */
//	public function getEGroups($user);
//
//	/**
//	 * Retrieves the User ID and Group ID to which the user identified by it's username
//	 * belgons to
//	 * @param string $username User's username
//	 */
//	public function getUidAndGid($username);
//
//	/**
//	 * Stores the User Id and Group Id to which the given user identified by it's username
//	 * belongs to
//	 * @param string $username User's username
//	 * @param array $data containing the uid and gid of the given user
//	 */
//	public function setUidAndGid($username, $data);

	public function getUidAndGid($key);
	public function setUidAndGid($key, array $data);
	public function getCacheEntry($key);
	public function setCacheEntry($key, ICacheEntry $data);
	public function getPathById($key);
	public function setPathById($key, $data);
	public function clearCacheEntry($key);
}
