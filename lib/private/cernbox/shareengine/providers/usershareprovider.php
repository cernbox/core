<?php

namespace OC\Cernbox\ShareEngine\Providers;

use OCP\Share\IShare;
use OC\Cernbox\ShareEngine\CernboxShareProvider;
use OC\Cernbox\Storage\EosUtil;
use OC\Cernbox\Storage\EosParser;
use OC\Cernbox\ShareEngine\AbstractProvider;
use OC\Cernbox\ShareEngine\ShareUtil;
use OC\Cernbox\ShareEngine\CernboxShare;

final class UserShareProvider implements CernboxShareProvider
{	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\CernboxShareProvider::__construct()
	 */
	public function __construct(AbstractProvider $provider) 
	{
		parent::__construct($provider);	
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\CernboxShareProvider::createShare()
	 */
	public function createShare(array $rawData)
	{
		$aclMap = ShareUtil::parseAcl($rawData);
		$shares = [];
		foreach($aclMap as $user => $data)
		{
			// This provider should only return shares to users
			if($data[1] !== 'u')
			{
				continue;	
			}
			
			$share = new CernboxShare($this->masterProvider->rootFolder);
			$share->setId((int)$rawData['fileid'])
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setPermissions((int)EosUtil::toOcPerm($data[0]))
			->setTarget('/'.trim($rawData['path'], '/') . ' (#' . $rawData['fileid'] . ')')
			->setMailSend(true);
		
			$shareTime = new \DateTime();
			$shareTime->setTimestamp((int)$rawData['share_stime']);
			$share->setShareTime($shareTime);
		
			$share->setSharedWith($user);
			
			if (!isset($rawData['uid_initiator']) || $rawData['uid_initiator'] === null)
			{
				//OLD SHARE
				$share->setSharedBy($rawData['uid_owner']);
				$path = $this->getNode($share->getSharedBy(), $rawData['fileid']);
		
				$owner = $path->getOwner();
				$share->setShareOwner($owner->getUID());
			}
			else
			{
				//New share!
				$share->setSharedBy($rawData['uid_initiator']);
				$share->setShareOwner($rawData['uid_owner']);
			}
		
			$share->setNodeId($rawData['fileid']);
			$share->setNodeType($rawData['eostype']);
		
			$share->setProviderId($this->masterProvider->identifier());
			
			$shares[] = $share;
		}
	
		return $shares;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::updateShareAttributes()
	 */
	public function updateShareAttributes(IShare $share) 
	{
		$symLinkPath = $this->masterProvider->buildShareEosPath($share);
		
		$eosMeta = EosUtil::getFileByEosPath($symLinkPath);
		if(!$eosMeta) // Cannot locate the symlink, so the share does not exist
		{
			return false;
		}
		
		$aclMap = ShareUtil::parseAcl($eosMeta['sys.acl']);
		
		if(!isset($aclMap[$share->getShareOwner()]))
		{
			return false;	
		}
		
		// Trying to share a file with someone with whom the file is already shared
		if(isset($aclMap[$share->getSharedWith()]))
		{
			throw new \Exception(\OC_User::getUser() . ' tried to share ' . $symLinkPath . ' with ' . $share->getSharedWith() . ', but the file was already shared to him');
		}
		
		if(!EosUtil::addUserToAcl($share->getShareOwner(), $share->getSharedWith(), $eosMeta['fileid'], $share->getPermissions(), 'u'))
		{
			\OCP\Util::writeLog('SHARE ENGINE', 'UserShareProvider.updatShareAttributes(): Could not add ' 
					.$share->getSharedWith() . ' to file [' .$eosMeta['fileid']. '] ACL', \OCP\Util::ERROR);
			throw new \Exception('Could not add user to file ' .$symLinkPath. ' ACL');
		}
		
		return true;
	}

	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::setShareAttributes()
	 */
	public function setShareAttributes(IShare $share, $symLinkEosPath = null) 
	{
		if($symLinkEosPath === null)
		{
			$symLinkEosPath = $this->masterProvider->buildShareEosPath($share);
		}
		
		$eosMeta = EosUtil::getFileByEosPath($symLinkEosPath);
		if(!EosUtil::addUserToAcl($share->getShareOwner(), $share->getSharedWith(), $eosMeta['fileid'], $share->getPermissions(), 'u'))
		{
			throw new \Exception('Could not add ' .$share->getSharedWith(). ' to file ACL: ' . $symLinkEosPath);	
		}
		
		if(!EosUtil::setExtendedAttribute($symLinkEosPath, 'cernbox.share_type', \OCP\Share::SHARE_TYPE_USER))
		{
			throw new \Exception('Could not set custom extended attributes [cernbox.share_type] when sharing ' . $symLinkEosPath);
		}
		
		if(!EosUtil::setExtendedAttribute($symLinkEosPath, 'cernbox.share_stime', $share->getShareTime()))
		{
			throw new \Exception('Could not set custom extended attributes [cernbox.share_stime] when sharing ' . $symLinkEosPath);
		}
	}

	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::shouldShareBeDelete()
	 */
	public function shouldShareBeDelete(IShare $share) 
	{
		$sharePath = $this->masterProvider->buildShareEosPath($share);
		$eosMeta = EosParser::executeWithParser(EosParser::SHARE_PARSER, function() use ($sharePath) 
		{ 
			return EosUtil::getFileByEosPath($sharePath);
		});
		
		//The symlink does not exist, we dont have to delete anything
		if(!$eosMeta)
		{
			return false;
		}
		
		$aclMap = ShareUtil::parseAcl($eosMeta['sys.acl']);
		$target = $share->getSharedWith();
		
		$sharePrefix = rtrim(EosUtil::getEosSharePrefix(), '/');
		$targetlinkDst = $sharePrefix . '/user/' . substr($target, 0, 1) . '/' . $target . '/shared_with_me/' . trim($share->getTarget(), '/');
		
		if(!EosUtil::removeSymLink($targetlinkDst))
		{
			\OCP\Util::writeLog('SHARE ENGINE', 'User share: Could not delete user local share link ' . $targetlinkDst, \OCP\Util::ERROR);
		}
		
		// The share does not exist
		if(!isset($aclMap[$share->getSharedWith()]))
		{
			// If it does not have anyone else in its ACL but the owner, delete it
			if(count($aclMap) < 2)
			{
				return true;
			}
			
			return false;
		}
		
		unset($aclMap[$share->getSharedWith()]);
		
		EosUtil::changePermAcl($share->getShareOwner(), $share->getSharedWith(), $eosMeta['fileid'], 0, 'u');
		
		// If after removing the current share, there are no more users in the ACL but the owner, remove the symlink
		if(count($aclMap) < 2)
		{
			return true;	
		}
		
		return false;
	}

	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::shareeTargetCreation()
	 */
	public function shareeTargetCreation(IShare $share) 
	{
		$sharePrefix = rtrim(EosUtil::getEosSharePrefix(), '/');
		$target = $share->getSharedWith();
		
		$targetlinkDst = $sharePrefix . '/' . substr($target, 0, 1) . '/' . $target . '/shared_with_me/' . trim($share->getTarget(), '/');
		$srcLink = $this->masterProvider->buildShareEosPath($share);
		
		if(!EosUtil::createSymLink($targetlinkDst, $srcLink))
		{
			\OCP\Util::writeLog('SHARE ENGINE', 'UserShareProvider.shareeTargetCreation(): Could not create symlink in recipents shared_with_you folder. File: ' . $srcLink, \OCP\Util::ERROR);
			return false;
		}
		
		return true;
	}

	/**
	 * {@inheritDoc}
	 * @see \OC\Cernbox\ShareEngine\ICernboxProvider::getShareOwnerDestFolder()
	 */
	public function getShareOwnerDestFolder() 
	{
		return \OC::$server->getConfig()->getSystemValue('share_type_user_folder', 'user_shares');
	}
}