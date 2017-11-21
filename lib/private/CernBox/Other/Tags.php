<?php
/**
 * File: TagManager.php
 * Author: hugo.gonzalez.labrador@cern.ch - CERN
 *
 */

/**
 * CERNBox - CERN Cloud Sync and Share Platform
 * Copyright (C) 2017  CERN
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU Affero General Public License for more details.
 *
 *   You should have received a copy of the GNU Affero General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace OC\CernBox\Other;


use OCP\ITags;

class Tags implements ITags {

	/**
	 * Constructor.
	 *
	 * @param TagMapper $mapper Instance of the TagMapper abstraction layer.
	 * @param string $user The user whose data the object will operate on.
	 * @param string $type The type of items for which tags will be loaded.
	 * @param array $defaultTags Tags that should be created at construction.
	 * @param boolean $includeShared Whether to include tags for items shared with this user by others.
	 */
	private $type;
	private $ocTags;
	private $instanceManager;

	public function __construct(TagMapper $mapper, $user, $type, $defaultTags = array(), $includeShared = false) {
		$this->type = $type;
		$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
		$this->ocTags = new \OC\Tags($mapper, $user, $type, $defaultTags, $includeShared);
	}

	public function isEmpty() {
		return $this->ocTags->isEmpty();
	}

	public function getTag($id) {
		return $this->ocTags->getTag($id);
	}

	public function getTags() {
		return $this->ocTags->getTags();
	}

	public function getTagsForObjects(array $objIds) {
		return $this->ocTags->getTagsForObjects($objIds);
	}

	public function getIdsForTag($tag) {
		return $this->ocTags->getIdsForTag($tag);
	}

	public function hasTag($name) {
		return $this->ocTags->hasTag($name);
	}

	public function userHasTag($name, $user) {
		return $this->ocTags->userHasTag($name, $user);
	}

	public function add($name) {
		return $this->ocTags->add($name);
	}

	public function rename($from, $to) {
		return $this->ocTags->rename($from, $to);
	}

	public function addMultiple($names, $sync = false, $id = null) {
		return $this->ocTags->addMultiple($names, $sync, $id);
	}

	public function purgeObjects(array $ids) {
		return $this->ocTags->purgeObjects($ids);
	}

	public function getFavorites() {
		return $this->ocTags->getFavorites();
	}

	public function addToFavorites($objid) {
		return $this->ocTags->addToFavorites($objid);
	}

	public function removeFromFavorites($objid) {
		return $this->ocTags->removeFromFavorites($objid);
	}

	public function tagAs($objid, $tag) {
		return $this->tagAs($objid, $tag);
	}

	public function unTag($objid, $tag) {
		return $this->unTag($objid, $tag);
	}

	public function delete($names) {
		return $this->delete($names);
	}
}