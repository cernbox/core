<?php
/**
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/**
 * Tests for the converting of legacy storages to home storages.
 *
 * @see \OC\Repair\RepairLegacyStorages
 */
class TestRepairLegacyStorages extends PHPUnit_Framework_TestCase {

	private $user;
	private $repair;

	private $dataDir;
	private $oldDataDir;

	private $legacyStorageId;
	private $newStorageId;

	public function setUp() {
		$this->user = uniqid('user_');

		$this->oldDataDir = \OC_Config::getValue('datadirectory', \OC::$SERVERROOT . '/data/');

		// hard-coded string as we want a predictable fixed length
		// no data will be written there
		$this->dataDir = '/tmp/oc-autotest/datadir/';
		\OC_Config::setValue('datadirectory', $this->dataDir);

		$this->legacyStorageId = 'local::' . $this->dataDir . $this->user . '/';
		$this->newStorageId = 'home::' . $this->user;

		$this->repair = new \OC\Repair\RepairLegacyStorages();
	}

	public function tearDown() {
		$sql = 'DELETE FROM `*PREFIX*storages`';
		\OC_DB::executeAudited($sql);
		$sql = 'DELETE FROM `*PREFIX*filecache`';
		\OC_DB::executeAudited($sql);
		\OC_Config::setValue('datadirectory', $this->oldDataDir);
	}

	/**
	 * Create a storage entry
	 *
	 * @param string $storageId
	 */
	private function createStorage($storageId) {
		$sql = 'INSERT INTO `*PREFIX*storages` (`id`)'
			. ' VALUES (?)';

		if (strlen($storageId) > 64) {
			$storageId = md5($storageId);
		}
		$numRows = \OC_DB::executeAudited($sql, array($storageId));
		$this->assertEquals(1, $numRows);

		return \OC_DB::insertid('*PREFIX*storages');
	}

	/**
	 * Returns the storage id based on the numeric id
	 *
	 * @param int $numericId numeric id of the storage
	 * @return string storage id or null if not found
	 */
	private function getStorageId($storageId) {
		$sql = 'SELECT `numeric_id` FROM `*PREFIX*storages` WHERE `id`= ?';
		$result = \OC_DB::executeAudited($sql, array($storageId));
		if ($row = $result->fetchRow()) {
			return (int)$row['numeric_id'];
		}
		return null;
	}

	/**
	 * Create dummy data in the filecache for the given storage numeric id
	 *
	 * @param string $storageId storage id
	 */
	private function createData($storageId) {
		$cache = new \OC\Files\Cache\Cache($storageId);
		$cache->put(
			'dummyfile.txt',
			array('size' => 5, 'mtime' => 12, 'mimetype' => 'text/plain')
		);
	}

	/**
	 * Test that existing home storages are left alone when valid.
	 */
	public function testNoopWithExistingHomeStorage() {
		$newStorageNumId = $this->createStorage($this->newStorageId);

		$this->repair->run();

		$this->assertNull($this->getStorageId($this->legacyStorageId));
		$this->assertEquals($newStorageNumId, $this->getStorageId($this->newStorageId));
	}

	/**
	 * Test that legacy storages are converted to home storages when
	 * the latter does not exist.
	 */
	public function testConvertLegacyToHomeStorage() {
		$legacyStorageNumId = $this->createStorage($this->legacyStorageId);

		$this->repair->run();

		$this->assertNull($this->getStorageId($this->legacyStorageId));
		$this->assertEquals($legacyStorageNumId, $this->getStorageId($this->newStorageId));
	}

	/**
	 * Test that legacy storages are converted to home storages
	 * when home storage already exists but has no data.
	 */
	public function testConvertLegacyToExistingEmptyHomeStorage() {
		$legacyStorageNumId = $this->createStorage($this->legacyStorageId);
		$newStorageNumId = $this->createStorage($this->newStorageId);

		$this->createData($this->legacyStorageId);

		$this->repair->run();

		$this->assertNull($this->getStorageId($this->legacyStorageId));
		$this->assertEquals($legacyStorageNumId, $this->getStorageId($this->newStorageId));
	}

	/**
	 * Test that legacy storages are converted to home storages
	 * when home storage already exists and the legacy storage
	 * has no data.
	 */
	public function testConvertEmptyLegacyToHomeStorage() {
		$legacyStorageNumId = $this->createStorage($this->legacyStorageId);
		$newStorageNumId = $this->createStorage($this->newStorageId);

		$this->createData($this->newStorageId);

		$this->repair->run();

		$this->assertNull($this->getStorageId($this->legacyStorageId));
		$this->assertEquals($newStorageNumId, $this->getStorageId($this->newStorageId));
	}

	/**
	 * Test that legacy storages with long names are converted
	 * to home storage when no home storage exists.
	 */
	public function testConvertLongEmptyLegacyToHomeStorage() {
		// long datadir name + user name
		$this->dataDir = '/tmp/oc-autotest/datadir01234567890123456789012345678901234567890123456789END';
		\OC_Config::setValue('datadirectory', $this->dataDir);
		$this->user = 'u01234567890123456789012345678901234567890123456789END';
		$this->legacyStorageId = 'local::' . $this->dataDir . $this->user . '/';
		$this->newStorageId = 'home::' . $this->user;

		$legacyStorageNumId = $this->createStorage($this->legacyStorageId);

		$this->repair->run();

		$this->assertNull($this->getStorageId($this->legacyStorageId));
		$this->assertEquals($legacyStorageNumId, $this->getStorageId($this->newStorageId));
	}

	/**
	 * Test that nothing is done when both conflicting legacy
	 * and home storage have data.
	 */
	public function testConflictNoop() {
		$legacyStorageNumId = $this->createStorage($this->legacyStorageId);
		$newStorageNumId = $this->createStorage($this->newStorageId);

		$this->createData($this->legacyStorageId);
		$this->createData($this->newStorageId);

		try {
			$thrown = false;
			$this->repair->run();
		}
		catch (\OC\RepairException $e) {
			$thrown = true;
		}

		$this->assertTrue($thrown);

		// storages left alone
		$this->assertEquals($legacyStorageNumId, $this->getStorageId($this->legacyStorageId));
		$this->assertEquals($newStorageNumId, $this->getStorageId($this->newStorageId));
	}

	/**
	 * Test that the data dir local entry is left alone
	 */
	public function testDataDirEntryNoop() {
		$storageId = 'local::' . $this->dataDir;
		$numId = $this->createStorage($storageId);

		$this->repair->run();

		$this->assertEquals($numId, $this->getStorageId($storageId));
	}

	/**
	 * Test that external local storages are left alone
	 */
	public function testLocalExtStorageNoop() {
		$storageId = 'local::/tmp/somedir/' . $this->user;
		$numId = $this->createStorage($storageId);

		$this->repair->run();

		$this->assertEquals($numId, $this->getStorageId($storageId));
	}

	/**
	 * Test that other external storages are left alone
	 */
	public function testExtStorageNoop() {
		$storageId = 'smb::user@password/tmp/somedir/' . $this->user;
		$numId = $this->createStorage($storageId);

		$this->repair->run();

		$this->assertEquals($numId, $this->getStorageId($storageId));
	}
}
