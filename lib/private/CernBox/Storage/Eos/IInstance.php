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
	public function downloadVersion($username, $ocPath, $version);
}