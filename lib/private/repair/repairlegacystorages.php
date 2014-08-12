<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Repair;

use OC\Hooks\BasicEmitter;

class RepairLegacyStorages extends BasicEmitter {

	public function getName() {
		return 'Repair legacy storages';
	}

	/**
	 * Extracts the user id	from a legacy storage id
	 *
	 * @param string $storageId legacy storage id in the
	 * format "local::/path/to/datadir/userid"
	 * @return string user id extracted from the storage id
	 */
	private function extractUserId($storageId) {
		$storageId = rtrim($storageId, '/');
		$pos = strrpos($storageId, '/');
		return substr($storageId, $pos + 1);
	}

	/**
	 * Fix the given legacy storage by renaming the old id
	 * to the new id. If the new id already exists, whichever
	 * storage that has data in the file cache will be used.
	 * If both have data, nothing will be done and false is
	 * returned.
	 *
	 * @param string $oldId old storage id
	 * @param int $oldNumericId old storage numeric id
	 *
	 * @return bool true if fixed, false otherwise
	 */
	private function fixLegacyStorage($oldId, $oldNumericId, $userId = null) {
		// check whether the new storage already exists
		if (is_null($userId)) {
			$userId = $this->extractUserId($oldId);
		}
		$newId = 'home::' . $userId;

		// check if target id already exists
		$newNumericId = (int)\OC\Files\Cache\Storage::getNumericStorageId($newId);
		if (!is_null($newNumericId)) {
			// try and resolve the conflict
			// check which one of "local::" or "home::" needs to be kept
			$sql = 'SELECT DISTINCT `storage` FROM `*PREFIX*filecache`'
				. ' WHERE `storage` in (?, ?)';
			$result = \OC_DB::executeAudited($sql, array($oldNumericId, $newNumericId));
			$row1 = $result->fetchRow();
			$row2 = $result->fetchRow();
			if ($row2 !== false) {
				// two results means both storages have data, not auto-fixable
				throw new \OC\RepairException(
					'Could not automatically fix legacy storage '
					. '"' . $oldId . '" => "' . $newId . '"'
					. ' because they both have data.'
				);
			}
			if ($row1 === false || (int)$row1['storage'] === $oldNumericId) {
				// old storage has data, then delete the empty new id
				$toDelete = $newId;
			} else if ((int)$row1['storage'] === $newNumericId) {
				// new storage has data, then delete the empty old id
				$toDelete = $oldId;
			} else {
				// unknown case, do not continue
				return false;
			}

			$sql = 'DELETE FROM `*PREFIX*storages`'
				. ' WHERE `id` = ?';
			\OC_DB::executeAudited($sql, array(\OC\Files\Cache\Storage::adjustStorageId($toDelete)));

			// if we deleted the old id, the new id will be used
			// automatically
			if ($toDelete === $oldId) {
				// nothing more to do
				return true;
			}
		}

		// rename old id to new id
		$sql = 'UPDATE `*PREFIX*storages`'
			. ' SET `id` = ?'
			. ' WHERE `id` = ?';
		$newId = \OC\Files\Cache\Storage::adjustStorageId($newId);
		$oldId = \OC\Files\Cache\Storage::adjustStorageId($oldId);
		$rowCount = \OC_DB::executeAudited($sql, array($newId, $oldId));
		return ($rowCount === 1);
	}

	/**
	 * Converts legacy home storage ids in the format
	 * "local::/data/dir/path/userid/" to the new format "home::userid"
	 */
	public function run() {
		$dataDir = \OC_Config::getValue('datadirectory', \OC::$SERVERROOT . '/data/');
		$dataDir = rtrim($dataDir, '/') . '/';
		$dataDirId = 'local::' . $dataDir;

		$count = 0;

		\OC_DB::beginTransaction();

		// note: not doing a direct UPDATE with the REPLACE function
		// because regexp search/extract is needed and it is not guaranteed
		// to work on all database types
		$sql = 'SELECT `id`, `numeric_id` FROM `*PREFIX*storages`'
			. ' WHERE `id` LIKE ?'
			. ' ORDER BY `id`';
		$result = \OC_DB::executeAudited($sql, array($dataDirId . '%'));
		while ($row = $result->fetchRow()) {
			$currentId = $row['id'];
			// one entry is the datadir itself
			if ($currentId === $dataDirId) {
				continue;
			}

			if ($this->fixLegacyStorage($currentId, (int)$row['numeric_id'])) {
				$count++;
			}
		}

		// check for md5 ids, not in the format "prefix::"
		$sql = 'SELECT COUNT(*) `c` FROM `*PREFIX*storages`'
			. ' WHERE `id` NOT LIKE "%::%"';
		$result = \OC_DB::executeAudited($sql);
		$row = $result->fetchRow();
		// find at least one to make sure it's worth
		// querying the user list
		if ((int)$row['c'] > 0) {
			$userManager = \OC_User::getManager();

			// use chunks to avoid caching too many users in memory
			$limit = 30;
			$offset = 0;

			do {
				// query the next page of users
				$results = $userManager->search('', $limit, $offset);
				$storageIds = array();
				$userIds = array();
				foreach ($results as $uid => $userObject) {
					$storageId = $dataDirId . $uid . '/';
					if (strlen($storageId) <= 64) {
						// skip short storage ids as they were handled in the previous section
						continue;
					}
					$storageIds[$uid] = $storageId;
				}

				if (count($storageIds) > 0) {
					// update the storages of these users
					foreach ($storageIds as $uid => $storageId) {
						$numericId = \OC\Files\Cache\Storage::getNumericStorageId($storageId);
						if (!is_null($numericId) && $this->fixLegacyStorage($storageId, (int)$numericId)) {
							$count++;
						}
					}
				}
				$offset += $limit;
			} while (count($results) > 0);
		}

		$this->emit('\OC\Repair', 'info', array('Updated ' . $count . ' legacy home storage ids'));

		\OC_DB::commit();
	}
}
