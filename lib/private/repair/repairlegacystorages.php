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
	private function fixLegacyStorage($oldId, $oldNumericId) {
		// check whether the new storage already exists
		$userId = $this->extractUserId($oldId);
		$newId = 'home::' . $userId;

		$sql = 'SELECT `numeric_id` FROM `*PREFIX*storages`'
			. ' WHERE `id` = ?';
		$result = \OC_DB::executeAudited($sql, array($newId));
		if ($row = $result->fetchRow()) {
			$newNumericId = (int)$row['numeric_id'];
			// check which one of "local::" or "home::" needs to be kept
			$sql = 'SELECT DISTINCT `storage` FROM `*PREFIX*filecache`'
				. ' WHERE `storage` in (?, ?)';
			$result = \OC_DB::executeAudited($sql, array($oldNumericId, $newNumericId));
			$row1 = $result->fetchRow();
			$row2 = $result->fetchRow();
			if ($row2 !== false) {
				// two results means both storages have data, not auto-fixable
				$this->emit(
					'\OC\Repair',
					'error',
					array(
						'Could not automatically fix legacy storage '
						. '"' . $oldId . '" => "' . $newId . '"'
						. ' because they both have data. '
					)
				);
				return false;
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
			\OC_DB::executeAudited($sql, array($toDelete));

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
		$rowCount = \OC_DB::executeAudited($sql, array($newId, $oldId));
		return ($rowCount === 1);
	}

	/**
	 * Converts legacy home storage ids in the format
	 * "local::/data/dir/patH/userid/" to the new format "home::userid"
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

		$this->emit('\OC\Repair', 'info', array('Updated ' . $count . ' legacy home storage ids'));

		\OC_DB::commit();
	}
}
