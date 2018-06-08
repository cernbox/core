<?php
namespace OC\CernBox\Share;


use OC\Share20\Exception\BackendError;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Exception\ProviderException;
use OC\Share20\Share;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Node;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShareProvider;

class LinkShareProvider implements IShareProvider {

	private $rootFolder;
	private $dbConn;
	private $userManager;

	public function __construct($rootFolder) {
		$this->dbConn = \OC::$server->getDatabaseConnection();
		$this->rootFolder = $rootFolder;
		$this->userManager = \OC::$server->getUserManager();
		$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
	}

	public function updateForRecipient(\OCP\Share\IShare $share, $recipient) {}

	public function identifier() {
		return "ocinternal";
	}

	public function getAllSharedWith($userId, $node) {
		// TODO(labkode): this function is never called from the MultiShareProvider.
		throw new \Exception("getAllSharedWith called");
	}
	public function  getAllSharesBy($userId, $shareTypes, $nodeIDs, $reshares) {
		// TODO(labkode): this function is never called from the MultiShareProvider.
		throw new \Exception("getAllSharesBy called");
	}

	public function create(\OCP\Share\IShare $share) {
		if($share->getShareType() !== \OCP\Share::SHARE_TYPE_LINK) {
			throw new \Exception($this->identifier() . " does not support share type:" . $share->getShareType());
		}

		$qb = $this->dbConn->getQueryBuilder();

		$qb->insert('share');
		$qb->setValue('share_type', $qb->createNamedParameter($share->getShareType()));

		//Set the token of the share
		$qb->setValue('token', $qb->createNamedParameter($share->getToken()));

		//If a password is set store it
		if ($share->getPassword() !== null) {
			$qb->setValue('share_with', $qb->createNamedParameter($share->getPassword()));
		}

		//If an expiration date is set store it
		if ($share->getExpirationDate() !== null) {
			$qb->setValue('expiration', $qb->createNamedParameter($share->getExpirationDate(), 'datetime'));
		}

		if (method_exists($share, 'getParent')) {
			$qb->setValue('parent', $qb->createNamedParameter($share->getParent()));
		}

		// Set what is shares
		$qb->setValue('item_type', $qb->createParameter('itemType'));
		if ($share->getNode() instanceof \OCP\Files\File) {
			$qb->setParameter('itemType', 'file');
		} else {
			$qb->setParameter('itemType', 'folder');
		}

		// Set the file id
		// If it is a file, we have to use the persistent versions folders instead
		$node = $share->getNode();
		if($node->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
			$metaData = $this->instanceManager->getVersionsFolderForFile($share->getShareOwner(), $share->getNode()->getInternalPath(), true);
			if(!$metaData) {
				throw new \Exception("cannot get versions folder for file link share");
			}
			$versionsFolderName = basename($metaData['path']);
			$share->setTarget($versionsFolderName);
			$share->setNodeId($metaData['fileid']);
		}

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

		$share = $this->createShare($data);
		return $share;
	}

	public function update(\OCP\Share\IShare $share) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->update('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
			->set('share_with', $qb->createNamedParameter($share->getPassword()))
			->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
			->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
			->set('permissions', $qb->createNamedParameter($share->getPermissions()))
			->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('token', $qb->createNamedParameter($share->getToken()))
			->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
			->execute();
		return $share;
	}

	public function delete(\OCP\Share\IShare $share) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())));

		$qb->execute();
	}

	public function deleteFromSelf(\OCP\Share\IShare $share, $recipient) {
		throw new ProviderException('Invalid shareType');
	}

	public function move(\OCP\Share\IShare $share, $recipient) {
		return $share;
	}

	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_LINK)));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		if ($node !== null) {
			if(is_string($node) || is_integer($node)) {
				$nodeID = $node;
			} else {
				$nodeID = $node->getId();
			}
			// if node is not null then we are asking for the information of a
			// particular file or folder.
			// If it is a file we need to get the versions folder id as this is
			// saved in the file_source sql field.
			if(is_string($node) || is_integer($node)){
				$path= $this->instanceManager->getPathById($userId, $node);
				if($path) {
					$metaData = $this->instanceManager->get($userId, $path);
				}
			} else {
				$metaData = $this->instanceManager->getVersionsFolderForFile($node->getOwner()->getUID(), $node->getInternalPath(), true);
			}
			if($metaData['mimetype'] !== 'httpd/unix-directory') {
				$metaData = $this->instanceManager->getVersionsFolderForFile($userId, $metaData['path'], true);
				if($metaData) {
					$nodeID = $metaData['fileid'];
				}
			}
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($nodeID)));
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

		// now that we have all the shares we have to convert back
		// versions folder shares to their original filename.
		foreach($shares as $index => $share) {
			if($share->getNodeType() === \OCP\Files\FileInfo::TYPE_FILE) {
				// the share can be a dangling share so we check if it exists in the fs
				try {
					$metaData = $this->instanceManager->getFileFromVersionsFolder($share->getShareOwner(), $share->getNode()->getInternalPath());
					$parentFolder = $share->getNode()->getParent();
					$originalNode = $parentFolder->get(basename($metaData['path']));
					$share->setNode($originalNode);
				} catch (\Exception $ex) {
					unset($shares[$index]);
				}
			}
		}
		return $shares;
	}

	public function getShareById($id, $recipientId = null) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->in(
					'share_type',
					$qb->createNamedParameter([
						\OCP\Share::SHARE_TYPE_LINK,
					], IQueryBuilder::PARAM_INT_ARRAY)
				)
			)
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));

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
		$shares = array();
		return $shares;
	}

	public function getSharedWith($userId, $shareType, $node, $limit, $offset) {
		throw new BackendError('Invalid backend');
	}

	public function getShareByToken($token) {
		$qb = $this->dbConn->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_LINK)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->execute();

		$data = $cursor->fetch();

		if ($data === false) {
			throw new ShareNotFound();
		}

		try {
			$share = $this->createShare($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}

		// if it is a file we have to give back
		// the information of the original file
		if($share->getNodeType() === \OCP\Files\FileInfo::TYPE_FILE) {
			$metaData = $this->instanceManager->getFileFromVersionsFolder($share->getShareOwner(), $share->getNode()->getInternalPath());
			$parentFolder = $share->getNode()->getParent();
			$originalNode = $parentFolder->get(basename($metaData['path']));
			$share->setNode($originalNode);
		}

		return $share;
	}

	public function userDeleted($uid, $shareType) {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->delete('share');

		/*
		 * Delete all link shares owned by this user.
		 * And all link shares initiated by this user (until #22327 is in)
		 */
		$qb->where($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_LINK)));
		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->eq('uid_owner', $qb->createNamedParameter($uid)),
				$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($uid))
			)
		);

		$qb->execute();

	}

	public function groupDeleted($gid) {
	}

	public function userDeletedFromGroup($uid, $gid) {
	}

	private function createShare($data) {
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
		} else if ($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
			$share->setSharedWith($data['share_with']);
		} else if ($share->getShareType() === \OCP\Share::SHARE_TYPE_LINK) {
			$share->setPassword($data['share_with']);
			$share->setToken($data['token']);
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
