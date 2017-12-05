<?php
/**
 * Created by PhpStorm.
 * User: labkode
 * Date: 9/28/16
 * Time: 4:20 PM
 */

namespace OC\CernBox\Share;


use OC\Group\Group;
use OC\Share20\Exception\BackendError;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Exception\ProviderException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Node;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;

class GroupShareProvider implements IShareProvider {

	private $dbConn;
	private $userManager;
	private $groupManager;
	private $rootFolder;
	private $util;
	
	protected $instanceManager;

	public function __construct($rootFolder) {
		$this->rootFolder = $rootFolder;
		$this->userManager = \OC::$server->getUserManager();
		$this->groupManager = \OC::$server->getGroupManager();
		$this->dbConn = \OC::$server->getDatabaseConnection();
		$this->instanceManager = \OC::$server->getCernBoxEosInstanceManager();
		$this->util = \OC::$server->getCernBoxShareUtil();
	}

	public function identifier() {
		return "ocinternal";
	}

	public function create(IShare $share) {
		// add unique suffix to file target
		// Ex: /ATF2 (#703291)
		$inode = $share->getNodeId();
		$share->setTarget($share->getTarget() . " (#$inode)");

		// Simplify ownCloud permissions
		// 1 => R/O
		// 15 => R/W
		$share->setPermissions($this->util->simplifyOwnCloudPermissions($share->getPermissions()));

		$qb = $this->dbConn->getQueryBuilder();
		$qb->insert('share');
		$qb->setValue('share_type', $qb->createNamedParameter($share->getShareType()));

		if($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
			//Set the GID of the group we share with
			$qb->setValue('share_with', $qb->createNamedParameter($share->getSharedWith()));
		} else {
			throw new \Exception('invalid share type!');
		}

		// Set what is shares
		$qb->setValue('item_type', $qb->createParameter('itemType'));
		if ($share->getNode() instanceof \OCP\Files\File) {
			$qb->setParameter('itemType', 'file');
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

		if(\OC::$server->getAppManager()->isInstalled("files_projectspaces")) {
			// if the file we are sharing is under a project space, we set the owner of the share
			// as the owner of the project
			$projectMapper = \OC::$server->getCernBoxProjectMapper();
			$node = $share->getNode();
			$metadata = $this->instanceManager->get($share->getShareOwner(), $node->getInternalPath());
			$eosPath = $metadata['eos.file'];
			if(strpos($eosPath, $this->instanceManager->getProjectPrefix()) === 0) {
				// strip project prefix
				$relPath = ltrim(substr($eosPath, strlen($this->instanceManager->getProjectPrefix())), "/");
				$projectInfo = $projectMapper->getProjectInfoByPath($relPath);
				if($projectInfo) {
					// Set who is the owner of this file/folder (and this the owner of the share)
					$qb->setValue('uid_owner', $qb->createNamedParameter($projectInfo->getProjectOwner()));
				}
			}
		} else {
			// Set who is the owner of this file/folder (and this the owner of the share)
			$qb->setValue('uid_owner', $qb->createNamedParameter($share->getShareOwner()));
		}

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

		// Add the egroup to the sys.acl attribute in Eos.
		// A node's path is '/gonzalhu/files/folder'
		// A node's internal path (ocPath) is 'files/folder'
		$ok = $this->instanceManager->addGroupToFolderACL(
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

	public function update(IShare $share) {
		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
			$qb = $this->dbConn->getQueryBuilder();
			$qb->update('share')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
				//->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
				//->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
				->set('permissions', $qb->createNamedParameter($share->getPermissions()))
				//->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
				//->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
				->execute();
		} else {
			throw new ProviderException('Invalid shareType');
		}

		// Update the group in the sys.acl attribute in Eos.
		$ok = $this->instanceManager->addGroupToFolderACL(
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

	public function delete(IShare $share) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())));
		$qb->execute();

		$ok = $this->instanceManager->removeGroupFromFolderACL(
			$share->getShareOwner(),
			$share->getSharedWith(),
			$share->getNode()->getInternalPath());

		if(!$ok) {
			// TODO(labkode): What to do here ?
			throw new ShareNotFound();
		}
	}

