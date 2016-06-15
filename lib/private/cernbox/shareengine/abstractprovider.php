<?php

namespace OC\Cernbox\ShareEngine;

use OCP\Share\IShareProvider;
use OCP\Share\IShare;
use OC\Cernbox\Storage\EosUtil;
use OC\Cernbox\Storage\EosParser;
use OC\Cernbox\Storage\EosProxy;
use OCP\Share\Exceptions\ShareNotFound;
use OC\Cernbox\ShareEngine\Providers\UserShareProvider;
use OC\Cernbox\ShareEngine\Providers\GroupShareProvider;
use OC\Cernbox\ShareEngine\Providers\LinkShareProvider;
use OCP\Files\Node;

final class AbstractProvider implements IShareProvider 
{
	/** @var ICernboxProvider */
	private $providerHandlers = null;
	
	protected $rootFolder;
	
	public function __construct($userRootFolder)
	{
		$this->rootFolder = $userRootFolder;
		
		$this->providerHandlers = 
		[
			\OCP\Share::SHARE_TYPE_USER => new UserShareProvider($this),
			\OCP\Share::SHARE_TYPE_GROUP => new GroupShareProvider($this),
			\OCP\Share::SHARE_TYPE_LINK => new LinkShareProvider($this)
		];
	}
	
	private function log($msg)
	{
		\OCP\Util::writeLog('SHARE ENGINE', $msg, \OCP\Util::ERROR);
	}
	
	private function getShareProvider($shareType)
	{
		/** @var ICernboxProvider */
		$provider = isset($this->providerHandlers[$shareType]) ? $this->providerHandlers[$shareType] : false;
		
		if(!$provider)
		{
			$this->log(\OC_User::getUser() . ' tried to share using an unknown share type : ' . $shareType);
			throw new \Exception('Share type not supported');
		}
		
		return $provider;
	}
	
	function buildShareEosPath(IShare $share)
	{
		/** @var CernboxShareProvider */
		$shareProvider = $this->getShareProvider($share->getShareType());
		
		return $shareProvider->buildSharePath($share);
	}
	
