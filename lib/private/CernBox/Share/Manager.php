<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/28/16
 * Time: 4:42 PM
 */

namespace OC\CernBox\Share;


use OCP\Share\Exceptions\ShareNotFound;

class Manager extends \OC\Share20\Manager {
	/**
	 * Delete a share
	 *
	 * @param \OCP\Share\IShare $share
	 * @throws ShareNotFound
	 * @throws \InvalidArgumentException
	 */
	public function deleteShare(\OCP\Share\IShare $share) {
		try {
			$share->getFullId();
		} catch (\UnexpectedValueException $e) {
			throw new \InvalidArgumentException('Share does not have a full id');
		}

		$formatHookParams = function(\OCP\Share\IShare $share) {
			// Prepare hook
			$shareType = $share->getShareType();
			$sharedWith = '';
			if ($shareType === \OCP\Share::SHARE_TYPE_USER) {
				$sharedWith = $share->getSharedWith();
			} else if ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
				$sharedWith = $share->getSharedWith();
			} else if ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
				$sharedWith = $share->getSharedWith();
			}

			$hookParams = [
				'id'         => $share->getId(),
				'itemType'   => $share->getNodeType(),
				'itemSource' => $share->getNodeId(),
				'shareType'  => $shareType,
				'shareWith'  => $sharedWith,
				'itemparent' => method_exists($share, 'getParent') ? $share->getParent() : '',
				'uidOwner'   => $share->getSharedBy(),
				'fileSource' => $share->getNodeId(),
				'fileTarget' => $share->getTarget()
			];
			return $hookParams;
		};

		$hookParams = $formatHookParams($share);

		// Emit pre-hook
		\OC_Hook::emit('OCP\Share', 'pre_unshare', $hookParams);

		// Get all children and delete them as well
		/**
		 * We do not have nested shares. This is the only reason
		 * we extend from ownCloud, to overwrite this function.
		 */
		// $deletedShares = $this->deleteChildren($share);

		// Do the actual delete
		$provider = $this->factory->getProviderForType($share->getShareType());
		$provider->delete($share);

		// All the deleted shares caused by this delete
		$deletedShares[] = $share;

		//Format hook info
		$formattedDeletedShares = array_map(function($share) use ($formatHookParams) {
			return $formatHookParams($share);
		}, $deletedShares);

		$hookParams['deletedShares'] = $formattedDeletedShares;

		// Emit post hook
		\OC_Hook::emit('OCP\Share', 'post_unshare', $hookParams);
	}
}