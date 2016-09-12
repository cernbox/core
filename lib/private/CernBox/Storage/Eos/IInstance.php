<?php

namespace OC\CernBox\Storage\Eos;

use OCP\Files\Cache\ICacheEntry;

interface IInstance {
	public function getId();

	public function createDir($username, $ocPath);

	public function remove($username, $ocPath);

	public function read($username, $ocPath);

	public function write($username, $ocPath, $stream);

	public function rename($username, $fromOcPath, $toOcPath);

	public function get($username, $ocPath);

	public function getFolderContents($username, $ocPath);

	public function getFolderContentsById($username, $id);

	public function getPathById($username, $id);
}