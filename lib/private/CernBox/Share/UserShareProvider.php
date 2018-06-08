<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/28/16
 * Time: 4:19 PM
 */

namespace OC\CernBox\Share;


use OC\Share20\Exception\BackendError;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Exception\ProviderException;
use OCP\Files\Node;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShareProvider;

class UserShareProvider implements IShareProvider {
	private $dbConn;
	private $userManager;
	private $rootFolder;
	private $util;
	private $log;

	protected $instanceManager;

	public function __construct($rootFolder) {
		$this->log = \OC::$server->getLogger();
		$this->rootFolder = $rootFolder;
		$this->userManager = \OC::$server->getUserManager();
		$this->dbConn = \OC::$server->getDatabaseConnection();
		$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
		$this->util = \OC::$server->getCernBoxShareUtil();
	}

	public function identifier() {
		return "ocinternal";
	}

	public function create(\OCP\Share\IShare $share) {
		// add unique suffix to file target
		// Ex: /ATF2 (#703291)
		$inode = $share->getNodeId();
		$share->setTarget($share->getTarget() . " (#$inode)");

		// Simplify ownCloud permissions
		// 1 => R/O
		// 15 => R/W
		//$share->setPermissions($this->util->simplifyOwnCloudPermissions($share->getPermissions()));
		// Force read-only permissions when creating a share
		$share->setPermissions(1);

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share');
		$qb->setValue('share_type', $qb->createNamedParameter($share->getShareType()));
		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER) {
			//Set the UID of the user we share with
			$qb->setValue('share_with', $qb->createNamedParameter($share->getSharedWith()));
		} else {
			throw new \Exception('invalid share type!');
		}

		// Set what is shares
		$qb->setValue('item_type', $qb->createParameter('itemType'));
		if ($share->getNode() instanceof \OCP\Files\File) {
			throw new \Exception('sharing files with individual users is not available!');
			// $qb->setParameter('itemType', 'file');
		} else {
			$qb->setParameter('itemType', 'folder');
		}

		// Set the file id
		$qb->setValue('item_source', $qb->createNamedParameter($share->getNode()->getId()));
		$qb->setValue('file_source', $qb->createNamedParameter($share->getNode()->getId()));
		// set the permissions
		$qb->setValue('permissions', $qb->createNamedParameter($share->getPermissions()));
		// Set who created this share
		$qb->setValue('uid_initiator', $qb->createNamedParameter($share->getSharedBy()));
		// Set who is the owner of this file/folder (and this the owner of the share)
		$qb->setValue('uid_owner', $qb->createNamedParameter($share->getShareOwner()));
		// Set the file target
		$qb->setValue('file_target', $qb->createNamedParameter($share->getTarget()));
		// Set the time this share was created
		$qb->setValue('stime', $qb->createNamedParameter(time()));
		// insert the data and fetch the id of the share

		$this->dbConn->beginTransaction();
		$qb->execute();
		$id = $this->dbConn->lastInsertId('*PREFIX*share');
		$this->dbConn->commit();
		// Now fetch the inserted share and create a complete share object
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();
		if ($data === false) {
			throw new ShareNotFound();
		}

		// Add the user to the sys.acl attribute in Eos.
		// A node's path is '/gonzalhu/files/folder'
		// A node's internal path (ocPath) is 'files/folder'
		$ok = $this->instanceManager->addUserToFolderACL(
			$share->getShareOwner(),
			$share->getSharedWith(),
			$share->getNode()->getInternalPath(),
			$share->getPermissions()
		);

