<?php

namespace OC\Files\Node;

/**
 * CERNBOX STORAGE PLUGIN PATCH
 * Original class is lib/private/files/node/owncloudfolder.php
 *
 */

class Folder extends OwncloudFolder
{
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\Node\OwncloudFolder::search()
	 */
	public function search($query) {
		return $this->searchCommon('search', array('%' . $query . '%'));
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\Node\OwncloudFolder::searchByMime()
	 */
	public function searchByMime($mimetype) {
		return $this->searchCommon('searchByMime', array($mimetype));
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Files\Node\OwncloudFolder::searchByTag()
	 */
	public function searchByTag($tag, $userId) {
		return $this->searchCommon('searchByTag', array($tag, $userId));
	}
	
	/**
	 *
	 * @param unknown $method
	 * @param unknown $args
	 */
	private function searchCommon($method, $args) {
		$files = array();
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 */
		list($storage, $internalPath) = $this->view->resolvePath($this->path);
		$internalPath = rtrim($internalPath, '/');
		if ($internalPath !== '') {
			$internalPath = $internalPath . '/';
		}
		$internalRootLength = strlen($internalPath);
	
		$cache = $storage->getCache('');
	
		$results = call_user_func_array(array($cache, $method), $args);
		foreach ($results as $result) {
			if ($internalRootLength === 0 or substr($result['path'], 0, $internalRootLength) === $internalPath) {
				$result['internalPath'] = $result['path'];
				$result['path'] = substr($result['path'], $internalRootLength);
				$result['storage'] = $storage;
				$files[] = $result;
			}
		}
	
		$result = array();
		foreach ($files as $file) {
			$result[] = $this->createNode($this->normalizePath($this->path . '/' . $file['path']), $file);
		}
	
		return $result;
	}
}