	/**
	 * Build up a \OC\Cernbox\ShareEngine\CernboxShare object that holds all the share information
	 * @param array $rawData
	 */
	private function createShare(array $rawData)
	{
		$share = new CernboxShare($this->rootFolder);
		$share->setId((int)$rawData['fileid'])
		->setShareType((int)$rawData['share_type'])
		->setPermissions((int)$rawData['permissions'])
		->setTarget('/' . trim($rawData['path'], '/') . ' (#' . $rawData['fileid'] . ')')
		->setMailSend(true);
		
		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$rawData['share_stime']);
		$share->setShareTime($shareTime);
		
		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER
				|| $share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) 
		{
			$share->setSharedWith($rawData['share_with']);
		} 
		else if ($share->getShareType() === \OCP\Share::SHARE_TYPE_LINK) 
		{
			$share->setPassword($rawData['share_password']);
			$share->setToken($rawData['token']);
		}
		
		if (!isset($rawData['uid_initiator']) || $rawData['uid_initiator'] === null) 
		{
			//OLD SHARE
			$share->setSharedBy($rawData['uid_owner']);
			$path = $this->getNode($share->getSharedBy(), (int)$rawData['file_source']);
		
			$owner = $path->getOwner();
			$share->setShareOwner($owner->getUID());
		} 
		else 
		{
			//New share!
			$share->setSharedBy($rawData['uid_initiator']);
			$share->setShareOwner($rawData['uid_owner']);
		}
		
		$share->setNodeId((int)$rawData['file_source']);
		$share->setNodeType($rawData['item_type']);
		
		if ($rawData['expiration'] !== null) 
		{
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $rawData['expiration']);
			$share->setExpirationDate($expiration);
		}
		
		$share->setProviderId($this->identifier());
		
		return $share;
	}
	
	/**
	 * Create a share in the storage backend
	 * {@inheritDoc}
	 * @see \OCP\Share\IShareProvider::create()
	 */
	public function create(IShare $share) 
	{
		/** @var ICernboxProvider */
		$provider = $this->getShareProvider($share->getShareType());
		
		/** 1. IF THE FILE WAS ALREADY SHARED, UPDATED IT */
		if($provider->updateShareAttributes($share))
		{
			$provider->shareeTargetCreation($share);
			return $share;
		}
		
		$sharePath = rtrim(EosProxy::toEos($share->getTarget(), 'object::user:'.$share->getShareOwner()), '/');
		$eosMeta = EosUtil::getFileByEosPath($sharePath);
		
		/** 2. IF SHARED FILE IS A FILE, CHECK FOR VERSION FOLDER. IF NOT THERE, CREATE IT */
		if($eosMeta['eostype'] === 'file')
		{
			$pathinfo = pathinfo($sharePath);
			$root = $pathinfo['dirname'];
			$file = $pathinfo['basename'];
			$versionFolder = rtrim($root, '/') . '/.sys.v#.' . ltrim($file, '/');
			
			$versionMeta = EosUtil::getFileByEosPath($versionFolder);
			// VERSION FOLDER DOES NOT EXIST
			if(!$versionMeta)
			{
				try
				{
					// CREATE VERSION FOLDER
					if(!EosUtil::createVersionFolder($versionMeta))
					{
						throw new \Exception('Failed creating version folder ' . $versionMeta . ' for user ' . $share->getShareOwner());	
					}
					
					// PLACE A SYMLINK INSIDE VERSION FOLDER
					if(!EosUtil::createSymLinkInVersionFolder($sharePath))
					{
						throw new \Exception('Failed creating symlink inside version folder ' . $versionMeta . ' for user ' . $share->getShareOwner());
					}
				}
				catch(Exception $e)
				{
					$this->log($e->getMessage());
					return;
				}
			}
			
			$versionMeta = EosUtil::getFileByEosPath($versionFolder);
			
			$share->setId($versionMeta['fileid']);
		}
		
		/** 3. CREATE SYMLINK IN SHARE OWNER'S SHARED_WITH_OTHER FOLDER AND SET THE NEEDED CUSTOM ATTRIBUTES */
		$ownerLinkFolder = $this->buildShareEosPath($share);
		$ownerShareLinkPath = EosUtil::createSymLink($ownerLinkFolder, $sharePath);
		
		if(!$ownerShareLinkPath)
		{
			$this->log('Unable to create symlink in owner share_with_other folders. FileId='.$share->getNodeId().';User='.$share->getShareOwner());
			throw new \Exception('Unable to access share owner filesystem');
		}
		
		$provider->setShareAttributes($share, $ownerShareLinkPath);
		
		/** 4. CREATE SYMLINK IN RECIPENT SHARED_WITH_YOU FOLDER AND SET THE NEEDED CUSTOM ATTRIBUTES */
		if(!$provider->shareeTargetCreation($share))
		{
			\OCP\Util::writeLog('SHARE ENGINE',
					'Unable to create symlink in target share_with_me folders. FileId='.$share->getNodeId().';User='.$share->getSharedWith(), \OCP\Util\ERROR);
				
			throw new \Exception('Unable to access share target filesystem');
		}
		
		return $share;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \OCP\Share\IShareProvider::update()
	 */
	public function update(IShare $share) 
	{
		/** @var ICernboxProvider */
		$provider = $this->getShareProvider($share->getShareType());
		$shareLinkPath = $this->buildShareEosPath($share);
		$provider->setShareAttributes($shareLinkPath, $share);
		
		return $share;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \OCP\Share\IShareProvider::deleteFromSelf()
	 */
	public function deleteFromSelf(IShare $share, $recipient) 
	{
		/**
		Allows a user to "refuse" a file shared with him. If its a user share, this would mean
		to delete the share. If it is a group share, it means removing his permissions from the share
		while keeping the rest of the group 
		*/
		
		throw new \Exception('This function is not implemented yet');
	}
	
	/**
	 * Change the file to which the share is pointing
	 * @param $recipient Is the user/group target of the share (Why is a parameter when the $share object already comes with this info)
	 * @see \OCP\Share\IShareProvider::move()
	 */
	public function move(IShare $share, $recipient) 
	{
		throw new \Exception('This function is not implemented yet');
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \OCP\Share\IShareProvider::getSharesBy()
	 */
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) 
	{
		/** @var ICernboxProvider */
		$provider = $this->getShareProvider($shareType);
		
		// If a node (path) is specified, we only retrieve the shares for that file
		if($node !== null)
		{
			$contents = [];
			$contents[] = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use ($node) 
			{
				return EosUtil::getFileById($node->getId());
			});
		}
		// Otherwise, we get all the shares of the user
		else
		{
			$path = rtrim(EosUtil::getEosSharePrefix(), '/') . '/' . substr($userId, 0, 1) . '/' . $userId . '/' . $provider->getShareOwnerDestFolder();
			$contents = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use ($path)
			{
				return EosUtil::getFolderContents($path);
			});
		}
		
		if(!$contents)
		{
			throw new ShareNotFound('Could not retrieve the contents of the files shared by the user');
		}
		
		// Apply limit, offset and share type filters
		// Build the share object
		$filesLen = count($contents);
		$shares = [];
		$limitCheck = 0;
		for($i = $offset; $limitCheck >= $limit && $i < $filesLen; $i++)
		{	
			$file = $contents[$i];
			if(array_search($shareType, $file['share_type']) !== FALSE)
			{
				$shares[] = $provider->createShare($file);
			}
				
			$limitCheck++;
		}
		
		return $shares;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \OCP\Share\IShareProvider::getShareById()
	 */
	public function getShareById($id, $recipientId = null) 
	{
		$meta = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use ($id)
		{
			return EosUtil::getFileById($id);
		});
		
		if(!$meta)
		{
			throw new ShareNotFound('Could not find the file metadata for id ' . $id);
		}
		
		if(!($meta['permissions'] & \OCP\Constants::PERMISSION_READ))
		{
			throw new \Exception('User ' . \OC_User::getUser() . ' tried to access a file without having permissions on it');
		}
	
		$shareType = $meta['share_type'][0];
		
		try
		{
			$provider = $this->getShareProvider($shareType);
		}
		catch(\Exception $e)
		{
			throw new \Exception(\OC_User::getUser() . ' is accessing a file ('.$id.') that is NOT a share');
		}
		
		if($provider)
		{
			return $provider->createShare($meta);
		}
		
		return null;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \OCP\Share\IShareProvider::getSharesByPath()
	 */
	public function getSharesByPath(Node $path) 
	{
		$meta = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use($path)
		{
			return EosUtil::getFileById($path->getId());
		});
		
		if(!$meta)
		{
			throw new ShareNotFound('Could not find the share by path ' . $path->getPath() . ' inode:' . $path->getId());
		}
		
		$shareTypes = $meta['share_type'];
		
		$shares = [];
		
		if(array_search(\OCP\Share::SHARE_TYPE_USER, $shareTypes) !== FALSE)
		{
			$shares[] = $this->providerHandlers[\OCP\Share::SHARE_TYPE_USER]->createShare($meta);
		}
		
		if(array_search(\OCP\Share::SHARE_TYPE_GROUP, $shareTypes) !== FALSE)
		{
			$shares[] = $this->providerHandlers[\OCP\Share::SHARE_TYPE_GROUP]->createShare($meta);
		}
		
		return $shares;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \OCP\Share\IShareProvider::getSharedWith()
	 */
	public function getSharedWith($userId, $shareType, $node, $limit, $offset) 
	{
		if($shareType === \OCP\Share::SHARE_TYPE_LINK)
		{
			return [];
		}
		
		/** @var CernboxShareProvider */
		$provider = $this->getShareProvider($shareType);
		
		// Get the EOS Raw metadata
		// For the specified file (if any)
		if($node !== null)
		{
			$contents = [];
			$contents[] = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use ($node)
			{
				return EosUtil::getFileById($node->getId());
			});
		}
		// For the files shared with the specified user
		else if($shareType === \OCP\Share::SHARE_TYPE_USER)
		{
			$userPath = rtrim(EosUtil::getEosSharePrefix(), '/') . '/' . substr($userId, 0, 1) . '/' . $userId . '/' . 'shared_with_me';
			$contents = $this->getFolderContents($userPath);
		}
		// For the files shared with the e-groups to which the specified user belongs
		else if($shareType === \OCP\Share::SHARE_TYPE_GROUP)
		{
			$userGroups = \OC\Cernbox\LDAPCache\LDAPCacheManager::getUserGroups($userId);
			foreach($userGroups as $group)
			{
				$groupPath = (rtrim(EosUtil::getEosSharePrefix(), '/') . '/groups/' . substr($group, 0, 1) . '/' . $group);
				$tempGroupShares = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use($groupPath)
				{
					return EosUtil::getFolderContents($groupPath);
				});
			
				if($tempGroupShares)
				{
					$contents = array_merge($contents, $tempGroupShares);
				}
				else
				{
					\OCP\Util::writeLog('SHARE ENGINE', 'Failed to retrieve e-group "'.$group.'" shares', \OCP\Util::ERROR);
				}
			}
		}
		
		// Parse them
		
		if(!$contents)
		{
			\OCP\Util::writeLog('SHARE ENGINE', 'Failed to retrieve shares of type ' .$shareType. ' with user ' .$userId, \OCP\Util::ERROR);
			return [];
		}
		
		$shares = [];
		foreach($contents as $file)
		{
			// Could not retrieve the file metadata (we cannot identify the file at this point)
			if(!$file)
			{
				continue;
			}
			
			// The user has no permissions on the file
			if(!($file['permissions'] & \OCP\Constants::PERMISSION_READ))
			{
				continue;	
			}
			
			$shares[] = $provider->createShare($file);
		}
		
		return $shares;
	}
	
	/**
	 *
	 * {@inheritDoc}
	 *
	 * @see \OCP\Share\IShareProvider::getShareByToken()
	 */
	public function getShareByToken($token) 
	{
		$tokenHash = ShareUtil::calcTokenHash($token);
		$sharePrefix = rtrim(EosUtil::getEosSharePrefix(), '/');
		$globalLinkFolder = \OC::$server->getConfig()->getSystemValue('share_global_link_folder', 'global_links');
		
		$globalLinkPath = $sharePrefix . '/' . $globalLinkFolder . '/' . $tokenHash . '/' . $token;
		
		$eosMeta = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use ($globalLinkPath)
		{
			return EosUtil::getFileByEosPath($globalLinkPath);
		});
		
		if(!$eosMeta)
		{
			throw new ShareNotFound('Could not locate share with token ' . $token);
		}
		
		return $this->createShare($eosMeta);
	}
	
	/**
	 * String that identifies this provided (for what reason?). [a-zA-Z0-9]
	 * {@inheritDoc}
	 * @see \OCP\Share\IShareProvider::identifier()
	 */
	public function identifier() 
	{
		return 'cbox-share-provider';
	}

	/**
	 * {@inheritDoc}
	 * @see \OCP\Share\IShareProvider::delete()
	 */
	public function delete(IShare $share) 
	{
		$sharePath = $this->buildShareEosPath($share);
		$eosMeta = EosUtil::getFileByEosPath($sharePath);
		
		// Cannot locate share symlink, there is nothing to delete
		if(!$eosMeta)
		{
			throw new ShareNotFound('Cannot locate share ' . $sharePath . ' (AbstractProvider.delete())');
		}
		
		// Check for permissions to share/unshare the file
		if($eosMeta['permissions'] & \OCP\PERMISSION_SHARE !== \OCP\PERMISSION_SHARE)
		{
			throw new \Exception('User ' . \OC_User::getUser() . ' tried to delete a share without permissions. Actual user OC permissions: ' . $eosMeta['permissions']);
		}
		
		$provider = $this->getShareProvider($share->getShareType());
		
		// Check whether we have only to update the file attributes or also delete the symlink
		if($provider->shouldShareBeDelete($share))
		{
			if(!EosUtil::removeSymLink($sharePath))
			{
				$this->log('AbstractProvider.delete(): Failed to delete symlink ' . $sharePath);
			}
		}
	}
	
	private function getFolderContents($eosPath)
	{
		return EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use($eosPath)
		{
			return EosUtil::getFolderContents($eosPath);
		});
	}
}