<?php
/**
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OC\CernBox\Share;

use OCA\Files_Sharing\API\Share20OCS;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Files\IRootFolder;
use OCP\Share\IManager;
use OCP\Files\NotFoundException;
use OCP\Lock\LockedException;
use OCP\Share\IShare;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Lock\ILockingProvider;
use OCP\IConfig;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class Share20OCS
 *
 * @package OCA\Files_Sharing\API
 */
class WrapperShare20OCS extends Share20OCS {
	public function __construct(
		IManager $shareManager,
		IGroupManager $groupManager,
		IUserManager $userManager,
		IRequest $request,
		IRootFolder $rootFolder,
		IURLGenerator $urlGenerator,
		IUser $currentUser,
		IL10N $l10n,
		IConfig $config,
		EventDispatcher $eventDispatcher
	) {
		$this->request = \OC::$server->getRequest();
		parent::__construct($shareManager, $groupManager, $userManager, $request, $rootFolder, $urlGenerator, $currentUser, $l10n, $config, $eventDispatcher);
	}


	private function addPrefix($result)
	{
		if(isset($_SESSION['DESKTOP_MAPPING_PREFIX']))
		{
			return $_SESSION['DESKTOP_MAPPING_PREFIX'] . $result;
		}

		return $result;
	}

	private function getNamespacedPath() {
		$path = $this->request->getParam("path", null);
		$headers = getallheaders();
		if(isset($headers['CBOX_CLIENT_MAPPING_ENABLED'])) {
			$homePrefix = \OC::$server->getCernBoxEosInstanceManager()->getPrefix();
			$projectPrefix = \OC::$server->getCernBoxEosInstanceManager()->getProjectPrefix();
			if(strpos($path, '/home') === 0) {
				$_SESSION['DESKTOP_MAPPING_PREFIX'] = '/home';
				$path = substr($path, 5);
			} else if(strpos($path, 'home') === 0) {
				$_SESSION['DESKTOP_MAPPING_PREFIX'] = 'home';
				$path = substr($path, 4);
			} else if(strpos($path, $homePrefix) === 0) {
				// split = ["", "g/gonzalhu/MTIMEPROB"]
				$split = explode($homePrefix, $path);
				$split = explode("/", $split[1]);
				// remove g/gonzalhu
				array_shift($split);
				array_shift($split);
				$path = implode("/", $split);
				$_SESSION['DESKTOP_MAPPING_PREFIX'] = $homePrefix;
			} else if (strpos($path, $projectPrefix) === 0) {
				// split = ["", "g/gonzalhu/MTIMEPROB"]
				$split = explode($projectPrefix, $path);
				$split = explode("/", $split[1]);
				// remove g/gonzalhu
				array_shift($split);
				$projectName = strtolower(array_shift($split));
				array_unshift($split, "  project " . $projectName);
				$path = implode("/", $split);
				$_SESSION['DESKTOP_MAPPING_PREFIX'] = $projectPrefix;

			}
		} else {
			unset($_SESSION['DESKTOP_MAPPING_PREFIX']);
		}
		return $path;
	}


