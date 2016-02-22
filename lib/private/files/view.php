<?php

namespace OC\Files;

use OCP\Files\InvalidPathException;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * CERNBOX EOS PLUGIN PATH
 * BEFORE UPDATE:
 * - RENAME THIS FILE TO SOMETHING ELSE
 * - RENAME /lib/private/files/owncloudview.php to view.php
 * 	- RENAME class OwncloudView to View
 *
 */
class View extends OwncloudView 
{
	private function assertPathLength($path) 
	{
		$maxLen = min(PHP_MAXPATHLEN, 4000);
		// Check for the string length - performed using isset() instead of strlen()
		// because isset() is about 5x-40x faster.
		if (isset($path[$maxLen])) 
		{
			$pathLen = strlen($path);
			throw new \OCP\Files\InvalidPathException("Path length($pathLen) exceeds max path length($maxLen): $path");
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\OwncloudView::getDirectoryContent()
	 */
	public function getDirectoryContent($directory, $mimetype_filter = '') 
	{
		$this->assertPathLength($directory);
		$result = array();
		if (!Filesystem::isValidPath($directory)) {
			return $result;
		}
		$path = $this->getAbsolutePath($directory);
		$path = Filesystem::normalizePath($path);
		$mount = $this->getMount($directory);
		$storage = $mount->getStorage();
		$internalPath = $mount->getInternalPath($path);
		if ($storage) {
			$cache = $storage->getCache($internalPath);
			$user = \OC_User::getUser();
		
			/**
			 * @var \OC\Files\FileInfo[] $files
			 */
			$files = array();
		
			$data = $cache->get($internalPath);
			$watcher = $storage->getWatcher($internalPath);
			try {
				if (!$data or $data['size'] === -1) {
					$this->lockFile($directory, ILockingProvider::LOCK_SHARED);
					if (!$storage->file_exists($internalPath)) {
						$this->unlockFile($directory, ILockingProvider::LOCK_SHARED);
						return array();
					}
					$scanner = $storage->getScanner($internalPath);
					$scanner->scan($internalPath, Cache\Scanner::SCAN_SHALLOW);
					$data = $cache->get($internalPath);
					$this->unlockFile($directory, ILockingProvider::LOCK_SHARED);
				} else if ($watcher->needsUpdate($internalPath, $data)) {
					$this->lockFile($directory, ILockingProvider::LOCK_SHARED);
					$watcher->update($internalPath, $data);
					$this->updater->propagate($path);
					$data = $cache->get($internalPath);
					$this->unlockFile($directory, ILockingProvider::LOCK_SHARED);
				}
			} catch (LockedException $e) {
				// if the file is locked we just use the old cache info
			}
		
			$folderId = $data['fileid'];
			$contents = $cache->getFolderContentsById($folderId); //TODO: mimetype_filter
		
			foreach ($contents as $content) {
				if ($content['permissions'] === 0) {
					$content['permissions'] = $storage->getPermissions($content['path']);
					$cache->update($content['fileid'], array('permissions' => $content['permissions']));
				}
				// if sharing was disabled for the user we remove the share permissions
				if (\OCP\Util::isSharingDisabledForUser()) {
					$content['permissions'] = $content['permissions'] & ~\OCP\Constants::PERMISSION_SHARE;
				}
				$files[] = new FileInfo($path . '/' . $content['name'], $storage, $content['path'], $content, $mount);
			}
		
			if ($mimetype_filter) {
				foreach ($files as $file) {
					if (strpos($mimetype_filter, '/')) {
						if ($file['mimetype'] === $mimetype_filter) {
							$result[] = $file;
						}
					} else {
						if ($file['mimepart'] === $mimetype_filter) {
							$result[] = $file;
						}
					}
				}
			} else {
				$result = $files;
			}
		}
		
		return $result;
	}
}