		if(!$ok) {
			// TODO(labkode): remove share from db.
			throw new ShareNotFound();
		} else {
			$share = $this->createShare($data);
			return $share;
		}
	}

	public function update(\OCP\Share\IShare $share) {
		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER) {
			/*
			 * We allow updating the recipient on user shares.
			 */
			$share->setPermissions($this->util->simplifyOwnCloudPermissions($share->getPermissions()));
			$qb = $this->dbConn->getQueryBuilder();
			$qb->update('share')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
				//->set('share_with', $qb->createNamedParameter($share->getSharedWith()))
				//->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
				//->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
				->set('permissions', $qb->createNamedParameter($share->getPermissions()))
				//->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
				//->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
				->execute();
		} else {
			throw new ProviderException('Invalid shareType');
		}

		// Update the user in the sys.acl attribute in Eos.
		$ok = $this->instanceManager->addUserToFolderACL(
			$share->getShareOwner(),
			$share->getSharedWith(),
			$share->getNode()->getInternalPath(),
			$share->getPermissions()
		);

		if(!$ok) {
			// TODO(labkode): remove share from db.
			throw new ShareNotFound();
		} else {
			return $share;
		}
	}

	public function delete(\OCP\Share\IShare $share) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())));
		$qb->execute();

		$ok = $this->instanceManager->removeUserFromFolderACL(
			$share->getShareOwner(),
			$share->getSharedWith(),
			$share->getNode()->getInternalPath());
		if(!$ok) {
			// TODO(labkode): What to do here ?
			throw new ShareNotFound();
		}
	}

	public function deleteFromSelf(\OCP\Share\IShare $share, $recipient) {
		throw new ProviderException('Unsharing is not allowed');
		/*
		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER) {
			if ($share->getSharedWith() !== $recipient) {
				throw new ProviderException('Recipient does not match');
			}
			// We can just delete user and link shares
			$this->delete($share);
		} else {
			throw new ProviderException('Invalid shareType');
		}
		*/
	}

	public function move(\OCP\Share\IShare $share, $recipient) {
		return $share;
	}

	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));
		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_USER)));
		$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		if ($node !== null) {
			if(is_string($node) || is_integer($node)){
				$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node)));
			} else {
				$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
			}
		}
		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}
		$qb->setFirstResult($offset);
		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while($data = $cursor->fetch()) {
			$shares[] = $this->createShare($data);
		}
		$cursor->closeCursor();
		return $shares;
	}
	
	public function  getAllSharesBy($userId, $shareTypes, $nodeIDs, $reshares) {
		// TODO(labkode): this function is never called from the MultiShareProvider.
		throw new \Exception("getAllSharesBy called");
	}

	public function getShareById($id, $recipientId = null) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->eq(
					'share_type',
					$qb->createNamedParameter(\OCP\Share::SHARE_TYPE_USER)
				)
			)
			->andWhere($qb->expr()->eq('item_type', $qb->createNamedParameter('folder')));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();
		if ($data === false) {
			throw new ShareNotFound();
		}
		try {
			$share = $this->createShare($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}
		return $share;
	}

	public function getSharesByPath(Node $path) {
		$qb = $this->dbConn->getQueryBuilder();
		$cursor = $qb->select('*')
			->from('share')
			->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($path->getId())))
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_USER)),
					$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP))
				)
			)
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->execute();
		$shares = [];
		while($data = $cursor->fetch()) {
			$shares[] = $this->createShare($data);
		}
		$cursor->closeCursor();
		return $shares;
	}

	public function getSharedWith($userId, $shareType, $node, $limit, $offset) {
		$logMessage = sprintf("unit(UserShareProvider) method(getSharedWith) userId(%s) shareType(%d)",
			$userId, $shareType);
		$this->log->info($logMessage);

		/** @var Share[] $shares */
		$shares = [];
		if ($shareType === \OCP\Share::SHARE_TYPE_USER) {
			//Get shares directly with this user
			$qb = $this->dbConn->getQueryBuilder();
			$qb->select('s.*')
				->from('share', 's');
			// Order by id
			$qb->orderBy('s.id');
			// Set limit and offset
			if ($limit !== -1) {
				$qb->setMaxResults($limit);
			}
			$qb->setFirstResult($offset);
			$qb->where($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_USER)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				));

			// Filter by node if provided
			if ($node !== null) {
				$logMessage = sprintf("unit(UserShareProvider) method(getSharedWith) node(%s)", $node->getInternalPath());
				$this->log->info($logMessage);
				$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
			}
			$cursor = $qb->execute();
			while($data = $cursor->fetch()) {
				if ($this->isAccessibleResult($data)) {
					$shares[] = $this->createShare($data);
				}
			}
			$cursor->closeCursor();
		} else {
			throw new BackendError('Invalid share backend');
		}

		return $shares;
	}
	
	public function getAllSharedWith($userId, $node) {
		// TODO(labkode): this function is never called from the MultiShareProvider.
		throw new \Exception("getAllSharedWith called");
	}

	public function getShareByToken($token) {
		return null;
	}

	public function userDeleted($uid, $shareType) {
		// TODO: Implement userDeleted() method.
	}

	public function groupDeleted($gid) {
		// TODO: Implement groupDeleted() method.
	}

	public function userDeletedFromGroup($uid, $gid) {
		// TODO: Implement userDeletedFromGroup() method.
	}

	/**
	 * Checks if the file pointed by the share exists
	 * and it is accessible (not in trash nor version folder).
	 * @param $shareData
	 * @returns boolean
	 */
	private function isAccessibleResult($shareData) {
		return true;
	}

	private function createShare($data) {
		// TODO(labkode): maybe add stat to Eos to see if share is orphaned
		// because file has been removed through FUSE or sync client?
		$share = new Share($this->rootFolder, $this->userManager);
		$share->setId((int)$data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target'])
			->setMailSend((bool)$data['mail_send']);

		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$data['stime']);
		$share->setShareTime($shareTime);

		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER) {
			$share->setSharedWith($data['share_with']);
		}

		$share->setSharedBy($data['uid_initiator']);
		$share->setShareOwner($data['uid_owner']);

		$share->setNodeId((int)$data['file_source']);
		$share->setNodeType($data['item_type']);

		if ($data['expiration'] !== null) {
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $data['expiration']);
			$share->setExpirationDate($expiration);
		}

		$share->setProviderId($this->identifier());

		return $share;
	}

}