	public function getShares() {

		if (!$this->shareManager->shareApiEnabled()) {
			return new \OC_OCS_Result();
		}

		$sharedWithMe = $this->request->getParam('shared_with_me', null);
		$reshares = $this->request->getParam('reshares', null);
		$subfiles = $this->request->getParam('subfiles');
		$path = $this->getNamespacedPath();

		if ($path !== null) {
			$userFolder = $this->rootFolder->getUserFolder($this->currentUser->getUID());
			try {
				$path = $userFolder->get($path);
				$path->lock(ILockingProvider::LOCK_SHARED);
			} catch (\OCP\Files\NotFoundException $e) {
				return new \OC_OCS_Result(null, 404, $this->l->t('Wrong path, file/folder doesn\'t exist'));
			} catch (LockedException $e) {
				return new \OC_OCS_Result(null, 404, $this->l->t('Could not lock path'));
			}
		}

		if ($sharedWithMe === 'true') {
			$result = $this->getSharedWithMe($path);
			if ($path !== null) {
				$path->unlock(ILockingProvider::LOCK_SHARED);
			}
			return $result;
		}

		if ($subfiles === 'true') {
			$result = $this->getSharesInDir($path);
			if ($path !== null) {
				$path->unlock(ILockingProvider::LOCK_SHARED);
			}
			return $result;
		}

		if ($reshares === 'true') {
			$reshares = true;
		} else {
			$reshares = false;
		}

		// Get all shares
		$userShares = $this->shareManager->getSharesBy($this->currentUser->getUID(), \OCP\Share::SHARE_TYPE_USER, $path, $reshares, -1, 0);
		$groupShares = $this->shareManager->getSharesBy($this->currentUser->getUID(), \OCP\Share::SHARE_TYPE_GROUP, $path, $reshares, -1, 0);
		$linkShares = $this->shareManager->getSharesBy($this->currentUser->getUID(), \OCP\Share::SHARE_TYPE_LINK, $path, $reshares, -1, 0);
		$shares = array_merge($userShares, $groupShares, $linkShares);

		if ($this->shareManager->outgoingServer2ServerSharesAllowed()) {
			$federatedShares = $this->shareManager->getSharesBy($this->currentUser->getUID(), \OCP\Share::SHARE_TYPE_REMOTE, $path, $reshares, -1, 0);
			$shares = array_merge($shares, $federatedShares);
		}

		$formatted = [];
		foreach ($shares as $share) {
			try {
				$formatted[] = $this->formatShare($share);
			} catch (NotFoundException $e) {
				//Ignore share
			}
		}

		if ($path !== null) {
			$path->unlock(ILockingProvider::LOCK_SHARED);
		}

		return new \OC_OCS_Result($formatted);
	}

