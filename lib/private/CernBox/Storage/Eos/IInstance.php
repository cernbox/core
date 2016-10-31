<?php

namespace OC\CernBox\Storage\Eos;

use OCP\Files\Cache\ICacheEntry;

interface IInstance {
	public function getId();
	public function getName();
	public function getPrefix();
	public function getProjectPrefix();

	public function createDir($username, $ocPath);
	public function remove($username, $ocPath);
	public function read($username, $ocPath);
	public function write($username, $ocPath, $stream);
	public function rename($username, $fromOcPath, $toOcPath);
	public function get($username, $ocPath);
	public function getFolderContents($username, $ocPath);
	public function getPathById($username, $id);

	/**
	 * @param $username
	 * @return IDeletedEntry[]
	 */
	public function getDeletedFiles($username);

	/**
	 * @param $username
	 * @param $key
	 * @return int errorCode
	 * errorCode === 0 => success
	 * errorCode > 0 => failure:
	 * 	17 => file/folder to be restored already exists
	 */
	public function restoreDeletedFile($username, $key);

	/**
	 * @param $username
	 * @return bool
	 */
	public function purgeAllDeletedFiles($username);


	public function getVersionsForFile($username, $ocPath);
	public function rollbackFileToVersion($username, $ocPath, $version);

	/**
	 * @param string $username
	 * @param string $ocPath
	 * @param string $version the version to rollback. Ex: 'TODO'
	 * @return mixed
	 */
	public function downloadVersion($username, $ocPath, $version);

	/**
	 * @param string $username
	 * @param string $allowedUser
	 * @param string $ocPath
	 * @param int $ocPermissions
	 * @return bool
	 */
	public function addUserToFolderACL($username, $allowedUser, $ocPath, $ocPermissions);

	/**
	 * @param string $username
	 * @param string $allowedUser
	 * @param string $ocPath
	 * @return bool
	 */
	public function removeUserFromFolderACL($username, $allowedUser, $ocPath);

	/**
	 * @param string $username
	 * @param string $allowedGroup
	 * @param string $ocPath
	 * @param int $ocPermissions
	 * @return bool
	 */
	public function addGroupToFolderACL($username, $allowedGroup, $ocPath, $ocPermissions);

	/**
	 * @param string $username
	 * @param string $allowedGroup
	 * @param string $ocPath
	 * @return bool
	 */
	public function removeGroupFromFolderACL($username, $allowedGroup, $ocPath);
}