	public function deleteFromSelf(IShare $share, $recipient) {
		// TODO: Implement deleteFromSelf() method.
	}

	public function move(IShare $share, $recipient) {
		return $share;
	}

	/**
	 * @inheritdoc
	 * Return here group shares where we have re-sharing permissions
	 */
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('*')
			->from('share');
		$qb->andWhere($qb->expr()->eq('item_type', $qb->createNamedParameter('folder')));
		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP)));


		$owners[] = $userId;
		if(\OC::$server->getAppManager()->isInstalled("files_projectspaces")) {
			$projects = \OC::$server->getCernBoxProjectMapper()->getProjectsUserIsAdmin($userId);
			$projectOwners = array_map(function ($project) {
				return $project->getProjectOwner();
			}, $projects);
			$owners =array_merge($projectOwners, $owners);
		}
		$owners = array_unique($owners);

		$qb->andWhere(
			$qb->expr()->in('uid_owner', $qb->createParameter('owners'))
		);
		$qb->setParameter('owners', $owners, IQueryBuilder::PARAM_STR_ARRAY);

		//$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
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

	public function getShareById($id, $recipientId = null) {
		$qb = $this->dbConn->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->eq(
					'share_type',
					$qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP)
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

		// we need to set the owner of the current logged in user if not the canAccess share function
		// will return false
		if(\OC::$server->getAppManager()->isInstalled("files_projectspaces")) {
			// if the file we are sharing is under a project space, we set the owner of the share
			// as the owner of the project
			$projectMapper = \OC::$server->getCernBoxProjectMapper();
			$node = $share->getNode();
			$metadata = $this->instanceManager->get($share->getShareOwner(), $node->getInternalPath());
			$eosPath = $metadata['eos.file'];
			if(strpos($eosPath, $this->instanceManager->getProjectPrefix()) === 0) {
				// strip project prefix
				$relPath = ltrim(substr($eosPath, strlen($this->instanceManager->getProjectPrefix())), "/");
				$projectInfo = $projectMapper->getProjectInfoByPath($relPath);
				if($projectInfo) {
					$share->setShareOwner(\OC::$server->getUserSession()->getUser()->getUID());
				}
			}
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
					$qb->expr()->eq(
						'share_type',
						$qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP)
					)
				)
			)
			->andWhere($qb->expr()->eq('item_type', $qb->createNamedParameter('folder')))
			->execute();
		$shares = [];
		while($data = $cursor->fetch()) {
			$shares[] = $this->createShare($data);
		}
		$cursor->closeCursor();
		return $shares;
	}

	public function getSharedWith($userId, $shareType, $node, $limit, $offset) {
		/** @var Share[] $shares */
		$shares = [];
		$groups = $this->getUserGroups($userId);

		if ($shareType === \OCP\Share::SHARE_TYPE_GROUP) {
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
			$qb->where($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_GROUP)))
				->andWhere(
					$qb->expr()->in(
						'share_with',
						$qb->createNamedParameter($groups, IQueryBuilder::PARAM_STR_ARRAY)
					))
				->andWhere($qb->expr()->eq('item_type', $qb->createNamedParameter('folder')));

			// Filter by node if provided
			if ($node !== null) {
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

	public function getShareByToken($token) {
		return null;
		// TODO: Implement getShareByToken() method.
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
	 * and it is accessible (not in trash nor version folder or the user doesn't exist anymore).
	 * @param $shareData
	 * @returns boolean
	 */
	private function isAccessibleResult($shareData) {
		// we always return true, if the share is not accessible the user will get an error message
		// when attempting to access it. The shares that are not valid are cleaned by background jobs anyway.
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

		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
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

	private function getUserGroups($username) {
		$groups = $this->groupManager->getUserIdGroups($username);
		$gids = array();
		foreach($groups as $group) {
			$gids[] = $group->getGID();
		}
		return $gids;
	}

}