	/**
	 * Get a specific share by id
	 *
	 * @param string $id
	 * @return \OC_OCS_Result
	 */
	public function getShare($id) {
		if (!$this->shareManager->shareApiEnabled()) {
			return new \OC_OCS_Result(null, 404, $this->l->t('Share API is disabled'));
		}

		try {
			$share = $this->getShareById($id);
		} catch (ShareNotFound $e) {
			return new \OC_OCS_Result(null, 404, $this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		if ($this->canAccessShare($share)) {
			try {
				$share = $this->formatShare($share);
				$share['path'] = $this->addPrefix($share['path']);
				return new \OC_OCS_Result([$share]);
			} catch (NotFoundException $e) {
				//Fall trough
			}
		}

		return new \OC_OCS_Result(null, 404, $this->l->t('Wrong share ID, share doesn\'t exist'));
	}

	/**
	 * @return \OC_OCS_Result
	 */
	public function createShare() {
		$share = $this->shareManager->newShare();

		if (!$this->shareManager->shareApiEnabled()) {
			return new \OC_OCS_Result(null, 404, $this->l->t('Share API is disabled'));
		}

		// Verify path
		$path = $this->getNamespacedPath();
		if ($path === null) {
			return new \OC_OCS_Result(null, 404, $this->l->t('Please specify a file or folder path'));
		}

		$userFolder = $this->rootFolder->getUserFolder($this->currentUser->getUID());
		try {
			$path = $userFolder->get($path);
		} catch (NotFoundException $e) {
			return new \OC_OCS_Result(null, 404, $this->l->t('Wrong path, file/folder doesn\'t exist'));
		}

		$share->setNode($path);

		try {
			$share->getNode()->lock(ILockingProvider::LOCK_SHARED);
		} catch (LockedException $e) {
			return new \OC_OCS_Result(null, 404, 'Could not create share');
		}

		// Parse permissions (if available)
		$permissions = $this->request->getParam('permissions', null);
		if ($permissions === null) {
			$permissions = \OCP\Constants::PERMISSION_ALL;
		} else {
			$permissions = (int)$permissions;
		}

		if ($permissions < 0 || $permissions > \OCP\Constants::PERMISSION_ALL) {
			$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
			return new \OC_OCS_Result(null, 404, 'invalid permissions');
		}

		// Shares always require read permissions
		$permissions |= \OCP\Constants::PERMISSION_READ;

		if ($path instanceof \OCP\Files\File) {
			// Single file shares should never have delete or create permissions
			$permissions &= ~\OCP\Constants::PERMISSION_DELETE;
			$permissions &= ~\OCP\Constants::PERMISSION_CREATE;
		}

		/*
		 * Hack for https://github.com/owncloud/core/issues/22587
		 * We check the permissions via webdav. But the permissions of the mount point
		 * do not equal the share permissions. Here we fix that for federated mounts.
		 */
		if ($path->getStorage()->instanceOfStorage('OCA\Files_Sharing\External\Storage')) {
			$permissions &= ~($permissions & ~$path->getPermissions());
		}

		$shareWith = $this->request->getParam('shareWith', null);
		$shareType = (int)$this->request->getParam('shareType', '-1');

		if ($shareType === \OCP\Share::SHARE_TYPE_USER) {
			// Valid user is required to share
			if ($shareWith === null || !$this->userManager->userExists($shareWith)) {
				$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
				return new \OC_OCS_Result(null, 404, $this->l->t('Please specify a valid user'));
			}
			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} else if ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
			if (!$this->shareManager->allowGroupSharing()) {
				$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
				return new \OC_OCS_Result(null, 404, $this->l->t('Group sharing is disabled by the administrator'));
			}

			// Valid group is required to share
			if ($shareWith === null || !$this->groupManager->groupExists($shareWith)) {
				$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
				return new \OC_OCS_Result(null, 404, $this->l->t('Please specify a valid group'));
			}
			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} else if ($shareType === \OCP\Share::SHARE_TYPE_LINK) {
			//Can we even share links?
			if (!$this->shareManager->shareApiAllowLinks()) {
				$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
				return new \OC_OCS_Result(null, 404, $this->l->t('Public link sharing is disabled by the administrator'));
			}

			/*
			 * For now we only allow 1 link share.
			 * Return the existing link share if this is a duplicate
			 */
			$existingShares = $this->shareManager->getSharesBy($this->currentUser->getUID(), \OCP\Share::SHARE_TYPE_LINK, $path, false, 1, 0);
			if (!empty($existingShares)) {
				$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
				return new \OC_OCS_Result($this->formatShare($existingShares[0]));
			}

			$publicUpload = $this->request->getParam('publicUpload', null);
			if ($publicUpload === 'true') {
				// Check if public upload is allowed
				if (!$this->shareManager->shareApiLinkAllowPublicUpload()) {
					$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
					return new \OC_OCS_Result(null, 403, $this->l->t('Public upload disabled by the administrator'));
				}

				// Public upload can only be set for folders
				if ($path instanceof \OCP\Files\File) {
					$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
					return new \OC_OCS_Result(null, 404, $this->l->t('Public upload is only possible for publicly shared folders'));
				}

				$share->setPermissions(
					\OCP\Constants::PERMISSION_READ |
					\OCP\Constants::PERMISSION_CREATE |
					\OCP\Constants::PERMISSION_UPDATE |
					\OCP\Constants::PERMISSION_DELETE
				);
			} else {
				$share->setPermissions(\OCP\Constants::PERMISSION_READ);
			}

			// Set password
			$password = $this->request->getParam('password', '');

			if ($password !== '') {
				$share->setPassword($password);
			}

			//Expire date
			$expireDate = $this->request->getParam('expireDate', '');

			if ($expireDate !== '') {
				try {
					$expireDate = $this->parseDate($expireDate);
					$share->setExpirationDate($expireDate);
				} catch (\Exception $e) {
					$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
					return new \OC_OCS_Result(null, 404, $this->l->t('Invalid date, date format must be YYYY-MM-DD'));
				}
			}

		} else if ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
			if (!$this->shareManager->outgoingServer2ServerSharesAllowed()) {
				$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
				return new \OC_OCS_Result(null, 403, $this->l->t('Sharing %s failed because the back end does not allow shares from type %s', [$path->getPath(), $shareType]));
			}

			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} else {
			$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
			return new \OC_OCS_Result(null, 400, $this->l->t('Unknown share type'));
		}

		$share->setShareType($shareType);
		$share->setSharedBy($this->currentUser->getUID());

		try {
			$share = $this->shareManager->createShare($share);
		} catch (GenericShareException $e) {
			$code = $e->getCode() === 0 ? 403 : $e->getCode();
			$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
			return new \OC_OCS_Result(null, $code, $e->getHint());
		}catch (\Exception $e) {
			$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
			return new \OC_OCS_Result(null, 403, $e->getMessage());
		}

		$output = $this->formatShare($share);

		$share->getNode()->unlock(\OCP\Lock\ILockingProvider::LOCK_SHARED);

		return new \OC_OCS_Result($output);
	}

	protected function formatShare(\OCP\Share\IShare $share) {
		$f = parent::formatShare($share);
		if($f) {
			$f['path'] = $this->addPrefix($f['path']);
		}
		return $f;
	}




}
