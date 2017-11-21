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


class TagManager extends \OC\TagManager {

	private $userSession;
	private $mapper;

	/**
	 * Constructor.
	 *
	 * @param TagMapper $mapper Instance of the TagMapper abstraction layer.
	 * @param \OCP\IUserSession $userSession the user session
	 */
	public function __construct(TagMapper $mapper, \OCP\IUserSession $userSession) {
		$this->userSession = $userSession;
		$this->mapper = $mapper;
		parent::__construct($mapper, $userSession);
	}

	public function load($type, $defaultTags = array(), $includeShared = false, $userId = null) {
		if($type === 'files') {
			return $this->loadForFilesType($type, $defaultTags, $includeShared, $userId);
		}
		return parent::load($type, $defaultTags, $includeShared, $userId);
	}

	private function loadForFilesType($type, $defaultTags = array(), $includeShared = false, $userId = null) {
		if (is_null($userId)) {
			$user = $this->userSession->getUser();
			if ($user === null) {
				// nothing we can do without a user
				return null;
			}
			$userId = $this->userSession->getUser()->getUId();
		}
		return new Tags($this->mapper, $userId, $type, $defaultTags, $includeShared);
	}
}